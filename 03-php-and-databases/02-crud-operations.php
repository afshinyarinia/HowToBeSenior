<?php
/**
 * PHP CRUD Operations with PDO (PHP 8.x)
 * ---------------------------------
 * This lesson covers:
 * 1. Prepared statements
 * 2. CRUD operations
 * 3. Transaction handling
 * 4. Result fetching
 * 5. Error handling
 * 6. Type handling
 */

// User entity class
readonly class User {
    public function __construct(
        public ?int $id,
        public string $name,
        public string $email,
        public \DateTime $createdAt
    ) {}

    public static function fromArray(array $data): self {
        return new self(
            id: $data['id'] ?? null,
            name: $data['name'],
            email: $data['email'],
            createdAt: new \DateTime($data['created_at'] ?? 'now')
        );
    }

    public function toArray(): array {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s')
        ];
    }
}

// Database operations class
class UserRepository {
    public function __construct(
        private readonly PDO $db
    ) {}

    // Create operation
    public function create(User $user): User {
        $sql = "INSERT INTO users (name, email, created_at) VALUES (:name, :email, :created_at)";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'name' => $user->name,
                'email' => $user->email,
                'created_at' => $user->createdAt->format('Y-m-d H:i:s')
            ]);
            
            return new User(
                id: (int) $this->db->lastInsertId(),
                name: $user->name,
                email: $user->email,
                createdAt: $user->createdAt
            );
        } catch (PDOException $e) {
            throw new RuntimeException("Create operation failed: " . $e->getMessage());
        }
    }

    // Read operation
    public function find(int $id): ?User {
        $sql = "SELECT * FROM users WHERE id = :id";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);
            
            $result = $stmt->fetch();
            return $result ? User::fromArray($result) : null;
        } catch (PDOException $e) {
            throw new RuntimeException("Read operation failed: " . $e->getMessage());
        }
    }

    // Update operation
    public function update(User $user): bool {
        if ($user->id === null) {
            throw new InvalidArgumentException("Cannot update user without ID");
        }

        $sql = "UPDATE users SET name = :name, email = :email WHERE id = :id";
        
        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute([
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email
            ]);
        } catch (PDOException $e) {
            throw new RuntimeException("Update operation failed: " . $e->getMessage());
        }
    }

    // Delete operation
    public function delete(int $id): bool {
        $sql = "DELETE FROM users WHERE id = :id";
        
        try {
            $stmt = $this->db->prepare($sql);
            return $stmt->execute(['id' => $id]);
        } catch (PDOException $e) {
            throw new RuntimeException("Delete operation failed: " . $e->getMessage());
        }
    }

    // List operation with pagination
    public function findAll(int $limit = 10, int $offset = 0): array {
        $sql = "SELECT * FROM users LIMIT :limit OFFSET :offset";
        
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();
            
            return array_map(
                fn(array $row) => User::fromArray($row),
                $stmt->fetchAll()
            );
        } catch (PDOException $e) {
            throw new RuntimeException("List operation failed: " . $e->getMessage());
        }
    }

    // Transaction example
    public function createMultiple(array $users): array {
        try {
            $this->db->beginTransaction();
            
            $createdUsers = array_map(
                fn(User $user) => $this->create($user),
                $users
            );
            
            $this->db->commit();
            return $createdUsers;
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw new RuntimeException("Batch creation failed: " . $e->getMessage());
        }
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: Why use prepared statements?
 * A: Prevents SQL injection and improves performance with statement reuse
 * 
 * Q2: When to use transactions?
 * A: When multiple operations need to succeed or fail together
 * 
 * Q3: How to handle large result sets?
 * A: Use pagination and iterate over results with cursors
 * 
 * Q4: What's the difference between fetch and fetchAll?
 * A: fetch returns one row, fetchAll returns all rows
 */

// Usage examples
try {
    // Setup database connection
    $db = new PDO(
        "mysql:host=localhost;dbname=test;charset=utf8mb4",
        "root",
        "secret",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    
    $repository = new UserRepository($db);

    // Create user
    $newUser = new User(
        id: null,
        name: "John Doe",
        email: "john@example.com",
        createdAt: new DateTime()
    );
    
    $createdUser = $repository->create($newUser);
    echo "Created user with ID: " . $createdUser->id . "\n";

    // Read user
    $user = $repository->find($createdUser->id);
    echo "Found user: " . $user?->name . "\n";

    // Update user
    $updatedUser = new User(
        id: $createdUser->id,
        name: "John Updated",
        email: $createdUser->email,
        createdAt: $createdUser->createdAt
    );
    
    if ($repository->update($updatedUser)) {
        echo "User updated successfully\n";
    }

    // List users
    $users = $repository->findAll(limit: 5, offset: 0);
    foreach ($users as $user) {
        echo "{$user->name} ({$user->email})\n";
    }

    // Delete user
    if ($repository->delete($createdUser->id)) {
        echo "User deleted successfully\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

/**
 * Best Practices:
 * 1. Always use prepared statements
 * 2. Handle transactions properly
 * 3. Use type declarations
 * 4. Implement proper error handling
 * 5. Use pagination for large datasets
 * 6. Validate input data
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly properties
 * 3. Named arguments
 * 4. Nullsafe operator
 * 5. Match expressions
 * 6. Array spreading
 */ 