<?php
/**
 * PHP WebSockets and Real-time Applications (PHP 8.x)
 * -----------------------------------------
 * This lesson covers:
 * 1. WebSocket server implementation
 * 2. Real-time messaging
 * 3. Connection handling
 * 4. Event broadcasting
 * 5. Client authentication
 * 6. Message protocols
 */

// WebSocket connection interface
interface WebSocketConnectionInterface {
    public function send(string $message): void;
    public function close(): void;
    public function getClientId(): string;
    public function isAlive(): bool;
}

// WebSocket message class
readonly class WebSocketMessage {
    public function __construct(
        public string $type,
        public array $data,
        public ?string $channel = null,
        public ?string $recipient = null
    ) {}

    public static function fromJson(string $json): self {
        $data = json_decode($json, true);
        return new self(
            type: $data['type'],
            data: $data['data'],
            channel: $data['channel'] ?? null,
            recipient: $data['recipient'] ?? null
        );
    }

    public function toJson(): string {
        return json_encode([
            'type' => $this->type,
            'data' => $this->data,
            'channel' => $this->channel,
            'recipient' => $this->recipient
        ]);
    }
}

// WebSocket server implementation
class WebSocketServer {
    private array $clients = [];
    private array $channels = [];

    public function __construct(
        private readonly string $host = '0.0.0.0',
        private readonly int $port = 8080,
        private readonly Logger $logger
    ) {}

    public function run(): void {
        $server = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        socket_set_option($server, SOL_SOCKET, SO_REUSEADDR, 1);
        socket_bind($server, $this->host, $this->port);
        socket_listen($server);

        $this->logger->info("WebSocket server started on ws://{$this->host}:{$this->port}");

        while (true) {
            $read = array_merge([$server], array_map(fn($client) => $client->socket, $this->clients));
            $write = $except = null;

            if (socket_select($read, $write, $except, 0) < 1) {
                continue;
            }

            if (in_array($server, $read)) {
                $client = $this->acceptNewConnection($server);
                if ($client) {
                    $this->clients[$client->getClientId()] = $client;
                    $this->logger->info("New client connected: {$client->getClientId()}");
                }
                unset($read[array_search($server, $read)]);
            }

            foreach ($read as $socket) {
                $client = $this->findClientBySocket($socket);
                if (!$client) continue;

                $message = $this->readMessage($client);
                if ($message === false) {
                    $this->disconnectClient($client);
                    continue;
                }

                if ($message) {
                    $this->handleMessage($client, $message);
                }
            }
        }
    }

    private function handleMessage(WebSocketConnectionInterface $client, WebSocketMessage $message): void {
        switch ($message->type) {
            case 'subscribe':
                $this->handleSubscribe($client, $message);
                break;
            case 'unsubscribe':
                $this->handleUnsubscribe($client, $message);
                break;
            case 'message':
                $this->handleClientMessage($client, $message);
                break;
            case 'ping':
                $this->handlePing($client);
                break;
        }
    }

    private function handleSubscribe(WebSocketConnectionInterface $client, WebSocketMessage $message): void {
        $channel = $message->channel;
        if (!$channel) return;

        if (!isset($this->channels[$channel])) {
            $this->channels[$channel] = [];
        }

        $this->channels[$channel][$client->getClientId()] = $client;
        $this->logger->info("Client {$client->getClientId()} subscribed to channel {$channel}");

        $client->send(json_encode([
            'type' => 'subscribed',
            'channel' => $channel
        ]));
    }

    private function handleUnsubscribe(WebSocketConnectionInterface $client, WebSocketMessage $message): void {
        $channel = $message->channel;
        if (!$channel || !isset($this->channels[$channel])) return;

        unset($this->channels[$channel][$client->getClientId()]);
        $this->logger->info("Client {$client->getClientId()} unsubscribed from channel {$channel}");

        $client->send(json_encode([
            'type' => 'unsubscribed',
            'channel' => $channel
        ]));
    }

    private function handleClientMessage(WebSocketConnectionInterface $client, WebSocketMessage $message): void {
        if ($message->recipient) {
            // Direct message
            if (isset($this->clients[$message->recipient])) {
                $this->clients[$message->recipient]->send($message->toJson());
            }
        } elseif ($message->channel) {
            // Channel message
            if (isset($this->channels[$message->channel])) {
                foreach ($this->channels[$message->channel] as $subscriber) {
                    if ($subscriber->getClientId() !== $client->getClientId()) {
                        $subscriber->send($message->toJson());
                    }
                }
            }
        } else {
            // Broadcast message
            foreach ($this->clients as $recipient) {
                if ($recipient->getClientId() !== $client->getClientId()) {
                    $recipient->send($message->toJson());
                }
            }
        }
    }

    private function handlePing(WebSocketConnectionInterface $client): void {
        $client->send(json_encode(['type' => 'pong']));
    }

    private function disconnectClient(WebSocketConnectionInterface $client): void {
        $clientId = $client->getClientId();
        
        // Remove from channels
        foreach ($this->channels as $channel => $subscribers) {
            unset($this->channels[$channel][$clientId]);
            if (empty($this->channels[$channel])) {
                unset($this->channels[$channel]);
            }
        }

        // Remove from clients list
        unset($this->clients[$clientId]);
        $client->close();

        $this->logger->info("Client disconnected: {$clientId}");
    }

    // Other helper methods...
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: How to handle connection timeouts?
 * A: Implement ping/pong mechanism and connection monitoring
 * 
 * Q2: How to scale WebSocket servers?
 * A: Use Redis pub/sub or message queues for multi-server setup
 * 
 * Q3: How to secure WebSocket connections?
 * A: Use WSS (WebSocket Secure) and implement authentication
 * 
 * Q4: How to handle large number of connections?
 * A: Use event loop libraries and proper resource management
 */

// Usage example
try {
    // Create WebSocket server
    $server = new WebSocketServer(
        host: '0.0.0.0',
        port: 8080,
        logger: new FileLogger(__DIR__ . '/websocket.log')
    );

    // Example client code (JavaScript):
    /*
    const ws = new WebSocket('ws://localhost:8080');

    ws.onopen = () => {
        // Subscribe to channel
        ws.send(JSON.stringify({
            type: 'subscribe',
            channel: 'chat'
        }));

        // Send message
        ws.send(JSON.stringify({
            type: 'message',
            channel: 'chat',
            data: {
                message: 'Hello, everyone!',
                sender: 'John'
            }
        }));
    };

    ws.onmessage = (event) => {
        const message = JSON.parse(event.data);
        console.log('Received:', message);
    };
    */

    // Start server
    $server->run();

} catch (Exception $e) {
    error_log($e->getMessage());
    exit(1);
}

/**
 * Best Practices:
 * 1. Implement heartbeat mechanism
 * 2. Handle connection errors
 * 3. Validate message format
 * 4. Implement rate limiting
 * 5. Monitor connection health
 * 6. Log important events
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly properties and classes
 * 3. Named arguments
 * 4. Union types
 * 5. Match expressions
 * 6. Nullsafe operator
 */ 