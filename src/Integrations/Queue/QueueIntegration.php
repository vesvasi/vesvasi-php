<?php

declare(strict_types=1);

namespace Vesvasi\Integrations\Queue;

use Vesvasi\Config\Config;
use Vesvasi\Trace\VesvasiTracer;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;

final class QueueIntegration
{
    private Config $config;
    private VesvasiTracer $tracer;
    private array $stats = [
        'queued' => 0,
        'processing' => 0,
        'completed' => 0,
        'failed' => 0,
        'delayed' => 0,
        'by_queue' => [],
        'by_connection' => [],
    ];

    private const ATTR_VESVASI_QUEUE = 'vesvasi.queue';
    private const ATTR_QUEUE_CONNECTION = 'queue.connection';
    private const ATTR_QUEUE_NAME = 'queue.name';
    private const ATTR_QUEUE_DRIVER = 'queue.driver';
    private const ATTR_QUEUE_OPERATION = 'queue.operation';
    private const ATTR_QUEUE_COMMAND = 'queue.command';
    private const ATTR_QUEUE_STATUS = 'queue.status';
    private const ATTR_QUEUE_PRIORITY = 'queue.priority';
    private const ATTR_QUEUE_DELAY = 'queue.delay_seconds';
    private const ATTR_QUEUE_TTR = 'queue.time_to_run_seconds';
    private const ATTR_QUEUE_ATTEMPTS = 'queue.attempts';
    private const ATTR_QUEUE_MAX_ATTEMPTS = 'queue.max_attempts';
    private const ATTR_QUEUE_JOB_ID = 'queue.job_id';
    private const ATTR_QUEUE_JOB_NAME = 'queue.job_name';
    private const ATTR_QUEUE_PAYLOAD_SIZE = 'queue.payload_size_bytes';
    private const ATTR_QUEUE_POOL_SIZE = 'queue.pool_size';
    private const ATTR_QUEUE_ACTIVE_JOBS = 'queue.active_jobs';
    private const ATTR_QUEUE_WAITING_JOBS = 'queue.waiting_jobs';
    private const ATTR_QUEUE_DELAYED_JOBS = 'queue.delayed_jobs';
    private const ATTR_QUEUE_FAILED_JOBS = 'queue.failed_jobs';
    private const ATTR_QUEUE_DURATION = 'queue.duration_ms';
    private const ATTR_QUEUE_FAILED_REASON = 'queue.failed_reason';
    private const ATTR_QUEUE_WORKER_NAME = 'queue.worker_name';
    private const ATTR_QUEUE_ITEMS_COUNT = 'queue.items_count';

    public function __construct(Config $config, VesvasiTracer $tracer)
    {
        $this->config = $config;
        $this->tracer = $tracer;
    }

    public function tracePush(
        string $job,
        array $payload = [],
        string $queue = 'default',
        string $connection = 'default',
        ?int $delay = null,
        ?int $priority = null,
        callable $callback = null
    ): ?string {
        $span = $this->createPushSpan($job, $queue, $connection, $delay, $priority);
        $startTime = microtime(true);
        $payloadSize = strlen(serialize($payload));

        try {
            $jobId = $callback !== null ? $callback() : uniqid('job_');

            $span->setAttribute(self::ATTR_QUEUE_JOB_ID, $jobId);
            $span->setAttribute(self::ATTR_QUEUE_STATUS, 'queued');
            $span->setAttribute(self::ATTR_QUEUE_PAYLOAD_SIZE, $payloadSize);
            $span->setStatus(StatusCode::STATUS_OK);
            $span->end();

            $this->recordPush($queue, $connection);
            $this->recordDuration($span, $startTime);

            return $jobId;
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setAttribute(self::ATTR_QUEUE_STATUS, 'failed');
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            $span->end();
            throw $e;
        }
    }

    public function traceLater(
        string $job,
        array $payload = [],
        int $delay,
        string $queue = 'default',
        string $connection = 'default',
        callable $callback = null
    ): ?string {
        $span = $this->createLaterSpan($job, $queue, $connection, $delay);
        $startTime = microtime(true);
        $payloadSize = strlen(serialize($payload));

        try {
            $jobId = $callback !== null ? $callback() : uniqid('job_delayed_');

            $span->setAttribute(self::ATTR_QUEUE_JOB_ID, $jobId);
            $span->setAttribute(self::ATTR_QUEUE_DELAY, $delay);
            $span->setAttribute(self::ATTR_QUEUE_STATUS, 'delayed');
            $span->setAttribute(self::ATTR_QUEUE_PAYLOAD_SIZE, $payloadSize);
            $span->setStatus(StatusCode::STATUS_OK);
            $span->end();

            $this->recordDelayed($queue, $connection, $delay);
            $this->recordDuration($span, $startTime);

            return $jobId;
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setAttribute(self::ATTR_QUEUE_STATUS, 'failed');
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            $span->end();
            throw $e;
        }
    }

