<?php

declare(strict_types=1);

/**
 * Command Execution Tracking Example
 *
 * This example demonstrates how to track command execution with custom attributes.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Vesvasi\Vesvasi;
use Vesvasi\Config\Config;

Config::reset();

Vesvasi::configure([
    'api_key' => 'your-api-key',
    'endpoint' => 'https://otlp.example.com:4318',
    'service' => [
        'name' => 'example-commands',
        'version' => '1.0.0',
        'environment' => 'development',
    ],
]);

echo "Command Execution Tracking Example\n";
echo "=================================\n";

$vesvasi = Vesvasi::getInstance();
$commandIntegration = $vesvasi->instrumentation()->getCommandIntegration();

echo "\n1. Simple command trace:\n";
$result = $commandIntegration->trace('echo "Hello World"');
echo "  Exit code: " . $result->getExitCode() . "\n";
echo "  Duration: " . number_format($result->getDurationMs(), 2) . "ms\n";

echo "\n2. Command with arguments:\n";
$result = $commandIntegration->trace('python script.py', [
    '--mode' => 'production',
    '--limit' => '100',
    '--verbose'
]);
echo "  Exit code: " . $result->getExitCode() . "\n";
echo "  Output: " . trim((string) $result->getOutput()) . "\n";

echo "\n3. Command with environment:\n";
$result = $commandIntegration->trace('node', ['server.js', '--port=3000'], function () {
    echo "  Executing: node server.js --port=3000\n";
    return "Server started on port 3000";
});
echo "  Result: " . $result->getOutput() . "\n";

echo "\n4. Command that fails:\n";
$result = $commandIntegration->trace('npm run build', ['--prod'], function () {
    echo "  Simulating build failure\n";
    throw new RuntimeException('Build failed: TypeScript error');
});
echo "  Exit code: " . $result->getExitCode() . "\n";
echo "  Exception: " . ($result->getException() ? $result->getException()->getMessage() : 'none') . "\n";

echo "\n5. Create command span manually:\n";
$span = $commandIntegration->createCommandSpan('docker-compose', ['up', '-d']);
echo "  Created span for docker-compose command\n";
$span->setAttribute('command.exit_code', 0);
$span->setStatus(\OpenTelemetry\API\Trace\StatusCode::STATUS_OK);
$span->end();

echo "\n6. Command span with exit code tracking:\n";
$span = $commandIntegration->createCommandSpan('artisan migrate', ['--force', '--seed']);
$span->end();

echo "\nAll commands tracked with vesvasi.command=true attribute!\n";

$vesvasi->shutdown();