<?php
/**
 * PHP Design Patterns: Proxy (PHP 8.x)
 * ------------------------------
 * This lesson covers:
 * 1. Virtual Proxy (lazy loading)
 * 2. Protection Proxy (access control)
 * 3. Remote Proxy (service communication)
 * 4. Cache Proxy (performance)
 * 5. Logging Proxy (monitoring)
 * 6. Best practices
 */

// Subject interface
interface Image {
    public function display(): void;
    public function getFilename(): string;
    public function getDimensions(): array;
}

// Real subject
class RealImage implements Image {
    private int $width;
    private int $height;

    public function __construct(
        private readonly string $filename
    ) {
        // Simulate expensive image loading
        echo "Loading image: {$filename}\n";
        [$this->width, $this->height] = getimagesize($filename);
    }

    public function display(): void {
        echo "Displaying image: {$this->filename}\n";
    }

    public function getFilename(): string {
        return $this->filename;
    }

    public function getDimensions(): array {
        return ['width' => $this->width, 'height' => $this->height];
    }
}

// Virtual proxy (lazy loading)
class LazyImage implements Image {
    private ?RealImage $realImage = null;

    public function __construct(
        private readonly string $filename
    ) {}

    public function display(): void {
        $this->realImage ??= new RealImage($this->filename);
        $this->realImage->display();
    }

    public function getFilename(): string {
        return $this->filename;
    }

    public function getDimensions(): array {
        $this->realImage ??= new RealImage($this->filename);
        return $this->realImage->getDimensions();
    }
}

// Protection proxy
class ProtectedImage implements Image {
    public function __construct(
        private readonly Image $image,
        private readonly AuthService $auth
    ) {}

    public function display(): void {
        if (!$this->auth->hasAccess('image.view')) {
            throw new AccessDeniedException('No permission to view image');
        }
        $this->image->display();
    }

    public function getFilename(): string {
        return $this->image->getFilename();
    }

    public function getDimensions(): array {
        if (!$this->auth->hasAccess('image.metadata')) {
            throw new AccessDeniedException('No permission to view image metadata');
        }
        return $this->image->getDimensions();
    }
}

// Cache proxy
class CachedImage implements Image {
    private array $dimensionsCache;
    private static array $globalCache = [];

    public function __construct(
        private readonly Image $image
    ) {}

    public function display(): void {
        $this->image->display();
    }

    public function getFilename(): string {
        return $this->image->getFilename();
    }

    public function getDimensions(): array {
        $filename = $this->getFilename();
        if (!isset(self::$globalCache[$filename])) {
            self::$globalCache[$filename] = $this->image->getDimensions();
        }
        return self::$globalCache[$filename];
    }
}

// Remote proxy
class RemoteImage implements Image {
    private ?array $metadata = null;

    public function __construct(
        private readonly string $url,
        private readonly HttpClient $client
    ) {}

    public function display(): void {
        $response = $this->client->get($this->url);
        echo "Displaying remote image from: {$this->url}\n";
        echo $response->getBody() . "\n";
    }

    public function getFilename(): string {
        return basename($this->url);
    }

    public function getDimensions(): array {
        if ($this->metadata === null) {
            $response = $this->client->head($this->url);
            $this->metadata = json_decode($response->getHeader('X-Image-Metadata'), true);
        }
        return $this->metadata;
    }
}

// Logging proxy
class LoggedImage implements Image {
    public function __construct(
        private readonly Image $image,
        private readonly Logger $logger
    ) {}

    public function display(): void {
        $this->logger->info("Displaying image: " . $this->image->getFilename());
        $start = microtime(true);
        
        try {
            $this->image->display();
            $duration = microtime(true) - $start;
            $this->logger->info("Image displayed in {$duration}s");
        } catch (Exception $e) {
            $this->logger->error("Failed to display image", [
                'error' => $e->getMessage(),
                'file' => $this->image->getFilename()
            ]);
            throw $e;
        }
    }

    public function getFilename(): string {
        return $this->image->getFilename();
    }

    public function getDimensions(): array {
        $this->logger->debug("Getting dimensions for: " . $this->image->getFilename());
        return $this->image->getDimensions();
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: When to use Proxy vs Decorator?
 * A: Proxy controls access, Decorator adds behavior
 * 
 * Q2: How to handle proxy chains?
 * A: Keep chains short, consider performance impact
 * 
 * Q3: What about circular references?
 * A: Avoid them, use dependency injection
 * 
 * Q4: Remote proxy error handling?
 * A: Implement proper retry and timeout mechanisms
 */

// Usage example
try {
    // Lazy loading example
    $image = new LazyImage('large-image.jpg');
    echo "Image created but not loaded\n";
    $image->display(); // Now image is loaded

    // Protection proxy
    $protectedImage = new ProtectedImage(
        new RealImage('confidential.jpg'),
        new AuthService()
    );
    $protectedImage->display(); // Checks permissions first

    // Cache proxy
    $cachedImage = new CachedImage(
        new RealImage('frequently-accessed.jpg')
    );
    $dimensions = $cachedImage->getDimensions(); // Cached after first call

    // Remote proxy
    $remoteImage = new RemoteImage(
        'https://example.com/image.jpg',
        new HttpClient()
    );
    $remoteImage->display(); // Fetches from remote server

    // Logging proxy
    $loggedImage = new LoggedImage(
        new RealImage('important.jpg'),
        new FileLogger('image-access.log')
    );
    $loggedImage->display(); // Logs access and timing

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

/**
 * Best Practices:
 * 1. Use appropriate proxy type
 * 2. Handle errors properly
 * 3. Consider caching strategy
 * 4. Monitor performance
 * 5. Implement security checks
 * 6. Log important operations
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly properties
 * 3. Named arguments
 * 4. Union types
 * 5. Nullsafe operator
 * 6. Match expressions
 */ 