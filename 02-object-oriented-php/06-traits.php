<?php
/**
 * PHP Traits (PHP 8.x)
 * ------------------
 * This lesson covers:
 * 1. Basic trait usage
 * 2. Trait properties and methods
 * 3. Trait conflicts resolution
 * 4. Abstract methods in traits
 * 5. Static methods in traits
 * 6. Private and protected members
 * 7. Trait composition
 */

// Timestamp functionality trait
trait Timestampable {
    private \DateTime $createdAt;
    private ?\DateTime $updatedAt = null;

    public function initializeTimestamps(): void {
        $this->createdAt = new \DateTime();
    }

    public function getCreatedAt(): \DateTime {
        return $this->createdAt;
    }

    public function setUpdatedAt(): void {
        $this->updatedAt = new \DateTime();
    }

    public function getUpdatedAt(): ?\DateTime {
        return $this->updatedAt;
    }
}

// Serialization trait
trait Serializable {
    public function toArray(): array {
        return get_object_vars($this);
    }

    public function toJson(): string {
        return json_encode($this->toArray());
    }
}

// Validation trait with abstract method
trait Validatable {
    private array $errors = [];

    abstract public function getRules(): array;

    public function validate(): bool {
        $this->errors = [];
        foreach ($this->getRules() as $property => $rules) {
            $value = $this->$property ?? null;
            foreach ($rules as $rule) {
                if (!$this->validateRule($property, $value, $rule)) {
                    return false;
                }
            }
        }
        return true;
    }

    private function validateRule(string $property, mixed $value, string $rule): bool {
        switch ($rule) {
            case 'required':
                if (empty($value)) {
                    $this->errors[$property][] = "$property is required";
                    return false;
                }
                break;
            case 'email':
                if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $this->errors[$property][] = "$property must be a valid email";
                    return false;
                }
                break;
        }
        return true;
    }

    public function getErrors(): array {
        return $this->errors;
    }
}

// Class using multiple traits
class User {
    // Use multiple traits
    use Timestampable, Serializable, Validatable;

    public function __construct(
        private readonly string $id,
        private string $name,
        private string $email
    ) {
        $this->initializeTimestamps();
    }

    // Implementation of abstract method from Validatable trait
    public function getRules(): array {
        return [
            'name' => ['required'],
            'email' => ['required', 'email']
        ];
    }
}

// Trait conflict resolution example
trait Logger {
    public function log(string $message): void {
        echo "[LOG] $message\n";
    }
}

trait Debugger {
    public function log(string $message): void {
        echo "[DEBUG] $message\n";
    }
}

class Application {
    // Resolve conflict by aliasing
    use Logger, Debugger {
        Logger::log as logInfo;
        Debugger::log insteadof Logger;
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: Can traits have constructors?
 * A: Yes, but they might conflict with class constructors.
 *    Better to use initialization methods.
 * 
 * Q2: Can traits implement interfaces?
 * A: No, but they can provide the implementation for a class
 *    that implements an interface.
 * 
 * Q3: What's the order of precedence for methods?
 * A: Class methods > Trait methods > Parent methods
 * 
 * Q4: Can traits have properties?
 * A: Yes, but be careful with property conflicts
 */

// Usage examples
echo "=== Traits Examples ===\n";

$user = new User(
    id: uniqid(),
    name: "John Doe",
    email: "invalid-email"
);

// Using trait methods
echo "Created at: " . $user->getCreatedAt()->format('Y-m-d H:i:s') . "\n";
echo "JSON: " . $user->toJson() . "\n";

// Validation
if (!$user->validate()) {
    echo "Validation errors:\n";
    print_r($user->getErrors());
}

// Trait conflict resolution
$app = new Application();
$app->log("Debug message");    // Uses Debugger trait
$app->logInfo("Info message"); // Uses Logger trait

/**
 * Best Practices:
 * 1. Use traits for cross-cutting concerns
 * 2. Keep traits focused and small
 * 3. Avoid trait property conflicts
 * 4. Document trait dependencies
 * 5. Use initialization methods instead of constructors
 * 6. Be careful with trait composition
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly properties
 * 3. Union types
 * 4. Mixed type
 * 5. Nullsafe operator
 */ 