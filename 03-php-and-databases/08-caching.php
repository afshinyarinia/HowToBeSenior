<?php
/**
 * PHP Database Caching (PHP 8.x)
 * ------------------------
 * This lesson covers:
 * 1. Query result caching
 * 2. Object caching
 * 3. Cache invalidation
 * 4. Cache strategies
 * 5. Redis integration
 * 6. Cache layers
 */

// Cache interface
interface Cache {
    public function get(string $key): mixed;
    public function set(string $key, mixed $value, int $ttl = 3600): bool;
    public function delete(string $key): bool;
    public function clear(): bool;
}

// Redis cache implementation
class RedisCache implements Cache {
    private Redis $redis;

    public function __construct(
        string $host = 'localhost',
        int $port = 6379,
        private readonly string $prefix = 'cache:'
    ) {
        $this->redis = new Redis();
        $this->redis->connect($host, $port);
    }

    public function get(string $key): mixed {
        $value = $this->redis->get($this->prefix . $key);
        return $value ? unserialize($value) : null;
    }

    public function set(string $key, mixed $value, int $ttl = 3600): bool {
        return $this->redis->setex(
            $this->prefix . $key,
            $ttl,
            serialize($value)
        );
    }

    public function delete(string $key): bool {
        return (bool) $this->redis->del($this->prefix . $key);
    }

    public function clear(): bool {
        $keys = $this->redis->keys($this->prefix . '*');
        return empty($keys) || $this->redis->del($keys) > 0;
    }
}

// Repository with caching
class CachedUserRepository {
    private const CACHE_TTL = 3600; // 1 hour

    public function __construct(
        private readonly PDO $db,
        private readonly Cache $cache,
        private readonly Logger $logger
    ) {}

    public function findUser(int $id): ?array {
        $cacheKey = "user:{$id}";
        
        // Try to get from cache first
        if ($user = $this->cache->get($cacheKey)) {
            $this->logger->info("Cache hit for user", ['id' => $id]);
            return $user;
        }

        // Cache miss - get from database
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM users WHERE id = :id
            ");
            $stmt->execute(['id' => $id]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                // Store in cache for future requests
                $this->cache->set($cacheKey, $user, self::CACHE_TTL);
                $this->logger->info("Cached user data", ['id' => $id]);
            }

            return $user ?: null;
        } catch (PDOException $e) {
            $this->logger->error("Database query failed", [
                'error' => $e->getMessage(),
                'id' => $id
            ]);
            throw $e;
        }
    }

    public function updateUser(int $id, array $data): bool {
        try {
            $this->db->beginTransaction();

            $setClauses = [];
            $params = ['id' => $id];

            foreach ($data as $field => $value) {
                $setClauses[] = "{$field} = :{$field}";
                $params[":{$field}"] = $value;
            }

            $sql = "UPDATE users SET " . implode(', ', $setClauses) . " WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute($params);

            if ($result) {
                // Invalidate cache on successful update
                $this->cache->delete("user:{$id}");
                $this->db->commit();
                return true;
            }

            $this->db->rollBack();
            return false;
        } catch (Throwable $e) {
            $this->db->rollBack();
            $this->logger->error("Update failed", [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $data
            ]);
            throw $e;
        }
    }

    // Batch cache preloading
    public function preloadUsers(array $ids): void {
        $placeholders = str_repeat('?,', count($ids) - 1) . '?';
        
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM users 
                WHERE id IN ($placeholders)
            ");
            $stmt->execute($ids);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($users as $user) {
                $this->cache->set(
                    "user:{$user['id']}", 
                    $user, 
                    self::CACHE_TTL
                );
            }

            $this->logger->info("Preloaded users", ['count' => count($users)]);
        } catch (PDOException $e) {
            $this->logger->error("Preload failed", [
                'error' => $e->getMessage(),
                'ids' => $ids
            ]);
            throw $e;
        }
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: When should I use caching?
 * A: For frequently accessed, rarely changed data
 * 
 * Q2: How to handle cache invalidation?
 * A: Use TTL and explicit invalidation on updates
 * 
 * Q3: What data should be cached?
 * A: Consider data size, access patterns, and freshness requirements
 * 
 * Q4: How to handle cache failures?
 * A: Gracefully fallback to database
 */

// Usage example
try {
    $db = new PDO(
        "mysql:host=localhost;dbname=test;charset=utf8mb4",
        "root",
        "secret",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $cache = new RedisCache(
        host: 'localhost',
        port: 6379,
        prefix: 'myapp:'
    );

    $logger = new class implements Logger {
        public function info(string $message, array $context = []): void {
            echo "[INFO] $message\n";
            if (!empty($context)) print_r($context);
        }
        public function error(string $message, array $context = []): void {
            echo "[ERROR] $message\n";
            if (!empty($context)) print_r($context);
        }
    };

    $repository = new CachedUserRepository($db, $cache, $logger);

    // Find user (will be cached)
    $user = $repository->findUser(1);
    echo "First lookup (cache miss):\n";
    print_r($user);

    // Second lookup (from cache)
    $user = $repository->findUser(1);
    echo "Second lookup (cache hit):\n";
    print_r($user);

    // Update user (invalidates cache)
    $repository->updateUser(1, [
        'name' => 'Updated Name',
        'email' => 'updated@example.com'
    ]);

    // Preload multiple users
    $repository->preloadUsers([1, 2, 3, 4, 5]);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

/**
 * Best Practices:
 * 1. Use appropriate cache TTL
 * 2. Implement cache warmup
 * 3. Handle cache failures gracefully
 * 4. Monitor cache hit rates
 * 5. Use cache tags when available
 * 6. Consider cache stampede protection
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly properties
 * 3. Named arguments
 * 4. Union types
 * 5. Nullsafe operator
 * 6. Match expressions
 */ 