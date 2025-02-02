<?php
/**
 * PHP Design Patterns: Singleton (PHP 8.x)
 * ------------------------------
 * This lesson covers:
 * 1. Singleton pattern implementation
 * 2. Thread safety considerations
 * 3. Lazy loading
 * 4. Common use cases
 * 5. Best practices
 * 6. Testing singletons
 */

// Modern Singleton implementation
class DatabaseConnection {
    private static ?self $instance = null;
    private PDO $connection;

    // Private constructor to prevent direct instantiation
    private function __construct(
        private readonly array $config
    ) {
        $this->connect();
    }

    // Prevent cloning
    private function __clone(): void {}

    // Prevent unserialization
    public function __wakeup(): void {
        throw new Exception("Cannot unserialize singleton");
    }

    public static function getInstance(array $config = []): self {
        if (self::$instance === null) {
            self::$instance = new self($config);
        }
        return self::$instance;
    }

    private function connect(): void {
        $dsn = sprintf(
            "mysql:host=%s;dbname=%s;charset=utf8mb4",
            $this->config['host'] ?? 'localhost',
            $this->config['database']
        );

        $this->connection = new PDO(
            $dsn,
            $this->config['username'],
            $this->config['password'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
    }

    public function query(string $sql, array $params = []): PDOStatement {
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
}

// Thread-safe singleton with double-checked locking
class Logger {
    private static ?self $instance = null;
    private static $mutex;
    private $logFile;

    private function __construct(string $logFile) {
        $this->logFile = $logFile;
    }

    public static function getInstance(string $logFile = 'app.log'): self {
        if (self::$instance === null) {
            if (!isset(self::$mutex)) {
                self::$mutex = new SyncMutex();
            }

            self::$mutex->lock();
            try {
                if (self::$instance === null) {
                    self::$instance = new self($logFile);
                }
            } finally {
                self::$mutex->unlock();
            }
        }
        return self::$instance;
    }

    public function log(string $message, string $level = 'INFO'): void {
        $timestamp = date('Y-m-d H:i:s');
        $formattedMessage = "[$timestamp] [$level] $message" . PHP_EOL;
        file_put_contents($this->logFile, $formattedMessage, FILE_APPEND | LOCK_EX);
    }
}

// Registry pattern (alternative to multiple singletons)
class Registry {
    private static array $instances = [];
    private static array $config = [];

    public static function set(string $key, mixed $value): void {
        self::$instances[$key] = $value;
    }

    public static function get(string $key): mixed {
        if (!isset(self::$instances[$key])) {
            throw new RuntimeException("No instance registered for key: $key");
        }
        return self::$instances[$key];
    }

    public static function setConfig(string $key, array $config): void {
        self::$config[$key] = $config;
    }

    public static function getConfig(string $key): array {
        return self::$config[$key] ?? [];
    }

    public static function reset(): void {
        self::$instances = [];
        self::$config = [];
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: When to use Singleton?
 * A: For resources that must be shared and limited to one instance
 * 
 * Q2: How to test code using singletons?
 * A: Use dependency injection and interfaces for better testability
 * 
 * Q3: What about dependency injection?
 * A: Consider using DI containers instead of singletons
 * 
 * Q4: Thread safety concerns?
 * A: Use mutex locks in multi-threaded environments
 */

// Usage example
try {
    // Database singleton usage
    $db = DatabaseConnection::getInstance([
        'host' => 'localhost',
        'database' => 'test',
        'username' => 'root',
        'password' => 'secret'
    ]);

    $users = $db->query("SELECT * FROM users WHERE active = ?", [1]);

    // Logger singleton usage
    $logger = Logger::getInstance('application.log');
    $logger->log("Database query executed successfully");

    // Registry usage
    Registry::set('db', $db);
    Registry::set('logger', $logger);

    // Later in the code...
    $db = Registry::get('db');
    $logger = Registry::get('logger');

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

/**
 * Best Practices:
 * 1. Use sparingly
 * 2. Consider alternatives
 * 3. Ensure thread safety
 * 4. Make testable
 * 5. Handle dependencies
 * 6. Document usage
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly properties
 * 3. Named arguments
 * 4. Union types
 * 5. Nullsafe operator
 * 6. Mixed type
 */ 