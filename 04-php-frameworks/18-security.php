<?php
/**
 * PHP Security Best Practices (PHP 8.x)
 * ----------------------------
 * This lesson covers:
 * 1. Input validation
 * 2. Output escaping
 * 3. CSRF protection
 * 4. XSS prevention
 * 5. SQL injection prevention
 * 6. Security headers
 */

// Security service
class SecurityService {
    public function __construct(
        private readonly Logger $logger,
        private readonly array $config = []
    ) {}

    // Input validation
    public function validateInput(array $input, array $rules): array {
        $errors = [];
        $sanitized = [];

        foreach ($rules as $field => $rule) {
            $value = $input[$field] ?? null;
            $sanitized[$field] = $this->sanitize($value, $rule);

            if ($error = $this->validate($value, $rule)) {
                $errors[$field] = $error;
            }
        }

        if (!empty($errors)) {
            throw new ValidationException("Input validation failed", $errors);
        }

        return $sanitized;
    }

    // Output escaping
    public function escape(string $value, string $context = 'html'): string {
        return match($context) {
            'html' => htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'js' => json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
            'url' => urlencode($value),
            'sql' => addslashes($value),
            default => throw new InvalidArgumentException("Unknown escape context: {$context}")
        };
    }

    // CSRF token generation and validation
    public function generateCsrfToken(): string {
        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;
        return $token;
    }

    public function validateCsrfToken(?string $token): bool {
        return $token && hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }

    // Security headers
    public function setSecurityHeaders(): void {
        $headers = [
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'X-Content-Type-Options' => 'nosniff',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Content-Security-Policy' => $this->getCSPHeader(),
            'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
            'Permissions-Policy' => 'geolocation=(), microphone=(), camera=()'
        ];

        foreach ($headers as $header => $value) {
            header("{$header}: {$value}");
        }
    }

    // Password hashing
    public function hashPassword(string $password): string {
        return password_hash($password, PASSWORD_ARGON2ID, [
            'memory_cost' => 65536,
            'time_cost' => 4,
            'threads' => 3
        ]);
    }

    public function verifyPassword(string $password, string $hash): bool {
        return password_verify($password, $hash);
    }

    // File upload security
    public function validateUpload(array $file, array $allowedTypes, int $maxSize): void {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new UploadException('Upload failed', $file['error']);
        }

        if ($file['size'] > $maxSize) {
            throw new UploadException('File too large');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes)) {
            throw new UploadException('Invalid file type');
        }
    }

    // Private helper methods
    private function sanitize(mixed $value, array $rule): mixed {
        if (is_string($value)) {
            $value = trim($value);
            
            if ($rule['type'] === 'email') {
                $value = filter_var($value, FILTER_SANITIZE_EMAIL);
            } elseif ($rule['type'] === 'url') {
                $value = filter_var($value, FILTER_SANITIZE_URL);
            }
        }

        return $value;
    }

    private function validate(mixed $value, array $rule): ?string {
        if ($rule['required'] && empty($value)) {
            return 'Field is required';
        }

        if ($value !== null) {
            if ($rule['type'] === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                return 'Invalid email format';
            }

            if ($rule['type'] === 'url' && !filter_var($value, FILTER_VALIDATE_URL)) {
                return 'Invalid URL format';
            }

            if (isset($rule['min']) && strlen($value) < $rule['min']) {
                return "Minimum length is {$rule['min']}";
            }

            if (isset($rule['max']) && strlen($value) > $rule['max']) {
                return "Maximum length is {$rule['max']}";
            }

            if (isset($rule['pattern']) && !preg_match($rule['pattern'], $value)) {
                return 'Invalid format';
            }
        }

        return null;
    }

    private function getCSPHeader(): string {
        $policies = [
            "default-src 'self'",
            "script-src 'self' 'strict-dynamic'",
            "style-src 'self' 'unsafe-inline'",
            "img-src 'self' data: https:",
            "font-src 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
            "base-uri 'self'",
            "upgrade-insecure-requests"
        ];

        return implode('; ', $policies);
    }
}

// Security middleware
class SecurityMiddleware implements MiddlewareInterface {
    public function __construct(
        private readonly SecurityService $security,
        private readonly array $config = []
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // Set security headers
        $this->security->setSecurityHeaders();

        // CSRF protection for non-GET requests
        if (!in_array($request->getMethod(), ['GET', 'HEAD', 'OPTIONS'])) {
            $token = $request->getHeaderLine('X-CSRF-Token');
            if (!$this->security->validateCsrfToken($token)) {
                throw new SecurityException('Invalid CSRF token');
            }
        }

        return $handler->handle($request);
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: How to handle file uploads securely?
 * A: Validate type, size, and use move_uploaded_file()
 * 
 * Q2: How to prevent XSS attacks?
 * A: Always escape output and use Content Security Policy
 * 
 * Q3: How to store passwords securely?
 * A: Use strong hashing algorithms like Argon2id
 * 
 * Q4: How to prevent CSRF attacks?
 * A: Use tokens and SameSite cookies
 */

// Usage example
try {
    $security = new SecurityService(
        logger: new FileLogger(__DIR__ . '/security.log'),
        config: [
            'allowed_hosts' => ['example.com'],
            'max_upload_size' => 5 * 1024 * 1024 // 5MB
        ]
    );

    // Input validation
    $input = $security->validateInput($_POST, [
        'email' => ['type' => 'email', 'required' => true],
        'password' => ['type' => 'string', 'min' => 8, 'required' => true]
    ]);

    // Password hashing
    $hash = $security->hashPassword($input['password']);

    // Output escaping
    echo $security->escape($input['email']);

    // File upload
    if (isset($_FILES['document'])) {
        $security->validateUpload(
            $_FILES['document'],
            ['application/pdf', 'image/jpeg'],
            5 * 1024 * 1024
        );
    }

} catch (Exception $e) {
    error_log($e->getMessage());
    http_response_code(400);
    echo "Security check failed";
}

/**
 * Best Practices:
 * 1. Validate all input
 * 2. Escape all output
 * 3. Use prepared statements
 * 4. Implement CSRF protection
 * 5. Set security headers
 * 6. Keep dependencies updated
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly properties
 * 3. Named arguments
 * 4. Union types
 * 5. Match expressions
 * 6. Nullsafe operator
 */ 