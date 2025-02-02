<?php
/**
 * PHP Advanced Concurrency (PHP 8.x)
 * -------------------------
 * This lesson covers:
 * 1. Process management
 * 2. Parallel processing
 * 3. Thread simulation
 * 4. Message queues
 * 5. Shared memory
 * 6. Process synchronization
 */

// Process manager class
class ProcessManager {
    private array $processes = [];
    private array $callbacks = [];
    private int $maxProcesses;

    public function __construct(
        int $maxProcesses = 5,
        private readonly Logger $logger
    ) {
        $this->maxProcesses = $maxProcesses;
    }

    public function fork(callable $callback): int {
        if (count($this->processes) >= $this->maxProcesses) {
            $this->wait();
        }

        $pid = pcntl_fork();

        if ($pid == -1) {
            throw new RuntimeException('Failed to fork process');
        }

        if ($pid) {
            // Parent process
            $this->processes[$pid] = time();
            $this->callbacks[$pid] = $callback;
            return $pid;
        } else {
            // Child process
            try {
                $result = $callback();
                exit(0);
            } catch (Throwable $e) {
                $this->logger->error('Process failed', [
                    'error' => $e->getMessage(),
                    'pid' => getmypid()
                ]);
                exit(1);
            }
        }
    }

    public function wait(): void {
        while (!empty($this->processes)) {
            $pid = pcntl_wait($status);
            if ($pid > 0) {
                $this->handleProcessCompletion($pid, $status);
            }
        }
    }

    private function handleProcessCompletion(int $pid, int $status): void {
        if (pcntl_wifexited($status)) {
            $exitStatus = pcntl_wexitstatus($status);
            if ($exitStatus !== 0) {
                $this->logger->warning('Process exited with error', [
                    'pid' => $pid,
                    'status' => $exitStatus
                ]);
            }
        }

        unset($this->processes[$pid], $this->callbacks[$pid]);
    }
}

// Shared memory manager
class SharedMemoryManager {
    private int $shmId;
    private int $semId;

    public function __construct(
        private readonly string $key,
        private readonly int $size = 1024
    ) {
        $this->initialize();
    }

    public function __destruct() {
        $this->cleanup();
    }

    public function write(string $data): void {
        $this->acquireLock();
        try {
            if (!shm_put_var($this->shmId, 1, $data)) {
                throw new RuntimeException('Failed to write to shared memory');
            }
        } finally {
            $this->releaseLock();
        }
    }

    public function read(): ?string {
        $this->acquireLock();
        try {
            return shm_get_var($this->shmId, 1);
        } catch (Throwable $e) {
            return null;
        } finally {
            $this->releaseLock();
        }
    }

    private function initialize(): void {
        // Create shared memory segment
        $this->shmId = shm_attach(ftok($this->key, 'a'), $this->size);
        if ($this->shmId === false) {
            throw new RuntimeException('Failed to create shared memory segment');
        }

        // Create semaphore
        $this->semId = sem_get(ftok($this->key, 'b'));
        if ($this->semId === false) {
            throw new RuntimeException('Failed to create semaphore');
        }
    }

    private function cleanup(): void {
        if (isset($this->shmId)) {
            shm_remove($this->shmId);
            shm_detach($this->shmId);
        }
    }

    private function acquireLock(): void {
        if (!sem_acquire($this->semId)) {
            throw new RuntimeException('Failed to acquire lock');
        }
    }

    private function releaseLock(): void {
        sem_release($this->semId);
    }
}

// Parallel task executor
class ParallelTaskExecutor {
    private ProcessManager $processManager;
    private SharedMemoryManager $sharedMemory;

    public function __construct(
        private readonly Logger $logger,
        int $maxProcesses = 5
    ) {
        $this->processManager = new ProcessManager($maxProcesses, $logger);
        $this->sharedMemory = new SharedMemoryManager(__FILE__);
    }

    public function executeInParallel(array $tasks): array {
        $results = [];
        
        foreach ($tasks as $id => $task) {
            $this->processManager->fork(function() use ($id, $task) {
                $result = $task();
                $this->sharedMemory->write(json_encode([
                    'id' => $id,
                    'result' => $result
                ]));
                return true;
            });
        }

        $this->processManager->wait();
        
        // Collect results
        while ($data = $this->sharedMemory->read()) {
            $decoded = json_decode($data, true);
            $results[$decoded['id']] = $decoded['result'];
        }

        return $results;
    }
}

// Message queue wrapper
class MessageQueue {
    private int $queue;

    public function __construct(
        private readonly string $key,
        private readonly int $type = 1
    ) {
        $this->queue = msg_get_queue(ftok($key, 'q'));
        if ($this->queue === false) {
            throw new RuntimeException('Failed to create message queue');
        }
    }

    public function send(mixed $message): void {
        if (!msg_send($this->queue, $this->type, $message)) {
            throw new RuntimeException('Failed to send message');
        }
    }

    public function receive(int &$messageType = null): mixed {
        $success = msg_receive(
            $this->queue,
            0,
            $messageType,
            1024,
            $message,
            true,
            MSG_IPC_NOWAIT,
            $error
        );

        if (!$success) {
            if ($error !== MSG_ENOMSG) {
                throw new RuntimeException('Failed to receive message');
            }
            return null;
        }

        return $message;
    }

    public function remove(): void {
        msg_remove_queue($this->queue);
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: When to use processes vs threads?
 * A: PHP uses processes, threads via extensions
 * 
 * Q2: How to handle shared state?
 * A: Use shared memory or message queues
 * 
 * Q3: What about race conditions?
 * A: Use semaphores and proper synchronization
 * 
 * Q4: How to handle process failures?
 * A: Implement proper error handling and logging
 */

// Usage example
try {
    $logger = new FileLogger(__DIR__ . '/parallel.log');

    // Parallel task execution
    $executor = new ParallelTaskExecutor($logger);
    $results = $executor->executeInParallel([
        'task1' => fn() => heavy_computation(1),
        'task2' => fn() => heavy_computation(2),
        'task3' => fn() => heavy_computation(3)
    ]);

    // Message queue example
    $queue = new MessageQueue(__FILE__);
    
    // Producer
    $queue->send(['type' => 'job', 'data' => 'process this']);
    
    // Consumer
    while ($message = $queue->receive($type)) {
        // Process message
        echo "Received message of type: $type\n";
        print_r($message);
    }

    // Cleanup
    $queue->remove();

} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

function heavy_computation(int $n): int {
    sleep(1); // Simulate heavy work
    return $n * $n;
}

/**
 * Best Practices:
 * 1. Handle process failures
 * 2. Use proper synchronization
 * 3. Clean up resources
 * 4. Monitor process status
 * 5. Implement timeouts
 * 6. Log important events
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly properties
 * 3. Named arguments
 * 4. Union types
 * 5. Match expressions
 * 6. Nullsafe operator
 */ 