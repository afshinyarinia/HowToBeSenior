<?php
/**
 * PHP Database Security (PHP 8.x)
 * ------------------------
 * This lesson covers:
 * 1. SQL injection prevention
 * 2. Password hashing
 * 3. Data encryption
 * 4. Access control
 * 5. Secure configuration
 * 6. Audit logging
 */

// Secure database configuration
readonly class SecureDbConfig {
    public function __construct(
        public string $host,
        public string $database,
        public string $username,
        public string $password,
        public array $options = [],
        public string $encryptionKey = '',
        public string $cipher = 'aes-256-gcm'
    ) {}

    public function getDsn(): string {
        return "mysql:host={$this->host};dbname={$this->database};charset=utf8mb4";
    }
}

// Security service for encryption/decryption
class SecurityService {
    private string $key;
    private string $cipher;

    public function __construct(SecureDbConfig $config) {
        $this->key = base64_decode($config->encryptionKey);
        $this->cipher = $config->cipher;
    }

    public function encrypt(string $data): array {
        $iv = random_bytes(openssl_cipher_iv_length($this->cipher));
        $tag = '';
        
        $encrypted = openssl_encrypt(
            $data,
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        return [
            'data' => base64_encode($encrypted),
            'iv' => base64_encode($iv),
            'tag' => base64_encode($tag)
        ];
    }

    public function decrypt(array $encryptedData): string {
        $decrypted = openssl_decrypt(
            base64_decode($encryptedData['data']),
            $this->cipher,
            $this->key,
            OPENSSL_RAW_DATA,
            base64_decode($encryptedData['iv']),
            base64_decode($encryptedData['tag'])
        );

        if ($decrypted === false) {
            throw new RuntimeException('Decryption failed');
        }

        return $decrypted;
    }

    public function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_ARGON2ID);
    }

    public function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }
}

// Secure user repository
class SecureUserRepository {
    private PDO $db;
    private SecurityService $security;

    public function __construct(
        SecureDbConfig $config,
        private readonly Logger $logger
    ) {
        $this->db = new PDO(
            $config->getDsn(),
            $config->username,
            $config->password,
            $config->options + [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        $this->security = new SecurityService($config);
    }

    // Secure user creation with encrypted sensitive data
    public function createUser(array $userData): int {
        try {
            // Hash password
            $passwordHash = $this->security->hashPassword($userData['password']);
            
            // Encrypt sensitive data
            $encryptedSsn = $this->security->encrypt($userData['ssn']);
            
            $stmt = $this->db->prepare("
                INSERT INTO users (
                    username, password_hash, email,
                    ssn_encrypted, ssn_iv, ssn_tag,
                    created_at
                ) VALUES (
                    :username, :password_hash, :email,
                    :ssn_encrypted, :ssn_iv, :ssn_tag,
                    NOW()
                )
            ");

            $stmt->execute([
                'username' => $userData['username'],
                'password_hash' => $passwordHash,
                'email' => $userData['email'],
                'ssn_encrypted' => $encryptedSsn['data'],
                'ssn_iv' => $encryptedSsn['iv'],
                'ssn_tag' => $encryptedSsn['tag']
            ]);

            $userId = (int) $this->db->lastInsertId();
            
            // Audit log
            $this->logAuditEvent('user_created', $userId);
            
            return $userId;
        } catch (PDOException $e) {
            $this->logger->error('User creation failed', [
                'error' => $e->getMessage(),
                'username' => $userData['username']
            ]);
            throw $e;
        }
    }

    // Secure authentication
    public function authenticateUser(string $username, string $password): ?array {
        try {
            $stmt = $this->db->prepare("
                SELECT id, username, password_hash, last_login
                FROM users
                WHERE username = :username
                AND active = 1
                LIMIT 1
            ");

            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && $this->security->verifyPassword($password, $user['password_hash'])) {
                // Update last login
                $this->updateLastLogin($user['id']);
                
                // Audit log
                $this->logAuditEvent('user_login', $user['id']);
                
                unset($user['password_hash']);
                return $user;
            }

            return null;
        } catch (PDOException $e) {
            $this->logger->error('Authentication failed', [
                'error' => $e->getMessage(),
                'username' => $username
            ]);
            throw $e;
        }
    }

    // Secure data retrieval with decryption
    public function getUserSensitiveData(int $userId): ?array {
        try {
            $stmt = $this->db->prepare("
                SELECT ssn_encrypted, ssn_iv, ssn_tag
                FROM users
                WHERE id = :id
                AND active = 1
                LIMIT 1
            ");

            $stmt->execute(['id' => $userId]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data) {
                // Decrypt sensitive data
                $ssn = $this->security->decrypt([
                    'data' => $data['ssn_encrypted'],
                    'iv' => $data['ssn_iv'],
                    'tag' => $data['ssn_tag']
                ]);

                // Audit log
                $this->logAuditEvent('sensitive_data_accessed', $userId);

                return ['ssn' => $ssn];
            }

            return null;
        } catch (PDOException $e) {
            $this->logger->error('Data retrieval failed', [
                'error' => $e->getMessage(),
                'user_id' => $userId
            ]);
            throw $e;
        }
    }

    private function updateLastLogin(int $userId): void {
        $stmt = $this->db->prepare("
            UPDATE users 
            SET last_login = NOW()
            WHERE id = :id
        ");
        $stmt->execute(['id' => $userId]);
    }

    private function logAuditEvent(string $event, int $userId): void {
        $stmt = $this->db->prepare("
            INSERT INTO audit_log (
                event_type, user_id, ip_address, created_at
            ) VALUES (
                :event, :user_id, :ip, NOW()
            )
        ");

        $stmt->execute([
            'event' => $event,
            'user_id' => $userId,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
        ]);
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: How to handle key rotation?
 * A: Implement versioned keys and re-encrypt data gradually
 * 
 * Q2: What data should be encrypted?
 * A: PII (Personal Identifiable Information) and sensitive data
 * 
 * Q3: How to secure database credentials?
 * A: Use environment variables and secure vaults
 * 
 * Q4: How to prevent SQL injection?
 * A: Always use prepared statements and proper escaping
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

    $config = new SecureDbConfig(
        host: 'localhost',
        database: 'secure_db',
        username: 'secure_user',
        password: 'secure_pass',
        encryptionKey: base64_encode(random_bytes(32))
    );

    $repository = new SecureUserRepository($config, $logger);

    // Create user with encrypted data
    $userId = $repository->createUser([
        'username' => 'john_doe',
        'password' => 'secure_password123',
        'email' => 'john@example.com',
        'ssn' => '123-45-6789'
    ]);

    // Authenticate user
    $user = $repository->authenticateUser('john_doe', 'secure_password123');
    if ($user) {
        echo "User authenticated successfully\n";
        
        // Access sensitive data
        $sensitiveData = $repository->getUserSensitiveData($user['id']);
        echo "Decrypted SSN: {$sensitiveData['ssn']}\n";
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

/**
 * Best Practices:
 * 1. Use prepared statements
 * 2. Encrypt sensitive data
 * 3. Implement proper access control
 * 4. Use strong password hashing
 * 5. Maintain audit logs
 * 6. Regular security updates
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly properties and classes
 * 3. Named arguments
 * 4. Union types
 * 5. Nullsafe operator
 * 6. Match expressions
 */ 