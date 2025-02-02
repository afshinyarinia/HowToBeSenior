<?php
/**
 * PHP Session Management and State Handling (PHP 8.x)
 * ------------------------------------------
 * This lesson covers:
 * 1. Session handling
 * 2. Session storage
 * 3. Session security
 * 4. State management
 * 5. Flash messages
 * 6. Session middleware
 */

// Session handler interface
interface SessionHandlerInterface {
    public function start(): bool;
    public function destroy(): bool;
    public function get(string $key, mixed $default = null): mixed;
    public function set(string $key, mixed $value): void;
    public function remove(string $key): void;
    public function has(string $key): bool;
    public function regenerate(): bool;
    public function flash(string $key, string $message): void;
    public function getFlash(string $key): ?string;
}

// Session configuration
readonly class SessionConfig {
    public function __construct(
        public string $name = 'PHPSESSID',
        public string $savePath = '',
        public int $lifetime = 3600,
        public string $domain = '',
        public string $secure = true,
        public string $httpOnly = true,
        public string $sameSite = 'Lax'
    ) {}
}

// Session handler implementation
class SessionHandler implements SessionHandlerInterface {
    private bool $started = false;

    public function __construct(
        private readonly SessionConfig $config,
        private readonly Logger $logger
    ) {
        $this->configure();
    }

    public function start(): bool {
        if ($this->started) {
            return true;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $this->started = true;
            return true;
        }

        try {
            if (session_start()) {
                $this->started = true;
                return true;
            }
        } catch (Exception $e) {
            $this->logger->error('Failed to start session', [
                'error' => $e->getMessage()
            ]);
        }

        return false;
    }

    public function destroy(): bool {
        if (!$this->started) {
            return true;
        }

        $_SESSION = [];

        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params["path"],
                $params["domain"],
                $params["secure"],
                $params["httponly"]
            );
        }

        $this->started = false;
        return session_destroy();
    }

    public function get(string $key, mixed $default = null): mixed {
        $this->ensureStarted();
        return $_SESSION[$key] ?? $default;
    }

    public function set(string $key, mixed $value): void {
        $this->ensureStarted();
        $_SESSION[$key] = $value;
    }

    public function remove(string $key): void {
        $this->ensureStarted();
        unset($_SESSION[$key]);
    }

    public function has(string $key): bool {
        $this->ensureStarted();
        return isset($_SESSION[$key]);
    }

    public function regenerate(): bool {
        return session_regenerate_id(true);
    }

    public function flash(string $key, string $message): void {
        $this->ensureStarted();
        $_SESSION['_flash'][$key] = $message;
    }

    public function getFlash(string $key): ?string {
        $this->ensureStarted();
        $message = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return $message;
    }

    private function configure(): void {
        session_name($this->config->name);
        
        if ($this->config->savePath) {
            session_save_path($this->config->savePath);
        }

        session_set_cookie_params([
            'lifetime' => $this->config->lifetime,
            'path' => '/',
            'domain' => $this->config->domain,
            'secure' => $this->config->secure,
            'httponly' => $this->config->httpOnly,
            'samesite' => $this->config->sameSite
        ]);
    }

    private function ensureStarted(): void {
        if (!$this->started && !$this->start()) {
            throw new RuntimeException('Session not started');
        }
    }
}

// Session middleware
class SessionMiddleware implements MiddlewareInterface {
    public function __construct(
        private readonly SessionHandlerInterface $session
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        try {
            // Start session
            $this->session->start();

            // Add session to request attributes
            $request = $request->withAttribute('session', $this->session);

            // Handle request
            $response = $handler->handle($request);

            // Potential security checks
            if ($this->shouldRegenerateSession($request)) {
                $this->session->regenerate();
            }

            return $response;
        } catch (Exception $e) {
            throw new RuntimeException('Session handling failed: ' . $e->getMessage());
        }
    }

    private function shouldRegenerateSession(ServerRequestInterface $request): bool {
        // Regenerate on privilege changes or periodically
        $lastRegenerate = $this->session->get('_session_regenerated', 0);
        $threshold = 300; // 5 minutes

        return (time() - $lastRegenerate) > $threshold;
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: How to handle session fixation?
 * A: Regenerate session ID on login and privilege changes
 * 
 * Q2: What about session timeouts?
 * A: Use both server-side and client-side timeouts
 * 
 * Q3: How to secure session cookies?
 * A: Use secure, httpOnly, and SameSite attributes
 * 
 * Q4: When to use flash messages?
 * A: For one-time notifications across redirects
 */

// Usage example
try {
    // Configure session
    $config = new SessionConfig(
        name: 'MY_SESSION',
        lifetime: 3600,
        secure: true,
        httpOnly: true,
        sameSite: 'Lax'
    );

    // Create session handler
    $session = new SessionHandler(
        config: $config,
        logger: new FileLogger(__DIR__ . '/session.log')
    );

    // Create middleware
    $middleware = new SessionMiddleware($session);

    // Example usage
    $session->start();

    // Store user data
    $session->set('user_id', 123);
    $session->set('user_role', 'admin');

    // Flash message
    $session->flash('success', 'Profile updated successfully');

    // Later, in another request
    $userId = $session->get('user_id');
    $message = $session->getFlash('success');

    if ($message) {
        echo "Flash message: $message\n";
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    echo "Session handling failed";
}

/**
 * Best Practices:
 * 1. Use secure session configuration
 * 2. Implement session regeneration
 * 3. Handle session timeouts
 * 4. Use flash messages appropriately
 * 5. Validate session data
 * 6. Log session anomalies
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly properties and classes
 * 3. Named arguments
 * 4. Union types
 * 5. Nullsafe operator
 * 6. Match expressions
 */ 