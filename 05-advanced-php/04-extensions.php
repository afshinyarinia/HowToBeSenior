<?php
/**
 * PHP Extensions Development (PHP 8.x)
 * ---------------------------
 * This lesson covers:
 * 1. Extension structure
 * 2. PHP internals
 * 3. Zend Engine
 * 4. Native functions
 * 5. Performance optimization
 * 6. FFI integration
 */

// FFI (Foreign Function Interface) example
class NativeLibrary {
    private FFI $ffi;

    public function __construct(string $headerFile, string $libraryPath) {
        // Load C header and library
        $this->ffi = FFI::cdef(
            file_get_contents($headerFile),
            $libraryPath
        );
    }

    public function call(string $function, mixed ...$args): mixed {
        return $this->ffi->$function(...$args);
    }
}

// Example of PHP extension functionality simulation
class ExtensionSimulator {
    private array $functions = [];
    private array $constants = [];
    private array $classes = [];

    public function registerFunction(string $name, callable $callback): void {
        $this->functions[$name] = $callback;
    }

    public function registerConstant(string $name, mixed $value): void {
        $this->constants[$name] = $value;
    }

    public function registerClass(string $name, string $definition): void {
        $this->classes[$name] = $definition;
    }

    public function call(string $function, mixed ...$args): mixed {
        if (!isset($this->functions[$function])) {
            throw new RuntimeException("Function not found: $function");
        }
        return ($this->functions[$function])(...$args);
    }

    public function getConstant(string $name): mixed {
        return $this->constants[$name] ?? null;
    }
}

// Native function wrapper
class NativeFunctionWrapper {
    private array $cache = [];

    public function wrap(string $function): callable {
        if (!isset($this->cache[$function])) {
            $this->cache[$function] = function(...$args) use ($function) {
                $start = microtime(true);
                $result = $function(...$args);
                $duration = microtime(true) - $start;
                
                // Log performance metrics
                $this->logPerformance($function, $duration);
                
                return $result;
            };
        }
        
        return $this->cache[$function];
    }

    private function logPerformance(string $function, float $duration): void {
        // Log performance data
        error_log(sprintf(
            "Function %s took %.4f seconds",
            $function,
            $duration
        ));
    }
}

// Zend Engine interaction simulation
class ZendEngineSimulator {
    private array $variables = [];
    private array $symbols = [];

    public function createVariable(string $name, mixed $value): void {
        $this->variables[$name] = [
            'value' => $value,
            'type' => $this->getZendType($value),
            'refcount' => 1
        ];
    }

    public function addSymbol(string $name, callable $handler): void {
        $this->symbols[$name] = $handler;
    }

    public function executeOpcode(string $opcode, array $operands): mixed {
        // Simulate opcode execution
        return match($opcode) {
            'ADD' => $operands[0] + $operands[1],
            'SUB' => $operands[0] - $operands[1],
            'MUL' => $operands[0] * $operands[1],
            'DIV' => $operands[0] / $operands[1],
            default => throw new RuntimeException("Unknown opcode: $opcode")
        };
    }

    private function getZendType(mixed $value): string {
        return match(true) {
            is_null($value) => 'IS_NULL',
            is_bool($value) => 'IS_BOOL',
            is_int($value) => 'IS_LONG',
            is_float($value) => 'IS_DOUBLE',
            is_string($value) => 'IS_STRING',
            is_array($value) => 'IS_ARRAY',
            is_object($value) => 'IS_OBJECT',
            is_resource($value) => 'IS_RESOURCE',
            default => 'UNKNOWN'
        };
    }
}

// Example extension implementation
class CustomExtension {
    private ExtensionSimulator $extension;
    private ZendEngineSimulator $zend;

    public function __construct() {
        $this->extension = new ExtensionSimulator();
        $this->zend = new ZendEngineSimulator();
        $this->initialize();
    }

    private function initialize(): void {
        // Register custom functions
        $this->extension->registerFunction('custom_hash', function(string $data): string {
            return hash('sha256', $data);
        });

        // Register constants
        $this->extension->registerConstant('CUSTOM_VERSION', '1.0.0');

        // Register Zend engine symbols
        $this->zend->addSymbol('custom_hash', function($context, $args) {
            return hash('sha256', $args[0]);
        });
    }

    public function getInfo(): array {
        return [
            'name' => 'custom_extension',
            'version' => $this->extension->getConstant('CUSTOM_VERSION'),
            'functions' => array_keys($this->extension->functions),
            'constants' => array_keys($this->extension->constants)
        ];
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: When to create a PHP extension?
 * A: For performance-critical operations or system-level integration
 * 
 * Q2: How to handle memory in extensions?
 * A: Use Zend Memory Manager and proper memory allocation/deallocation
 * 
 * Q3: How to debug extensions?
 * A: Use GDB, Valgrind, and PHP's debug build
 * 
 * Q4: How to handle PHP version compatibility?
 * A: Use version-specific macros and conditional compilation
 */

// Usage example
try {
    // FFI example with libcurl
    $curl = new NativeLibrary(
        headerFile: 'curl.h',
        libraryPath: 'libcurl.so'
    );

    // Custom extension usage
    $extension = new CustomExtension();
    $info = $extension->getInfo();
    print_r($info);

    // Native function wrapping
    $wrapper = new NativeFunctionWrapper();
    $wrappedStrlen = $wrapper->wrap('strlen');
    $length = $wrappedStrlen('test string');

    // Zend engine simulation
    $zend = new ZendEngineSimulator();
    $result = $zend->executeOpcode('ADD', [5, 3]);
    echo "5 + 3 = $result\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

/**
 * Best Practices:
 * 1. Follow PHP extension standards
 * 2. Handle errors properly
 * 3. Document extension API
 * 4. Test thoroughly
 * 5. Consider compatibility
 * 6. Profile performance
 * 
 * New PHP 8.x Features Used:
 * 1. FFI
 * 2. Named arguments
 * 3. Match expressions
 * 4. Constructor property promotion
 * 5. Union types
 * 6. Nullsafe operator
 */ 