<?php
/**
 * PHP Design Patterns: Decorator (PHP 8.x)
 * ------------------------------
 * This lesson covers:
 * 1. Decorator pattern implementation
 * 2. Dynamic behavior extension
 * 3. Stacking decorators
 * 4. Common use cases
 * 5. Best practices
 * 6. Performance considerations
 */

// Base interface
interface DataSource {
    public function readData(): string;
    public function writeData(string $data): void;
}

// Concrete component
class FileDataSource implements DataSource {
    public function __construct(
        private readonly string $filename
    ) {}

    public function readData(): string {
        return file_get_contents($this->filename);
    }

    public function writeData(string $data): void {
        file_put_contents($this->filename, $data);
    }
}

// Base decorator
abstract class DataSourceDecorator implements DataSource {
    public function __construct(
        protected readonly DataSource $source
    ) {}

    public function readData(): string {
        return $this->source->readData();
    }

    public function writeData(string $data): void {
        $this->source->writeData($data);
    }
}

// Encryption decorator
class EncryptionDecorator extends DataSourceDecorator {
    public function __construct(
        DataSource $source,
        private readonly string $key
    ) {
        parent::__construct($source);
    }

    public function readData(): string {
        $encryptedData = parent::readData();
        return $this->decrypt($encryptedData);
    }

    public function writeData(string $data): void {
        $encryptedData = $this->encrypt($data);
        parent::writeData($encryptedData);
    }

    private function encrypt(string $data): string {
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt(
            $data,
            'AES-256-CBC',
            $this->key,
            0,
            $iv
        );
        return base64_encode($iv . $encrypted);
    }

    private function decrypt(string $data): string {
        $data = base64_decode($data);
        $iv = substr($data, 0, 16);
        $encrypted = substr($data, 16);
        return openssl_decrypt(
            $encrypted,
            'AES-256-CBC',
            $this->key,
            0,
            $iv
        );
    }
}

// Compression decorator
class CompressionDecorator extends DataSourceDecorator {
    public function readData(): string {
        $compressed = parent::readData();
        return gzuncompress($compressed);
    }

    public function writeData(string $data): void {
        $compressed = gzcompress($data);
        parent::writeData($compressed);
    }
}

// Caching decorator
class CachingDecorator extends DataSourceDecorator {
    private ?string $cache = null;

    public function readData(): string {
        if ($this->cache === null) {
            $this->cache = parent::readData();
        }
        return $this->cache;
    }

    public function writeData(string $data): void {
        parent::writeData($data);
        $this->cache = $data;
    }
}

// Logging decorator
class LoggingDecorator extends DataSourceDecorator {
    public function __construct(
        DataSource $source,
        private readonly Logger $logger
    ) {
        parent::__construct($source);
    }

    public function readData(): string {
        $this->logger->info("Reading data");
        $result = parent::readData();
        $this->logger->info("Read " . strlen($result) . " bytes");
        return $result;
    }

    public function writeData(string $data): void {
        $this->logger->info("Writing " . strlen($data) . " bytes");
        parent::writeData($data);
        $this->logger->info("Write completed");
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: When to use Decorator vs Inheritance?
 * A: Use Decorator for dynamic behavior extension, inheritance for static
 * 
 * Q2: How to handle decorator order?
 * A: Consider dependencies between decorators (e.g., compress before encrypt)
 * 
 * Q3: What about performance impact?
 * A: Each decorator adds overhead, use only when necessary
 * 
 * Q4: How to maintain decorator state?
 * A: Use instance variables but be careful with memory usage
 */

// Usage example
try {
    // Create base component
    $source = new FileDataSource('data.txt');

    // Stack decorators
    $encrypted = new EncryptionDecorator($source, 'secret-key');
    $compressed = new CompressionDecorator($encrypted);
    $cached = new CachingDecorator($compressed);
    $logged = new LoggingDecorator($cached, new FileLogger('app.log'));

    // Use decorated object
    $logged->writeData("Hello, World!");
    $data = $logged->readData();
    echo $data . "\n";

    // Example with selective decorators
    $simpleSource = new FileDataSource('simple.txt');
    $withCompression = new CompressionDecorator($simpleSource);
    $withCache = new CachingDecorator($withCompression);

    $withCache->writeData("Simple data");
    echo $withCache->readData() . "\n";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

/**
 * Best Practices:
 * 1. Keep decorators focused
 * 2. Consider decorator order
 * 3. Use dependency injection
 * 4. Handle errors properly
 * 5. Document decorator effects
 * 6. Monitor performance impact
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly properties
 * 3. Named arguments
 * 4. Union types
 * 5. Nullsafe operator
 * 6. Match expressions
 */ 