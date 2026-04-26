<?php

declare(strict_types=1);

/**
 * Queue Integration Example
 *
 * This example demonstrates how to track queue operations with
 * detailed metrics (status, counts, delays, etc.).
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Vesvasi\Vesvasi;
use Vesvasi\Config\Config;

Config::reset();

Vesvasi::configure([
    'api_key' => 'your-api-key',
    'endpoint' => 'https://otlp.example.com:4318',
    'service' => [
        'name' => 'example-queue',
        'version' => '1.0.0',
        'environment' => 'development',
    ],
]);

echo "Queue Integration Example\n";
echo "========================\n";

$vesvasi = Vesvasi::getInstance();
$queueIntegration = $vesvasi->instrumentation()->getQueueIntegration();

echo "\n1. Pushing a job to queue:\n";
$jobId = $queueIntegration->tracePush(
    'SendWelcomeEmail',
    ['user_id' => 123, 'email' => 'john@example.com', 'name' => 'John'],
    'emails',
    'redis',
    null,
    null,
    fn() => 'job_' . uniqid()
);
echo "   Job ID: {$jobId}\n";

echo "\n2. Pushing delayed job:\n";
$jobId = $queueIntegration->traceLater(
    'SendReminderEmail',
    ['user_id' => 456, 'email' => 'jane@example.com', 'template' => 'reminder'],
    3600,
    'emails',
    'redis',
    fn() => 'job_delayed_' . uniqid()
);
echo "   Delayed job scheduled for 3600s later\n";

echo "\n3. Pushing multiple jobs (bulk):\n";
$jobs = [];
for ($i = 1; $i <= 5; $i++) {
    $jobs[] = [
        'job' => 'ProcessReport',
        'payload' => ['report_id' => $i, 'format' => 'pdf'],
        'priority' => $i,
    ];
}
$count = $queueIntegration->traceBulk($jobs, 'reports', 'database');
echo "   Pushed {$count} jobs to reports queue\n";

echo "\n4. Processing a job:\n";
$jobId = 'job_' . uniqid();
$result = $queueIntegration->traceJob(
    'SendWelcomeEmail',
    $jobId,
    'emails',
    'redis',
    1,
    3,
    function () {
        usleep(100000);
        return ['sent' => true, 'message_id' => 'msg_' . uniqid()];
    }
);
echo "   Job completed: " . json_encode($result) . "\n";

echo "\n5. Processing a failing job:\n";
try {
    $jobId = 'job_' . uniqid();
    $queueIntegration->traceJob(
        'ProcessPayment',
        $jobId,
        'payments',
        'redis',
        1,
        3,
        function () {
            throw new RuntimeException('Payment gateway timeout');
        }
    );
} catch (RuntimeException $e) {
    echo "   Expected failure: " . $e->getMessage() . "\n";
}

echo "\n6. Processing job with retries:\n";
$jobId = 'job_' . uniqid();
$maxAttempts = 3;

for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
    try {
        $result = $queueIntegration->traceJob(
            'IndexDocument',
            $jobId,
            'search',
            'redis',
            $attempt,
            $maxAttempts,
            function () use ($attempt, $maxAttempts) {
                if ($attempt < $maxAttempts) {
                    throw new RuntimeException("Attempt {$attempt} failed");
                }
                return ['indexed' => true, 'document_id' => 'doc_123'];
            }
        );
        echo "   Job succeeded on attempt {$attempt}\n";
        break;
    } catch (RuntimeException $e) {
        if ($attempt < $maxAttempts) {
            echo "   Attempt {$attempt} failed, retrying...\n";
        } else {
            echo "   All {$maxAttempts} attempts exhausted\n";
        }
    }
}

echo "\n7. Popping a job from queue:\n";
$job = $queueIntegration->tracePop('emails', 'redis', function () {
    usleep(50000);
    return [
        'id' => 'job_' . uniqid(),
        'job' => 'SendNotificationEmail',
        'payload' => ['user_id' => 789, 'message' => 'Hello!'],
        'attempts' => 0,
    ];
});
echo "   Popped job: " . ($job['job'] ?? 'none') . "\n";

echo "\n8. Empty queue pop:\n";
$job = $queueIntegration->tracePop('emails', 'redis', function () {
    return null;
});
echo "   Queue empty\n";

echo "\n9. Queue statistics:\n";
$stats = $queueIntegration->getStats();
echo "   Queued: " . $stats['queued'] . "\n";
echo "   Processing: " . $stats['processing'] . "\n";
echo "   Completed: " . $stats['completed'] . "\n";
echo "   Failed: " . $stats['failed'] . "\n";
echo "   Delayed: " . $stats['delayed'] . "\n";
echo "   Success Rate: " . ($stats['success_rate'] * 100) . "%\n";

echo "\n10. Specific queue stats:\n";
$emailStats = $queueIntegration->getQueueStats('emails');
echo "   Emails - Queued: " . $emailStats['queued'] . "\n";
echo "   Emails - Processing: " . $emailStats['processing'] . "\n";
echo "   Emails - Delayed: " . $emailStats['delayed'] . "\n";

echo "\nQueue attributes captured:\n";
echo "   - vesvasi.queue = true\n";
echo "   - queue.operation = push/later/bulk/process/pop\n";
echo "   - queue.name = queue name\n";
echo "   - queue.connection = connection name\n";
echo "   - queue.driver = redis/sqs/beanstalkd/etc\n";
echo "   - queue.job_name = job class/function name\n";
echo "   - queue.job_id = unique job ID\n";
echo "   - queue.status = queued/processing/completed/failed/delayed\n";
echo "   - queue.delay_seconds = delay for scheduled jobs\n";
echo "   - queue.attempts = current attempt number\n";
echo "   - queue.max_attempts = maximum retry attempts\n";
echo "   - queue.payload_size_bytes = job payload size\n";
echo "   - queue.duration_ms = job execution time\n";
echo "   - queue.failed_reason = error message if failed\n";

$vesvasi->shutdown();