<?php
/**
 * PHP API Development (PHP 8.x)
 * ----------------------
 * This lesson covers:
 * 1. RESTful API design
 * 2. API versioning
 * 3. Response formatting
 * 4. Rate limiting
 * 5. API documentation
 * 6. API security
 */

// API response class
readonly class ApiResponse {
    public function __construct(
        public int $status = 200,
        public mixed $data = null,
        public ?string $message = null,
        public ?array $errors = null,
        public ?array $meta = null
    ) {}

    public function toArray(): array {
        return array_filter([
            'status' => $this->status,
            'data' => $this->data,
            'message' => $this->message,
            'errors' => $this->errors,
            'meta' => $this->meta
        ], fn($value) => !is_null($value));
    }

    public function toJson(): string {
        return json_encode($this->toArray());
    }
}

// API controller base class
abstract class ApiController {
    protected function json(
        mixed $data = null,
        int $status = 200,
        ?string $message = null,
        ?array $meta = null
    ): ApiResponse {
        return new ApiResponse(
            status: $status,
            data: $data,
            message: $message,
            meta: $meta
        );
    }

    protected function error(
        string $message,
        int $status = 400,
        ?array $errors = null
    ): ApiResponse {
        return new ApiResponse(
            status: $status,
            message: $message,
            errors: $errors
        );
    }

    protected function paginate(array $items, int $total, int $page, int $perPage): array {
        return [
            'items' => $items,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage)
            ]
        ];
    }
}

// Rate limiter middleware
class RateLimiterMiddleware implements MiddlewareInterface {
    public function __construct(
        private readonly Cache $cache,
        private readonly int $maxRequests = 60,
        private readonly int $decayMinutes = 1
    ) {}

    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $key = $this->getRequestSignature($request);
        $current = (int) $this->cache->get($key, 0);

        if ($current >= $this->maxRequests) {
            return new Response(
                429,
                ['Content-Type' => 'application/json'],
                json_encode([
                    'error' => 'Too Many Requests',
                    'message' => 'API rate limit exceeded'
                ])
            );
        }

        $this->cache->set($key, $current + 1, $this->decayMinutes * 60);

        $response = $handler->handle($request);
        return $response->withHeader('X-RateLimit-Limit', (string) $this->maxRequests)
                       ->withHeader('X-RateLimit-Remaining', (string) ($this->maxRequests - $current - 1));
    }

    private function getRequestSignature(ServerRequestInterface $request): string {
        return md5(
            $request->getHeaderLine('X-API-Key') . 
            $request->getServerParams()['REMOTE_ADDR']
        );
    }
}

// Example API controller
class UserApiController extends ApiController {
    public function __construct(
        private readonly UserRepository $users,
        private readonly Validator $validator
    ) {}

    public function index(ServerRequestInterface $request): ApiResponse {
        $page = (int) ($request->getQueryParams()['page'] ?? 1);
        $perPage = (int) ($request->getQueryParams()['per_page'] ?? 10);

        $users = $this->users->paginate($page, $perPage);
        $total = $this->users->count();

        return $this->json(
            data: $this->paginate($users, $total, $page, $perPage)
        );
    }

    public function show(int $id): ApiResponse {
        $user = $this->users->find($id);
        
        if (!$user) {
            return $this->error('User not found', 404);
        }

        return $this->json($user);
    }

    public function store(ServerRequestInterface $request): ApiResponse {
        $data = json_decode($request->getBody()->getContents(), true);

        if (!$this->validator->validate($data, [
            'name' => 'required|min:2',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:8'
        ])) {
            return $this->error(
                'Validation failed',
                422,
                $this->validator->errors()
            );
        }

        try {
            $user = $this->users->create($data);
            return $this->json($user, 201, 'User created successfully');
        } catch (Exception $e) {
            return $this->error('Failed to create user', 500);
        }
    }

    public function update(int $id, ServerRequestInterface $request): ApiResponse {
        $user = $this->users->find($id);
        
        if (!$user) {
            return $this->error('User not found', 404);
        }

        $data = json_decode($request->getBody()->getContents(), true);

        if (!$this->validator->validate($data, [
            'name' => 'min:2',
            'email' => "email|unique:users,email,{$id}"
        ])) {
            return $this->error(
                'Validation failed',
                422,
                $this->validator->errors()
            );
        }

        try {
            $user = $this->users->update($id, $data);
            return $this->json($user, 200, 'User updated successfully');
        } catch (Exception $e) {
            return $this->error('Failed to update user', 500);
        }
    }

    public function delete(int $id): ApiResponse {
        try {
            if ($this->users->delete($id)) {
                return $this->json(message: 'User deleted successfully');
            }
            return $this->error('User not found', 404);
        } catch (Exception $e) {
            return $this->error('Failed to delete user', 500);
        }
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: How to handle API versioning?
 * A: Use URL prefixes, headers, or content negotiation
 * 
 * Q2: How to implement rate limiting?
 * A: Use cache/database to track requests per key/IP
 * 
 * Q3: How to secure API endpoints?
 * A: Use authentication, API keys, and proper validation
 * 
 * Q4: How to handle API documentation?
 * A: Use OpenAPI/Swagger or similar documentation tools
 */

// Usage example
try {
    // Create API router
    $router = new Router();
    
    // API middleware stack
    $middleware = [
        new RateLimiterMiddleware(new RedisCache()),
        new AuthenticationMiddleware(),
        new JsonResponseMiddleware()
    ];

    // API routes (v1)
    $router->group('/api/v1', function(Router $router) {
        $router->get('/users', [UserApiController::class, 'index']);
        $router->get('/users/{id}', [UserApiController::class, 'show']);
        $router->post('/users', [UserApiController::class, 'store']);
        $router->put('/users/{id}', [UserApiController::class, 'update']);
        $router->delete('/users/{id}', [UserApiController::class, 'delete']);
    }, $middleware);

    // Handle request
    $response = $router->dispatch(
        $_SERVER['REQUEST_METHOD'],
        parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
    );

    // Send response
    http_response_code($response->status);
    header('Content-Type: application/json');
    echo $response->toJson();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal Server Error',
        'message' => $e->getMessage()
    ]);
}

/**
 * Best Practices:
 * 1. Use proper HTTP methods
 * 2. Implement versioning
 * 3. Use consistent response format
 * 4. Validate all input
 * 5. Rate limit requests
 * 6. Document API endpoints
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly properties and classes
 * 3. Named arguments
 * 4. Union types
 * 5. Match expressions
 * 6. Nullsafe operator
 */ 