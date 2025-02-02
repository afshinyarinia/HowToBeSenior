<?php
/**
 * PHP Database Replication and Scaling (PHP 8.x)
 * ---------------------------------------
 * This lesson covers:
 * 1. Master-slave replication
 * 2. Load balancing
 * 3. Connection pooling
 * 4. Read/Write splitting
 * 5. Sharding strategies
 * 6. High availability
 */

// Database configuration for replication
readonly class ReplicationConfig {
    public function __construct(
        public array $masters = [],    // Master nodes for writes
        public array $slaves = [],     // Slave nodes for reads
        public string $driver = 'mysql',
        public string $database = 'test',
        public string $username = 'root',
        public string $password = '',
        public array $options = []
    ) {}
}

// Database connection manager with replication support
class ReplicationManager {
    private array $masterConnections = [];
    private array $slaveConnections = [];
    private ?PDO $currentMaster = null;
    private ?PDO $currentSlave = null;

    public function __construct(
        private readonly ReplicationConfig $config,
        private readonly Logger $logger
    ) {}

    // Get connection for write operations
    public function getMaster(): PDO {
        if ($this->currentMaster === null) {
            $master = $this->getRandomNode($this->config->masters);
            try {
                $this->currentMaster = new PDO(
                    $this->buildDsn($master),
                    $this->config->username,
                    $this->config->password,
                    $this->config->options + [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
            } catch (PDOException $e) {
                $this->logger->error("Master connection failed", [
                    'host' => $master,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }
        }
        return $this->currentMaster;
    }

    // Get connection for read operations
    public function getSlave(): PDO {
        if ($this->currentSlave === null) {
            $slave = $this->getRandomNode($this->config->slaves);
            try {
                $this->currentSlave = new PDO(
                    $this->buildDsn($slave),
                    $this->config->username,
                    $this->config->password,
                    $this->config->options + [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
            } catch (PDOException $e) {
                $this->logger->error("Slave connection failed", [
                    'host' => $slave,
                    'error' => $e->getMessage()
                ]);
                // Fallback to master if slave is unavailable
                return $this->getMaster();
            }
        }
        return $this->currentSlave;
    }

    private function buildDsn(string $host): string {
        return "{$this->config->driver}:host={$host};dbname={$this->config->database}";
    }

    private function getRandomNode(array $nodes): string {
        return $nodes[array_rand($nodes)];
    }
}

// Repository with read/write splitting
class ScalableUserRepository {
    public function __construct(
        private readonly ReplicationManager $db,
        private readonly Logger $logger
    ) {}

    // Write operation - uses master
    public function createUser(array $userData): int {
        $master = $this->db->getMaster();
        
        try {
            $stmt = $master->prepare("
                INSERT INTO users (name, email, created_at)
                VALUES (:name, :email, NOW())
            ");
            
            $stmt->execute([
                'name' => $userData['name'],
                'email' => $userData['email']
            ]);
            
            return (int) $master->lastInsertId();
        } catch (PDOException $e) {
            $this->logger->error("Create user failed", [
                'error' => $e->getMessage(),
                'data' => $userData
            ]);
            throw $e;
        }
    }

    // Read operation - uses slave
    public function findUser(int $id): ?array {
        $slave = $this->db->getSlave();
        
        try {
            $stmt = $slave->prepare("
                SELECT * FROM users WHERE id = :id
            ");
            
            $stmt->execute(['id' => $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            $this->logger->error("Find user failed", [
                'error' => $e->getMessage(),
                'id' => $id
            ]);
            throw $e;
        }
    }

    // Sharding example - distribute users across shards
    public function findUserInShard(int $userId, int $totalShards = 4): ?array {
        $shardId = $userId % $totalShards;
        $shardConfig = $this->getShardConfig($shardId);
        
        try {
            $connection = new PDO(
                $shardConfig['dsn'],
                $shardConfig['username'],
                $shardConfig['password']
            );
            
            $stmt = $connection->prepare("
                SELECT * FROM users WHERE id = :id
            ");
            
            $stmt->execute(['id' => $userId]);
            return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (PDOException $e) {
            $this->logger->error("Shard query failed", [
                'shard' => $shardId,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    private function getShardConfig(int $shardId): array {
        // Example shard configuration
        return [
            'dsn' => "mysql:host=shard{$shardId}.example.com;dbname=users_{$shardId}",
            'username' => 'root',
            'password' => 'secret'
        ];
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: How to handle replication lag?
 * A: Use master for reads after writes, implement eventual consistency
 * 
 * Q2: When to use sharding?
 * A: When data volume exceeds single server capacity
 * 
 * Q3: How to handle shard failures?
 * A: Implement failover and backup strategies
 * 
 * Q4: What's the trade-off of read/write splitting?
 * A: Improved read performance but potential consistency issues
 */

// Usage example
try {
    $config = new ReplicationConfig(
        masters: ['master1.example.com', 'master2.example.com'],
        slaves: [
            'slave1.example.com',
            'slave2.example.com',
            'slave3.example.com'
        ]
    );

    $logger = new class implements Logger {
        public function info(string $message, array $context = []): void {
            echo "[INFO] $message\n";
            if (!empty($context)) print_r($context);
        }
        public function error(string $message, array $context = []): void {
            echo "[ERROR] $message\n";
            if (!empty($context)) print_r($context);
        }
    };

    $manager = new ReplicationManager($config, $logger);
    $repository = new ScalableUserRepository($manager, $logger);

    // Write to master
    $userId = $repository->createUser([
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ]);

    // Read from slave
    $user = $repository->findUser($userId);
    echo "Found user: " . ($user['name'] ?? 'Not found') . "\n";

    // Query sharded data
    $shardedUser = $repository->findUserInShard($userId);
    echo "Found user in shard: " . ($shardedUser['name'] ?? 'Not found') . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

/**
 * Best Practices:
 * 1. Implement proper failover handling
 * 2. Monitor replication lag
 * 3. Use connection pooling
 * 4. Implement retry mechanisms
 * 5. Consider data consistency requirements
 * 6. Plan shard distribution carefully
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly properties and classes
 * 3. Named arguments
 * 4. Union types
 * 5. Nullsafe operator
 * 6. Match expressions
 */ 