<?php
/**
 * PHP Authentication and Authorization (PHP 8.x)
 * --------------------------------------
 * This lesson covers:
 * 1. Authentication system
 * 2. Authorization (RBAC)
 * 3. JWT tokens
 * 4. Session management
 * 5. Password hashing
 * 6. Access control
 */

// User entity
readonly class User {
    public function __construct(
        public int $id,
        public string $email,
        public array $roles = [],
        public array $permissions = []
    ) {}

    public function hasRole(string $role): bool {
        return in_array($role, $this->roles);
    }

    public function hasPermission(string $permission): bool {
        return in_array($permission, $this->permissions);
    }
}

// Authentication service
interface AuthenticationService {
    public function authenticate(string $email, string $password): ?User;
    public function logout(): void;
    public function getCurrentUser(): ?User;
}

// JWT service for token management
class JWTService {
    public function __construct(
        private readonly string $secretKey,
        private readonly int $expiryTime = 3600
    ) {}

    public function generateToken(User $user): string {
        $payload = [
            'user_id' => $user->id,
            'email' => $user->email,
            'roles' => $user->roles,
            'exp' => time() + $this->expiryTime
        ];

        return $this->encode($payload);
    }

    public function validateToken(string $token): ?array {
        try {
            $payload = $this->decode($token);
            
            if ($payload['exp'] < time()) {
                return null;
            }

            return $payload;
        } catch (Exception $e) {
            return null;
        }
    }

    private function encode(array $payload): string {
        $header = $this->base64UrlEncode(json_encode([
            'typ' => 'JWT',
            'alg' => 'HS256'
        ]));

        $payload = $this->base64UrlEncode(json_encode($payload));
        $signature = $this->generateSignature($header, $payload);

        return "$header.$payload.$signature";
    }

    private function decode(string $token): array {
        [$header, $payload, $signature] = explode('.', $token);
        
        if ($signature !== $this->generateSignature($header, $payload)) {
            throw new RuntimeException('Invalid token signature');
        }

        return json_decode($this->base64UrlDecode($payload), true);
    }

    private function generateSignature(string $header, string $payload): string {
        return $this->base64UrlEncode(
            hash_hmac('sha256', "$header.$payload", $this->secretKey, true)
        );
    }

    private function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}

// Database authentication implementation
class DatabaseAuthService implements AuthenticationService {
    private ?User $currentUser = null;

    public function __construct(
        private readonly PDO $db,
        private readonly JWTService $jwt,
        private readonly Logger $logger
    ) {}

    public function authenticate(string $email, string $password): ?User {
        try {
            $stmt = $this->db->prepare("
                SELECT id, email, password_hash, roles, permissions
                FROM users
                WHERE email = ? AND active = 1
            ");
            
            $stmt->execute([$email]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$userData || !$this->verifyPassword($password, $userData['password_hash'])) {
                return null;
            }

            $this->currentUser = new User(
                id: $userData['id'],
                email: $userData['email'],
                roles: json_decode($userData['roles'], true),
                permissions: json_decode($userData['permissions'], true)
            );

            // Generate JWT token
            $_SESSION['token'] = $this->jwt->generateToken($this->currentUser);
            
            return $this->currentUser;

        } catch (Exception $e) {
            $this->logger->error('Authentication failed', [
                'error' => $e->getMessage(),
                'email' => $email
            ]);
            return null;
        }
    }

    public function logout(): void {
        $this->currentUser = null;
        unset($_SESSION['token']);
        session_destroy();
    }

    public function getCurrentUser(): ?User {
        if ($this->currentUser) {
            return $this->currentUser;
        }

        $token = $_SESSION['token'] ?? null;
        if (!$token) {
            return null;
        }

        $payload = $this->jwt->validateToken($token);
        if (!$payload) {
            $this->logout();
            return null;
        }

        // Load user from database
        try {
            $stmt = $this->db->prepare("
                SELECT id, email, roles, permissions
                FROM users
                WHERE id = ? AND active = 1
            ");
            
            $stmt->execute([$payload['user_id']]);
            $userData = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$userData) {
                $this->logout();
                return null;
            }

            $this->currentUser = new User(
                id: $userData['id'],
                email: $userData['email'],
                roles: json_decode($userData['roles'], true),
                permissions: json_decode($userData['permissions'], true)
            );

            return $this->currentUser;

        } catch (Exception $e) {
            $this->logger->error('User loading failed', [
                'error' => $e->getMessage(),
                'user_id' => $payload['user_id']
            ]);
            return null;
        }
    }

    private function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }
}

// Authorization middleware
class AuthorizationMiddleware implements MiddlewareInterface {
    public function __construct(
        private readonly AuthenticationService $auth,
        private readonly array $rules = []
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $user = $this->auth->getCurrentUser();
        $path = $request->getUri();

        // Check if path requires authentication
        if (isset($this->rules[$path])) {
            if (!$user) {
                return new Response(401, [], 'Unauthorized');
            }

            $rule = $this->rules[$path];
            
            // Check roles
            if (isset($rule['roles'])) {
                foreach ($rule['roles'] as $role) {
                    if (!$user->hasRole($role)) {
                        return new Response(403, [], 'Forbidden');
                    }
                }
            }

            // Check permissions
            if (isset($rule['permissions'])) {
                foreach ($rule['permissions'] as $permission) {
                    if (!$user->hasPermission($permission)) {
                        return new Response(403, [], 'Forbidden');
                    }
                }
            }
        }

        return $handler->handle($request);
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: How to handle password resets securely?
 * A: Use time-limited tokens and secure communication
 * 
 * Q2: What about remember me functionality?
 * A: Use secure, signed cookies with refresh tokens
 * 
 * Q3: How to implement role inheritance?
 * A: Use role hierarchies and permission inheritance
 * 
 * Q4: How to handle session fixation?
 * A: Regenerate session ID on login/privilege changes
 */

// Usage example
try {
    session_start();

    // Initialize services
    $jwt = new JWTService(
        secretKey: getenv('JWT_SECRET'),
        expiryTime: 3600
    );

    $auth = new DatabaseAuthService(
        db: new PDO("mysql:host=localhost;dbname=test", "root", "secret"),
        jwt: $jwt,
        logger: new FileLogger(__DIR__ . '/auth.log')
    );

    // Define authorization rules
    $rules = [
        '/admin' => [
            'roles' => ['admin'],
            'permissions' => ['access_admin']
        ],
        '/api/users' => [
            'roles' => ['admin', 'manager'],
            'permissions' => ['manage_users']
        ]
    ];

    // Create middleware
    $middleware = new AuthorizationMiddleware($auth, $rules);

    // Example login
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $user = $auth->authenticate(
            $_POST['email'],
            $_POST['password']
        );

        if ($user) {
            header('Location: /dashboard');
            exit;
        }
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(500);
    echo "Authentication failed";
}

/**
 * Best Practices:
 * 1. Use secure password hashing
 * 2. Implement proper session management
 * 3. Use HTTPS for all auth requests
 * 4. Implement rate limiting
 * 5. Log authentication attempts
 * 6. Use secure token generation
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly properties and classes
 * 3. Named arguments
 * 4. Union types
 * 5. Nullsafe operator
 * 6. Match expressions
 */ 