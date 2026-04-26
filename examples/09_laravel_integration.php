<?php

declare(strict_types=1);

/**
 * Laravel Integration Example
 *
 * This example demonstrates how to integrate Vesvasi with a Laravel application.
 * Copy this to your Laravel project's bootstrap or service provider.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Vesvasi\Vesvasi;
use Vesvasi\Config\Config;

Config::reset();

Vesvasi::configure([
    'api_key' => env('VESVASI_API_KEY', ''),
    'endpoint' => env('VESVASI_ENDPOINT', 'https://otlp.example.com:4318'),
    'protocol' => env('VESVASI_PROTOCOL', 'http/protobuf'),
    'service' => [
        'name' => config('app.name', 'laravel-app'),
        'version' => config('app.version', '1.0.0'),
        'environment' => config('app.env', 'production'),
    ],
    'sampling' => [
        'head_percentage' => (float) env('VESVASI_SAMPLE_RATE', 10),
        'always_sample_errors' => true,
    ],
    'metrics' => [
        'enabled' => env('VESVASI_METRICS_ENABLED', true),
        'collect_cpu' => true,
        'collect_memory' => true,
    ],
    'logs' => [
        'enabled' => env('VESVASI_LOGS_ENABLED', true),
        'levels' => ['error', 'critical', 'warning'],
    ],
]);

class VesvasiServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Vesvasi::class, function () {
            return Vesvasi::getInstance();
        });
    }

    public function boot(): void
    {
        $this->registerExceptionHandler();
        $this->registerMiddleware();
        $this->registerCommands();
    }

    private function registerExceptionHandler(): void
    {
        // Exception tracking is automatically enabled
    }

    private function registerMiddleware(): void
    {
        // Web middleware for request tracking
    }

    private function registerCommands(): void
    {
        // Artisan commands for management
    }
}

echo "Laravel Integration Pattern\n";
echo "=========================\n";
echo "See code comments for integration details\n";

$vesvasi = Vesvasi::getInstance();
$logger = $vesvasi->logger();

$logger->info('Vesvasi initialized for Laravel', [
    'service' => config('app.name', 'laravel-app'),
]);

$vesvasi->shutdown();