<?php
/**
 * PHP Constructors and Destructors (PHP 8.x)
 * ---------------------------------------
 * This lesson covers:
 * 1. Constructor promotion
 * 2. Named arguments in constructors
 * 3. Constructor property types
 * 4. Nullsafe operator
 * 5. Multiple constructors pattern
 * 6. Destructors and cleanup
 * 7. Clone method
 */

// Resource class to demonstrate cleanup
class DatabaseConnection {
    private $connection;
    
    public function __construct(
        private readonly string $host,
        private readonly string $username,
        private readonly string $password,
        private readonly string $database
    ) {
        $this->connect();
    }
    
    private function connect(): void {
        $this->connection = "Connected to {$this->host}/{$this->database}";
        echo "Connection established\n";
    }
    
    public function __destruct() {
        // Cleanup when object is destroyed
        $this->connection = null;
        echo "Connection closed\n";
    }
}

// Class with multiple constructor patterns
class User {
    public function __construct(
        private readonly string $id,
        private string $name,
        private ?string $email = null,
        private array $metadata = [],
    ) {}
    
    // Named constructor pattern
    public static function fromArray(array $data): static {
        return new static(
            id: $data['id'],
            name: $data['name'],
            email: $data['email'] ?? null,
            metadata: $data['metadata'] ?? []
        );
    }
    
    // Another named constructor
    public static function fromJson(string $json): static {
        $data = json_decode($json, true);
        return self::fromArray($data);
    }
    
    // Clone with modifications
    public function __clone() {
        $this->id = uniqid('user_');
        $this->metadata = array_merge($this->metadata, ['cloned' => true]);
    }
    
    // Getter example
    public function getName(): string {
        return $this->name;
    }
}

// Class demonstrating constructor promotion with defaults
class Configuration {
    public function __construct(
        private readonly string $environment = 'development',
        private array $settings = [],
        private ?DatabaseConnection $db = null,
    ) {}
    
    // Nullsafe operator example
    public function getDatabaseName(): ?string {
        return $this->db?->database;
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: When is the destructor called?
 * A: When there are no more references to the object or during script shutdown,
 *    but exact timing isn't guaranteed
 * 
 * Q2: Can I have multiple constructors?
 * A: No, but you can use named constructors (static methods) as alternatives
 * 
 * Q3: What happens to readonly properties in __clone()?
 * A: They can be modified inside __clone() but nowhere else
 * 
 * Q4: Should I rely on destructors for cleanup?
 * A: No, prefer explicit cleanup methods when possible as destructor timing
 *    isn't guaranteed
 */

// Usage examples
echo "=== Constructor Examples ===\n";

// Using named arguments
$user = new User(
    id: "user_1",
    name: "John Doe",
    email: "john@example.com"
);

// Using named constructor
$userData = [
    'id' => 'user_2',
    'name' => 'Jane Doe',
    'email' => 'jane@example.com',
    'metadata' => ['role' => 'admin']
];
$user2 = User::fromArray($userData);

// Using JSON constructor
$jsonData = json_encode([
    'id' => 'user_3',
    'name' => 'Bob Smith',
    'email' => 'bob@example.com'
]);
$user3 = User::fromJson($jsonData);

// Cloning example
$clonedUser = clone $user;

// Database connection example
$db = new DatabaseConnection(
    host: 'localhost',
    username: 'root',
    password: 'secret',
    database: 'myapp'
);

/**
 * Best Practices:
 * 1. Use constructor promotion for simple property initialization
 * 2. Provide named constructors for alternative creation patterns
 * 3. Make properties readonly when they shouldn't change
 * 4. Use explicit cleanup methods instead of relying on destructors
 * 5. Be careful with circular references in destructors
 * 6. Use type declarations for all constructor parameters
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly properties
 * 3. Named arguments
 * 4. Nullsafe operator
 * 5. Union types
 * 6. Static return type
 */ 