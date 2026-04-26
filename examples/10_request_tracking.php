<?php

declare(strict_types=1);

/**
 * Request/Performance Tracking Example
 *
 * This example demonstrates how to track HTTP requests with detailed
 * performance metrics (CPU, memory, duration).
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Vesvasi\Vesvasi;
use Vesvasi\Config\Config;

Config::reset();

Vesvasi::configure([
    'api_key' => 'your-api-key',
    'endpoint' => 'https://otlp.example.com:4318',
    'service' => [
        'name' => 'example-requests',
        'version' => '1.0.0',
        'environment' => 'development',
    ],
]);

echo "Request/Performance Tracking Example\n";
echo "==================================\n";

$vesvasi = Vesvasi::getInstance();
$requestIntegration = $vesvasi->instrumentation()->getRequestIntegration();

echo "\n1. Simulating API request with performance tracking:\n";
$span = $requestIntegration->startRequestSpan(
    'POST',
    'https://api.example.com/v1/users',
    [
        'Content-Type' => 'application/json',
        'User-Agent' => 'VesvasiSDK/1.0',
        'Authorization' => 'Bearer token_xxx',
    ],
    '{"name":"John","email":"john@example.com"}',
    'name=John&email=john@example.com'
);

echo "   Span started at: " . date('H:i:s.u') . "\n";
echo "   Initial memory: " . round(memory_get_usage(true) / 1024 / 1024, 2) . "MB\n";

usleep(500000);

$requestIntegration->endRequestSpan(
    $span,
    201,
    ['Content-Type' => 'application/json', 'X-Request-Id' => 'req_123'],
    256
);

echo "   Request completed with full performance metrics!\n";

echo "\n2. Simulating GET request:\n";
$span = $requestIntegration->traceRequest(
    'GET',
    'https://api.example.com/v1/users/123',
    ['Accept' => 'application/json'],
    null,
    function () {
        usleep(100000);
        return ['id' => 123, 'name' => 'John'];
    }
);

echo "   GET request completed\n";

echo "\n3. Simulating request with error:\n";
try {
    $span = $requestIntegration->traceRequest(
        'POST',
        'https://api.example.com/v1/users',
        ['Content-Type' => 'application/json'],
        '{"email":"invalid"}',
        function () {
            throw new RuntimeException('Validation failed: email is required');
        }
    );
} catch (RuntimeException $e) {
    echo "   Expected error caught: " . $e->getMessage() . "\n";
}

echo "\n4. Simulating slow request:\n";
$span = $requestIntegration->startRequestSpan(
    'GET',
    'https://api.example.com/v1/reports/generate',
    ['Accept' => 'application/pdf']
);

usleep(2000000);

$requestIntegration->endRequestSpan($span, 200, ['Content-Type' => 'application/pdf'], 1024000);
echo "   Slow request completed (2s duration)\n";

echo "\n5. Request count statistics:\n";
$counts = $requestIntegration->getRequestCounts();
foreach ($counts as $type => $count) {
    echo "   {$type}: {$count}\n";
}

echo "\nPerformance attributes captured per request:\n";
echo "   - vesvasi.request = true\n";
echo "   - vesvasi.duration_ms = request duration in milliseconds\n";
echo "   - vesvasi.cpu_user_ms = CPU user time in milliseconds\n";
echo "   - vesvasi.memory_used_mb = memory used in MB\n";
echo "   - vesvasi.memory_peak_mb = peak memory in MB\n";
echo "   - request.method, request.url, request.path\n";
echo "   - response.status_code, response.time_ms, response.size\n";

$vesvasi->shutdown();