    public function traceBulk(
        array $jobs,
        string $queue = 'default',
        string $connection = 'default',
        callable $callback = null
    ): int {
        $span = $this->createBulkSpan($queue, $connection, count($jobs));
        $startTime = microtime(true);
        $totalSize = 0;

        foreach ($jobs as $job) {
            $totalSize += strlen(serialize($job));
        }

        try {
            $count = $callback !== null ? $callback() : count($jobs);

            $span->setAttribute(self::ATTR_QUEUE_ITEMS_COUNT, count($jobs));
            $span->setAttribute(self::ATTR_QUEUE_PAYLOAD_SIZE, $totalSize);
            $span->setAttribute(self::ATTR_QUEUE_STATUS, 'queued');
            $span->setStatus(StatusCode::STATUS_OK);
            $span->end();

            $this->stats['queued'] += $count;
            $this->recordDuration($span, $startTime);

            return $count;
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setAttribute(self::ATTR_QUEUE_STATUS, 'failed');
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            $span->end();
            throw $e;
        }
    }

    public function traceJob(
        string $jobName,
        string $jobId,
        string $queue = 'default',
        string $connection = 'default',
        int $attempts = 1,
        ?int $maxAttempts = null,
        callable $callback = null
    ): mixed {
        $span = $this->createJobSpan($jobName, $jobId, $queue, $connection, $attempts, $maxAttempts);
        $startTime = microtime(true);

        $this->stats['processing']++;
        $this->recordProcessing($queue, $connection);

        try {
            $result = $callback !== null ? $callback() : null;

            $span->setAttribute(self::ATTR_QUEUE_STATUS, 'completed');
            $span->setAttribute(self::ATTR_QUEUE_DURATION, round((microtime(true) - $startTime) * 1000, 2));
            $span->setStatus(StatusCode::STATUS_OK);
            $span->end();

            $this->stats['processing']--;
            $this->stats['completed']++;
            $this->recordCompleted($queue, $connection);
            $this->recordDuration($span, $startTime);

            return $result;
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setAttribute(self::ATTR_QUEUE_STATUS, 'failed');
            $span->setAttribute(self::ATTR_QUEUE_FAILED_REASON, $e->getMessage());
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            $span->end();

            $this->stats['processing']--;
            $this->stats['failed']++;
            $this->recordFailed($queue, $connection, $e->getMessage());
            $this->recordDuration($span, $startTime);

            throw $e;
        }
    }

    public function tracePop(
        string $queue = 'default',
        string $connection = 'default',
        callable $callback = null
    ): ?array {
        $span = $this->createPopSpan($queue, $connection);
        $startTime = microtime(true);

        $this->stats['processing']++;

        try {
            $job = $callback !== null ? $callback() : null;

            if ($job !== null) {
                $span->setAttribute(self::ATTR_QUEUE_JOB_ID, $job['id'] ?? 'unknown');
                $span->setAttribute(self::ATTR_QUEUE_JOB_NAME, $job['job'] ?? 'unknown');
                $span->setAttribute(self::ATTR_QUEUE_STATUS, 'processing');
                $span->setAttribute(self::ATTR_QUEUE_ATTEMPTS, $job['attempts'] ?? 1);
            } else {
                $span->setAttribute(self::ATTR_QUEUE_STATUS, 'empty');
            }

            $span->setStatus(StatusCode::STATUS_OK);
            $span->end();

            $this->recordDuration($span, $startTime);

            return $job;
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setAttribute(self::ATTR_QUEUE_STATUS, 'failed');
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            $span->end();
            throw $e;
        }
    }

    public function traceWorker(
        string $workerName,
        string $queue = 'default',
        string $connection = 'default',
        int $poolSize = 1,
        callable $callback = null
    ): void {
        $span = $this->createWorkerSpan($workerName, $queue, $connection, $poolSize);
        $startTime = microtime(true);

        try {
            if ($callback !== null) {
                $callback();
            }

            $span->setStatus(StatusCode::STATUS_OK);
            $span->end();

            $this->recordDuration($span, $startTime);
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            $span->end();
            throw $e;
        }
    }

    public function createJobCountSpan(
        string $queue = 'default',
        string $connection = 'default'
    ): SpanInterface {
        return $this->tracer->spanBuilder("Queue COUNT {$queue}")
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute(self::ATTR_VESVASI_QUEUE, true)
            ->setAttribute(self::ATTR_QUEUE_OPERATION, 'count')
            ->setAttribute(self::ATTR_QUEUE_NAME, $queue)
            ->setAttribute(self::ATTR_QUEUE_CONNECTION, $connection)
            ->setAttribute(self::ATTR_QUEUE_DRIVER, $this->detectDriver($connection))
            ->startSpan();
    }

