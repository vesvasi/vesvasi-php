<?php

declare(strict_types=1);

/**
 * Custom Metrics Example
 *
 * This example demonstrates how to record and track custom metrics.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Vesvasi\Vesvasi;
use Vesvasi\Config\Config;

Config::reset();

Vesvasi::configure([
    'api_key' => 'your-api-key',
    'endpoint' => 'https://otlp.example.com:4318',
    'service' => [
        'name' => 'example-metrics',
        'version' => '1.0.0',
        'environment' => 'development',
    ],
    'metrics' => [
        'enabled' => true,
        'collect_cpu' => true,
        'collect_memory' => true,
        'enable_runtime_metrics' => true,
    ],
]);

echo "Custom Metrics Example\n";
echo "=====================\n";

$vesvasi = Vesvasi::getInstance();
$metrics = $vesvasi->metrics();

echo "\n1. Recording CPU usage:\n";
$metrics->recordCpuUsage(45.5);
echo "  CPU: 45.5%\n";

$metrics->recordCpuUsage(72.3);
echo "  CPU: 72.3%\n";

echo "\n2. Recording memory usage:\n";
$metrics->recordMemoryUsage(256.0, 1024.0);
echo "  Memory used: 256MB / 1024MB available\n";

$metrics->recordMemoryUsage(512.0, 1024.0);
echo "  Memory used: 512MB / 1024MB available\n";

echo "\n3. Recording custom metrics:\n";
$metrics->recordCustomMetric('app.requests.count', 1500, ['endpoint' => '/api/users']);
echo "  Recorded: app.requests.count = 1500\n";

$metrics->recordCustomMetric('app.cache.hit_rate', 0.85, ['cache' => 'redis']);
echo "  Recorded: app.cache.hit_rate = 0.85\n";

$metrics->recordCustomMetric('app.queue.size', 42, ['queue' => 'default']);
echo "  Recorded: app.queue.size = 42\n";

echo "\n4. Incrementing counters:\n";
$metrics->incrementCounter('app.requests.total', 1.0, ['method' => 'GET', 'status' => '200']);
echo "  Incremented: app.requests.total (+1)\n";

$metrics->incrementCounter('app.requests.total', 1.0, ['method' => 'POST', 'status' => '201']);
echo "  Incremented: app.requests.total (+1)\n";

$metrics->incrementCounter('app.errors.total', 1.0, ['type' => 'validation']);
echo "  Incremented: app.errors.total (+1)\n";

$metrics->incrementCounter('app.errors.total', 1.0, ['type' => 'database']);
echo "  Incremented: app.errors.total (+1)\n";

echo "\n5. Recording histograms:\n";
$metrics->recordHistogram('app.response.time', 245.5, ['endpoint' => '/api/users']);
echo "  Recorded: app.response.time = 245.5ms\n";

$metrics->recordHistogram('app.response.time', 120.3, ['endpoint' => '/api/users']);
echo "  Recorded: app.response.time = 120.3ms\n";

$metrics->recordHistogram('app.response.time', 890.1, ['endpoint' => '/api/users']);
echo "  Recorded: app.response.time = 890.1ms\n";

echo "\n6. Using static helpers:\n";
Vesvasi::recordMetric('app.static.metric', 100.0, ['type' => 'static']);
echo "  Recorded via static helper\n";

Vesvasi::incrementCounter('app.static.counter', 5.0);
echo "  Incremented via static helper\n";

echo "\n7. Simulating request processing:\n";
for ($i = 0; $i < 100; $i++) {
    Vesvasi::incrementCounter('app.requests.total', 1.0, ['method' => 'GET']);

    if ($i % 10 === 0) {
        Vesvasi::incrementCounter('app.errors.total', 1.0, ['type' => 'timeout']);
    }

    $responseTime = rand(50, 500);
    Vesvasi::recordMetric('app.response.time', (float) $responseTime);
}
echo "  Simulated 100 requests with some errors\n";

echo "\nAll metrics tracked and exported!\n";

$vesvasi->flush();
$vesvasi->shutdown();