<?php
/**
 * PHP Middleware and Request Handling (PHP 8.x)
 * --------------------------------------
 * This lesson covers:
 * 1. Middleware concept
 * 2. Request/Response objects
 * 3. Middleware pipeline
 * 4. Common middleware types
 * 5. Error handling
 * 6. Request validation
 */

// PSR-7 inspired interfaces
interface ServerRequestInterface {
    public function getMethod(): string;
    public function getUri(): string;
    public function getHeaders(): array;
    public function getBody(): string;
    public function getAttribute(string $name): mixed;
    public function withAttribute(string $name, mixed $value): self;
}

interface ResponseInterface {
    public function getStatusCode(): int;
    public function withStatus(int $code): self;
    public function getHeaders(): array;
    public function withHeader(string $name, string $value): self;
    public function getBody(): string;
    public function withBody(string $body): self;
}

// Middleware interface
interface MiddlewareInterface {
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface;
}

interface RequestHandlerInterface {
    public function handle(ServerRequestInterface $request): ResponseInterface;
}

// Basic implementations
class ServerRequest implements ServerRequestInterface {
    private array $attributes = [];

    public function __construct(
        private readonly string $method,
        private readonly string $uri,
        private readonly array $headers = [],
        private readonly string $body = ''
    ) {}

    public function getMethod(): string {
        return $this->method;
    }

    public function getUri(): string {
        return $this->uri;
    }

    public function getHeaders(): array {
        return $this->headers;
    }

    public function getBody(): string {
        return $this->body;
    }

    public function getAttribute(string $name): mixed {
        return $this->attributes[$name] ?? null;
    }

    public function withAttribute(string $name, mixed $value): self {
        $clone = clone $this;
        $clone->attributes[$name] = $value;
        return $clone;
    }
}

class Response implements ResponseInterface {
    public function __construct(
        private int $status = 200,
        private array $headers = [],
        private string $body = ''
    ) {}

    public function getStatusCode(): int {
        return $this->status;
    }

    public function withStatus(int $code): self {
        $clone = clone $this;
        $clone->status = $code;
        return $clone;
    }

    public function getHeaders(): array {
        return $this->headers;
    }

    public function withHeader(string $name, string $value): self {
        $clone = clone $this;
        $clone->headers[$name] = $value;
        return $clone;
    }

    public function getBody(): string {
        return $this->body;
    }

    public function withBody(string $body): self {
        $clone = clone $this;
        $clone->body = $body;
        return $clone;
    }
}

// Middleware pipeline
class MiddlewarePipeline implements RequestHandlerInterface {
    private array $middleware = [];
    private ?RequestHandlerInterface $fallbackHandler = null;

    public function pipe(MiddlewareInterface $middleware): self {
        $this->middleware[] = $middleware;
        return $this;
    }

    public function setFallbackHandler(RequestHandlerInterface $handler): self {
        $this->fallbackHandler = $handler;
        return $this;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface {
        return $this->process(0, $request);
    }

    private function process(int $index, ServerRequestInterface $request): ResponseInterface {
        if (isset($this->middleware[$index])) {
            return $this->middleware[$index]->process(
                $request,
                new class($this, $index + 1) implements RequestHandlerInterface {
                    public function __construct(
                        private MiddlewarePipeline $pipeline,
                        private int $index
                    ) {}

                    public function handle(ServerRequestInterface $request): ResponseInterface {
                        return $this->pipeline->process($this->index, $request);
                    }
                }
            );
        }

        if ($this->fallbackHandler) {
            return $this->fallbackHandler->handle($request);
        }

        throw new RuntimeException('No handler available');
    }
}

// Example middleware implementations
class AuthenticationMiddleware implements MiddlewareInterface {
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $token = $request->getHeaders()['Authorization'] ?? null;

        if (!$token) {
            return new Response(401, [], 'Unauthorized');
        }

        // Validate token and set user
        $user = $this->validateToken($token);
        return $handler->handle($request->withAttribute('user', $user));
    }

    private function validateToken(string $token): array {
        // Simulate token validation
        return ['id' => 1, 'name' => 'John Doe'];
    }
}

class ValidationMiddleware implements MiddlewareInterface {
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        if ($request->getMethod() === 'POST') {
            $data = json_decode($request->getBody(), true);
            
            if (!$this->validate($data)) {
                return new Response(
                    400,
                    ['Content-Type' => 'application/json'],
                    json_encode(['error' => 'Invalid request data'])
                );
            }
        }

        return $handler->handle($request);
    }

    private function validate(array $data): bool {
        // Implement validation logic
        return !empty($data['name'] ?? '') && !empty($data['email'] ?? '');
    }
}

class LoggingMiddleware implements MiddlewareInterface {
    public function __construct(
        private readonly Logger $logger
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $start = microtime(true);
        
        try {
            $response = $handler->handle($request);
            $duration = microtime(true) - $start;
            
            $this->logger->log('Request processed', [
                'method' => $request->getMethod(),
                'uri' => $request->getUri(),
                'status' => $response->getStatusCode(),
                'duration' => $duration
            ]);
            
            return $response;
        } catch (Throwable $e) {
            $this->logger->log('Request failed', [
                'error' => $e->getMessage(),
                'uri' => $request->getUri()
            ]);
            throw $e;
        }
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: What's the difference between middleware and filters?
 * A: Middleware is more flexible and can modify both request and response
 * 
 * Q2: How to handle middleware order?
 * A: Order matters! Authentication before authorization, logging first/last
 * 
 * Q3: When to use middleware vs controllers?
 * A: Middleware for cross-cutting concerns, controllers for business logic
 * 
 * Q4: How to share data between middleware?
 * A: Use request attributes or dependency injection
 */

// Usage example
try {
    // Create pipeline
    $pipeline = new MiddlewarePipeline();
    
    // Add middleware
    $pipeline
        ->pipe(new LoggingMiddleware(new FileLogger(__DIR__ . '/app.log')))
        ->pipe(new AuthenticationMiddleware())
        ->pipe(new ValidationMiddleware());

    // Create request
    $request = new ServerRequest(
        'POST',
        '/api/users',
        ['Authorization' => 'Bearer token123'],
        json_encode(['name' => 'John', 'email' => 'john@example.com'])
    );

    // Process request
    $response = $pipeline->handle($request);
    
    // Output response
    http_response_code($response->getStatusCode());
    foreach ($response->getHeaders() as $name => $value) {
        header("$name: $value");
    }
    echo $response->getBody();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Best Practices:
 * 1. Keep middleware focused
 * 2. Use dependency injection
 * 3. Handle errors properly
 * 4. Consider middleware order
 * 5. Use immutable objects
 * 6. Log middleware actions
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly properties
 * 3. Named arguments
 * 4. Union types
 * 5. Match expressions
 * 6. Nullsafe operator
 */ 