    private function createPushSpan(string $job, string $queue, string $connection, ?int $delay, ?int $priority): SpanInterface
    {
        $span = $this->tracer->spanBuilder("Queue PUSH {$job}")
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute(self::ATTR_VESVASI_QUEUE, true)
            ->setAttribute(self::ATTR_QUEUE_OPERATION, 'push')
            ->setAttribute(self::ATTR_QUEUE_COMMAND, 'LPUSH/RPUSH')
            ->setAttribute(self::ATTR_QUEUE_NAME, $queue)
            ->setAttribute(self::ATTR_QUEUE_CONNECTION, $connection)
            ->setAttribute(self::ATTR_QUEUE_JOB_NAME, $job)
            ->setAttribute(self::ATTR_QUEUE_DRIVER, $this->detectDriver($connection));

        if ($delay !== null) {
            $span->setAttribute(self::ATTR_QUEUE_DELAY, $delay);
        }

        if ($priority !== null) {
            $span->setAttribute(self::ATTR_QUEUE_PRIORITY, $priority);
        }

        return $span->startSpan();
    }

    private function createLaterSpan(string $job, string $queue, string $connection, int $delay): SpanInterface
    {
        return $this->tracer->spanBuilder("Queue LATER {$job} (delay: {$delay}s)")
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute(self::ATTR_VESVASI_QUEUE, true)
            ->setAttribute(self::ATTR_QUEUE_OPERATION, 'later')
            ->setAttribute(self::ATTR_QUEUE_COMMAND, 'ZADD')
            ->setAttribute(self::ATTR_QUEUE_NAME, $queue)
            ->setAttribute(self::ATTR_QUEUE_CONNECTION, $connection)
            ->setAttribute(self::ATTR_QUEUE_JOB_NAME, $job)
            ->setAttribute(self::ATTR_QUEUE_DELAY, $delay)
            ->setAttribute(self::ATTR_QUEUE_DRIVER, $this->detectDriver($connection))
            ->startSpan();
    }

    private function createBulkSpan(string $queue, string $connection, int $count): SpanInterface
    {
        return $this->tracer->spanBuilder("Queue BULK PUSH [{$count} jobs]")
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute(self::ATTR_VESVASI_QUEUE, true)
            ->setAttribute(self::ATTR_QUEUE_OPERATION, 'bulk_push')
            ->setAttribute(self::ATTR_QUEUE_COMMAND, 'LPUSH/RPUSH')
            ->setAttribute(self::ATTR_QUEUE_NAME, $queue)
            ->setAttribute(self::ATTR_QUEUE_CONNECTION, $connection)
            ->setAttribute(self::ATTR_QUEUE_ITEMS_COUNT, $count)
            ->setAttribute(self::ATTR_QUEUE_DRIVER, $this->detectDriver($connection))
            ->startSpan();
    }

    private function createJobSpan(string $jobName, string $jobId, string $queue, string $connection, int $attempts, ?int $maxAttempts): SpanInterface
    {
        $spanBuilder = $this->tracer->spanBuilder("Queue JOB {$jobName}")
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setAttribute(self::ATTR_VESVASI_QUEUE, true)
            ->setAttribute(self::ATTR_QUEUE_OPERATION, 'process')
            ->setAttribute(self::ATTR_QUEUE_NAME, $queue)
            ->setAttribute(self::ATTR_QUEUE_CONNECTION, $connection)
            ->setAttribute(self::ATTR_QUEUE_JOB_NAME, $jobName)
            ->setAttribute(self::ATTR_QUEUE_JOB_ID, $jobId)
            ->setAttribute(self::ATTR_QUEUE_ATTEMPTS, $attempts)
            ->setAttribute(self::ATTR_QUEUE_STATUS, 'processing')
            ->setAttribute(self::ATTR_QUEUE_DRIVER, $this->detectDriver($connection));

        if ($maxAttempts !== null) {
            $spanBuilder->setAttribute(self::ATTR_QUEUE_MAX_ATTEMPTS, $maxAttempts);
        }

        return $spanBuilder->startSpan();
    }

    private function createPopSpan(string $queue, string $connection): SpanInterface
    {
        return $this->tracer->spanBuilder("Queue POP {$queue}")
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute(self::ATTR_VESVASI_QUEUE, true)
            ->setAttribute(self::ATTR_QUEUE_OPERATION, 'pop')
            ->setAttribute(self::ATTR_QUEUE_COMMAND, 'BRPOP/BLPOP')
            ->setAttribute(self::ATTR_QUEUE_NAME, $queue)
            ->setAttribute(self::ATTR_QUEUE_CONNECTION, $connection)
            ->setAttribute(self::ATTR_QUEUE_DRIVER, $this->detectDriver($connection))
            ->startSpan();
    }

