<?php
/**
 * PHP Dependency Injection and Service Container (PHP 8.x)
 * -----------------------------------------------
 * This lesson covers:
 * 1. Dependency Injection principles
 * 2. Service Container implementation
 * 3. Autowiring
 * 4. Service configuration
 * 5. Dependency resolution
 * 6. Scoped services
 */

// Interface definitions
interface Logger {
    public function log(string $message, array $context = []): void;
}

interface Database {
    public function query(string $sql, array $params = []): array;
}

interface UserRepository {
    public function findById(int $id): ?array;
    public function create(array $data): int;
}

// Concrete implementations
class FileLogger implements Logger {
    public function __construct(
        private readonly string $logPath
    ) {}

    public function log(string $message, array $context = []): void {
        $logEntry = date('Y-m-d H:i:s') . ' - ' . $message . 
                    ($context ? ' ' . json_encode($context) : '') . PHP_EOL;
        file_put_contents($this->logPath, $logEntry, FILE_APPEND);
    }
}

class MySQLDatabase implements Database {
    private PDO $pdo;

    public function __construct(
        private readonly string $dsn,
        private readonly string $username,
        private readonly string $password
    ) {
        $this->pdo = new PDO($dsn, $username, $password, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    }

    public function query(string $sql, array $params = []): array {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Service implementation with dependencies
class DatabaseUserRepository implements UserRepository {
    public function __construct(
        private readonly Database $db,
        private readonly Logger $logger
    ) {}

    public function findById(int $id): ?array {
        try {
            $result = $this->db->query(
                "SELECT * FROM users WHERE id = ?",
                [$id]
            );
            return $result[0] ?? null;
        } catch (Exception $e) {
            $this->logger->log('Error finding user', [
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    public function create(array $data): int {
        try {
            $this->db->query(
                "INSERT INTO users (name, email) VALUES (?, ?)",
                [$data['name'], $data['email']]
            );
            return (int) $this->pdo->lastInsertId();
        } catch (Exception $e) {
            $this->logger->log('Error creating user', [
                'data' => $data,
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }
}

// Service Container implementation
class Container {
    private array $services = [];
    private array $factories = [];
    private array $instances = [];

    // Register a service definition
    public function set(string $id, callable $factory): void {
        $this->factories[$id] = $factory;
    }

    // Get a service (creates if not exists)
    public function get(string $id): mixed {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        if (!isset($this->factories[$id])) {
            throw new RuntimeException("Service not found: $id");
        }

        return $this->instances[$id] = ($this->factories[$id])($this);
    }

    // Auto-wire a class
    public function autowire(string $className): object {
        $reflection = new ReflectionClass($className);
        $constructor = $reflection->getConstructor();
        
        if (!$constructor) {
            return new $className();
        }

        $parameters = [];
        foreach ($constructor->getParameters() as $param) {
            $type = $param->getType();
            if (!$type || $type->isBuiltin()) {
                throw new RuntimeException(
                    "Cannot autowire parameter {$param->getName()}"
                );
            }
            $parameters[] = $this->get($type->getName());
        }

        return new $className(...$parameters);
    }
}

// Service configuration
class ServiceConfig {
    public static function configure(Container $container): void {
        // Configure logger
        $container->set(Logger::class, fn() => new FileLogger(
            logPath: __DIR__ . '/app.log'
        ));

        // Configure database
        $container->set(Database::class, fn() => new MySQLDatabase(
            dsn: "mysql:host=localhost;dbname=test;charset=utf8mb4",
            username: "root",
            password: "secret"
        ));

        // Configure user repository
        $container->set(UserRepository::class, function(Container $c) {
            return new DatabaseUserRepository(
                db: $c->get(Database::class),
                logger: $c->get(Logger::class)
            );
        });
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: When to use dependency injection?
 * A: Always! It makes code more testable and maintainable
 * 
 * Q2: What's the difference between DI and Service Location?
 * A: DI injects dependencies, Service Locator requires classes to ask for them
 * 
 * Q3: How to handle optional dependencies?
 * A: Use nullable types or default values in constructor
 * 
 * Q4: When to use autowiring?
 * A: For simple dependencies; configure complex ones manually
 */

// Usage example
try {
    // Create and configure container
    $container = new Container();
    ServiceConfig::configure($container);

    // Get user repository service
    $userRepo = $container->get(UserRepository::class);

    // Use the service
    $user = $userRepo->findById(1);
    if ($user) {
        echo "Found user: {$user['name']}\n";
    }

    // Example of autowiring
    class UserService {
        public function __construct(
            private readonly UserRepository $repository,
            private readonly Logger $logger
        ) {}

        public function getUser(int $id): ?array {
            $this->logger->log('Fetching user', ['id' => $id]);
            return $this->repository->findById($id);
        }
    }

    // Autowire UserService
    $userService = $container->autowire(UserService::class);
    $user = $userService->getUser(1);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

/**
 * Best Practices:
 * 1. Program to interfaces
 * 2. Use constructor injection
 * 3. Keep services immutable
 * 4. Configure complex services
 * 5. Use autowiring for simple cases
 * 6. Document service dependencies
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly properties
 * 3. Named arguments
 * 4. Union types
 * 5. Nullsafe operator
 * 6. Attributes (for autowiring)
 */ 