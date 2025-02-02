<?php
/**
 * PHP Classes and Objects
 * ----------------------
 * This lesson covers:
 * 1. Class definition and instantiation
 * 2. Properties and their types
 * 3. Access modifiers (public, private, protected)
 * 4. Constructor and destructor
 * 5. Static methods and properties
 * 6. Constants within classes
 */

// Basic class definition
class User {
    // Properties with type declarations (PHP 7.4+)
    private string $name;
    private int $age;
    public static int $userCount = 0;
    const MINIMUM_AGE = 18;

    // Constructor
    public function __construct(string $name, int $age) {
        $this->name = $name;
        $this->age = $age;
        self::$userCount++;
    }

    // Destructor
    public function __destruct() {
        self::$userCount--;
    }

    // Regular method
    public function getInfo(): string {
        return "Name: {$this->name}, Age: {$this->age}";
    }

    // Static method
    public static function getUserCount(): int {
        return self::$userCount;
    }

    // Method with type checking
    public function setAge(int $age): void {
        if ($age < self::MINIMUM_AGE) {
            throw new InvalidArgumentException("Age must be at least " . self::MINIMUM_AGE);
        }
        $this->age = $age;
    }
}

// Using the class
echo "=== Basic Class Usage ===\n";
$user1 = new User("John", 25);
$user2 = new User("Jane", 30);

echo $user1->getInfo() . "\n";
echo "Total users: " . User::getUserCount() . "\n";

/**
 * Common Questions and Tricky Situations:
 * -------------------------------------
 * Q1: What's the difference between self:: and $this->?
 * A: self:: refers to the current class and is used for static members,
 *    while $this-> refers to the current instance and is used for instance members.
 * 
 * Q2: Why use type declarations?
 * A: They provide better code documentation, catch errors early,
 *    and improve IDE support.
 * 
 * Q3: When is the destructor called?
 * A: When object reference count reaches zero or at shutdown.
 *    Don't rely on exact timing for cleanup.
 * 
 * Q4: What's the difference between private and protected?
 * A: Private members are only accessible within the declaring class,
 *    while protected members are also accessible in child classes.
 * 
 * Q5: Can you have multiple constructors?
 * A: PHP doesn't support constructor overloading like Java.
 *    Use named constructors (static methods) instead:
 */

class Example {
    private function __construct() {}
    
    public static function fromArray(array $data): self {
        $instance = new self();
        // Initialize from array
        return $instance;
    }
    
    public static function fromJson(string $json): self {
        $instance = new self();
        // Initialize from JSON
        return $instance;
    }
}

/**
 * Best Practices:
 * 1. Use type declarations for properties and methods
 * 2. Keep classes focused on a single responsibility
 * 3. Use meaningful names for classes and methods
 * 4. Avoid public properties, use getters/setters
 * 5. Use constants for fixed values
 * 6. Document your classes with PHPDoc comments
 * 
 * Common Pitfalls:
 * 1. Forgetting to use $this-> inside methods
 * 2. Mixing static and instance contexts
 * 3. Circular references causing memory leaks
 * 4. Not handling constructor exceptions
 * 5. Overusing static methods/properties
 */

// Example of a more complex class structure
class UserManager {
    private static ?UserManager $instance = null;
    private array $users = [];

    private function __construct() {}

    public static function getInstance(): self {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function addUser(User $user): void {
        $this->users[] = $user;
    }

    public function getUsers(): array {
        return $this->users;
    }
}

// Using the Singleton pattern
$manager = UserManager::getInstance();
$manager->addUser($user1);
$manager->addUser($user2);

foreach ($manager->getUsers() as $user) {
    echo $user->getInfo() . "\n";
}

/**
 * PHP Classes and Objects (PHP 8.x Features)
 * ----------------------------------------
 * New PHP 8.x Features:
 * 1. Constructor property promotion
 * 2. Readonly properties
 * 3. Named arguments
 * 4. Match expression
 * 5. Nullsafe operator
 * 6. Enums
 * 7. Mixed type
 * 8. Union types
 * 9. Null coalescing assignment operator
 */

// Enum example (PHP 8.1+)
enum UserStatus: string {
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case PENDING = 'pending';
}

// Modern class with PHP 8.x features
class User {
    // Constructor property promotion with readonly properties (PHP 8.1+)
    public function __construct(
        private readonly string $name,
        private readonly int $age,
        private UserStatus $status = UserStatus::PENDING,
        private ?string $email = null  // Union type with null
    ) {
        self::$userCount++;
    }

    // Static property with type (PHP 7.4+)
    public static int $userCount = 0;
    
    // Class constant
    final public const MINIMUM_AGE = 18;

    // Method using match expression (PHP 8.0+)
    public function getStatusMessage(): string {
        return match($this->status) {
            UserStatus::ACTIVE => "User is active",
            UserStatus::INACTIVE => "User is inactive",
            UserStatus::PENDING => "User is pending",
        };
    }

    // Method with union type (PHP 8.0+)
    public function setEmail(string|null $email): void {
        $this->email = $email;
    }

    // Method with mixed type (PHP 8.0+)
    public function processData(mixed $data): string {
        return match(true) {
            is_array($data) => json_encode($data),
            is_object($data) => serialize($data),
            default => (string) $data,
        };
    }
}

// Advanced class example with more PHP 8 features
class UserRepository {
    public function __construct(
        private readonly PDO $db,
        private readonly ?Logger $logger = null,
    ) {}

    // Nullsafe operator (PHP 8.0+)
    public function logUserAction(string $action): void {
        $this->logger?->log($action);
    }

    // Named arguments example
    public function createUser(
        string $name,
        int $age,
        ?string $email = null,
        UserStatus $status = UserStatus::PENDING,
    ): User {
        // Using named arguments
        return new User(
            name: $name,
            age: $age,
            email: $email,
            status: $status,
        );
    }
}

/**
 * New PHP 8.x Features Explained:
 * -----------------------------
 * 1. Constructor Property Promotion:
 *    - Automatically creates and initializes properties from constructor parameters
 *    - Reduces boilerplate code
 * 
 * 2. Readonly Properties (8.1+):
 *    - Can only be set once in the constructor
 *    - Prevents modification after initialization
 * 
 * 3. Named Arguments:
 *    - Makes code more readable
 *    - Allows skipping optional parameters
 * 
 * 4. Enums (8.1+):
 *    - Type-safe way to represent a fixed set of values
 *    - Can have methods and implement interfaces
 * 
 * 5. Union Types:
 *    - Allow multiple types for properties/parameters
 *    - Example: string|null, int|float
 * 
 * Common Questions about PHP 8.x Features:
 * -------------------------------------
 * Q1: Can readonly properties be modified using reflection?
 * A: No, readonly is enforced at the engine level
 * 
 * Q2: What's the difference between mixed and union types?
 * A: mixed accepts any type, while union types specify exact allowed types
 * 
 * Q3: When should I use enums vs class constants?
 * A: Use enums when you need type safety and/or methods for the values
 * 
 * Q4: Can I use nullsafe operator with method chains?
 * A: Yes, $object?->method1()?->method2()
 */

// Usage examples
$user = new User(
    name: "John Doe",
    age: 25,
    status: UserStatus::ACTIVE,
    email: "john@example.com"
);

echo $user->getStatusMessage() . "\n";

// Using repository with named arguments
$repo = new UserRepository(
    db: new PDO('mysql:host=localhost;dbname=test', 'user', 'pass'),
    logger: new Logger()
);

$newUser = $repo->createUser(
    name: "Jane Doe",
    age: 30,
    email: "jane@example.com"
); 