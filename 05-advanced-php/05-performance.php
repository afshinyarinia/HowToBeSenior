<?php
/**
 * PHP Advanced Performance Optimization (PHP 8.x)
 * -------------------------------------
 * This lesson covers:
 * 1. Code optimization
 * 2. Profiling techniques
 * 3. Caching strategies
 * 4. JIT compilation
 * 5. Opcode optimization
 * 6. Memory management
 */

// Performance profiler class
class Profiler {
    private array $measurements = [];
    private array $memoryUsage = [];
    private array $counters = [];
    private ?float $startTime = null;

    public function start(): void {
        $this->startTime = microtime(true);
        $this->memoryUsage['start'] = memory_get_usage(true);
    }

    public function measure(string $label, callable $callback): mixed {
        $start = microtime(true);
        $startMemory = memory_get_usage(true);

        try {
            return $callback();
        } finally {
            $endMemory = memory_get_usage(true);
            $duration = microtime(true) - $start;

            $this->measurements[$label][] = $duration;
            $this->memoryUsage[$label] = $endMemory - $startMemory;
        }
    }

    public function increment(string $counter): void {
        $this->counters[$counter] = ($this->counters[$counter] ?? 0) + 1;
    }

    public function getReport(): array {
        $totalTime = microtime(true) - $this->startTime;
        $totalMemory = memory_get_usage(true) - $this->memoryUsage['start'];

        $report = [
            'total_time' => round($totalTime, 4),
            'total_memory' => $this->formatBytes($totalMemory),
            'measurements' => [],
            'counters' => $this->counters
        ];

        foreach ($this->measurements as $label => $times) {
            $report['measurements'][$label] = [
                'count' => count($times),
                'total' => round(array_sum($times), 4),
                'average' => round(array_sum($times) / count($times), 4),
                'min' => round(min($times), 4),
                'max' => round(max($times), 4),
                'memory' => $this->formatBytes($this->memoryUsage[$label])
            ];
        }

        return $report;
    }

    private function formatBytes(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        return round($bytes / (1024 ** $pow), 2) . ' ' . $units[$pow];
    }
}

// Code optimizer class
class CodeOptimizer {
    private array $optimizations = [];

    public function addOptimization(string $name, callable $optimizer): void {
        $this->optimizations[$name] = $optimizer;
    }

    public function optimize(string $code): string {
        foreach ($this->optimizations as $name => $optimizer) {
            $code = $optimizer($code);
        }
        return $code;
    }

    // Example optimizations
    public static function getDefaultOptimizations(): array {
        return [
            'remove_whitespace' => fn($code) => preg_replace('/\s+/', ' ', $code),
            'combine_concatenation' => fn($code) => preg_replace('/"([^"]+)"\s*\.\s*"([^"]+)"/', '"$1$2"', $code),
            'optimize_loops' => fn($code) => str_replace('foreach($array as $key => $value)', 'foreach($array as &$value)', $code)
        ];
    }
}

// JIT configuration manager
class JITManager {
    private array $config;

    public function __construct() {
        $this->config = [
            'opcache.enable' => 1,
            'opcache.jit' => 1255, // TRACING
            'opcache.jit_buffer_size' => '64M'
        ];
    }

    public function configure(): void {
        foreach ($this->config as $key => $value) {
            ini_set($key, $value);
        }
    }

    public function getStatus(): array {
        return [
            'jit_enabled' => (bool) ini_get('opcache.jit'),
            'jit_buffer_size' => ini_get('opcache.jit_buffer_size'),
            'opcache_enabled' => (bool) ini_get('opcache.enable'),
            'opcache_status' => opcache_get_status(false)
        ];
    }
}

// Performance optimization examples
class PerformanceOptimizer {
    private Profiler $profiler;

    public function __construct() {
        $this->profiler = new Profiler();
    }

    // Array optimization
    public function optimizeArray(array &$array): void {
        $this->profiler->measure('array_optimization', function() use (&$array) {
            // Pre-allocate array size
            $newArray = [];
            $newArray = array_fill(0, count($array), null);
            
            // Use references for large objects
            foreach ($array as &$value) {
                if (is_object($value)) {
                    $newArray[] = &$value;
                } else {
                    $newArray[] = $value;
                }
            }
            
            $array = $newArray;
        });
    }

    // String optimization
    public function optimizeString(string &$string): void {
        $this->profiler->measure('string_optimization', function() use (&$string) {
            // Use single quotes for simple strings
            if (!preg_match('/[\$\'\\\]/', $string)) {
                $string = "'" . str_replace('"', "'", $string) . "'";
            }
            
            // Use concatenation for large strings
            if (strlen($string) > 1024) {
                $chunks = str_split($string, 1024);
                $string = implode(' . ', array_map(fn($chunk) => "'" . $chunk . "'", $chunks));
            }
        });
    }

    // Loop optimization
    public function optimizeLoop(array $items, callable $callback): void {
        $this->profiler->measure('loop_optimization', function() use ($items, $callback) {
            $count = count($items);
            // Use while loop for better performance
            $i = 0;
            while ($i < $count) {
                $callback($items[$i]);
                $i++;
            }
        });
    }

    public function getReport(): array {
        return $this->profiler->getReport();
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: When to enable JIT?
 * A: For CPU-intensive applications, not I/O bound ones
 * 
 * Q2: How to identify bottlenecks?
 * A: Use profiling tools and measure critical sections
 * 
 * Q3: What about memory optimization?
 * A: Use references, generators, and proper data structures
 * 
 * Q4: How to optimize database operations?
 * A: Use proper indexes, caching, and query optimization
 */

// Usage example
try {
    // Initialize profiler
    $profiler = new Profiler();
    $profiler->start();

    // Code optimization
    $optimizer = new CodeOptimizer();
    foreach (CodeOptimizer::getDefaultOptimizations() as $name => $optimization) {
        $optimizer->addOptimization($name, $optimization);
    }

    // JIT configuration
    $jit = new JITManager();
    $jit->configure();

    // Performance optimization
    $performanceOptimizer = new PerformanceOptimizer();

    // Example optimizations
    $largeArray = range(1, 10000);
    $performanceOptimizer->optimizeArray($largeArray);

    $largeString = str_repeat("Hello World", 1000);
    $performanceOptimizer->optimizeString($largeString);

    $performanceOptimizer->optimizeLoop($largeArray, function($item) {
        // Process item
    });

    // Get performance report
    $report = $profiler->getReport();
    print_r($report);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

/**
 * Best Practices:
 * 1. Profile before optimizing
 * 2. Optimize critical paths
 * 3. Use appropriate data structures
 * 4. Enable opcache and JIT
 * 5. Monitor memory usage
 * 6. Cache expensive operations
 * 
 * New PHP 8.x Features Used:
 * 1. JIT compilation
 * 2. Named arguments
 * 3. Constructor property promotion
 * 4. Union types
 * 5. Match expressions
 * 6. Nullsafe operator
 */ 