    private function createWorkerSpan(string $workerName, string $queue, string $connection, int $poolSize): SpanInterface
    {
        return $this->tracer->spanBuilder("Queue WORKER {$workerName}")
            ->setSpanKind(SpanKind::KIND_CONSUMER)
            ->setAttribute(self::ATTR_VESVASI_QUEUE, true)
            ->setAttribute(self::ATTR_QUEUE_OPERATION, 'work')
            ->setAttribute(self::ATTR_QUEUE_WORKER_NAME, $workerName)
            ->setAttribute(self::ATTR_QUEUE_NAME, $queue)
            ->setAttribute(self::ATTR_QUEUE_CONNECTION, $connection)
            ->setAttribute(self::ATTR_QUEUE_POOL_SIZE, $poolSize)
            ->setAttribute(self::ATTR_QUEUE_DRIVER, $this->detectDriver($connection))
            ->startSpan();
    }

    private function recordPush(string $queue, string $connection): void
    {
        $this->stats['queued']++;
        $this->incrementByKey($this->stats['by_queue'], $queue);
        $this->incrementByKey($this->stats['by_connection'], $connection);
    }

    private function recordDelayed(string $queue, string $connection, int $delay): void
    {
        $this->stats['delayed']++;
        $this->incrementByKey($this->stats['by_queue'], "{$queue}_delayed");
    }

    private function recordProcessing(string $queue, string $connection): void
    {
        $this->incrementByKey($this->stats['by_queue'], "{$queue}_processing");
    }

    private function recordCompleted(string $queue, string $connection): void
    {
        $this->decrementByKey($this->stats['by_queue'], "{$queue}_processing");
    }

    private function recordFailed(string $queue, string $connection, string $reason): void
    {
        $this->decrementByKey($this->stats['by_queue'], "{$queue}_processing");
    }

    private function incrementByKey(array &$arr, string $key): void
    {
        if (!isset($arr[$key])) {
            $arr[$key] = 0;
        }
        $arr[$key]++;
    }

    private function decrementByKey(array &$arr, string $key): void
    {
        if (isset($arr[$key]) && $arr[$key] > 0) {
            $arr[$key]--;
        }
    }

    private function recordDuration(SpanInterface $span, float $startTime): void
    {
        $duration = (microtime(true) - $startTime) * 1000;
        $span->setAttribute('vesvasi.queue.duration_ms', round($duration, 2));
    }

    private function detectDriver(string $connection): string
    {
        $connectionDrivers = [
            'redis' => 'redis',
            'database' => 'sql',
            'sqs' => 'sqs',
            'beanstalk' => 'beanstalkd',
            'rabbitmq' => 'rabbitmq',
            'sync' => 'sync',
        ];

        foreach ($connectionDrivers as $prefix => $driver) {
            if (str_starts_with($connection, $prefix)) {
                return $driver;
            }
        }

        return 'unknown';
    }

    public function getStats(): array
    {
        return [
            'queued' => $this->stats['queued'],
            'processing' => $this->stats['processing'],
            'completed' => $this->stats['completed'],
            'failed' => $this->stats['failed'],
            'delayed' => $this->stats['delayed'],
            'by_queue' => $this->stats['by_queue'],
            'by_connection' => $this->stats['by_connection'],
            'success_rate' => $this->calculateSuccessRate(),
            'failure_rate' => $this->calculateFailureRate(),
        ];
    }

    private function calculateSuccessRate(): float
    {
        $total = $this->stats['completed'] + $this->stats['failed'];
        return $total > 0 ? round($this->stats['completed'] / $total, 4) : 0;
    }

    private function calculateFailureRate(): float
    {
        $total = $this->stats['completed'] + $this->stats['failed'];
        return $total > 0 ? round($this->stats['failed'] / $total, 4) : 0;
    }

    public function getQueueStats(string $queue): array
    {
        return [
            'queued' => $this->stats['by_queue'][$queue] ?? 0,
            'processing' => $this->stats['by_queue']["{$queue}_processing"] ?? 0,
            'delayed' => $this->stats['by_queue']["{$queue}_delayed"] ?? 0,
        ];
    }

    public function resetStats(): void
    {
        $this->stats = [
            'queued' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
            'delayed' => 0,
            'by_queue' => [],
            'by_connection' => [],
        ];
    }

    public function shutdown(): void
    {
        $this->resetStats();
    }
}