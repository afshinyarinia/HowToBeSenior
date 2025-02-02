<?php
/**
 * PHP Service Providers and Application Bootstrapping (PHP 8.x)
 * --------------------------------------------------
 * This lesson covers:
 * 1. Service Provider pattern
 * 2. Application bootstrapping
 * 3. Configuration management
 * 4. Environment handling
 * 5. Service registration
 * 6. Application lifecycle
 */

// Service Provider Interface
interface ServiceProviderInterface {
    public function register(Container $container): void;
    public function boot(Application $app): void;
}

// Application class
class Application {
    private array $providers = [];
    private array $booted = [];
    private bool $isBooted = false;

    public function __construct(
        private readonly Container $container,
        private readonly array $config = []
    ) {}

    public function register(ServiceProviderInterface $provider): void {
        $name = get_class($provider);
        if (isset($this->providers[$name])) {
            return;
        }

        $this->providers[$name] = $provider;
        $provider->register($this->container);
    }

    public function boot(): void {
        if ($this->isBooted) {
            return;
        }

        foreach ($this->providers as $provider) {
            $this->bootProvider($provider);
        }

        $this->isBooted = true;
    }

    private function bootProvider(ServiceProviderInterface $provider): void {
        $name = get_class($provider);
        if (isset($this->booted[$name])) {
            return;
        }

        $provider->boot($this);
        $this->booted[$name] = true;
    }

    public function getContainer(): Container {
        return $this->container;
    }

    public function getConfig(string $key, mixed $default = null): mixed {
        return $this->config[$key] ?? $default;
    }
}

// Example Service Providers
class DatabaseServiceProvider implements ServiceProviderInterface {
    public function register(Container $container): void {
        // Register database connection
        $container->set(Database::class, function(Container $c) {
            $config = $c->get('config');
            return new MySQLDatabase(
                dsn: $config['db_dsn'],
                username: $config['db_username'],
                password: $config['db_password']
            );
        });

        // Register repositories
        $container->set(UserRepository::class, function(Container $c) {
            return new DatabaseUserRepository(
                db: $c->get(Database::class),
                logger: $c->get(Logger::class)
            );
        });
    }

    public function boot(Application $app): void {
        // Perform any database initialization
        $db = $app->getContainer()->get(Database::class);
        // Set up connections, verify tables, etc.
    }
}

class LoggingServiceProvider implements ServiceProviderInterface {
    public function register(Container $container): void {
        $container->set(Logger::class, function(Container $c) {
            $config = $c->get('config');
            return new FileLogger(
                logPath: $config['log_path']
            );
        });
    }

    public function boot(Application $app): void {
        // Initialize logging
        $logger = $app->getContainer()->get(Logger::class);
        $logger->log('Application starting');
    }
}

class RoutingServiceProvider implements ServiceProviderInterface {
    public function register(Container $container): void {
        $container->set(Router::class, function(Container $c) {
            $router = new Router();
            
            // Load routes from configuration
            $routes = $c->get('config')['routes'] ?? [];
            foreach ($routes as $route) {
                $router->addRoute(
                    $route['method'],
                    $route['path'],
                    $route['handler']
                );
            }
            
            return $router;
        });
    }

    public function boot(Application $app): void {
        // Set up route middleware
        $router = $app->getContainer()->get(Router::class);
        // Configure global middleware, etc.
    }
}

// Configuration management
class ConfigurationManager {
    private array $config = [];

    public function loadFromFile(string $path): void {
        if (!file_exists($path)) {
            throw new RuntimeException("Configuration file not found: $path");
        }

        $this->config = array_merge(
            $this->config,
            require $path
        );
    }

    public function loadFromEnvironment(): void {
        // Load from .env file or environment variables
        $this->config = array_merge($this->config, [
            'db_dsn' => $_ENV['DB_DSN'] ?? null,
            'db_username' => $_ENV['DB_USERNAME'] ?? null,
            'db_password' => $_ENV['DB_PASSWORD'] ?? null,
            'log_path' => $_ENV['LOG_PATH'] ?? __DIR__ . '/app.log',
            'environment' => $_ENV['APP_ENV'] ?? 'production'
        ]);
    }

    public function get(): array {
        return $this->config;
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: When to use service providers?
 * A: For complex service setup and initialization that requires configuration
 * 
 * Q2: What's the difference between register and boot?
 * A: Register binds services, boot initializes them after all services registered
 * 
 * Q3: How to handle provider dependencies?
 * A: Register providers in correct order, use boot phase for cross-dependencies
 * 
 * Q4: When to use environment variables vs configuration files?
 * A: Env for sensitive/variable data, config files for application settings
 */

// Usage example
try {
    // Load configuration
    $configManager = new ConfigurationManager();
    $configManager->loadFromFile(__DIR__ . '/config/app.php');
    $configManager->loadFromEnvironment();

    // Create container and application
    $container = new Container();
    $container->set('config', fn() => $configManager->get());

    $app = new Application($container, $configManager->get());

    // Register service providers
    $app->register(new LoggingServiceProvider());
    $app->register(new DatabaseServiceProvider());
    $app->register(new RoutingServiceProvider());

    // Boot application
    $app->boot();

    // Use services
    $router = $container->get(Router::class);
    $response = $router->dispatch(
        $_SERVER['REQUEST_METHOD'],
        parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)
    );

    // Output response
    echo $response;

} catch (Exception $e) {
    // Handle fatal errors
    error_log($e->getMessage());
    http_response_code(500);
    echo "Internal Server Error";
}

/**
 * Best Practices:
 * 1. Keep providers focused and single-purpose
 * 2. Use environment variables for sensitive data
 * 3. Cache configuration when possible
 * 4. Handle provider dependencies carefully
 * 5. Log provider initialization
 * 6. Use proper error handling
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly properties
 * 3. Named arguments
 * 4. Union types
 * 5. Nullsafe operator
 * 6. Match expressions
 */ 