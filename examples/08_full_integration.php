<?php

declare(strict_types=1);

/**
 * Full Integration Example
 *
 * This example demonstrates a complete real-world scenario with all Vesvasi features.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Vesvasi\Vesvasi;
use Vesvasi\Config\Config;

Config::reset();

Vesvasi::configure([
    'api_key' => 'your-api-key',
    'endpoint' => 'https://otlp.example.com:4318',
    'protocol' => 'http/protobuf',
    'timeout' => 30,
    'max_batch_size' => 512,
    'service' => [
        'name' => 'example-full',
        'version' => '1.0.0',
        'environment' => 'development',
        'namespace' => 'production',
        'instance_id' => gethostname(),
    ],
    'sampling' => [
        'head_percentage' => 10,
        'always_sample_errors' => true,
        'use_parent_sampling' => true,
    ],
    'filters' => [
        'cpu_threshold' => 0,
        'memory_threshold' => 0,
        'include_classes' => ['App\\*'],
        'exclude_classes' => ['App\\Test\\*'],
    ],
    'metrics' => [
        'enabled' => true,
        'collect_cpu' => true,
        'collect_memory' => true,
        'enable_runtime_metrics' => true,
        'export_interval' => 60000,
    ],
    'logs' => [
        'enabled' => true,
        'levels' => ['error', 'critical', 'warning', 'info'],
        'include_context' => true,
        'redact_fields' => ['password', 'token', 'secret', 'api_key'],
    ],
    'network' => [
        'verify_ssl' => true,
        'connect_timeout' => 10,
        'read_timeout' => 30,
    ],
]);

echo "Full Integration Example\n";
echo "=======================\n";
echo "Service: " . Vesvasi::getInstance()->getServiceName() . "\n";
echo "Environment: " . Vesvasi::getInstance()->getEnvironment() . "\n\n";

$vesvasi = Vesvasi::getInstance();
$tracer = $vesvasi->tracer();
$sqlIntegration = $vesvasi->instrumentation()->getSqlIntegration();
$httpIntegration = $vesvasi->instrumentation()->getHttpIntegration();
$logger = $vesvasi->logger();

$logger->info('Application started', [
    'pid' => getmypid(),
    'memory_limit' => ini_get('memory_limit'),
]);

echo "=== Simulating User Registration Flow ===\n\n";

$rootSpan = $tracer->spanBuilder('user-registration')
    ->setAttribute('user.email', 'newuser@example.com')
    ->setAttribute('user.action', 'register')
    ->startSpan();

try {
    echo "1. Validating user input...\n";
    $validateSpan = $tracer->spanBuilder('validate-user-input')
        ->setSpanKind(\OpenTelemetry\API\Trace\SpanKind::KIND_INTERNAL)
        ->startSpan();

    $logger->info('Validating user input', ['email' => 'newuser@example.com']);

    usleep(100000);
    $validateSpan->end();

    echo "2. Checking for existing user...\n";
    $sqlIntegration->traceQuery(
        'SELECT id FROM users WHERE email = ?',
        'app_db',
        'mysql',
        ['newuser@example.com'],
        fn() => null
    );

    echo "3. Creating user record...\n";
    $sqlIntegration->traceExecute(
        'INSERT INTO users (email, name, created_at) VALUES (?, ?, NOW())',
        ['newuser@example.com', 'New User'],
        'app_db',
        'mysql',
        fn() => 123
    );

    echo "4. Sending welcome email...\n";
    $httpIntegration->traceRequest(
        'POST',
        'https://email-service.example.com/send',
        ['Content-Type' => 'application/json'],
        json_encode([
            'to' => 'newuser@example.com',
            'template' => 'welcome',
        ]),
        fn() => ['status' => 200, 'message_id' => 'msg_123']
    );

    echo "5. Recording signup metric...\n";
    Vesvasi::incrementCounter('app.users.registered', 1.0, ['source' => 'web']);

    $logger->info('User registered successfully', [
        'user_id' => 123,
        'email' => 'newuser@example.com',
    ]);

    $rootSpan->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_OK);
    $rootSpan->end();

} catch (Exception $e) {
    echo "Error occurred: " . $e->getMessage() . "\n";

    $logger->error('User registration failed', [
        'email' => 'newuser@example.com',
        'error' => $e->getMessage(),
    ], $e);

    $rootSpan->recordException($e);
    $rootSpan->setStatus(
        \OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR,
        $e->getMessage()
    );
    $rootSpan->end();

} finally {
    echo "\n6. Flushing and shutting down...\n";
    $vesvasi->flush();
    $vesvasi->shutdown();
}

echo "\n=== Registration flow completed ===\n";
echo "Check your OTLP backend for:\n";
echo "  - user-registration span with all child spans\n";
echo "  - SQL spans with vesvasi.sql=true\n";
echo "  - HTTP span with vesvasi.http=true\n";
echo "  - Error tracking with vesvasi.error=true\n";
echo "  - Metrics: app.users.registered counter\n";
echo "  - Logs: registration events\n";