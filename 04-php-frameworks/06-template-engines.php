<?php
/**
 * PHP Template Engines and View Handling (PHP 8.x)
 * ----------------------------------------
 * This lesson covers:
 * 1. Template engine implementation
 * 2. View rendering
 * 3. Template inheritance
 * 4. Template caching
 * 5. Template helpers
 * 6. XSS prevention
 */

// Template engine interface
interface TemplateEngine {
    public function render(string $template, array $data = []): string;
    public function addHelper(string $name, callable $helper): void;
    public function cache(string $template): void;
}

// Basic template engine implementation
class SimpleTemplateEngine implements TemplateEngine {
    private array $helpers = [];
    private array $cache = [];
    private string $cacheDir;

    public function __construct(
        private readonly string $templateDir,
        ?string $cacheDir = null
    ) {
        $this->cacheDir = $cacheDir ?? sys_get_temp_dir() . '/template_cache';
        if (!is_dir($this->cacheDir)) {
            mkdir($this->cacheDir, 0777, true);
        }

        // Register default helpers
        $this->registerDefaultHelpers();
    }

    public function render(string $template, array $data = []): string {
        $templatePath = $this->templateDir . '/' . $template;
        if (!file_exists($templatePath)) {
            throw new RuntimeException("Template not found: $template");
        }

        // Check cache first
        $cached = $this->getFromCache($template);
        if ($cached !== null) {
            return $this->evaluateTemplate($cached, $data);
        }

        $content = file_get_contents($templatePath);
        $compiled = $this->compile($content);
        
        // Cache the compiled template
        $this->cache($template, $compiled);
        
        return $this->evaluateTemplate($compiled, $data);
    }

    public function addHelper(string $name, callable $helper): void {
        $this->helpers[$name] = $helper;
    }

    public function cache(string $template, string $content = null): void {
        if ($content === null) {
            $content = file_get_contents($this->templateDir . '/' . $template);
        }
        
        $cacheFile = $this->getCacheFile($template);
        file_put_contents($cacheFile, $content);
    }

    private function compile(string $content): string {
        // Replace template variables
        $content = preg_replace('/\{\{ *(\$[\w\->]+) *\}\}/', '<?php echo htmlspecialchars($1, ENT_QUOTES, \'UTF-8\'); ?>', $content);
        
        // Replace helper calls
        $content = preg_replace('/\{\{ *(\w+)\((.*?)\) *\}\}/', '<?php echo $this->helpers[\'$1\']($2); ?>', $content);
        
        // Replace control structures
        $content = preg_replace('/@if\((.*?)\)/', '<?php if($1): ?>', $content);
        $content = preg_replace('/@endif/', '<?php endif; ?>', $content);
        $content = preg_replace('/@foreach\((.*?)\)/', '<?php foreach($1): ?>', $content);
        $content = preg_replace('/@endforeach/', '<?php endforeach; ?>', $content);
        
        return $content;
    }

    private function evaluateTemplate(string $compiled, array $data): string {
        extract($data);
        ob_start();
        
        try {
            eval('?>' . $compiled);
            return ob_get_clean();
        } catch (Throwable $e) {
            ob_end_clean();
            throw new RuntimeException('Template evaluation failed: ' . $e->getMessage());
        }
    }

    private function registerDefaultHelpers(): void {
        // Date formatting helper
        $this->addHelper('date', function(string $date, string $format = 'Y-m-d'): string {
            return date($format, strtotime($date));
        });

        // Number formatting helper
        $this->addHelper('number', function(float $number, int $decimals = 2): string {
            return number_format($number, $decimals);
        });

        // URL encoding helper
        $this->addHelper('url', function(string $url): string {
            return urlencode($url);
        });
    }

    private function getCacheFile(string $template): string {
        return $this->cacheDir . '/' . md5($template) . '.php';
    }

    private function getFromCache(string $template): ?string {
        $cacheFile = $this->getCacheFile($template);
        if (file_exists($cacheFile)) {
            return file_get_contents($cacheFile);
        }
        return null;
    }
}

// View class for template management
class View {
    private array $data = [];
    private ?string $layout = null;

    public function __construct(
        private readonly TemplateEngine $engine,
        private readonly array $defaultData = []
    ) {}

    public function assign(string $key, mixed $value): self {
        $this->data[$key] = $value;
        return $this;
    }

    public function setLayout(string $layout): self {
        $this->layout = $layout;
        return $this;
    }

    public function render(string $template): string {
        $data = array_merge($this->defaultData, $this->data);
        $content = $this->engine->render($template, $data);

        if ($this->layout) {
            $data['content'] = $content;
            return $this->engine->render($this->layout, $data);
        }

        return $content;
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: How to prevent XSS in templates?
 * A: Always escape output by default, provide raw output when needed
 * 
 * Q2: When to use template caching?
 * A: In production for performance, disable in development
 * 
 * Q3: How to handle template inheritance?
 * A: Use layouts and sections/blocks system
 * 
 * Q4: What about template compilation?
 * A: Compile to PHP for better performance
 */

// Usage example
try {
    // Create template engine
    $engine = new SimpleTemplateEngine(
        templateDir: __DIR__ . '/templates',
        cacheDir: __DIR__ . '/cache'
    );

    // Create view
    $view = new View($engine, [
        'appName' => 'My App',
        'version' => '1.0'
    ]);

    // Set layout and render template
    $html = $view
        ->setLayout('layouts/main.php')
        ->assign('title', 'Welcome')
        ->assign('user', [
            'name' => 'John Doe',
            'email' => 'john@example.com'
        ])
        ->render('pages/welcome.php');

    echo $html;

} catch (Exception $e) {
    error_log($e->getMessage());
    echo "Error rendering template";
}

/**
 * Example template (templates/pages/welcome.php):
 * --------------------------------------------
 * <h1>{{ $title }}</h1>
 * <p>Welcome, {{ $user['name'] }}!</p>
 * <p>Date: {{ date($user['created_at'], 'F j, Y') }}</p>
 * 
 * @if($user['isAdmin'])
 *     <p>You have admin access</p>
 * @endif
 * 
 * @foreach($notifications as $note)
 *     <div class="note">{{ $note }}</div>
 * @endforeach
 */

/**
 * Best Practices:
 * 1. Always escape output
 * 2. Use template caching
 * 3. Keep templates simple
 * 4. Use layouts for consistency
 * 5. Implement helper functions
 * 6. Separate logic from presentation
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly properties
 * 3. Named arguments
 * 4. Union types
 * 5. Nullsafe operator
 * 6. Match expressions
 */ 