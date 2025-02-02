<?php
/**
 * PHP Abstract Classes and Interfaces (PHP 8.x)
 * -----------------------------------------
 * This lesson covers:
 * 1. Abstract classes and methods
 * 2. Interfaces and implementation
 * 3. Multiple interface inheritance
 * 4. Interface constants
 * 5. Type declarations
 * 6. Interface inheritance
 * 7. Mixed implementation
 */

// Interface definitions
interface Loggable {
    public function getLogEntry(): string;
}

interface Jsonable {
    public function toJson(): string;
}

interface Identifiable {
    public function getId(): string|int;
}

// Abstract class with interface implementation
abstract class Entity implements Identifiable, Jsonable {
    public function __construct(
        protected readonly string|int $id,
        protected string $name,
        protected readonly \DateTime $createdAt = new \DateTime(),
    ) {}

    // Implementation of Identifiable interface
    public function getId(): string|int {
        return $this->id;
    }

    // Implementation of Jsonable interface
    public function toJson(): string {
        return json_encode([
            'id' => $this->id,
            'name' => $this->name,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s')
        ]);
    }

    // Abstract method that must be implemented by children
    abstract public function validate(): bool;
}

// Interface with PHP 8 features
interface Repository {
    public function find(int|string $id): ?Entity;
    public function save(Entity $entity): void;
    public function delete(Entity $entity): void;
    public function findAll(): array;
}

// Concrete class implementing abstract class and additional interface
class User extends Entity implements Loggable {
    public function __construct(
        string|int $id,
        string $name,
        protected string $email,
        protected array $roles = [],
        \DateTime $createdAt = new \DateTime(),
    ) {
        parent::__construct($id, $name, $createdAt);
    }

    // Implementation of abstract method
    public function validate(): bool {
        return filter_var($this->email, FILTER_VALIDATE_EMAIL) !== false;
    }

    // Implementation of Loggable interface
    public function getLogEntry(): string {
        return sprintf(
            "User[%s]: %s (%s) - Roles: %s",
            $this->id,
            $this->name,
            $this->email,
            implode(', ', $this->roles)
        );
    }
}

// Concrete repository implementation
class UserRepository implements Repository {
    private array $users = [];

    public function find(int|string $id): ?Entity {
        return $this->users[$id] ?? null;
    }

    public function save(Entity $entity): void {
        $this->users[$entity->getId()] = $entity;
    }

    public function delete(Entity $entity): void {
        unset($this->users[$entity->getId()]);
    }

    public function findAll(): array {
        return array_values($this->users);
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: Can interfaces have properties?
 * A: No, interfaces can only declare constants and methods
 * 
 * Q2: Can a class implement multiple interfaces?
 * A: Yes, PHP supports multiple interface implementation
 * 
 * Q3: When should I use abstract classes vs interfaces?
 * A: Use interfaces for defining contracts, abstract classes for sharing implementation
 * 
 * Q4: Can interfaces extend other interfaces?
 * A: Yes, interfaces can extend one or more other interfaces
 */

// Usage examples
echo "=== Abstract Classes and Interfaces Examples ===\n";

$user = new User(
    id: 1,
    name: "John Doe",
    email: "john@example.com",
    roles: ['user', 'admin']
);

$repository = new UserRepository();
$repository->save($user);

// Using interface methods
echo "User JSON: " . $user->toJson() . "\n";
echo "Log Entry: " . $user->getLogEntry() . "\n";

// Validation
if ($user->validate()) {
    echo "User is valid\n";
} else {
    echo "User is invalid\n";
}

/**
 * Best Practices:
 * 1. Keep interfaces focused and small
 * 2. Use type declarations consistently
 * 3. Prefer interface inheritance over class inheritance
 * 4. Use abstract classes to reduce code duplication
 * 5. Follow Interface Segregation Principle
 * 6. Document interface contracts clearly
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Union types
 * 3. Readonly properties
 * 4. Named arguments
 * 5. Nullsafe operator
 * 6. Mixed type
 */ 