<?php
/**
 * PHP Database Indexing and Query Optimization (PHP 8.x)
 * ----------------------------------------------
 * This lesson covers:
 * 1. Database indexing strategies
 * 2. Query optimization techniques
 * 3. EXPLAIN query analysis
 * 4. Query profiling
 * 5. Common optimization patterns
 * 6. Performance monitoring
 */

// Query analyzer class
class QueryAnalyzer {
    public function __construct(
        private readonly PDO $db,
        private readonly Logger $logger
    ) {}

    // Analyze query execution plan
    public function explainQuery(string $query, array $params = []): array {
        $stmt = $this->db->prepare("EXPLAIN FORMAT=JSON " . $query);
        $stmt->execute($params);
        return json_decode($stmt->fetch(PDO::FETCH_COLUMN), true);
    }

    // Profile query execution
    public function profileQuery(string $query, array $params = []): array {
        $this->db->exec("SET profiling = 1");
        
        $startTime = microtime(true);
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $duration = microtime(true) - $startTime;
        
        $profiling = $this->db->query("SHOW PROFILES")->fetchAll(PDO::FETCH_ASSOC);
        $this->db->exec("SET profiling = 0");

        return [
            'duration' => $duration,
            'rows_affected' => $stmt->rowCount(),
            'profiling' => $profiling
        ];
    }

    // Check if index exists
    public function hasIndex(string $table, string $indexName): bool {
        $stmt = $this->db->prepare("
            SELECT 1 
            FROM information_schema.statistics 
            WHERE table_schema = DATABASE()
            AND table_name = ?
            AND index_name = ?
        ");
        $stmt->execute([$table, $indexName]);
        return (bool) $stmt->fetch();
    }

    // Suggest missing indexes
    public function suggestIndexes(string $query): array {
        $explain = $this->explainQuery($query);
        $suggestions = [];

        // Analyze EXPLAIN output for potential improvements
        $this->analyzePlan($explain, $suggestions);

        return $suggestions;
    }

    private function analyzePlan(array $plan, array &$suggestions, string $prefix = ''): void {
        foreach ($plan as $node) {
            if (isset($node['table'])) {
                if (isset($node['possible_keys']) && empty($node['possible_keys'])) {
                    $suggestions[] = [
                        'table' => $node['table'],
                        'type' => 'missing_index',
                        'columns' => $this->suggestColumnsForIndex($node)
                    ];
                }
            }
            
            if (isset($node['Extra']) && str_contains($node['Extra'], 'Using filesort')) {
                $suggestions[] = [
                    'table' => $node['table'],
                    'type' => 'ordering_index',
                    'message' => 'Consider adding index for ORDER BY columns'
                ];
            }
        }
    }

    private function suggestColumnsForIndex(array $node): array {
        $columns = [];
        if (isset($node['ref'])) {
            $columns = array_merge($columns, explode(',', $node['ref']));
        }
        if (isset($node['filtered']) && $node['filtered'] < 100) {
            $columns[] = $node['filtered_column'] ?? 'unknown';
        }
        return array_unique($columns);
    }
}

// Example usage with optimization patterns
class OptimizedUserRepository {
    private QueryAnalyzer $analyzer;

    public function __construct(
        private readonly PDO $db,
        private readonly Logger $logger
    ) {
        $this->analyzer = new QueryAnalyzer($db, $logger);
    }

    // Optimized search with index usage
    public function findUsers(array $criteria, int $limit = 10): array {
        $conditions = [];
        $params = [];
        $index_hint = '';

        // Build query using available indexes
        if (isset($criteria['email'])) {
            $conditions[] = "email = :email";
            $params[':email'] = $criteria['email'];
            $index_hint = "USE INDEX (idx_email)";
        }

        if (isset($criteria['status'])) {
            $conditions[] = "status = :status";
            $params[':status'] = $criteria['status'];
        }

        $query = "SELECT * FROM users {$index_hint} ";
        if (!empty($conditions)) {
            $query .= "WHERE " . implode(' AND ', $conditions);
        }
        $query .= " LIMIT :limit";
        $params[':limit'] = $limit;

        // Analyze query before execution
        $analysis = $this->analyzer->explainQuery($query, $params);
        $this->logger->info("Query analysis", ['explain' => $analysis]);

        // Execute optimized query
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Batch processing pattern
    public function processBatch(array $userIds, callable $processor): void {
        $batchSize = 1000;
        $processed = 0;

        while ($processed < count($userIds)) {
            $batch = array_slice($userIds, $processed, $batchSize);
            $placeholders = str_repeat('?,', count($batch) - 1) . '?';
            
            $query = "SELECT * FROM users WHERE id IN ($placeholders)";
            $stmt = $this->db->prepare($query);
            $stmt->execute($batch);

            while ($user = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $processor($user);
            }

            $processed += $batchSize;
        }
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: When should I add an index?
 * A: Add indexes on frequently searched/joined columns,
 *    but be careful of write performance impact
 * 
 * Q2: How to handle large result sets?
 * A: Use batch processing and cursors
 * 
 * Q3: What's the difference between EXPLAIN and EXPLAIN ANALYZE?
 * A: EXPLAIN shows the plan, ANALYZE actually executes the query
 * 
 * Q4: How to optimize JOIN operations?
 * A: Use proper indexes on join columns and consider denormalization
 */

// Usage example
try {
    $db = new PDO(
        "mysql:host=localhost;dbname=test;charset=utf8mb4",
        "root",
        "secret",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $logger = new class implements Logger {
        public function info(string $message, array $context = []): void {
            echo "[INFO] $message\n";
            if (!empty($context)) {
                print_r($context);
            }
        }
        public function error(string $message, array $context = []): void {
            echo "[ERROR] $message\n";
            if (!empty($context)) {
                print_r($context);
            }
        }
    };

    $repository = new OptimizedUserRepository($db, $logger);
    
    // Search with optimization
    $users = $repository->findUsers([
        'status' => 'active',
        'email' => 'test@example.com'
    ]);

    // Batch processing
    $repository->processBatch(range(1, 10000), function($user) {
        // Process each user
        echo "Processing user {$user['id']}\n";
    });

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

/**
 * Best Practices:
 * 1. Use EXPLAIN to analyze queries
 * 2. Index frequently searched columns
 * 3. Use batch processing for large datasets
 * 4. Monitor query performance
 * 5. Optimize JOIN operations
 * 6. Consider denormalization when appropriate
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly properties
 * 3. Named arguments
 * 4. Union types
 * 5. Match expressions
 * 6. Nullsafe operator
 */ 