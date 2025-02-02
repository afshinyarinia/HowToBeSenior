<?php
/**
 * PHP Magic Methods (PHP 8.x)
 * ------------------------
 * This lesson covers:
 * 1. __construct() and __destruct()
 * 2. __get(), __set(), __isset(), __unset()
 * 3. __call() and __callStatic()
 * 4. __toString()
 * 5. __clone()
 * 6. __serialize() and __unserialize()
 * 7. __invoke()
 * 8. __debugInfo()
 */

class DynamicProperties {
    private array $data = [];
    private array $allowedProperties = ['name', 'email', 'age'];

    // Magic method for getting undefined properties
    public function __get(string $name): mixed {
        if (!in_array($name, $this->allowedProperties)) {
            throw new \InvalidArgumentException("Property $name is not allowed");
        }
        return $this->data[$name] ?? null;
    }

    // Magic method for setting undefined properties
    public function __set(string $name, mixed $value): void {
        if (!in_array($name, $this->allowedProperties)) {
            throw new \InvalidArgumentException("Property $name is not allowed");
        }
        $this->data[$name] = $value;
    }

    // Magic method for checking if property exists
    public function __isset(string $name): bool {
        return isset($this->data[$name]);
    }

    // Magic method for unsetting properties
    public function __unset(string $name): void {
        unset($this->data[$name]);
    }

    // Magic method for string representation
    public function __toString(): string {
        return json_encode($this->data, JSON_PRETTY_PRINT);
    }

    // Magic method for debugging
    public function __debugInfo(): array {
        return [
            'data' => $this->data,
            'allowedProperties' => $this->allowedProperties,
            'propertyCount' => count($this->data)
        ];
    }
}

// Class demonstrating method interception
class MethodInterceptor {
    private array $methods = [
        'hello' => 'sayHello',
        'goodbye' => 'sayGoodbye'
    ];

    // Magic method for handling undefined method calls
    public function __call(string $name, array $arguments): mixed {
        if (isset($this->methods[$name])) {
            $method = $this->methods[$name];
            return $this->$method(...$arguments);
        }
        throw new \BadMethodCallException("Method $name does not exist");
    }

    // Magic method for handling undefined static method calls
    public static function __callStatic(string $name, array $arguments): mixed {
        return match($name) {
            'version' => 'v1.0.0',
            'info' => 'Static method interceptor',
            default => throw new \BadMethodCallException("Static method $name does not exist")
        };
    }

    private function sayHello(string $name): string {
        return "Hello, $name!";
    }

    private function sayGoodbye(string $name): string {
        return "Goodbye, $name!";
    }
}

// Class demonstrating invokable objects
class Calculator {
    public function __construct(
        private readonly string $operation
    ) {}

    // Magic method to make object callable
    public function __invoke(float $a, float $b): float {
        return match($this->operation) {
            '+' => $a + $b,
            '-' => $a - $b,
            '*' => $a * $b,
            '/' => $b != 0 ? $a / $b : throw new \DivisionByZeroError("Division by zero"),
            default => throw new \InvalidArgumentException("Unknown operation: {$this->operation}")
        };
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: When should I use __get() and __set()?
 * A: Use for dynamic properties, validation, or property access control
 * 
 * Q2: What's the difference between __toString() and __debugInfo()?
 * A: __toString() is for string conversion, __debugInfo() for var_dump() output
 * 
 * Q3: Can I prevent cloning of objects?
 * A: Yes, by making __clone() private or throwing an exception
 * 
 * Q4: When should I use __call()?
 * A: For method interception, dynamic methods, or proxy patterns
 */

// Usage examples
echo "=== Magic Methods Examples ===\n";

// Dynamic properties
$obj = new DynamicProperties();
$obj->name = "John Doe";
$obj->email = "john@example.com";
echo $obj . "\n"; // Uses __toString()

// Method interception
$interceptor = new MethodInterceptor();
echo $interceptor->hello("John") . "\n";
echo MethodInterceptor::version() . "\n";

// Invokable object
$add = new Calculator('+');
$multiply = new Calculator('*');
echo "2 + 3 = " . $add(2, 3) . "\n";
echo "4 * 5 = " . $multiply(4, 5) . "\n";

// Debugging
var_dump($obj); // Uses __debugInfo()

/**
 * Best Practices:
 * 1. Use magic methods sparingly
 * 2. Document magic method behavior
 * 3. Implement proper error handling
 * 4. Keep magic methods simple
 * 5. Consider performance implications
 * 6. Use type declarations
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Match expressions
 * 3. Named arguments
 * 4. Union types
 * 5. Readonly properties
 * 6. Mixed type
 */ 