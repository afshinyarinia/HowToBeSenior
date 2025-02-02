<?php
/**
 * PHP Caching and Performance (PHP 8.x)
 * ------------------------------
 * This lesson covers:
 * 1. Cache implementation
 * 2. Cache strategies
 * 3. Cache invalidation
 * 4. Performance optimization
 * 5. Cache drivers
 * 6. Cache middleware
 */

// Cache interface
interface CacheInterface {
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value, ?int $ttl = null): bool;
    public function delete(string $key): bool;
    public function clear(): bool;
    public function has(string $key): bool;
    public function remember(string $key, callable $callback, ?int $ttl = null): mixed;
}

// Cache configuration
readonly class CacheConfig {
    public function __construct(
        public string $driver = 'file',
        public string $path = '',
        public string $prefix = 'cache_',
        public int $defaultTtl = 3600,
        public array $servers = []
    ) {}
}

// File cache implementation
class FileCache implements CacheInterface {
    private string $path;

    public function __construct(
        private readonly CacheConfig $config,
        private readonly Logger $logger
    ) {
        $this->path = $config->path ?: sys_get_temp_dir() . '/cache';
        if (!is_dir($this->path)) {
            mkdir($this->path, 0777, true);
        }
    }

    public function get(string $key, mixed $default = null): mixed {
        $filename = $this->getFilename($key);
        
        if (!file_exists($filename)) {
            return $default;
        }

        try {
            $data = unserialize(file_get_contents($filename));
            
            if ($data['expires'] > 0 && $data['expires'] < time()) {
                $this->delete($key);
                return $default;
            }

            return $data['value'];
        } catch (Exception $e) {
            $this->logger->error('Cache read failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return $default;
        }
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool {
        $filename = $this->getFilename($key);
        $ttl = $ttl ?? $this->config->defaultTtl;

        try {
            $data = serialize([
                'value' => $value,
                'expires' => $ttl > 0 ? time() + $ttl : 0
            ]);

            return file_put_contents($filename, $data, LOCK_EX) !== false;
        } catch (Exception $e) {
            $this->logger->error('Cache write failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function delete(string $key): bool {
        $filename = $this->getFilename($key);
        return !file_exists($filename) || unlink($filename);
    }

    public function clear(): bool {
        $files = glob($this->path . '/' . $this->config->prefix . '*');
        foreach ($files as $file) {
            if (!unlink($file)) {
                return false;
            }
        }
        return true;
    }

    public function has(string $key): bool {
        return $this->get($key, null) !== null;
    }

    public function remember(string $key, callable $callback, ?int $ttl = null): mixed {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);
        
        return $value;
    }

    private function getFilename(string $key): string {
        return $this->path . '/' . $this->config->prefix . md5($key);
    }
}

// Redis cache implementation
class RedisCache implements CacheInterface {
    private Redis $redis;

    public function __construct(
        private readonly CacheConfig $config,
        private readonly Logger $logger
    ) {
        $this->redis = new Redis();
        $this->connect();
    }

    public function get(string $key, mixed $default = null): mixed {
        try {
            $value = $this->redis->get($this->config->prefix . $key);
            return $value !== false ? unserialize($value) : $default;
        } catch (Exception $e) {
            $this->logger->error('Redis read failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return $default;
        }
    }

    public function set(string $key, mixed $value, ?int $ttl = null): bool {
        try {
            $ttl = $ttl ?? $this->config->defaultTtl;
            $key = $this->config->prefix . $key;
            
            if ($ttl > 0) {
                return $this->redis->setex($key, $ttl, serialize($value));
            }
            
            return $this->redis->set($key, serialize($value));
        } catch (Exception $e) {
            $this->logger->error('Redis write failed', [
                'key' => $key,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function delete(string $key): bool {
        return (bool) $this->redis->del($this->config->prefix . $key);
    }

    public function clear(): bool {
        return $this->redis->flushDB();
    }

    public function has(string $key): bool {
        return $this->redis->exists($this->config->prefix . $key);
    }

    public function remember(string $key, callable $callback, ?int $ttl = null): mixed {
        $value = $this->get($key);
        
        if ($value !== null) {
            return $value;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);
        
        return $value;
    }

    private function connect(): void {
        $server = $this->config->servers[0] ?? ['host' => 'localhost', 'port' => 6379];
        
        if (!$this->redis->connect($server['host'], $server['port'])) {
            throw new RuntimeException('Redis connection failed');
        }
    }
}

// Cache middleware
class CacheMiddleware implements MiddlewareInterface {
    public function __construct(
        private readonly CacheInterface $cache,
        private readonly array $rules = []
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $cacheKey = $this->getCacheKey($request);
        
        // Check if route is cacheable
        if (!$this->isCacheable($request)) {
            return $handler->handle($request);
        }

        // Try to get from cache
        $cached = $this->cache->get($cacheKey);
        if ($cached !== null) {
            return new Response(
                200,
                ['Content-Type' => 'application/json'],
                $cached
            );
        }

        // Handle request and cache response
        $response = $handler->handle($request);
        $this->cache->set($cacheKey, (string) $response->getBody());
        
        return $response;
    }

    private function getCacheKey(ServerRequestInterface $request): string {
        return md5($request->getMethod() . $request->getUri());
    }

    private function isCacheable(ServerRequestInterface $request): bool {
        $path = $request->getUri()->getPath();
        return isset($this->rules[$path]) && $request->getMethod() === 'GET';
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: When to use which cache driver?
 * A: File for simple, Redis for distributed/high-performance
 * 
 * Q2: How to handle cache invalidation?
 * A: Use tags, versioning, or explicit invalidation
 * 
 * Q3: What to cache?
 * A: Expensive computations, frequent queries, static content
 * 
 * Q4: How to handle cache stampede?
 * A: Use lock mechanisms or probabilistic early expiration
 */

// Usage example
try {
    // Configure cache
    $config = new CacheConfig(
        driver: 'redis',
        prefix: 'app_',
        defaultTtl: 3600,
        servers: [
            ['host' => 'localhost', 'port' => 6379]
        ]
    );

    // Create cache instance
    $cache = match($config->driver) {
        'redis' => new RedisCache($config, new FileLogger(__DIR__ . '/cache.log')),
        'file' => new FileCache($config, new FileLogger(__DIR__ . '/cache.log')),
        default => throw new RuntimeException('Invalid cache driver')
    };

    // Example usage
    $cache->set('user_count', 100, 300);
    
    // Using remember pattern
    $value = $cache->remember('expensive_computation', function() {
        // Simulate expensive operation
        sleep(1);
        return ['result' => 42];
    }, 3600);

    echo "Cached value: " . json_encode($value) . "\n";

} catch (Exception $e) {
    error_log($e->getMessage());
    echo "Cache operation failed";
}

/**
 * Best Practices:
 * 1. Use appropriate cache driver
 * 2. Set reasonable TTLs
 * 3. Handle cache failures gracefully
 * 4. Implement cache warming
 * 5. Monitor cache hit rates
 * 6. Use cache tags when available
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly properties
 * 3. Named arguments
 * 4. Union types
 * 5. Match expressions
 * 6. Nullsafe operator
 */ 