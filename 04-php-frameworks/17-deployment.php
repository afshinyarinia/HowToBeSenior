<?php
/**
 * PHP Deployment and DevOps (PHP 8.x)
 * ---------------------------
 * This lesson covers:
 * 1. Deployment strategies
 * 2. Environment configuration
 * 3. Build processes
 * 4. Server provisioning
 * 5. Monitoring
 * 6. CI/CD pipelines
 */

// Environment configuration manager
readonly class Environment {
    private array $config;

    public function __construct(string $envFile = '.env') {
        $this->config = $this->load($envFile);
    }

    public function get(string $key, mixed $default = null): mixed {
        return $this->config[$key] ?? $_ENV[$key] ?? $default;
    }

    public function isProduction(): bool {
        return $this->get('APP_ENV') === 'production';
    }

    private function load(string $file): array {
        if (!file_exists($file)) {
            throw new RuntimeException("Environment file not found: {$file}");
        }

        $config = [];
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '#')) continue;

            list($key, $value) = explode('=', $line, 2);
            $config[trim($key)] = trim($value);
        }

        return $config;
    }
}

// Deployment manager
class DeploymentManager {
    public function __construct(
        private readonly Environment $env,
        private readonly Logger $logger
    ) {}

    public function deploy(): void {
        try {
            $this->preDeploymentChecks();
            $this->backup();
            $this->updateCode();
            $this->runMigrations();
            $this->clearCache();
            $this->restartServices();
            $this->postDeploymentChecks();
        } catch (Throwable $e) {
            $this->logger->error("Deployment failed", [
                'error' => $e->getMessage()
            ]);
            $this->rollback();
            throw $e;
        }
    }

    private function preDeploymentChecks(): void {
        // Check disk space
        $freeSpace = disk_free_space('/');
        if ($freeSpace < 1024 * 1024 * 100) { // 100MB
            throw new RuntimeException("Insufficient disk space");
        }

        // Check PHP version
        if (version_compare(PHP_VERSION, '8.0.0', '<')) {
            throw new RuntimeException("PHP 8.0+ required");
        }

        // Check extensions
        $required = ['pdo', 'redis', 'mbstring'];
        foreach ($required as $ext) {
            if (!extension_loaded($ext)) {
                throw new RuntimeException("Required extension not loaded: {$ext}");
            }
        }
    }

    private function backup(): void {
        $backupDir = $this->env->get('BACKUP_DIR', '/var/backups/app');
        $timestamp = date('Y-m-d_H-i-s');

        // Backup database
        $this->runCommand(sprintf(
            'mysqldump -u%s -p%s %s > %s/db_%s.sql',
            $this->env->get('DB_USER'),
            $this->env->get('DB_PASS'),
            $this->env->get('DB_NAME'),
            $backupDir,
            $timestamp
        ));

        // Backup code
        $this->runCommand(sprintf(
            'tar -czf %s/code_%s.tar.gz %s',
            $backupDir,
            $timestamp,
            $this->env->get('APP_DIR')
        ));
    }

    private function updateCode(): void {
        $branch = $this->env->get('DEPLOY_BRANCH', 'main');
        
        $commands = [
            'git fetch origin',
            "git checkout {$branch}",
            'git pull origin ' . $branch,
            'composer install --no-dev --optimize-autoloader'
        ];

        foreach ($commands as $command) {
            $this->runCommand($command);
        }
    }

    private function runMigrations(): void {
        if ($this->env->isProduction()) {
            // Run with confirmation in production
            $this->logger->info("Running migrations in production");
            $this->runCommand('php artisan migrate --force');
        } else {
            $this->runCommand('php artisan migrate');
        }
    }

    private function clearCache(): void {
        $commands = [
            'php artisan cache:clear',
            'php artisan config:clear',
            'php artisan view:clear',
            'php artisan route:clear'
        ];

        foreach ($commands as $command) {
            $this->runCommand($command);
        }
    }

    private function restartServices(): void {
        $services = ['php-fpm', 'nginx', 'supervisor'];
        
        foreach ($services as $service) {
            $this->runCommand("systemctl restart {$service}");
        }
    }

    private function postDeploymentChecks(): void {
        // Check application health
        $response = file_get_contents($this->env->get('APP_URL') . '/health');
        $health = json_decode($response, true);

        if ($health['status'] !== 'ok') {
            throw new RuntimeException("Health check failed");
        }

        // Check database connectivity
        try {
            $db = new PDO(
                $this->env->get('DB_DSN'),
                $this->env->get('DB_USER'),
                $this->env->get('DB_PASS')
            );
            $db->query('SELECT 1');
        } catch (PDOException $e) {
            throw new RuntimeException("Database check failed");
        }

        // Check cache connectivity
        $redis = new Redis();
        if (!$redis->connect($this->env->get('REDIS_HOST'))) {
            throw new RuntimeException("Cache check failed");
        }
    }

    private function rollback(): void {
        $this->logger->info("Starting rollback procedure");
        
        // Restore from latest backup
        $backupDir = $this->env->get('BACKUP_DIR');
        $latest = $this->getLatestBackup($backupDir);

        if ($latest) {
            // Restore database
            $this->runCommand(sprintf(
                'mysql -u%s -p%s %s < %s/db_%s.sql',
                $this->env->get('DB_USER'),
                $this->env->get('DB_PASS'),
                $this->env->get('DB_NAME'),
                $backupDir,
                $latest
            ));

            // Restore code
            $this->runCommand(sprintf(
                'tar -xzf %s/code_%s.tar.gz -C /',
                $backupDir,
                $latest
            ));

            $this->restartServices();
        }
    }

    private function runCommand(string $command): void {
        $output = [];
        $returnVar = 0;

        exec($command . ' 2>&1', $output, $returnVar);

        if ($returnVar !== 0) {
            throw new RuntimeException(
                "Command failed: " . implode("\n", $output)
            );
        }

        $this->logger->info("Command executed", [
            'command' => $command,
            'output' => $output
        ]);
    }

    private function getLatestBackup(string $dir): ?string {
        $backups = glob($dir . '/db_*.sql');
        if (empty($backups)) return null;

        sort($backups);
        $latest = end($backups);

        return basename($latest, '.sql');
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: How to handle database migrations?
 * A: Use versioned migrations and always test rollbacks
 * 
 * Q2: What about zero-downtime deployments?
 * A: Use blue-green deployment or rolling updates
 * 
 * Q3: How to secure sensitive data?
 * A: Use environment variables and secure vaults
 * 
 * Q4: How to handle deployment failures?
 * A: Implement automated rollback procedures
 */

// Usage example
try {
    $env = new Environment(__DIR__ . '/.env');
    $logger = new FileLogger(__DIR__ . '/deployment.log');

    $deployer = new DeploymentManager($env, $logger);
    $deployer->deploy();

    echo "Deployment completed successfully\n";
} catch (Exception $e) {
    error_log("Deployment failed: " . $e->getMessage());
    exit(1);
}

/**
 * Best Practices:
 * 1. Automate everything
 * 2. Use version control
 * 3. Implement monitoring
 * 4. Have rollback plans
 * 5. Test deployment process
 * 6. Document procedures
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly properties and classes
 * 3. Named arguments
 * 4. Union types
 * 5. Match expressions
 * 6. Nullsafe operator
 */ 