<?php
/**
 * PHP Error Handling and Logging (PHP 8.x)
 * ---------------------------------
 * This lesson covers:
 * 1. Exception handling
 * 2. Error logging
 * 3. Custom exceptions
 * 4. Error middleware
 * 5. Debug handling
 * 6. Error reporting
 */

// Base exception for application
class AppException extends Exception {
    protected array $context = [];

    public function setContext(array $context): self {
        $this->context = $context;
        return $this;
    }

    public function getContext(): array {
        return $this->context;
    }
}

// Specific exception types
class ValidationException extends AppException {}
class NotFoundException extends AppException {}
class AuthenticationException extends AppException {}
class AuthorizationException extends AppException {}

// PSR-3 inspired logger interface
interface LoggerInterface {
    public function emergency(string $message, array $context = []): void;
    public function alert(string $message, array $context = []): void;
    public function critical(string $message, array $context = []): void;
    public function error(string $message, array $context = []): void;
    public function warning(string $message, array $context = []): void;
    public function notice(string $message, array $context = []): void;
    public function info(string $message, array $context = []): void;
    public function debug(string $message, array $context = []): void;
}

// File logger implementation
class FileLogger implements LoggerInterface {
    private string $logFile;

    public function __construct(
        string $logFile,
        private readonly bool $includeStackTraces = true
    ) {
        $this->logFile = $logFile;
        $this->ensureLogFileExists();
    }

    public function emergency(string $message, array $context = []): void {
        $this->log('EMERGENCY', $message, $context);
    }

    public function alert(string $message, array $context = []): void {
        $this->log('ALERT', $message, $context);
    }

    public function critical(string $message, array $context = []): void {
        $this->log('CRITICAL', $message, $context);
    }

    public function error(string $message, array $context = []): void {
        $this->log('ERROR', $message, $context);
    }

    public function warning(string $message, array $context = []): void {
        $this->log('WARNING', $message, $context);
    }

    public function notice(string $message, array $context = []): void {
        $this->log('NOTICE', $message, $context);
    }

    public function info(string $message, array $context = []): void {
        $this->log('INFO', $message, $context);
    }

    public function debug(string $message, array $context = []): void {
        $this->log('DEBUG', $message, $context);
    }

    private function log(string $level, string $message, array $context): void {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = empty($context) ? '' : ' ' . json_encode($context);
        
        if (isset($context['exception']) && $this->includeStackTraces) {
            $contextStr .= "\nStack trace:\n" . $context['exception']->getTraceAsString();
        }

        $logEntry = "[$timestamp] $level: $message$contextStr" . PHP_EOL;
        
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }

    private function ensureLogFileExists(): void {
        $directory = dirname($this->logFile);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }
        if (!file_exists($this->logFile)) {
            touch($this->logFile);
            chmod($this->logFile, 0666);
        }
    }
}

// Error handler middleware
class ErrorHandlerMiddleware implements MiddlewareInterface {
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly bool $displayErrors = false
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        try {
            return $handler->handle($request);
        } catch (Throwable $error) {
            return $this->handleError($error, $request);
        }
    }

    private function handleError(Throwable $error, ServerRequestInterface $request): ResponseInterface {
        // Log the error
        $context = [
            'exception' => $error,
            'url' => (string) $request->getUri(),
            'method' => $request->getMethod(),
        ];

        if ($error instanceof AppException) {
            $context = array_merge($context, $error->getContext());
        }

        $this->logger->error($error->getMessage(), $context);

        // Determine response status code
        $status = match(true) {
            $error instanceof ValidationException => 422,
            $error instanceof NotFoundException => 404,
            $error instanceof AuthenticationException => 401,
            $error instanceof AuthorizationException => 403,
            default => 500
        };

        // Create error response
        $response = [
            'error' => [
                'message' => $this->displayErrors ? $error->getMessage() : 'An error occurred',
                'code' => $error->getCode()
            ]
        ];

        if ($this->displayErrors) {
            $response['error']['trace'] = $error->getTraceAsString();
        }

        return new Response(
            $status,
            ['Content-Type' => 'application/json'],
            json_encode($response)
        );
    }
}

// Error handler class
class ErrorHandler {
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
        $this->registerHandlers();
    }

    public function handleException(Throwable $error): void {
        $this->logger->error($error->getMessage(), [
            'exception' => $error,
            'file' => $error->getFile(),
            'line' => $error->getLine()
        ]);

        if (php_sapi_name() === 'cli') {
            echo "Error: {$error->getMessage()}\n";
            echo $error->getTraceAsString() . "\n";
        }
    }

    public function handleError(
        int $level,
        string $message,
        string $file,
        int $line
    ): bool {
        if (!(error_reporting() & $level)) {
            return false;
        }

        $this->logger->warning($message, [
            'level' => $level,
            'file' => $file,
            'line' => $line
        ]);

        return true;
    }

    private function registerHandlers(): void {
        set_exception_handler([$this, 'handleException']);
        set_error_handler([$this, 'handleError']);
        
        register_shutdown_function(function() {
            $error = error_get_last();
            if ($error !== null && ($error['type'] & (E_ERROR | E_CORE_ERROR | E_COMPILE_ERROR))) {
                $this->handleError(
                    $error['type'],
                    $error['message'],
                    $error['file'],
                    $error['line']
                );
            }
        });
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: When to use exceptions vs error handling?
 * A: Exceptions for expected errors, error handling for system errors
 * 
 * Q2: How to handle fatal errors?
 * A: Use register_shutdown_function and error_get_last
 * 
 * Q3: What to log and when?
 * A: Log all errors but with appropriate levels
 * 
 * Q4: How to handle errors in production?
 * A: Log detailed errors but display generic messages to users
 */

// Usage example
try {
    // Initialize logger
    $logger = new FileLogger(__DIR__ . '/app.log');

    // Initialize error handler
    $errorHandler = new ErrorHandler($logger);

    // Create error middleware
    $middleware = new ErrorHandlerMiddleware(
        logger: $logger,
        displayErrors: false
    );

    // Example error handling
    try {
        throw new ValidationException('Invalid input data');
    } catch (ValidationException $e) {
        $logger->error('Validation failed', [
            'exception' => $e,
            'data' => ['field' => 'email']
        ]);
    }

    // Example with middleware
    $response = $middleware->process(
        new ServerRequest('GET', '/api/users'),
        new class implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface {
                throw new NotFoundException('User not found');
            }
        }
    );

    echo $response->getBody() . "\n";

} catch (Exception $e) {
    // This should be caught by the error handler
    error_log($e->getMessage());
}

/**
 * Best Practices:
 * 1. Use appropriate error levels
 * 2. Log context with errors
 * 3. Handle all error types
 * 4. Use custom exceptions
 * 5. Implement proper logging
 * 6. Secure error displays
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly properties
 * 3. Named arguments
 * 4. Union types
 * 5. Match expressions
 * 6. Nullsafe operator
 */ 