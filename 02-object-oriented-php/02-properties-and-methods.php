<?php
/**
 * PHP Properties and Methods (PHP 8.x)
 * ----------------------------------
 * This lesson covers:
 * 1. Property types and type declarations
 * 2. Readonly properties (8.1+)
 * 3. Constructor property promotion
 * 4. Method return types
 * 5. Method parameter types
 * 6. Intersection types (8.1+)
 * 7. First-class callable syntax (8.1+)
 */

// Interface for type demonstration
interface Nameable {
    public function getName(): string;
}

interface Identifiable {
    public function getId(): int;
}

// Class using modern PHP 8.x features
class Product implements Nameable, Identifiable {
    // Constructor property promotion with types
    public function __construct(
        private readonly int $id,
        private string $name,
        private float $price,
        protected ?string $description = null,
        private array $tags = [],
    ) {}

    // Getter with union type return
    public function getName(): string {
        return $this->name;
    }

    public function getId(): int {
        return $this->id;
    }

    // Method with intersection types (PHP 8.1+)
    public function processHandler(Nameable&Identifiable $handler): void {
        echo "Processing {$handler->getName()} with ID {$handler->getId()}\n";
    }

    // Method with union types and mixed
    public function setMetadata(array|object $metadata): mixed {
        return match(true) {
            is_array($metadata) => $metadata['value'] ?? null,
            is_object($metadata) => $metadata->value ?? null,
        };
    }

    // First-class callable syntax (PHP 8.1+)
    public function getTagProcessor(): callable {
        return $this->processTags(...);
    }

    private function processTags(array $tags): array {
        return array_map(strtoupper(...), $tags);
    }
}

// New class with readonly properties (PHP 8.1+)
readonly class Configuration {
    public function __construct(
        public string $apiKey,
        public string $apiSecret,
        public array $options = [],
    ) {}
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: When should I use readonly classes vs readonly properties?
 * A: Use readonly classes when all properties should be immutable,
 *    use readonly properties when only specific properties should be immutable
 * 
 * Q2: Can I use intersection types with built-in types?
 * A: No, intersection types only work with interfaces and classes
 * 
 * Q3: What's the difference between mixed and union types?
 * A: mixed accepts any type, while union types specify exact allowed types
 * 
 * Q4: When should I use constructor property promotion?
 * A: Use it when properties are initialized through the constructor
 *    and don't need complex initialization logic
 */

// Usage examples
$product = new Product(
    id: 1,
    name: "Laptop",
    price: 999.99,
    description: "Powerful laptop",
    tags: ['electronics', 'computers']
);

// Using first-class callable
$tagProcessor = $product->getTagProcessor();
$tags = $tagProcessor(['php', 'programming']);
print_r($tags);

// Using readonly class
$config = new Configuration(
    apiKey: "abc123",
    apiSecret: "xyz789",
    options: ['debug' => true]
);

// This would cause an error because Configuration is readonly
// $config->apiKey = "new-key";

/**
 * Best Practices:
 * 1. Use type declarations for all properties and methods
 * 2. Use readonly when properties shouldn't change after initialization
 * 3. Use constructor property promotion for simple property initialization
 * 4. Use intersection types for more precise type checking
 * 5. Consider using readonly classes for value objects
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly properties and classes
 * 3. Union types
 * 4. Intersection types
 * 5. First-class callable syntax
 * 6. Named arguments
 * 7. Match expression
 */ 