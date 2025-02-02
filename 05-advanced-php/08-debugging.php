<?php
/**
 * PHP Advanced Debugging Techniques (PHP 8.x)
 * ---------------------------------
 * This lesson covers:
 * 1. Advanced debugging tools
 * 2. Stack trace analysis
 * 3. Memory debugging
 * 4. Performance profiling
 * 5. Remote debugging
 * 6. Debug visualization
 */

// Debug context manager
class DebugContext {
    private array $traces = [];
    private array $variables = [];
    private array $queries = [];
    private float $startTime;
    private float $startMemory;

    public function __construct() {
        $this->startTime = microtime(true);
        $this->startMemory = memory_get_usage(true);
    }

    public function captureTrace(): void {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
        array_shift($trace); // Remove current method
        $this->traces[] = [
            'trace' => $trace,
            'time' => microtime(true) - $this->startTime,
            'memory' => memory_get_usage(true) - $this->startMemory
        ];
    }

    public function watchVariable(string $name, mixed $value): void {
        $this->variables[$name][] = [
            'value' => $value,
            'type' => gettype($value),
            'time' => microtime(true) - $this->startTime,
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS)
        ];
    }

    public function logQuery(string $sql, array $params = [], ?float $duration = null): void {
        $this->queries[] = [
            'sql' => $sql,
            'params' => $params,
            'duration' => $duration ?? 0,
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS),
            'time' => microtime(true) - $this->startTime
        ];
    }

    public function getDebugData(): array {
        return [
            'execution_time' => microtime(true) - $this->startTime,
            'memory_usage' => memory_get_usage(true) - $this->startMemory,
            'traces' => $this->traces,
            'variables' => $this->variables,
            'queries' => $this->queries
        ];
    }
}

// Debug visualizer
class DebugVisualizer {
    public function renderHtml(array $debugData): string {
        $html = '<div class="debug-panel">';
        $html .= $this->renderSummary($debugData);
        $html .= $this->renderTraces($debugData['traces']);
        $html .= $this->renderVariables($debugData['variables']);
        $html .= $this->renderQueries($debugData['queries']);
        $html .= '</div>';

        return $html;
    }

    private function renderSummary(array $debugData): string {
        return sprintf(
            '<div class="debug-summary">
                <h3>Debug Summary</h3>
                <p>Execution Time: %.4f seconds</p>
                <p>Memory Usage: %s</p>
                <p>Query Count: %d</p>
            </div>',
            $debugData['execution_time'],
            $this->formatBytes($debugData['memory_usage']),
            count($debugData['queries'])
        );
    }

    private function renderTraces(array $traces): string {
        $html = '<div class="debug-traces"><h3>Stack Traces</h3>';
        
        foreach ($traces as $trace) {
            $html .= '<div class="trace">';
            foreach ($trace['trace'] as $level) {
                $html .= sprintf(
                    '<div class="trace-line">%s:%d - %s::%s</div>',
                    $level['file'] ?? 'unknown',
                    $level['line'] ?? 0,
                    $level['class'] ?? '',
                    $level['function'] ?? ''
                );
            }
            $html .= '</div>';
        }

        return $html . '</div>';
    }

    private function renderVariables(array $variables): string {
        $html = '<div class="debug-variables"><h3>Watched Variables</h3>';
        
        foreach ($variables as $name => $history) {
            $html .= sprintf('<div class="variable"><h4>%s</h4>', htmlspecialchars($name));
            foreach ($history as $entry) {
                $html .= sprintf(
                    '<div class="value">%s (%s) at %.4fs</div>',
                    htmlspecialchars(print_r($entry['value'], true)),
                    $entry['type'],
                    $entry['time']
                );
            }
            $html .= '</div>';
        }

        return $html . '</div>';
    }

    private function renderQueries(array $queries): string {
        $html = '<div class="debug-queries"><h3>SQL Queries</h3>';
        
        foreach ($queries as $query) {
            $html .= sprintf(
                '<div class="query">
                    <div class="sql">%s</div>
                    <div class="params">%s</div>
                    <div class="duration">%.4f ms</div>
                </div>',
                htmlspecialchars($query['sql']),
                htmlspecialchars(print_r($query['params'], true)),
                $query['duration'] * 1000
            );
        }

        return $html . '</div>';
    }

    private function formatBytes(int $bytes): string {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        return round($bytes / (1024 ** $pow), 2) . ' ' . $units[$pow];
    }
}

// Remote debugger
class RemoteDebugger {
    private string $host;
    private int $port;
    private $socket;

    public function __construct(string $host = 'localhost', int $port = 9000) {
        $this->host = $host;
        $this->port = $port;
    }

    public function connect(): void {
        $this->socket = fsockopen($this->host, $this->port);
        if (!$this->socket) {
            throw new RuntimeException('Failed to connect to debug server');
        }
        stream_set_blocking($this->socket, true);
    }

    public function sendCommand(string $command, array $data = []): mixed {
        $payload = json_encode([
            'command' => $command,
            'data' => $data
        ]);

        fwrite($this->socket, $payload . "\n");
        $response = fgets($this->socket);
        
        return json_decode($response, true);
    }

    public function disconnect(): void {
        if ($this->socket) {
            fclose($this->socket);
        }
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: How to debug memory leaks?
 * A: Use memory tracking and heap analysis tools
 * 
 * Q2: What about production debugging?
 * A: Use logging and monitoring instead of interactive debugging
 * 
 * Q3: How to handle async debugging?
 * A: Use specialized tools and proper logging
 * 
 * Q4: What about performance impact?
 * A: Enable debugging only when needed, use sampling
 */

// Usage example
try {
    // Initialize debug context
    $debug = new DebugContext();
    
    // Example code with debugging
    function complexOperation(array $data) {
        global $debug;
        $debug->captureTrace();
        
        $result = array_map(function($item) use ($debug) {
            $debug->watchVariable('item', $item);
            return $item * 2;
        }, $data);
        
        $debug->logQuery(
            "SELECT * FROM items WHERE value IN (?)",
            [$result],
            0.023
        );
        
        return $result;
    }

    // Run code with debugging
    $data = [1, 2, 3, 4, 5];
    $result = complexOperation($data);

    // Visualize debug data
    $visualizer = new DebugVisualizer();
    echo $visualizer->renderHtml($debug->getDebugData());

    // Remote debugging example
    $remote = new RemoteDebugger();
    $remote->connect();
    $response = $remote->sendCommand('get_breakpoints');
    $remote->disconnect();

} catch (Exception $e) {
    echo "Debug Error: " . $e->getMessage() . "\n";
}

/**
 * Best Practices:
 * 1. Use proper error levels
 * 2. Implement structured logging
 * 3. Monitor performance impact
 * 4. Secure debug information
 * 5. Clean up debug data
 * 6. Document debugging tools
 * 
 * New PHP 8.x Features Used:
 * 1. Named arguments
 * 2. Constructor property promotion
 * 3. Match expressions
 * 4. Union types
 * 5. Nullsafe operator
 * 6. Mixed type
 */ 