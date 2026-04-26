<?php

declare(strict_types=1);

/**
 * Exception Tracking Example
 *
 * This example demonstrates how to track exceptions automatically and manually.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Vesvasi\Vesvasi;
use Vesvasi\Config\Config;

Config::reset();

Vesvasi::configure([
    'api_key' => 'your-api-key',
    'endpoint' => 'https://otlp.example.com:4318',
    'service' => [
        'name' => 'example-exceptions',
        'version' => '1.0.0',
        'environment' => 'development',
    ],
]);

echo "Exception Tracking Example\n";
echo "=========================\n";

$vesvasi = Vesvasi::getInstance();
$exceptionIntegration = $vesvasi->instrumentation()->getExceptionIntegration();
$tracer = $vesvasi->tracer();

echo "\n1. Recording exception to current span:\n";
$span = $tracer->spanBuilder('risky-operation')
    ->startSpan();

try {
    throw new RuntimeException('Something went wrong in risky operation');
} catch (RuntimeException $e) {
    $tracer->recordException($e, $span);
}
$span->end();
echo "  Exception recorded to span with vesvasi.error=true\n";

echo "\n2. Creating error span directly:\n";
$errorSpan = $tracer->createErrorSpan(
    'Database connection failed',
    new RuntimeException('Connection refused: localhost:3306'),
    \OpenTelemetry\API\Trace\SpanKind::KIND_INTERNAL
)->startSpan();
$errorSpan->end();
echo "  Error span created with full exception details\n";

echo "\n3. Using span builder with error attributes:\n";
$builder = $tracer->spanBuilder('file-operation')
    ->setVesvasiError(true)
    ->setAttribute('file.path', '/tmp/data.json')
    ->setAttribute('file.operation', 'read');

try {
    throw new InvalidArgumentException('Invalid file format');
} catch (InvalidArgumentException $e) {
    $builder->recordException($e);
}

$span = $builder->startSpan();
$span->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_ERROR, $e->getMessage());
$span->end();
echo "  Error span created for file operation\n";

echo "\n4. Creating exception span manually:\n";
$exceptionSpan = $exceptionIntegration->createExceptionSpan(
    new RuntimeException('API rate limit exceeded')
);
echo "  Exception span ID: " . $exceptionSpan->getContext()->getSpanId() . "\n";
$exceptionSpan->end();

echo "\n5. Adding custom exception handler:\n";
$handled = false;
$exceptionIntegration->addHandler(function ($exception) use (&$handled) {
    $handled = true;
    echo "  Custom handler: Caught " . get_class($exception) . "\n";
});

try {
    throw new RuntimeException('Test exception');
} catch (RuntimeException $e) {
    $exceptionIntegration->recordException($e);
}

echo "  Custom handler was triggered: " . ($handled ? 'Yes' : 'No') . "\n";

echo "\n6. Simulating multi-level exception tracking:\n";
$span = $tracer->spanBuilder('outer-operation')->startSpan();

try {
    $innerSpan = $tracer->spanBuilder('inner-operation')->startSpan();
    try {
        throw new RuntimeException('Inner error');
    } catch (RuntimeException $e) {
        $tracer->recordException($e, $innerSpan);
    }
    $innerSpan->end();

    throw new RuntimeException('Outer error');
} catch (RuntimeException $e) {
    $tracer->recordException($e, $span);
}
$span->end();

echo "\nAll exceptions tracked with vesvasi.error=true!\n";

$vesvasi->shutdown();