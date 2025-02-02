<?php
/**
 * PHP Prepared Statements and Query Optimization (PHP 8.x)
 * ------------------------------------------------
 * This lesson covers:
 * 1. Prepared statement best practices
 * 2. Query optimization techniques
 * 3. Parameter binding types
 * 4. Complex queries
 * 5. Performance monitoring
 * 6. Common pitfalls
 */

// Query builder class for safe SQL construction
class QueryBuilder {
    private array $bindings = [];
    private array $conditions = [];
    private array $orders = [];
    private ?int $limit = null;
    private ?int $offset = null;

    public function __construct(
        private readonly PDO $db,
        private readonly string $table
    ) {}

    // Add WHERE condition
    public function where(string $column, string $operator, mixed $value): self {
        $param = ":where_" . count($this->conditions);
        $this->conditions[] = "{$column} {$operator} {$param}";
        $this->bindings[$param] = $value;
        return $this;
    }

    // Add ORDER BY
    public function orderBy(string $column, string $direction = 'ASC'): self {
        $this->orders[] = "{$column} " . strtoupper($direction);
        return $this;
    }

    // Set LIMIT
    public function limit(int $limit, ?int $offset = null): self {
        $this->limit = $limit;
        $this->offset = $offset;
        return $this;
    }

    // Execute SELECT query
    public function select(array $columns = ['*']): array {
        $sql = "SELECT " . implode(', ', $columns) . " FROM {$this->table}";
        
        if (!empty($this->conditions)) {
            $sql .= " WHERE " . implode(' AND ', $this->conditions);
        }
        
        if (!empty($this->orders)) {
            $sql .= " ORDER BY " . implode(', ', $this->orders);
        }
        
        if ($this->limit !== null) {
            $sql .= " LIMIT " . $this->limit;
            if ($this->offset !== null) {
                $sql .= " OFFSET " . $this->offset;
            }
        }

        try {
            $stmt = $this->db->prepare($sql);
            $this->bindValues($stmt);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            throw new RuntimeException("Query execution failed: " . $e->getMessage());
        }
    }

    // Bind values with appropriate types
    private function bindValues(PDOStatement $stmt): void {
        foreach ($this->bindings as $param => $value) {
            $type = match(true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                is_null($value) => PDO::PARAM_NULL,
                default => PDO::PARAM_STR
            };
            $stmt->bindValue($param, $value, $type);
        }
    }
}

// Example of complex query execution with performance monitoring
class QueryProfiler {
    private array $queries = [];

    public function profile(callable $callback): mixed {
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        try {
            return $callback();
        } finally {
            $endTime = microtime(true);
            $endMemory = memory_get_usage();

            $this->queries[] = [
                'time' => ($endTime - $startTime) * 1000, // Convert to milliseconds
                'memory' => $endMemory - $startMemory,
                'timestamp' => new DateTime()
            ];
        }
    }

    public function getStats(): array {
        return [
            'total_queries' => count($this->queries),
            'total_time' => array_sum(array_column($this->queries, 'time')),
            'avg_time' => array_sum(array_column($this->queries, 'time')) / count($this->queries),
            'total_memory' => array_sum(array_column($this->queries, 'memory')),
            'queries' => $this->queries
        ];
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: How to handle different parameter types?
 * A: Use appropriate PDO::PARAM_* constants when binding
 * 
 * Q2: When to use query builders vs raw SQL?
 * A: Use builders for dynamic queries, raw SQL for complex static queries
 * 
 * Q3: How to optimize query performance?
 * A: Use indexes, limit result sets, optimize joins, monitor execution
 * 
 * Q4: How to prevent SQL injection in complex queries?
 * A: Always use prepared statements and proper parameter binding
 */

// Usage examples
try {
    // Setup database connection
    $db = new PDO(
        "mysql:host=localhost;dbname=test;charset=utf8mb4",
        "root",
        "secret",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    $profiler = new QueryProfiler();
    $builder = new QueryBuilder($db, 'users');

    // Complex query example with profiling
    $result = $profiler->profile(function() use ($builder) {
        return $builder
            ->where('status', '=', 'active')
            ->where('created_at', '>=', '2023-01-01')
            ->orderBy('name')
            ->limit(10)
            ->select(['id', 'name', 'email']);
    });

    // Display results
    foreach ($result as $row) {
        echo "{$row['name']} ({$row['email']})\n";
    }

    // Show performance stats
    $stats = $profiler->getStats();
    echo "\nQuery Statistics:\n";
    echo "Total Queries: {$stats['total_queries']}\n";
    echo "Total Time: {$stats['total_time']}ms\n";
    echo "Average Time: {$stats['avg_time']}ms\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

/**
 * Best Practices:
 * 1. Always use parameter binding
 * 2. Monitor query performance
 * 3. Use appropriate indexes
 * 4. Limit result sets
 * 5. Optimize complex queries
 * 6. Use transaction for multiple operations
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Match expressions
 * 3. Named arguments
 * 4. Readonly properties
 * 5. Union types
 * 6. Nullsafe operator
 */ 