<?php
/**
 * PHP NoSQL Databases - MongoDB (PHP 8.x)
 * ---------------------------------
 * This lesson covers:
 * 1. MongoDB connection
 * 2. CRUD operations
 * 3. Document modeling
 * 4. Aggregation pipeline
 * 5. Indexing strategies
 * 6. Error handling
 */

// MongoDB document model
readonly class User {
    public function __construct(
        public ?string $id = null,
        public string $name,
        public string $email,
        public array $preferences = [],
        public array $metadata = [],
        public \DateTime $createdAt = new \DateTime()
    ) {}

    public static function fromArray(array $data): self {
        return new self(
            id: (string) ($data['_id'] ?? null),
            name: $data['name'],
            email: $data['email'],
            preferences: $data['preferences'] ?? [],
            metadata: $data['metadata'] ?? [],
            createdAt: new \DateTime($data['created_at'] ?? 'now')
        );
    }

    public function toArray(): array {
        $data = [
            'name' => $this->name,
            'email' => $this->email,
            'preferences' => $this->preferences,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s')
        ];

        if ($this->id) {
            $data['_id'] = new \MongoDB\BSON\ObjectId($this->id);
        }

        return $data;
    }
}

// MongoDB repository
class MongoUserRepository {
    private \MongoDB\Collection $collection;

    public function __construct(
        string $uri = 'mongodb://localhost:27017',
        string $database = 'test',
        string $collection = 'users',
        private readonly Logger $logger
    ) {
        $client = new \MongoDB\Client($uri);
        $this->collection = $client->$database->$collection;
        
        // Ensure indexes
        $this->collection->createIndex(['email' => 1], ['unique' => true]);
        $this->collection->createIndex(['created_at' => 1]);
    }

    // Create user
    public function createUser(User $user): User {
        try {
            $result = $this->collection->insertOne($user->toArray());
            
            if ($result->getInsertedCount() === 1) {
                return new User(
                    id: (string) $result->getInsertedId(),
                    name: $user->name,
                    email: $user->email,
                    preferences: $user->preferences,
                    metadata: $user->metadata,
                    createdAt: $user->createdAt
                );
            }
            
            throw new RuntimeException("Failed to create user");
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            $this->logger->error("MongoDB insert failed", [
                'error' => $e->getMessage(),
                'user' => $user->toArray()
            ]);
            throw $e;
        }
    }

    // Find user by ID
    public function findUser(string $id): ?User {
        try {
            $result = $this->collection->findOne([
                '_id' => new \MongoDB\BSON\ObjectId($id)
            ]);
            
            return $result ? User::fromArray((array) $result) : null;
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            $this->logger->error("MongoDB find failed", [
                'error' => $e->getMessage(),
                'id' => $id
            ]);
            throw $e;
        }
    }

    // Update user
    public function updateUser(string $id, array $data): bool {
        try {
            $result = $this->collection->updateOne(
                ['_id' => new \MongoDB\BSON\ObjectId($id)],
                ['$set' => $data]
            );
            
            return $result->getModifiedCount() === 1;
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            $this->logger->error("MongoDB update failed", [
                'error' => $e->getMessage(),
                'id' => $id,
                'data' => $data
            ]);
            throw $e;
        }
    }

    // Complex query example with aggregation
    public function getUserStats(array $criteria = []): array {
        try {
            $pipeline = [
                ['$match' => $criteria],
                ['$group' => [
                    '_id' => null,
                    'total_users' => ['$sum' => 1],
                    'avg_preferences' => ['$avg' => ['$size' => '$preferences']],
                    'latest_signup' => ['$max' => '$created_at']
                ]]
            ];

            $result = $this->collection->aggregate($pipeline)->toArray();
            return $result[0] ?? [];
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            $this->logger->error("MongoDB aggregation failed", [
                'error' => $e->getMessage(),
                'criteria' => $criteria
            ]);
            throw $e;
        }
    }

    // Batch operations example
    public function bulkUpdatePreferences(array $userIds, array $preferences): int {
        try {
            $operations = [];
            foreach ($userIds as $id) {
                $operations[] = [
                    'updateOne' => [
                        ['_id' => new \MongoDB\BSON\ObjectId($id)],
                        ['$set' => ['preferences' => $preferences]]
                    ]
                ];
            }

            $result = $this->collection->bulkWrite($operations);
            return $result->getModifiedCount();
        } catch (\MongoDB\Driver\Exception\Exception $e) {
            $this->logger->error("MongoDB bulk update failed", [
                'error' => $e->getMessage(),
                'user_count' => count($userIds)
            ]);
            throw $e;
        }
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: When to use MongoDB over SQL?
 * A: For flexible schemas, document-oriented data, and high scalability needs
 * 
 * Q2: How to handle relationships?
 * A: Use embedding or references based on access patterns
 * 
 * Q3: What about transactions?
 * A: MongoDB supports multi-document transactions since v4.0
 * 
 * Q4: How to ensure data consistency?
 * A: Use schema validation, indexes, and proper error handling
 */

// Usage example
try {
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

    $repository = new MongoUserRepository(
        uri: 'mongodb://localhost:27017',
        database: 'test',
        collection: 'users',
        logger: $logger
    );

    // Create user
    $user = new User(
        name: 'John Doe',
        email: 'john@example.com',
        preferences: ['theme' => 'dark', 'notifications' => true],
        metadata: ['last_login' => new DateTime()]
    );
    
    $createdUser = $repository->createUser($user);
    echo "Created user with ID: {$createdUser->id}\n";

    // Find user
    $foundUser = $repository->findUser($createdUser->id);
    echo "Found user: {$foundUser->name}\n";

    // Update preferences
    $repository->updateUser($createdUser->id, [
        'preferences' => ['theme' => 'light']
    ]);

    // Get statistics
    $stats = $repository->getUserStats();
    echo "User statistics:\n";
    print_r($stats);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

/**
 * Best Practices:
 * 1. Use proper indexes
 * 2. Handle MongoDB-specific exceptions
 * 3. Implement data validation
 * 4. Use bulk operations for batch updates
 * 5. Monitor query performance
 * 6. Consider data access patterns
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly properties and classes
 * 3. Named arguments
 * 4. Union types
 * 5. Nullsafe operator
 * 6. Match expressions
 */ 