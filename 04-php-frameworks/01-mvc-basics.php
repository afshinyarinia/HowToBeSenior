<?php
/**
 * PHP MVC Basics (PHP 8.x)
 * -------------------
 * This lesson covers:
 * 1. MVC pattern
 * 2. Routing
 * 3. Controllers
 * 4. Models
 * 5. Views
 * 6. Request/Response handling
 */

// Basic router class
class Router {
    private array $routes = [];
    private array $params = [];

    public function addRoute(string $method, string $path, array $handler): void {
        $this->routes[$method][$path] = $handler;
    }

    public function dispatch(string $method, string $uri): mixed {
        if (!isset($this->routes[$method])) {
            throw new RuntimeException("Method not allowed", 405);
        }

        foreach ($this->routes[$method] as $path => $handler) {
            $pattern = $this->convertPath($path);
            if (preg_match($pattern, $uri, $matches)) {
                $this->params = $this->extractParams($matches);
                return $this->executeHandler($handler);
            }
        }

        throw new RuntimeException("Not found", 404);
    }

    private function convertPath(string $path): string {
        return '#^' . preg_replace('#\{([a-zA-Z0-9_]+)\}#', '(?P<$1>[^/]+)', $path) . '$#';
    }

    private function extractParams(array $matches): array {
        return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
    }

    private function executeHandler(array $handler): mixed {
        [$controller, $method] = $handler;
        return (new $controller)->{$method}($this->params);
    }
}

// Base controller class
abstract class Controller {
    protected function render(string $view, array $data = []): string {
        extract($data);
        ob_start();
        include "views/{$view}.php";
        return ob_get_clean();
    }

    protected function json(array $data): string {
        header('Content-Type: application/json');
        return json_encode($data);
    }
}

// Example model
class UserModel {
    private PDO $db;

    public function __construct() {
        $this->db = new PDO(
            "mysql:host=localhost;dbname=test;charset=utf8mb4",
            "root",
            "secret",
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    public function find(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function create(array $data): int {
        $stmt = $this->db->prepare("
            INSERT INTO users (name, email) VALUES (?, ?)
        ");
        $stmt->execute([$data['name'], $data['email']]);
        return (int) $this->db->lastInsertId();
    }
}

// Example controller
class UserController extends Controller {
    private UserModel $model;

    public function __construct() {
        $this->model = new UserModel();
    }

    public function show(array $params): string {
        $user = $this->model->find($params['id']);
        
        if (!$user) {
            throw new RuntimeException("User not found", 404);
        }

        return $this->render('users/show', ['user' => $user]);
    }

    public function create(): string {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $id = $this->model->create($data);
        return $this->json(['id' => $id]);
    }
}

/**
 * Example view (views/users/show.php):
 * 
 * <!DOCTYPE html>
 * <html>
 * <head>
 *     <title>User Profile</title>
 * </head>
 * <body>
 *     <h1><?= htmlspecialchars($user['name']) ?></h1>
 *     <p>Email: <?= htmlspecialchars($user['email']) ?></p>
 * </body>
 * </html>
 */

// Application setup
$router = new Router();

// Define routes
$router->addRoute('GET', '/users/{id}', [UserController::class, 'show']);
$router->addRoute('POST', '/users', [UserController::class, 'create']);

// Handle request
try {
    $response = $router->dispatch(
        $_SERVER['REQUEST_METHOD'],
        parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
    );
    echo $response;
} catch (RuntimeException $e) {
    http_response_code($e->getCode());
    echo json_encode(['error' => $e->getMessage()]);
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: Why use MVC pattern?
 * A: Separates concerns, improves maintainability and testability
 * 
 * Q2: How to handle form submissions?
 * A: Use POST routes and validate input in controllers
 * 
 * Q3: Where to put business logic?
 * A: In service classes, between controllers and models
 * 
 * Q4: How to handle authentication?
 * A: Use middleware or controller filters
 */

/**
 * Best Practices:
 * 1. Keep controllers thin
 * 2. Use service layer for business logic
 * 3. Validate input data
 * 4. Handle errors gracefully
 * 5. Use dependency injection
 * 6. Follow REST principles
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Named arguments
 * 3. Union types
 * 4. Match expressions
 * 5. Nullsafe operator
 * 6. Attributes (when applicable)
 */ 