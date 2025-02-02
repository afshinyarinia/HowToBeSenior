<?php
/**
 * PHP Database Connections (PHP 8.x)
 * ------------------------------
 * This lesson covers:
 * 1. PDO connection setup
 * 2. Connection options
 * 3. Error handling
 * 4. Connection pooling
 * 5. Multiple database connections
 * 6. Secure connection practices
 */

// Database configuration class
readonly class DatabaseConfig {
    public function __construct(
        public string $driver = 'mysql',
        public string $host = 'localhost',
        public int $port = 3306,
        public string $database = 'test',
        public string $username = 'root',
        public string $password = '',
        public string $charset = 'utf8mb4',
        public array $options = []
    ) {}

    public function getDsn(): string {
        return match($this->driver) {
            'mysql' => "mysql:host={$this->host};port={$this->port};dbname={$this->database};charset={$this->charset}",
            'pgsql' => "pgsql:host={$this->host};port={$this->port};dbname={$this->database}",
            'sqlite' => "sqlite:{$this->database}",
            default => throw new InvalidArgumentException("Unsupported driver: {$this->driver}")
        };
    }
}

// Database connection manager
class DatabaseManager {
    private static ?DatabaseManager $instance = null;
    private array $connections = [];
    
    private function __construct() {}
    
    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function connect(
        DatabaseConfig $config,
        string $connectionName = 'default'
    ): PDO {
        if (isset($this->connections[$connectionName])) {
            return $this->connections[$connectionName];
        }

        try {
            // Default PDO options for better security and error handling
            $defaultOptions = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $options = $defaultOptions + $config->options;

            $this->connections[$connectionName] = new PDO(
                $config->getDsn(),
                $config->username,
                $config->password,
                $options
            );

            return $this->connections[$connectionName];
        } catch (PDOException $e) {
            throw new RuntimeException(
                "Database connection failed: " . $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
    }

    public function getConnection(string $name = 'default'): ?PDO {
        return $this->connections[$name] ?? null;
    }

    public function closeConnection(string $name = 'default'): void {
        $this->connections[$name] = null;
        unset($this->connections[$name]);
    }

    public function closeAll(): void {
        foreach (array_keys($this->connections) as $name) {
            $this->closeConnection($name);
        }
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: Why use PDO over mysqli?
 * A: PDO supports multiple databases and provides better abstraction
 * 
 * Q2: How to handle connection failures?
 * A: Use try-catch and proper error handling with logging
 * 
 * Q3: Should I reuse connections?
 * A: Yes, use connection pooling when possible
 * 
 * Q4: How to secure database credentials?
 * A: Use environment variables or secure configuration management
 */

// Usage examples
try {
    // Create database configuration
    $config = new DatabaseConfig(
        driver: 'mysql',
        host: 'localhost',
        database: 'test',
        username: 'root',
        password: 'secret',
        options: [
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );

    // Get database manager instance
    $manager = DatabaseManager::getInstance();

    // Connect to database
    $db = $manager->connect($config);
    
    // Test connection
    $stmt = $db->query('SELECT NOW() as current_time');
    $result = $stmt->fetch();
    echo "Current time: " . $result['current_time'] . "\n";

    // Multiple database example
    $configReadOnly = new DatabaseConfig(
        driver: 'mysql',
        host: 'readonly.example.com',
        database: 'test_readonly'
    );
    
    $readOnlyDb = $manager->connect($configReadOnly, 'readonly');

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
} finally {
    // Clean up connections
    $manager?->closeAll();
}

/**
 * Best Practices:
 * 1. Always use prepared statements
 * 2. Set appropriate connection options
 * 3. Handle connection errors gracefully
 * 4. Use connection pooling
 * 5. Secure credential management
 * 6. Close connections when done
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly classes
 * 3. Named arguments
 * 4. Match expressions
 * 5. Nullsafe operator
 * 6. Union types
 */ 