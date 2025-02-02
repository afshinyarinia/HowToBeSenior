<?php
/**
 * PHP Events and Observers (PHP 8.x)
 * ---------------------------
 * This lesson covers:
 * 1. Event system implementation
 * 2. Event dispatching
 * 3. Event listeners/subscribers
 * 4. Event prioritization
 * 5. Asynchronous events
 * 6. Event propagation
 */

// Event interface
interface Event {
    public function getName(): string;
    public function getTimestamp(): int;
    public function isPropagationStopped(): bool;
    public function stopPropagation(): void;
}

// Base event class
abstract class AbstractEvent implements Event {
    private bool $propagationStopped = false;
    private int $timestamp;

    public function __construct() {
        $this->timestamp = time();
    }

    public function getTimestamp(): int {
        return $this->timestamp;
    }

    public function isPropagationStopped(): bool {
        return $this->propagationStopped;
    }

    public function stopPropagation(): void {
        $this->propagationStopped = true;
    }
}

// Example events
class UserCreatedEvent extends AbstractEvent {
    public function __construct(
        private readonly array $user,
        private readonly string $source = 'api'
    ) {
        parent::__construct();
    }

    public function getName(): string {
        return 'user.created';
    }

    public function getUser(): array {
        return $this->user;
    }

    public function getSource(): string {
        return $this->source;
    }
}

class UserUpdatedEvent extends AbstractEvent {
    public function __construct(
        private readonly array $user,
        private readonly array $changes
    ) {
        parent::__construct();
    }

    public function getName(): string {
        return 'user.updated';
    }

    public function getUser(): array {
        return $this->user;
    }

    public function getChanges(): array {
        return $this->changes;
    }
}

// Event listener interface
interface EventListener {
    public function handle(Event $event): void;
}

// Event dispatcher
class EventDispatcher {
    private array $listeners = [];
    private array $sorted = [];

    public function addListener(string $eventName, EventListener $listener, int $priority = 0): void {
        $this->listeners[$eventName][$priority][] = $listener;
        unset($this->sorted[$eventName]);
    }

    public function dispatch(Event $event): Event {
        $eventName = $event->getName();
        
        foreach ($this->getListeners($eventName) as $listener) {
            if ($event->isPropagationStopped()) {
                break;
            }
            
            $listener->handle($event);
        }

        return $event;
    }

    public function getListeners(string $eventName): array {
        if (!isset($this->sorted[$eventName])) {
            $this->sortListeners($eventName);
        }

        return $this->sorted[$eventName];
    }

    private function sortListeners(string $eventName): void {
        $this->sorted[$eventName] = [];
        
        if (isset($this->listeners[$eventName])) {
            krsort($this->listeners[$eventName]);
            
            foreach ($this->listeners[$eventName] as $listeners) {
                foreach ($listeners as $listener) {
                    $this->sorted[$eventName][] = $listener;
                }
            }
        }
    }
}

// Example listeners
class EmailNotificationListener implements EventListener {
    public function __construct(
        private readonly Logger $logger,
        private readonly Mailer $mailer
    ) {}

    public function handle(Event $event): void {
        if ($event instanceof UserCreatedEvent) {
            $user = $event->getUser();
            
            try {
                $this->mailer->send(
                    to: $user['email'],
                    subject: 'Welcome!',
                    body: "Welcome to our platform, {$user['name']}!"
                );
                
                $this->logger->info('Welcome email sent', [
                    'user_id' => $user['id'],
                    'email' => $user['email']
                ]);
            } catch (Exception $e) {
                $this->logger->error('Failed to send welcome email', [
                    'error' => $e->getMessage(),
                    'user_id' => $user['id']
                ]);
            }
        }
    }
}

class AuditLogListener implements EventListener {
    public function __construct(
        private readonly Logger $logger
    ) {}

    public function handle(Event $event): void {
        $context = [
            'event' => $event->getName(),
            'timestamp' => $event->getTimestamp()
        ];

        if ($event instanceof UserCreatedEvent) {
            $context['user'] = $event->getUser();
            $context['source'] = $event->getSource();
        } elseif ($event instanceof UserUpdatedEvent) {
            $context['user'] = $event->getUser();
            $context['changes'] = $event->getChanges();
        }

        $this->logger->info('Audit log entry', $context);
    }
}

// Async event dispatcher (example with queue)
class AsyncEventDispatcher extends EventDispatcher {
    public function __construct(
        private readonly Queue $queue,
        private readonly Logger $logger
    ) {
        parent::__construct();
    }

    public function dispatch(Event $event): Event {
        try {
            $this->queue->push([
                'event' => serialize($event),
                'timestamp' => time()
            ]);
            
            $this->logger->info('Event queued', [
                'event' => $event->getName()
            ]);
        } catch (Exception $e) {
            $this->logger->error('Failed to queue event', [
                'error' => $e->getMessage(),
                'event' => $event->getName()
            ]);
            
            // Fallback to synchronous dispatch
            return parent::dispatch($event);
        }

        return $event;
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: When to use events vs direct method calls?
 * A: Use events for loose coupling and when multiple actions need to happen
 * 
 * Q2: How to handle event errors?
 * A: Log errors but don't let them propagate to other listeners
 * 
 * Q3: When to use async events?
 * A: For time-consuming operations that don't need immediate response
 * 
 * Q4: How to maintain event order?
 * A: Use priority system and carefully plan listener dependencies
 */

// Usage example
try {
    // Create dispatcher
    $dispatcher = new EventDispatcher();
    
    // Register listeners
    $dispatcher->addListener(
        'user.created',
        new EmailNotificationListener(
            new FileLogger(__DIR__ . '/app.log'),
            new SmtpMailer('smtp.example.com')
        ),
        priority: 10
    );

    $dispatcher->addListener(
        'user.created',
        new AuditLogListener(
            new FileLogger(__DIR__ . '/audit.log')
        ),
        priority: 0
    );

    // Create and dispatch event
    $event = new UserCreatedEvent([
        'id' => 1,
        'name' => 'John Doe',
        'email' => 'john@example.com'
    ]);

    $dispatcher->dispatch($event);

    // Example with async dispatcher
    $asyncDispatcher = new AsyncEventDispatcher(
        new RedisQueue('localhost', 6379),
        new FileLogger(__DIR__ . '/async.log')
    );

    $asyncDispatcher->dispatch($event);

} catch (Exception $e) {
    error_log($e->getMessage());
}

/**
 * Best Practices:
 * 1. Keep events focused and single-purpose
 * 2. Use meaningful event names
 * 3. Handle listener errors gracefully
 * 4. Document event contracts
 * 5. Consider async processing for heavy operations
 * 6. Use proper error logging
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly properties
 * 3. Named arguments
 * 4. Union types
 * 5. Match expressions
 * 6. Nullsafe operator
 */ 