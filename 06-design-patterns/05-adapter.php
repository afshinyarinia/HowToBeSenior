<?php
/**
 * PHP Design Patterns: Adapter (PHP 8.x)
 * ------------------------------
 * This lesson covers:
 * 1. Adapter pattern implementation
 * 2. Object vs class adapters
 * 3. Interface conversion
 * 4. Legacy code integration
 * 5. Third-party library adaptation
 * 6. Best practices
 */

// Target interface that clients expect to use
interface NotificationService {
    public function send(string $title, string $message, array $recipients): bool;
    public function getStatus(string $notificationId): string;
}

// Legacy/Third-party service that needs to be adapted
class EmailService {
    public function sendEmail(string $subject, array $to, string $body): string {
        // Simulate sending email
        $id = uniqid('email_');
        echo "Sending email '$subject' to " . implode(', ', $to) . "\n";
        return $id;
    }

    public function checkStatus(string $emailId): array {
        return [
            'id' => $emailId,
            'status' => 'delivered',
            'delivered_at' => date('Y-m-d H:i:s')
        ];
    }
}

// Another service that needs adaptation
class SMSProvider {
    private array $config;

    public function __construct(array $config) {
        $this->config = $config;
    }

    public function sendMessage(array $data): int {
        // Simulate sending SMS
        echo "Sending SMS to {$data['phone']}: {$data['text']}\n";
        return random_int(1000, 9999);
    }

    public function getDeliveryInfo(int $messageId): string {
        return "Message $messageId: Delivered";
    }
}

// Adapter for EmailService
class EmailServiceAdapter implements NotificationService {
    private array $sentMessages = [];

    public function __construct(
        private readonly EmailService $emailService
    ) {}

    public function send(string $title, string $message, array $recipients): bool {
        try {
            $messageId = $this->emailService->sendEmail(
                subject: $title,
                to: $recipients,
                body: $message
            );
            
            $this->sentMessages[$messageId] = [
                'title' => $title,
                'recipients' => $recipients
            ];
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function getStatus(string $notificationId): string {
        $status = $this->emailService->checkStatus($notificationId);
        return $status['status'];
    }
}

// Adapter for SMSProvider
class SMSProviderAdapter implements NotificationService {
    private array $messageMap = [];

    public function __construct(
        private readonly SMSProvider $smsProvider
    ) {}

    public function send(string $title, string $message, array $recipients): bool {
        try {
            foreach ($recipients as $phone) {
                $messageId = $this->smsProvider->sendMessage([
                    'phone' => $phone,
                    'text' => "$title: $message"
                ]);
                $this->messageMap[$messageId] = $phone;
            }
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public function getStatus(string $notificationId): string {
        return $this->smsProvider->getDeliveryInfo((int)$notificationId);
    }
}

// Notification manager using adapters
class NotificationManager {
    private array $services = [];

    public function addService(string $name, NotificationService $service): void {
        $this->services[$name] = $service;
    }

    public function notify(
        string $serviceName,
        string $title,
        string $message,
        array $recipients
    ): bool {
        if (!isset($this->services[$serviceName])) {
            throw new InvalidArgumentException("Unknown service: $serviceName");
        }

        return $this->services[$serviceName]->send($title, $message, $recipients);
    }

    public function checkStatus(string $serviceName, string $notificationId): string {
        if (!isset($this->services[$serviceName])) {
            throw new InvalidArgumentException("Unknown service: $serviceName");
        }

        return $this->services[$serviceName]->getStatus($notificationId);
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: When to use Adapter vs Facade?
 * A: Adapter changes interface, Facade simplifies it
 * 
 * Q2: Object vs Class Adapter?
 * A: Object adapter is more flexible, Class adapter enables overriding
 * 
 * Q3: How to handle incompatible data types?
 * A: Convert data in adapter methods, provide reasonable defaults
 * 
 * Q4: Multiple adaptations needed?
 * A: Create separate adapters, consider composition
 */

// Usage example
try {
    // Create notification manager
    $manager = new NotificationManager();

    // Set up email service adapter
    $emailAdapter = new EmailServiceAdapter(new EmailService());
    $manager->addService('email', $emailAdapter);

    // Set up SMS service adapter
    $smsAdapter = new SMSProviderAdapter(
        new SMSProvider(['api_key' => 'secret'])
    );
    $manager->addService('sms', $smsAdapter);

    // Send notifications using different services
    $manager->notify(
        'email',
        'Welcome',
        'Welcome to our service!',
        ['user@example.com']
    );

    $manager->notify(
        'sms',
        'Alert',
        'System update required',
        ['+1234567890']
    );

    // Check status
    echo $manager->checkStatus('email', 'email_123') . "\n";
    echo $manager->checkStatus('sms', '1234') . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

/**
 * Best Practices:
 * 1. Keep adapters focused
 * 2. Document adaptations
 * 3. Handle errors gracefully
 * 4. Use dependency injection
 * 5. Consider testability
 * 6. Maintain ISP principle
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly properties
 * 3. Named arguments
 * 4. Union types
 * 5. Nullsafe operator
 * 6. Match expressions
 */ 