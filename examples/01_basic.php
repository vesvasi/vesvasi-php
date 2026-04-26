<?php

declare(strict_types=1);

/**
 * Basic Usage Example
 *
 * This example demonstrates the simplest way to use Vesvasi for tracing.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Vesvasi\Vesvasi;

Vesvasi::configure([
    'api_key' => 'your-api-key',
    'endpoint' => 'https://otlp.example.com:4318',
    'protocol' => 'http/protobuf',
    'service' => [
        'name' => 'example-basic',
        'version' => '1.0.0',
        'environment' => 'development',
    ],
]);

echo "Starting basic example...\n";

$span = Vesvasi::startSpan('hello-world');
echo "Started span: " . $span->getContext()->getSpanId() . "\n";

sleep(1);

$span->end();
echo "Span ended\n";

Vesvasi::log('info', 'Hello world operation completed');

Vesvasi::getInstance()->shutdown();
echo "Done!\n";