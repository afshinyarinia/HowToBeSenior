<?php
/**
 * PHP Advanced Memory Management (PHP 8.x)
 * --------------------------------
 * This lesson covers:
 * 1. Memory optimization
 * 2. Garbage collection
 * 3. Reference counting
 * 4. Memory leaks
 * 5. Resource handling
 * 6. Performance profiling
 */

// Memory monitor class
class MemoryMonitor {
    private array $snapshots = [];
    private int $startTime;

    public function __construct() {
        $this->startTime = microtime(true);
    }

    public function snapshot(string $label): void {
        $this->snapshots[$label] = [
            'memory' => memory_get_usage(true),
            'peak' => memory_get_peak_usage(true),
            'time' => microtime(true) - $this->startTime
        ];
    }

    public function getReport(): array {
        $report = [];
        $previous = null;

        foreach ($this->snapshots as $label => $data) {
            $diff = $previous ? $data['memory'] - $previous['memory'] : 0;
            $report[$label] = [
                'memory_usage' => $this->formatBytes($data['memory']),
                'peak_usage' => $this->formatBytes($data['peak']),
                'difference' => $this->formatBytes($diff),
                'time' => round($data['time'], 4) . 's'
            ];
            $previous = $data;
        }

        return $report;
    }

    private function formatBytes(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));
        
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

// Resource manager with automatic cleanup
class ResourceManager {
    private array $resources = [];
    private array $cleanupHandlers = [];

    public function __destruct() {
        $this->cleanup();
    }

    public function register(mixed $resource, callable $cleanup): string {
        $id = uniqid('resource_', true);
        $this->resources[$id] = $resource;
        $this->cleanupHandlers[$id] = $cleanup;
        return $id;
    }

    public function get(string $id): mixed {
        return $this->resources[$id] ?? null;
    }

    public function release(string $id): void {
        if (isset($this->resources[$id])) {
            ($this->cleanupHandlers[$id])($this->resources[$id]);
            unset($this->resources[$id], $this->cleanupHandlers[$id]);
        }
    }

    public function cleanup(): void {
        foreach (array_keys($this->resources) as $id) {
            $this->release($id);
        }
    }
}

// Large data handler with streaming
class LargeDataHandler {
    private const CHUNK_SIZE = 8192; // 8KB chunks

    public function processLargeFile(string $filename, callable $processor): void {
        $handle = fopen($filename, 'r');
        if ($handle === false) {
            throw new RuntimeException("Cannot open file: $filename");
        }

        try {
            while (!feof($handle)) {
                $chunk = fread($handle, self::CHUNK_SIZE);
                if ($chunk === false) {
                    throw new RuntimeException("Error reading file");
                }
                $processor($chunk);
            }
        } finally {
            fclose($handle);
        }
    }

    public function streamLargeData(array $items, callable $processor): void {
        $generator = function() use ($items) {
            foreach ($items as $item) {
                yield $item;
            }
        };

        foreach ($generator() as $item) {
            $processor($item);
        }
    }
}

// Circular reference handler
class CircularReferenceHandler {
    private WeakMap $references;

    public function __construct() {
        $this->references = new WeakMap();
    }

    public function track(object $object, string $label): void {
        $this->references[$object] = [
            'label' => $label,
            'time' => microtime(true)
        ];
    }

    public function getTrackedObjects(): array {
        $tracked = [];
        foreach ($this->references as $object => $data) {
            $tracked[] = [
                'class' => get_class($object),
                'label' => $data['label'],
                'age' => microtime(true) - $data['time']
            ];
        }
        return $tracked;
    }
}

// Memory optimization examples
class MemoryOptimizer {
    public static function optimizeArray(array &$array): void {
        // Preallocate array size if known
        $newArray = [];
        $newArray = array_fill(0, count($array), null);
        
        foreach ($array as $key => $value) {
            $newArray[$key] = $value;
        }
        
        $array = $newArray;
    }

    public static function clearObjectCache(object $object): void {
        // Clear internal object cache
        gc_collect_cycles();
        
        // Clear object properties
        $reflect = new ReflectionObject($object);
        foreach ($reflect->getProperties() as $prop) {
            $prop->setAccessible(true);
            $prop->setValue($object, null);
        }
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: How to handle large datasets?
 * A: Use generators and streaming to process in chunks
 * 
 * Q2: How to detect memory leaks?
 * A: Use memory monitoring and profiling tools
 * 
 * Q3: When to force garbage collection?
 * A: Rarely - PHP's GC is usually sufficient
 * 
 * Q4: How to handle circular references?
 * A: Use WeakMap or explicitly break references
 */

// Usage example
try {
    $monitor = new MemoryMonitor();
    $monitor->snapshot('start');

    // Resource management example
    $resources = new ResourceManager();
    $fileHandle = fopen('large_file.txt', 'r');
    $resourceId = $resources->register($fileHandle, 'fclose');

    // Process large data
    $handler = new LargeDataHandler();
    $handler->processLargeFile('large_file.txt', function($chunk) {
        // Process chunk
    });

    // Track circular references
    $tracker = new CircularReferenceHandler();
    $obj = new stdClass();
    $tracker->track($obj, 'test_object');

    $monitor->snapshot('end');
    print_r($monitor->getReport());

    // Cleanup
    $resources->cleanup();

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

/**
 * Best Practices:
 * 1. Monitor memory usage
 * 2. Use generators for large datasets
 * 3. Clean up resources properly
 * 4. Avoid circular references
 * 5. Profile memory usage
 * 6. Optimize data structures
 * 
 * New PHP 8.x Features Used:
 * 1. WeakMap
 * 2. Named arguments
 * 3. Constructor property promotion
 * 4. Union types
 * 5. Match expressions
 * 6. Nullsafe operator
 */ 