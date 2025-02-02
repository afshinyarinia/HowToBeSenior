<?php
/**
 * PHP Design Patterns: Prototype (PHP 8.x)
 * ------------------------------
 * This lesson covers:
 * 1. Prototype pattern implementation
 * 2. Deep vs shallow cloning
 * 3. Clone customization
 * 4. Prototype registry
 * 5. Best practices
 * 6. Common use cases
 */

// Prototype interface
interface Prototype {
    public function clone(): self;
}

// Concrete prototype
class Page implements Prototype {
    private array $metadata = [];
    private array $components = [];

    public function __construct(
        private string $title,
        private string $url,
        private ?DateTime $lastModified = null
    ) {
        $this->lastModified = $lastModified ?? new DateTime();
    }

    public function addMetadata(string $key, string $value): void {
        $this->metadata[$key] = $value;
    }

    public function addComponent(PageComponent $component): void {
        $this->components[] = $component;
    }

    public function clone(): self {
        $clone = new self(
            $this->title . " (Copy)",
            $this->url,
            new DateTime()
        );

        // Deep clone metadata
        $clone->metadata = $this->metadata;

        // Deep clone components
        foreach ($this->components as $component) {
            $clone->addComponent($component->clone());
        }

        return $clone;
    }

    public function getInfo(): array {
        return [
            'title' => $this->title,
            'url' => $this->url,
            'lastModified' => $this->lastModified->format('Y-m-d H:i:s'),
            'metadata' => $this->metadata,
            'components' => array_map(
                fn($component) => $component->getInfo(),
                $this->components
            )
        ];
    }
}

// Component prototype
class PageComponent implements Prototype {
    public function __construct(
        private readonly string $type,
        private readonly array $properties = []
    ) {}

    public function clone(): self {
        return new self(
            $this->type,
            $this->properties
        );
    }

    public function getInfo(): array {
        return [
            'type' => $this->type,
            'properties' => $this->properties
        ];
    }
}

// Prototype registry
class PrototypeRegistry {
    private array $prototypes = [];

    public function addPrototype(string $key, Prototype $prototype): void {
        $this->prototypes[$key] = $prototype;
    }

    public function getPrototype(string $key): ?Prototype {
        return $this->prototypes[$key] ?? null;
    }

    public function createFromPrototype(string $key): ?Prototype {
        $prototype = $this->getPrototype($key);
        return $prototype?->clone();
    }
}

// Example usage with template system
class PageTemplate implements Prototype {
    private array $sections = [];
    private array $styles = [];

    public function __construct(
        private readonly string $name,
        private readonly string $layout
    ) {}

    public function addSection(string $name, string $content): void {
        $this->sections[$name] = $content;
    }

    public function addStyle(string $selector, array $properties): void {
        $this->styles[$selector] = $properties;
    }

    public function clone(): self {
        $clone = new self($this->name . " (Copy)", $this->layout);
        
        // Deep clone sections and styles
        $clone->sections = $this->sections;
        $clone->styles = $this->styles;
        
        return $clone;
    }

    public function render(): string {
        // Simplified rendering
        $output = "Template: {$this->name}\n";
        $output .= "Layout: {$this->layout}\n\n";
        
        foreach ($this->sections as $name => $content) {
            $output .= "[$name]\n$content\n\n";
        }
        
        return $output;
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: When to use Prototype over Factory?
 * A: When object creation is expensive or complex
 * 
 * Q2: How to handle deep cloning?
 * A: Implement __clone() or custom clone() method
 * 
 * Q3: What about circular references?
 * A: Use a registry to track cloned objects
 * 
 * Q4: Performance considerations?
 * A: Clone only what's necessary, use shallow copy when possible
 */

// Usage example
try {
    // Create prototype registry
    $registry = new PrototypeRegistry();

    // Create base page template
    $blogTemplate = new PageTemplate('Blog Post', 'two-column');
    $blogTemplate->addSection('header', '<h1>{{title}}</h1>');
    $blogTemplate->addSection('content', '<article>{{content}}</article>');
    $blogTemplate->addSection('sidebar', '<aside>{{sidebar}}</aside>');
    $blogTemplate->addStyle('article', ['margin' => '20px', 'padding' => '10px']);

    // Register template
    $registry->addPrototype('blog', $blogTemplate);

    // Create page from template
    $page = new Page('My Blog Post', '/blog/post-1');
    $page->addMetadata('description', 'A sample blog post');
    $page->addComponent(new PageComponent('header', [
        'title' => 'Welcome to my blog'
    ]));

    // Clone page
    $clonedPage = $page->clone();
    
    // Create new template from prototype
    $newTemplate = $registry->createFromPrototype('blog');
    
    // Display results
    echo "Original Page:\n";
    print_r($page->getInfo());
    
    echo "\nCloned Page:\n";
    print_r($clonedPage->getInfo());
    
    if ($newTemplate instanceof PageTemplate) {
        echo "\nNew Template:\n";
        echo $newTemplate->render();
    }

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

/**
 * Best Practices:
 * 1. Implement deep cloning
 * 2. Use prototype registry
 * 3. Handle circular references
 * 4. Document cloning behavior
 * 5. Consider performance
 * 6. Test clone results
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly properties
 * 3. Named arguments
 * 4. Union types
 * 5. Nullsafe operator
 * 6. Match expressions
 */ 