<?php
/**
 * PHP Design Patterns: Factory (PHP 8.x)
 * ------------------------------
 * This lesson covers:
 * 1. Simple Factory
 * 2. Factory Method
 * 3. Abstract Factory
 * 4. Common use cases
 * 5. Best practices
 * 6. Testing factories
 */

// Product interface
interface Notification {
    public function send(string $message): bool;
    public function getStatus(): string;
}

// Concrete products
class EmailNotification implements Notification {
    public function __construct(
        private readonly string $to,
        private readonly string $from,
        private readonly array $config = []
    ) {}

    public function send(string $message): bool {
        // Simulate email sending
        echo "Sending email to {$this->to}: $message\n";
        return true;
    }

    public function getStatus(): string {
        return "Email notification ready";
    }
}

class SMSNotification implements Notification {
    public function __construct(
        private readonly string $phoneNumber,
        private readonly array $config = []
    ) {}

    public function send(string $message): bool {
        // Simulate SMS sending
        echo "Sending SMS to {$this->phoneNumber}: $message\n";
        return true;
    }

    public function getStatus(): string {
        return "SMS notification ready";
    }
}

class PushNotification implements Notification {
    public function __construct(
        private readonly string $deviceToken,
        private readonly array $config = []
    ) {}

    public function send(string $message): bool {
        // Simulate push notification
        echo "Sending push to {$this->deviceToken}: $message\n";
        return true;
    }

    public function getStatus(): string {
        return "Push notification ready";
    }
}

// Simple Factory
class NotificationFactory {
    public function createNotification(string $type, array $config): Notification {
        return match($type) {
            'email' => new EmailNotification(
                $config['to'],
                $config['from'],
                $config
            ),
            'sms' => new SMSNotification(
                $config['phone_number'],
                $config
            ),
            'push' => new PushNotification(
                $config['device_token'],
                $config
            ),
            default => throw new InvalidArgumentException("Unknown notification type: $type")
        };
    }
}

// Factory Method Pattern
abstract class NotificationCreator {
    abstract protected function createNotification(array $config): Notification;

    public function sendNotification(string $message, array $config): bool {
        $notification = $this->createNotification($config);
        return $notification->send($message);
    }
}

class EmailNotificationCreator extends NotificationCreator {
    protected function createNotification(array $config): Notification {
        return new EmailNotification(
            $config['to'],
            $config['from'],
            $config
        );
    }
}

class SMSNotificationCreator extends NotificationCreator {
    protected function createNotification(array $config): Notification {
        return new SMSNotification(
            $config['phone_number'],
            $config
        );
    }
}

// Abstract Factory Pattern
interface NotificationFactory2 {
    public function createUserNotification(): Notification;
    public function createSystemNotification(): Notification;
    public function createMarketingNotification(): Notification;
}

class EmailNotificationFactory implements NotificationFactory2 {
    public function __construct(
        private readonly array $config
    ) {}

    public function createUserNotification(): Notification {
        return new EmailNotification(
            $this->config['user_email'],
            'user@system.com',
            $this->config
        );
    }

    public function createSystemNotification(): Notification {
        return new EmailNotification(
            $this->config['admin_email'],
            'system@system.com',
            $this->config
        );
    }

    public function createMarketingNotification(): Notification {
        return new EmailNotification(
            $this->config['marketing_email'],
            'marketing@system.com',
            $this->config
        );
    }
}

// Notification service using factories
class NotificationService {
    private array $notifications = [];

    public function __construct(
        private readonly NotificationFactory $factory
    ) {}

    public function addNotification(string $type, array $config): void {
        $this->notifications[] = $this->factory->createNotification($type, $config);
    }

    public function sendAll(string $message): array {
        $results = [];
        foreach ($this->notifications as $notification) {
            $results[] = $notification->send($message);
        }
        return $results;
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: Factory vs Service Locator?
 * A: Factories create new instances, service locators manage existing ones
 * 
 * Q2: When to use which factory pattern?
 * A: Simple Factory for basic cases, Factory Method for inheritance, Abstract Factory for families
 * 
 * Q3: How to handle configuration?
 * A: Pass through factory methods or use dependency injection
 * 
 * Q4: What about performance?
 * A: Use object pooling or caching for expensive instantiations
 */

// Usage example
try {
    // Simple Factory usage
    $factory = new NotificationFactory();
    
    $email = $factory->createNotification('email', [
        'to' => 'user@example.com',
        'from' => 'system@example.com'
    ]);
    
    $sms = $factory->createNotification('sms', [
        'phone_number' => '+1234567890'
    ]);

    // Factory Method usage
    $emailCreator = new EmailNotificationCreator();
    $emailCreator->sendNotification("Hello via factory method", [
        'to' => 'user@example.com',
        'from' => 'system@example.com'
    ]);

    // Abstract Factory usage
    $emailFactory = new EmailNotificationFactory([
        'user_email' => 'user@example.com',
        'admin_email' => 'admin@example.com',
        'marketing_email' => 'marketing@example.com'
    ]);

    $userNotification = $emailFactory->createUserNotification();
    $systemNotification = $emailFactory->createSystemNotification();

    // Notification service usage
    $service = new NotificationService($factory);
    $service->addNotification('email', [
        'to' => 'user@example.com',
        'from' => 'system@example.com'
    ]);
    $service->addNotification('sms', [
        'phone_number' => '+1234567890'
    ]);
    
    $results = $service->sendAll("Broadcast message");

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

/**
 * Best Practices:
 * 1. Use interfaces
 * 2. Follow SOLID principles
 * 3. Keep factories focused
 * 4. Handle errors gracefully
 * 5. Document factory methods
 * 6. Consider performance
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly properties
 * 3. Named arguments
 * 4. Match expressions
 * 5. Union types
 * 6. Mixed type
 */ 