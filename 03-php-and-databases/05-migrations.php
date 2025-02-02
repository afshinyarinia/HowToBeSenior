<?php
/**
 * PHP Database Migrations and Schema Management (PHP 8.x)
 * -----------------------------------------------
 * This lesson covers:
 * 1. Database migrations
 * 2. Schema versioning
 * 3. Migration tracking
 * 4. Rollback handling
 * 5. Schema validation
 * 6. Safe schema changes
 */

// Base migration class
abstract class Migration {
    protected PDO $db;
    protected string $version;

    public function __construct(PDO $db, string $version) {
        $this->db = $db;
        $this->version = $version;
    }

    abstract public function up(): void;
    abstract public function down(): void;

    public function getVersion(): string {
        return $this->version;
    }
}

// Migration manager class
class MigrationManager {
    private const MIGRATIONS_TABLE = 'migrations';

    public function __construct(
        private readonly PDO $db,
        private readonly string $migrationsPath,
        private readonly Logger $logger
    ) {
        $this->ensureMigrationsTable();
    }

    // Initialize migrations table
    private function ensureMigrationsTable(): void {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS " . self::MIGRATIONS_TABLE . " (
                version VARCHAR(255) PRIMARY KEY,
                applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    // Get applied migrations
    public function getAppliedMigrations(): array {
        $stmt = $this->db->query("SELECT version FROM " . self::MIGRATIONS_TABLE . " ORDER BY version");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // Get available migrations
    public function getAvailableMigrations(): array {
        $migrations = [];
        foreach (new DirectoryIterator($this->migrationsPath) as $file) {
            if ($file->isDot() || $file->getExtension() !== 'php') continue;
            
            require_once $file->getPathname();
            $className = pathinfo($file->getFilename(), PATHINFO_FILENAME);
            if (class_exists($className) && is_subclass_of($className, Migration::class)) {
                $migration = new $className($this->db, $className);
                $migrations[$migration->getVersion()] = $migration;
            }
        }
        ksort($migrations);
        return $migrations;
    }

    // Run pending migrations
    public function migrate(): void {
        $applied = $this->getAppliedMigrations();
        $available = $this->getAvailableMigrations();

        foreach ($available as $version => $migration) {
            if (!in_array($version, $applied)) {
                $this->db->beginTransaction();
                try {
                    $this->logger->info("Applying migration: $version");
                    $migration->up();
                    
                    $stmt = $this->db->prepare(
                        "INSERT INTO " . self::MIGRATIONS_TABLE . " (version) VALUES (?)"
                    );
                    $stmt->execute([$version]);
                    
                    $this->db->commit();
                    $this->logger->info("Migration applied successfully: $version");
                } catch (Throwable $e) {
                    $this->db->rollBack();
                    $this->logger->error("Migration failed: $version", ['error' => $e->getMessage()]);
                    throw $e;
                }
            }
        }
    }

    // Rollback last migration
    public function rollback(): void {
        $applied = $this->getAppliedMigrations();
        if (empty($applied)) {
            return;
        }

        $lastVersion = end($applied);
        $available = $this->getAvailableMigrations();

        if (isset($available[$lastVersion])) {
            $this->db->beginTransaction();
            try {
                $this->logger->info("Rolling back migration: $lastVersion");
                $available[$lastVersion]->down();
                
                $stmt = $this->db->prepare(
                    "DELETE FROM " . self::MIGRATIONS_TABLE . " WHERE version = ?"
                );
                $stmt->execute([$lastVersion]);
                
                $this->db->commit();
                $this->logger->info("Rollback successful: $lastVersion");
            } catch (Throwable $e) {
                $this->db->rollBack();
                $this->logger->error("Rollback failed: $lastVersion", ['error' => $e->getMessage()]);
                throw $e;
            }
        }
    }
}

// Example migration
class CreateUsersTable extends Migration {
    public function up(): void {
        $this->db->exec("
            CREATE TABLE users (
                id INT AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_email (email)
            )
        ");
    }

    public function down(): void {
        $this->db->exec("DROP TABLE IF EXISTS users");
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: How to handle failed migrations?
 * A: Use transactions and proper error handling with rollback
 * 
 * Q2: When to use timestamps vs sequential numbers for versions?
 * A: Timestamps prevent conflicts in team environments
 * 
 * Q3: How to handle data migrations?
 * A: Create separate migrations for schema and data changes
 * 
 * Q4: How to manage dependencies between migrations?
 * A: Use sequential versioning and document dependencies
 */

// Usage example
try {
    $db = new PDO(
        "mysql:host=localhost;dbname=test;charset=utf8mb4",
        "root",
        "secret",
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    $logger = new class implements Logger {
        public function info(string $message, array $context = []): void {
            echo "[INFO] $message\n";
        }
        public function error(string $message, array $context = []): void {
            echo "[ERROR] $message\n";
            if (!empty($context)) {
                print_r($context);
            }
        }
    };

    $manager = new MigrationManager($db, __DIR__ . '/migrations', $logger);

    // Run migrations
    $manager->migrate();

    // Rollback last migration
    // $manager->rollback();

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

/**
 * Best Practices:
 * 1. Use transactions for migrations
 * 2. Keep migrations atomic
 * 3. Make migrations reversible
 * 4. Version control migrations
 * 5. Test migrations thoroughly
 * 6. Document breaking changes
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly properties
 * 3. Named arguments
 * 4. Union types
 * 5. Match expressions
 * 6. Nullsafe operator
 */ 