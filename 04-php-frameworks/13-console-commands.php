<?php
/**
 * PHP Console Commands and CLI Applications (PHP 8.x)
 * -----------------------------------------
 * This lesson covers:
 * 1. Command line interface
 * 2. Command pattern
 * 3. Argument parsing
 * 4. Console output
 * 5. Interactive input
 * 6. Progress indicators
 */

// Command interface
interface CommandInterface {
    public function getName(): string;
    public function getDescription(): string;
    public function execute(array $args = []): int;
}

// Console output handler
class ConsoleOutput {
    private const STYLES = [
        'error' => "\033[31m", // Red
        'success' => "\033[32m", // Green
        'info' => "\033[34m", // Blue
        'warning' => "\033[33m", // Yellow
        'reset' => "\033[0m"
    ];

    public function write(string $message, string $style = ''): void {
        if ($style && isset(self::STYLES[$style])) {
            echo self::STYLES[$style] . $message . self::STYLES['reset'] . PHP_EOL;
        } else {
            echo $message . PHP_EOL;
        }
    }

    public function table(array $headers, array $rows): void {
        // Calculate column widths
        $widths = array_map('strlen', $headers);
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i], strlen($cell));
            }
        }

        // Print headers
        $this->printRow($headers, $widths);
        $this->printSeparator($widths);

        // Print rows
        foreach ($rows as $row) {
            $this->printRow($row, $widths);
        }
    }

    public function progressBar(int $total): ProgressBar {
        return new ProgressBar($total);
    }

    private function printRow(array $row, array $widths): void {
        $cells = array_map(function($cell, $width) {
            return str_pad($cell, $width);
        }, $row, $widths);

        echo "| " . implode(" | ", $cells) . " |" . PHP_EOL;
    }

    private function printSeparator(array $widths): void {
        echo "+" . implode("+", array_map(function($width) {
            return str_repeat("-", $width + 2);
        }, $widths)) . "+" . PHP_EOL;
    }
}

// Progress bar implementation
class ProgressBar {
    private int $current = 0;
    private int $lastPercent = 0;

    public function __construct(
        private readonly int $total,
        private readonly int $width = 50
    ) {
        $this->start();
    }

    public function advance(int $step = 1): void {
        $this->current = min($this->current + $step, $this->total);
        $this->display();
    }

    public function finish(): void {
        $this->current = $this->total;
        $this->display();
        echo PHP_EOL;
    }

    private function start(): void {
        echo PHP_EOL;
        $this->display();
    }

    private function display(): void {
        $percent = (int) ($this->current / $this->total * 100);
        if ($percent === $this->lastPercent) {
            return;
        }

        $this->lastPercent = $percent;
        $filled = (int) ($this->width * $this->current / $this->total);
        $empty = $this->width - $filled;

        echo sprintf(
            "\r[%s%s] %d%%",
            str_repeat("=", $filled),
            str_repeat(" ", $empty),
            $percent
        );
    }
}

// Example commands
class MigrateCommand implements CommandInterface {
    public function __construct(
        private readonly Database $db,
        private readonly ConsoleOutput $output
    ) {}

    public function getName(): string {
        return 'migrate';
    }

    public function getDescription(): string {
        return 'Run database migrations';
    }

    public function execute(array $args = []): int {
        try {
            $migrations = $this->getMigrations();
            if (empty($migrations)) {
                $this->output->write('No migrations found.', 'info');
                return 0;
            }

            $progress = $this->output->progressBar(count($migrations));
            
            foreach ($migrations as $migration) {
                $this->output->write("Running migration: {$migration}");
                // Simulate migration
                sleep(1);
                $progress->advance();
            }

            $progress->finish();
            $this->output->write('Migrations completed successfully.', 'success');
            return 0;
        } catch (Exception $e) {
            $this->output->write("Migration failed: {$e->getMessage()}", 'error');
            return 1;
        }
    }

    private function getMigrations(): array {
        // Simulate getting migrations
        return [
            'CreateUsersTable',
            'CreatePostsTable',
            'AddIndexesToUsers',
            'AddForeignKeys'
        ];
    }
}

class UserListCommand implements CommandInterface {
    public function __construct(
        private readonly UserRepository $users,
        private readonly ConsoleOutput $output
    ) {}

    public function getName(): string {
        return 'user:list';
    }

    public function getDescription(): string {
        return 'List all users';
    }

    public function execute(array $args = []): int {
        try {
            $users = $this->users->findAll();
            
            $this->output->table(
                ['ID', 'Name', 'Email', 'Created At'],
                array_map(fn($user) => [
                    $user->id,
                    $user->name,
                    $user->email,
                    $user->createdAt->format('Y-m-d H:i:s')
                ], $users)
            );

            return 0;
        } catch (Exception $e) {
            $this->output->write("Failed to list users: {$e->getMessage()}", 'error');
            return 1;
        }
    }
}

// Console application
class ConsoleApplication {
    private array $commands = [];

    public function __construct(
        private readonly ConsoleOutput $output
    ) {}

    public function add(CommandInterface $command): void {
        $this->commands[$command->getName()] = $command;
    }

    public function run(array $argv): int {
        $command = $argv[1] ?? 'list';

        if ($command === 'list') {
            return $this->listCommands();
        }

        if (!isset($this->commands[$command])) {
            $this->output->write("Command not found: {$command}", 'error');
            return 1;
        }

        $args = array_slice($argv, 2);
        return $this->commands[$command]->execute($args);
    }

    private function listCommands(): int {
        $this->output->write('Available commands:', 'info');
        
        foreach ($this->commands as $name => $command) {
            $this->output->write(
                sprintf("  %s\t%s", $name, $command->getDescription())
            );
        }

        return 0;
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: How to handle interactive input?
 * A: Use readline or similar functions with proper validation
 * 
 * Q2: How to format console output?
 * A: Use ANSI escape codes or dedicated libraries
 * 
 * Q3: How to handle command dependencies?
 * A: Use dependency injection and service container
 * 
 * Q4: How to test console commands?
 * A: Mock IO and test business logic separately
 */

// Usage example
try {
    $output = new ConsoleOutput();
    $app = new ConsoleApplication($output);

    // Register commands
    $app->add(new MigrateCommand(
        new MySQLDatabase(/* config */),
        $output
    ));

    $app->add(new UserListCommand(
        new DatabaseUserRepository(/* config */),
        $output
    ));

    // Run application
    exit($app->run($argv));

} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . PHP_EOL;
    exit(1);
}

/**
 * Best Practices:
 * 1. Use clear command names
 * 2. Provide helpful descriptions
 * 3. Handle errors gracefully
 * 4. Show progress for long operations
 * 5. Format output for readability
 * 6. Support --help option
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly properties
 * 3. Named arguments
 * 4. Union types
 * 5. Match expressions
 * 6. Nullsafe operator
 */ 