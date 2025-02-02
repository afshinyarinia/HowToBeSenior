<?php
/**
 * PHP Advanced Design Patterns (PHP 8.x)
 * ------------------------------
 * This lesson covers:
 * 1. Behavioral Patterns
 * 2. Structural Patterns
 * 3. Creational Patterns
 * 4. Architectural Patterns
 * 5. Anti-patterns
 * 6. Pattern combinations
 */

// Command Pattern with Undo capability
interface Command {
    public function execute(): void;
    public function undo(): void;
}

// Example commands
class AddUserCommand implements Command {
    private ?User $createdUser = null;

    public function __construct(
        private readonly UserRepository $repository,
        private readonly array $userData
    ) {}

    public function execute(): void {
        $this->createdUser = $this->repository->create($this->userData);
    }

    public function undo(): void {
        if ($this->createdUser) {
            $this->repository->delete($this->createdUser->id);
            $this->createdUser = null;
        }
    }
}

// Command invoker with history
class CommandInvoker {
    private array $history = [];

    public function execute(Command $command): void {
        $command->execute();
        $this->history[] = $command;
    }

    public function undoLast(): void {
        if (!empty($this->history)) {
            $command = array_pop($this->history);
            $command->undo();
        }
    }
}

// Specification Pattern
interface Specification {
    public function isSatisfiedBy(object $candidate): bool;
}

class UserSpecification implements Specification {
    public function __construct(
        private readonly array $criteria
    ) {}

    public function isSatisfiedBy(object $candidate): bool {
        foreach ($this->criteria as $property => $value) {
            if (!isset($candidate->$property) || $candidate->$property !== $value) {
                return false;
            }
        }
        return true;
    }
}

// Null Object Pattern
interface Logger {
    public function log(string $message): void;
}

class NullLogger implements Logger {
    public function log(string $message): void {
        // Do nothing
    }
}

// Decorator Pattern with attributes
#[Decorator]
class LoggingDecorator implements Logger {
    public function __construct(
        private readonly Logger $logger,
        private readonly string $prefix = ''
    ) {}

    public function log(string $message): void {
        $this->logger->log("[{$this->prefix}] {$message}");
    }
}

// Event Sourcing Pattern
abstract class DomainEvent {
    public function __construct(
        public readonly string $aggregateId,
        public readonly \DateTimeImmutable $occurredOn = new \DateTimeImmutable()
    ) {}

    abstract public function apply(object $aggregate): void;
}

class UserCreatedEvent extends DomainEvent {
    public function __construct(
        string $aggregateId,
        public readonly string $name,
        public readonly string $email
    ) {
        parent::__construct($aggregateId);
    }

    public function apply(object $aggregate): void {
        $aggregate->name = $this->name;
        $aggregate->email = $this->email;
        $aggregate->createdAt = $this->occurredOn;
    }
}

// Event Store
class EventStore {
    private array $events = [];

    public function append(DomainEvent $event): void {
        $this->events[] = $event;
    }

    public function getEventsForAggregate(string $aggregateId): array {
        return array_filter(
            $this->events,
            fn($event) => $event->aggregateId === $aggregateId
        );
    }
}

// Unit of Work Pattern
class UnitOfWork {
    private array $newObjects = [];
    private array $dirtyObjects = [];
    private array $removedObjects = [];

    public function registerNew(object $object): void {
        $this->newObjects[spl_object_hash($object)] = $object;
    }

    public function registerDirty(object $object): void {
        $this->dirtyObjects[spl_object_hash($object)] = $object;
    }

    public function registerRemoved(object $object): void {
        $this->removedObjects[spl_object_hash($object)] = $object;
    }

    public function commit(): void {
        foreach ($this->newObjects as $object) {
            // Persist new object
        }

        foreach ($this->dirtyObjects as $object) {
            // Update object
        }

        foreach ($this->removedObjects as $object) {
            // Remove object
        }

        $this->clear();
    }

    private function clear(): void {
        $this->newObjects = [];
        $this->dirtyObjects = [];
        $this->removedObjects = [];
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: When to use which pattern?
 * A: Choose based on problem domain and requirements
 * 
 * Q2: How to avoid pattern overuse?
 * A: Follow YAGNI principle, use patterns only when needed
 * 
 * Q3: How to combine patterns?
 * A: Ensure patterns complement each other and don't create complexity
 * 
 * Q4: What about performance?
 * A: Consider overhead of patterns vs benefits they provide
 */

// Usage example
try {
    // Command pattern example
    $invoker = new CommandInvoker();
    $command = new AddUserCommand(
        new UserRepository(),
        ['name' => 'John', 'email' => 'john@example.com']
    );
    
    $invoker->execute($command);
    $invoker->undoLast();

    // Specification pattern example
    $spec = new UserSpecification([
        'status' => 'active',
        'role' => 'admin'
    ]);

    $user = new User(/* ... */);
    if ($spec->isSatisfiedBy($user)) {
        echo "User matches specification\n";
    }

    // Event sourcing example
    $store = new EventStore();
    $event = new UserCreatedEvent(
        'user-123',
        'John Doe',
        'john@example.com'
    );
    
    $store->append($event);

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

/**
 * Best Practices:
 * 1. Keep patterns simple
 * 2. Document pattern usage
 * 3. Consider maintainability
 * 4. Test pattern implementations
 * 5. Use interfaces
 * 6. Follow SOLID principles
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly properties
 * 3. Named arguments
 * 4. Attributes
 * 5. Union types
 * 6. Match expressions
 */