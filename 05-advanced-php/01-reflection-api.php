<?php
/**
 * PHP Reflection API and Metaprogramming (PHP 8.x)
 * --------------------------------------
 * This lesson covers:
 * 1. Class reflection
 * 2. Method reflection
 * 3. Property reflection
 * 4. Attribute handling
 * 5. Dynamic code execution
 * 6. Code generation
 */

// Example attributes
#[Attribute]
class Route {
    public function __construct(
        public readonly string $path,
        public readonly string $method = 'GET'
    ) {}
}

#[Attribute]
class Injectable {
    public function __construct(
        public readonly string $scope = 'singleton'
    ) {}
}

// Example class with attributes and different features to reflect
#[Injectable('singleton')]
class UserService {
    private string $secretKey;
    
    public function __construct(
        private readonly UserRepository $repository,
        private readonly Logger $logger
    ) {
        $this->secretKey = bin2hex(random_bytes(32));
    }

    #[Route('/users/{id}', 'GET')]
    public function getUser(int $id): ?User {
        return $this->repository->find($id);
    }

    #[Route('/users', 'POST')]
    protected function createUser(array $data): User {
        return $this->repository->create($data);
    }
}

// Reflection utility class
class ReflectionUtil {
    public static function analyzeClass(string $className): array {
        $reflection = new ReflectionClass($className);
        
        return [
            'class' => self::getClassInfo($reflection),
            'attributes' => self::getAttributesInfo($reflection),
            'properties' => self::getPropertiesInfo($reflection),
            'methods' => self::getMethodsInfo($reflection)
        ];
    }

    private static function getClassInfo(ReflectionClass $reflection): array {
        return [
            'name' => $reflection->getName(),
            'namespace' => $reflection->getNamespaceName(),
            'isAbstract' => $reflection->isAbstract(),
            'isFinal' => $reflection->isFinal(),
            'isReadonly' => $reflection->isReadOnly(),
            'parentClass' => $reflection->getParentClass()?->getName(),
            'interfaces' => $reflection->getInterfaceNames(),
            'traits' => array_keys($reflection->getTraits())
        ];
    }

    private static function getAttributesInfo(ReflectionClass|ReflectionMethod|ReflectionProperty $reflection): array {
        $attributes = [];
        
        foreach ($reflection->getAttributes() as $attribute) {
            $attributes[] = [
                'name' => $attribute->getName(),
                'arguments' => $attribute->getArguments()
            ];
        }

        return $attributes;
    }

    private static function getPropertiesInfo(ReflectionClass $reflection): array {
        $properties = [];
        
        foreach ($reflection->getProperties() as $property) {
            $properties[$property->getName()] = [
                'type' => $property->getType()?->getName(),
                'isPrivate' => $property->isPrivate(),
                'isProtected' => $property->isProtected(),
                'isPublic' => $property->isPublic(),
                'isReadonly' => $property->isReadOnly(),
                'isStatic' => $property->isStatic(),
                'attributes' => self::getAttributesInfo($property)
            ];
        }

        return $properties;
    }

    private static function getMethodsInfo(ReflectionClass $reflection): array {
        $methods = [];
        
        foreach ($reflection->getMethods() as $method) {
            $methods[$method->getName()] = [
                'returnType' => $method->getReturnType()?->getName(),
                'isPrivate' => $method->isPrivate(),
                'isProtected' => $method->isProtected(),
                'isPublic' => $method->isPublic(),
                'isStatic' => $method->isStatic(),
                'parameters' => self::getParametersInfo($method),
                'attributes' => self::getAttributesInfo($method)
            ];
        }

        return $methods;
    }

    private static function getParametersInfo(ReflectionMethod $method): array {
        $parameters = [];
        
        foreach ($method->getParameters() as $param) {
            $parameters[$param->getName()] = [
                'type' => $param->getType()?->getName(),
                'isOptional' => $param->isOptional(),
                'hasDefaultValue' => $param->isDefaultValueAvailable(),
                'defaultValue' => $param->isDefaultValueAvailable() ? $param->getDefaultValue() : null
            ];
        }

        return $parameters;
    }
}

// Dynamic class generator
class ClassGenerator {
    public static function generateClass(string $className, array $properties, array $methods): string {
        $code = "class {$className} {\n";
        
        // Generate properties
        foreach ($properties as $name => $type) {
            $code .= "    private {$type} \${$name};\n";
        }
        
        // Generate constructor
        $code .= "\n    public function __construct(\n";
        foreach ($properties as $name => $type) {
            $code .= "        private readonly {$type} \${$name},\n";
        }
        $code = rtrim($code, ",\n") . "\n    ) {}\n";
        
        // Generate methods
        foreach ($methods as $name => $return) {
            $code .= "\n    public function {$name}(): {$return} {\n";
            $code .= "        // TODO: Implement {$name}\n";
            $code .= "    }\n";
        }
        
        $code .= "}\n";
        
        return $code;
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: When to use reflection?
 * A: For framework development, testing, and metaprogramming
 * 
 * Q2: What are the performance implications?
 * A: Reflection is slower than direct code, use caching when possible
 * 
 * Q3: How to handle private properties/methods?
 * A: Use setAccessible(true) but be careful with encapsulation
 * 
 * Q4: What about PHP 8 attributes?
 * A: Use getAttributes() and instanceof for type-safe handling
 */

// Usage example
try {
    // Analyze class using reflection
    $analysis = ReflectionUtil::analyzeClass(UserService::class);
    
    // Print analysis
    echo "Class Analysis:\n";
    print_r($analysis);

    // Generate dynamic class
    $code = ClassGenerator::generateClass(
        className: 'DynamicUser',
        properties: [
            'id' => 'int',
            'name' => 'string',
            'email' => 'string'
        ],
        methods: [
            'getId' => 'int',
            'getName' => 'string',
            'getEmail' => 'string'
        ]
    );

    // Save generated code
    file_put_contents('DynamicUser.php', "<?php\n\n" . $code);
    
    echo "\nGenerated Class:\n";
    echo $code;

} catch (ReflectionException $e) {
    echo "Reflection Error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

/**
 * Best Practices:
 * 1. Cache reflection results
 * 2. Handle security implications
 * 3. Respect encapsulation
 * 4. Document generated code
 * 5. Use type declarations
 * 6. Handle errors gracefully
 * 
 * New PHP 8.x Features Used:
 * 1. Attributes
 * 2. Constructor property promotion
 * 3. Readonly properties
 * 4. Named arguments
 * 5. Union types
 * 6. Nullsafe operator
 */ 