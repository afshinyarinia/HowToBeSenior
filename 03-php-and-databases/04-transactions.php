<?php
/**
 * PHP Database Transactions and Error Handling (PHP 8.x)
 * -----------------------------------------------
 * This lesson covers:
 * 1. Transaction management
 * 2. Savepoints
 * 3. Error handling strategies
 * 4. Deadlock handling
 * 5. Connection failures
 * 6. Retry mechanisms
 */

// Transaction manager class
class TransactionManager {
    private int $transactionLevel = 0;
    
    public function __construct(
        private readonly PDO $db,
        private readonly int $maxRetries = 3,
        private readonly int $retryDelay = 100 // milliseconds
    ) {
        // Disable auto-commit
        $this->db->setAttribute(PDO::ATTR_AUTOCOMMIT, false);
    }

    // Begin a new transaction or create a savepoint
    public function begin(): void {
        if ($this->transactionLevel === 0) {
            $this->db->beginTransaction();
        } else {
            $this->db->exec("SAVEPOINT trans{$this->transactionLevel}");
        }
        $this->transactionLevel++;
    }

    // Commit transaction or release savepoint
    public function commit(): void {
        if ($this->transactionLevel === 0) {
            throw new RuntimeException("No active transaction");
        }

        $this->transactionLevel--;
        
        if ($this->transactionLevel === 0) {
            $this->db->commit();
        } else {
            $this->db->exec("RELEASE SAVEPOINT trans{$this->transactionLevel}");
        }
    }

    // Rollback transaction or to savepoint
    public function rollback(): void {
        if ($this->transactionLevel === 0) {
            throw new RuntimeException("No active transaction");
        }

        $this->transactionLevel--;
        
        if ($this->transactionLevel === 0) {
            $this->db->rollBack();
        } else {
            $this->db->exec("ROLLBACK TO SAVEPOINT trans{$this->transactionLevel}");
        }
    }

    // Execute callback in transaction with retry mechanism
    public function transactional(callable $callback): mixed {
        $attempts = 0;
        
        while (true) {
            try {
                $this->begin();
                $result = $callback($this->db);
                $this->commit();
                return $result;
            } catch (PDOException $e) {
                $this->rollback();
                
                // Check if error is deadlock or lock wait timeout
                if ($this->isDeadlockError($e) && $attempts < $this->maxRetries) {
                    $attempts++;
                    // Wait before retry (exponential backoff)
                    usleep($this->retryDelay * 1000 * $attempts);
                    continue;
                }
                
                throw $e;
            }
        }
    }

    private function isDeadlockError(PDOException $e): bool {
        // MySQL deadlock error codes
        return in_array($e->errorInfo[1] ?? 0, [1213, 1205]);
    }
}

// Example repository using transactions
class OrderRepository {
    public function __construct(
        private readonly TransactionManager $tm,
        private readonly Logger $logger
    ) {}

    public function createOrder(array $orderData, array $items): int {
        return $this->tm->transactional(function(PDO $db) use ($orderData, $items) {
            try {
                // Insert order
                $stmt = $db->prepare("
                    INSERT INTO orders (customer_id, total_amount, status)
                    VALUES (:customer_id, :total_amount, :status)
                ");
                
                $stmt->execute([
                    'customer_id' => $orderData['customer_id'],
                    'total_amount' => $orderData['total_amount'],
                    'status' => 'pending'
                ]);
                
                $orderId = (int) $db->lastInsertId();

                // Insert order items
                $itemStmt = $db->prepare("
                    INSERT INTO order_items (order_id, product_id, quantity, price)
                    VALUES (:order_id, :product_id, :quantity, :price)
                ");

                foreach ($items as $item) {
                    $itemStmt->execute([
                        'order_id' => $orderId,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                        'price' => $item['price']
                    ]);
                }

                return $orderId;
            } catch (Throwable $e) {
                $this->logger->error("Order creation failed", [
                    'error' => $e->getMessage(),
                    'order_data' => $orderData
                ]);
                throw $e;
            }
        });
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: When should I use transactions?
 * A: When multiple operations need to succeed or fail together
 * 
 * Q2: How to handle deadlocks?
 * A: Implement retry mechanism with exponential backoff
 * 
 * Q3: What's the difference between rollback and savepoint?
 * A: Rollback undoes entire transaction, savepoint allows partial rollback
 * 
 * Q4: How to handle nested transactions?
 * A: Use savepoints for nested transaction-like behavior
 */

// Simple logger interface
interface Logger {
    public function error(string $message, array $context = []): void;
}

// Usage examples
try {
    $db = new PDO(
        "mysql:host=localhost;dbname=test;charset=utf8mb4",
        "root",
        "secret",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $logger = new class implements Logger {
        public function error(string $message, array $context = []): void {
            echo "[ERROR] $message\n";
            print_r($context);
        }
    };

    $tm = new TransactionManager($db);
    $orderRepo = new OrderRepository($tm, $logger);

    // Create order with items
    $orderId = $orderRepo->createOrder(
        orderData: [
            'customer_id' => 1,
            'total_amount' => 99.99
        ],
        items: [
            ['product_id' => 1, 'quantity' => 2, 'price' => 49.99],
            ['product_id' => 2, 'quantity' => 1, 'price' => 0.01]
        ]
    );

    echo "Order created with ID: $orderId\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

/**
 * Best Practices:
 * 1. Always use transactions for multiple operations
 * 2. Implement retry mechanism for deadlocks
 * 3. Keep transactions as short as possible
 * 4. Handle all possible errors
 * 5. Log transaction failures
 * 6. Use appropriate isolation levels
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Named arguments
 * 3. Readonly properties
 * 4. Union types
 * 5. Match expressions
 * 6. Nullsafe operator
 */ 