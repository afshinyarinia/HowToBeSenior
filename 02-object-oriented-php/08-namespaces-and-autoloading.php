<?php
/**
 * PHP Namespaces and Autoloading (PHP 8.x)
 * -------------------------------------
 * This lesson covers:
 * 1. Namespace declaration and usage
 * 2. PSR-4 autoloading
 * 3. Composer autoloading
 * 4. Use statements and aliases
 * 5. Global namespace
 * 6. Multiple namespace syntax
 * 7. Namespace resolution
 */

// Namespace declaration
namespace App\Examples;

// Use statements
use DateTime;
use App\Examples\Models\{User, Product};
use App\Examples\Services\UserService;
use App\Examples\Interfaces\Repository;
use InvalidArgumentException;
use Throwable;

// Class in the current namespace
class AutoloadExample {
    private array $items = [];
    
    public function __construct(
        private readonly UserService $userService,
        private readonly ?Repository $repository = null,
    ) {}

    public function addItem(string $name, mixed $value): void {
        $this->items[$name] = [
            'value' => $value,
            'added_at' => new DateTime(),
        ];
    }

    public function getItem(string $name): mixed {
        return $this->items[$name]['value'] ?? throw new InvalidArgumentException("Item not found: $name");
    }
}

// Example of namespace resolution
namespace App\Examples\Models {
    class User {
        public function __construct(
            public readonly string $name,
            public readonly string $email
        ) {}
    }

    class Product {
        public function __construct(
            public readonly string $name,
            public readonly float $price
        ) {}
    }
}

// Interface in a different namespace
namespace App\Examples\Interfaces {
    interface Repository {
        public function find(int $id): mixed;
        public function save(mixed $entity): void;
    }
}

// Service in another namespace
namespace App\Examples\Services {
    use App\Examples\Models\User;
    use App\Examples\Interfaces\Repository;

    class UserService {
        public function __construct(
            private readonly Repository $repository
        ) {}

        public function createUser(string $name, string $email): User {
            return new User($name, $email);
        }
    }
}

/**
 * PSR-4 Autoloading Example (typically in composer.json):
 * {
 *     "autoload": {
 *         "psr-4": {
 *             "App\\": "src/"
 *         }
 *     }
 * }
 */

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: What's the difference between use and namespace?
 * A: namespace declares the current namespace,
 *    use imports classes from other namespaces
 * 
 * Q2: How does PSR-4 autoloading work?
 * A: Maps namespace prefixes to directory structures,
 *    automatically loads classes when used
 * 
 * Q3: Can I have multiple namespaces in one file?
 * A: Yes, but it's not recommended for maintainability
 * 
 * Q4: How do I reference global namespace classes?
 * A: Use leading backslash: \DateTime or import with use
 */

// Usage example (in a different file)
namespace App\Examples\Usage {
    use App\Examples\AutoloadExample;
    use App\Examples\Services\UserService;
    use App\Examples\Models\User;

    // Example implementation
    class Usage {
        public function example(): void {
            $service = new UserService(new class implements \App\Examples\Interfaces\Repository {
                public function find(int $id): mixed {
                    return null;
                }
                public function save(mixed $entity): void {}
            });

            $example = new AutoloadExample($service);
            $example->addItem('user', new User('John Doe', 'john@example.com'));
            
            try {
                $user = $example->getItem('user');
                echo $user->name . "\n";
            } catch (\Throwable $e) {
                echo "Error: " . $e->getMessage() . "\n";
            }
        }
    }
}

/**
 * Best Practices:
 * 1. Follow PSR-4 autoloading standard
 * 2. Use meaningful namespace hierarchies
 * 3. One class per file
 * 4. Match namespace to directory structure
 * 5. Use composer for autoloading
 * 6. Import classes with use statements
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly properties
 * 3. Union types
 * 4. Nullsafe operator
 * 5. Named arguments
 * 6. Match expressions
 */ 