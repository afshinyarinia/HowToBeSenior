<?php
/**
 * PHP Queue Workers and Background Jobs (PHP 8.x)
 * --------------------------------------
 * This lesson covers:
 * 1. Job queue implementation
 * 2. Worker processes
 * 3. Job scheduling
 * 4. Failed job handling
 * 5. Queue monitoring
 * 6. Distributed queues
 */

// Job interface
interface JobInterface {
    public function handle(): void;
    public function failed(Throwable $e): void;
    public function getAttempts(): int;
    public function incrementAttempts(): void;
    public function getMaxAttempts(): int;
}

// Abstract job class
abstract class AbstractJob implements JobInterface {
    protected int $attempts = 0;
    protected int $maxAttempts = 3;

    public function getAttempts(): int {
        return $this->attempts;
    }

    public function incrementAttempts(): void {
        $this->attempts++;
    }

    public function getMaxAttempts(): int {
        return $this->maxAttempts;
    }

    public function failed(Throwable $e): void {
        // Default failure handling
        error_log("Job failed: " . $e->getMessage());
    }
}

// Example email job
class SendEmailJob extends AbstractJob {
    public function __construct(
        private readonly string $to,
        private readonly string $subject,
        private readonly string $body,
        private readonly Mailer $mailer,
        private readonly Logger $logger
    ) {}

    public function handle(): void {
        $this->logger->info("Sending email", [
            'to' => $this->to,
            'subject' => $this->subject
        ]);

        $this->mailer->send(
            to: $this->to,
            subject: $this->subject,
            body: $this->body
        );
    }
}

// Queue interface
interface QueueInterface {
    public function push(JobInterface $job, ?string $queue = null): string;
    public function pop(?string $queue = null): ?JobInterface;
    public function delete(string $id): void;
    public function release(string $id, int $delay = 0): void;
    public function failed(string $id, Throwable $e): void;
}

// Redis queue implementation
class RedisQueue implements QueueInterface {
    private Redis $redis;

    public function __construct(
        private readonly string $host = 'localhost',
        private readonly int $port = 6379,
        private readonly Logger $logger
    ) {
        $this->redis = new Redis();
        $this->connect();
    }

    public function push(JobInterface $job, ?string $queue = null): string {
        $queue = $queue ?? 'default';
        $id = uniqid('job_', true);

        $this->redis->hSet(
            "job:{$id}",
            [
                'class' => get_class($job),
                'data' => serialize($job),
                'attempts' => 0,
                'created_at' => time()
            ]
        );

        $this->redis->rPush("queue:{$queue}", $id);
        
        $this->logger->info("Job pushed to queue", [
            'id' => $id,
            'queue' => $queue,
            'class' => get_class($job)
        ]);

        return $id;
    }

    public function pop(?string $queue = null): ?JobInterface {
        $queue = $queue ?? 'default';
        $id = $this->redis->lPop("queue:{$queue}");

        if (!$id) {
            return null;
        }

        $jobData = $this->redis->hGetAll("job:{$id}");
        if (!$jobData) {
            return null;
        }

        return unserialize($jobData['data']);
    }

    public function delete(string $id): void {
        $this->redis->del("job:{$id}");
    }

    public function release(string $id, int $delay = 0): void {
        $jobData = $this->redis->hGetAll("job:{$id}");
        if (!$jobData) {
            return;
        }

        if ($delay > 0) {
            $this->redis->zAdd(
                'delayed_jobs',
                time() + $delay,
                $id
            );
        } else {
            $this->redis->rPush('queue:default', $id);
        }
    }

    public function failed(string $id, Throwable $e): void {
        $jobData = $this->redis->hGetAll("job:{$id}");
        if (!$jobData) {
            return;
        }

        $this->redis->hMSet("failed_jobs:{$id}", [
            'class' => $jobData['class'],
            'data' => $jobData['data'],
            'error' => $e->getMessage(),
            'failed_at' => time()
        ]);

        $this->delete($id);
    }

    private function connect(): void {
        if (!$this->redis->connect($this->host, $this->port)) {
            throw new RuntimeException('Redis connection failed');
        }
    }
}

// Queue worker
class QueueWorker {
    public function __construct(
        private readonly QueueInterface $queue,
        private readonly Logger $logger,
        private readonly int $sleep = 1
    ) {}

    public function work(string $queueName = 'default'): void {
        $this->logger->info("Worker started", ['queue' => $queueName]);

        while (true) {
            $job = $this->queue->pop($queueName);

            if (!$job) {
                sleep($this->sleep);
                continue;
            }

            try {
                $this->processJob($job);
            } catch (Throwable $e) {
                $this->handleFailedJob($job, $e);
            }
        }
    }

    private function processJob(JobInterface $job): void {
        $job->incrementAttempts();

        $this->logger->info("Processing job", [
            'class' => get_class($job),
            'attempts' => $job->getAttempts()
        ]);

        $job->handle();
    }

    private function handleFailedJob(JobInterface $job, Throwable $e): void {
        if ($job->getAttempts() < $job->getMaxAttempts()) {
            // Retry with exponential backoff
            $delay = (int) pow(2, $job->getAttempts());
            $this->queue->release($job->getId(), $delay);
            
            $this->logger->warning("Job failed, will retry", [
                'class' => get_class($job),
                'attempts' => $job->getAttempts(),
                'delay' => $delay,
                'error' => $e->getMessage()
            ]);
        } else {
            $job->failed($e);
            $this->queue->failed($job->getId(), $e);
            
            $this->logger->error("Job failed permanently", [
                'class' => get_class($job),
                'error' => $e->getMessage()
            ]);
        }
    }
}

/**
 * Common Questions and Tricky Situations:
 * ------------------------------------
 * Q1: How to handle job timeouts?
 * A: Implement timeout monitoring and process management
 * 
 * Q2: What about unique jobs?
 * A: Use job IDs or unique constraints in queue
 * 
 * Q3: How to scale workers?
 * A: Run multiple worker processes and use supervisor
 * 
 * Q4: How to handle failed jobs?
 * A: Implement retry mechanism and failure logging
 */

// Usage example
try {
    // Create queue and worker
    $queue = new RedisQueue(
        host: 'localhost',
        port: 6379,
        logger: new FileLogger(__DIR__ . '/queue.log')
    );

    // Push job to queue
    $emailJob = new SendEmailJob(
        to: 'user@example.com',
        subject: 'Welcome!',
        body: 'Welcome to our platform',
        mailer: new SmtpMailer(),
        logger: new FileLogger(__DIR__ . '/email.log')
    );

    $jobId = $queue->push($emailJob);

    // Start worker
    $worker = new QueueWorker(
        queue: $queue,
        logger: new FileLogger(__DIR__ . '/worker.log')
    );

    $worker->work();

} catch (Exception $e) {
    error_log($e->getMessage());
    exit(1);
}

/**
 * Best Practices:
 * 1. Monitor worker processes
 * 2. Implement job timeouts
 * 3. Handle failures gracefully
 * 4. Log job execution
 * 5. Use appropriate retry strategies
 * 6. Monitor queue health
 * 
 * New PHP 8.x Features Used:
 * 1. Constructor property promotion
 * 2. Readonly properties
 * 3. Named arguments
 * 4. Union types
 * 5. Match expressions
 * 6. Nullsafe operator
 */ 