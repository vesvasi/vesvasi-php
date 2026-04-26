# Vesvasi Examples

This directory contains example usage patterns for the Vesvasi PHP SDK.

## Running Examples

```bash
# Install dependencies
composer install

# Run any example
php examples/01_basic.php
php examples/02_sql_tracking.php
php examples/03_http_tracking.php
```

## Examples Overview

| File | Description |
|------|-------------|
| `01_basic.php` | Basic tracing with Vesvasi |
| `02_sql_tracking.php` | SQL/database query tracking |
| `03_http_tracking.php` | HTTP request tracking |
| `04_command_tracking.php` | Command execution tracking |
| `05_custom_metrics.php` | Custom metrics (counters, histograms) |
| `06_logging.php` | Structured logging with redaction |
| `07_exception_tracking.php` | Exception tracking with vesvasi.error |
| `08_full_integration.php` | Complete integration example |
| `09_laravel_integration.php` | Laravel integration pattern |
| `10_request_tracking.php` | Request/performance tracking (CPU, memory, duration) |
| `11_cache_tracking.php` | Cache tracking (hits, misses, TTL, size) |
| `12_queue_tracking.php` | Queue tracking (status, counts, delays) |
| `13_full_observability.php` | Complete observability with all integrations |

## Configuration

Each example is configured to use a placeholder endpoint. Update the `api_key` and `endpoint` in each file or use `VESVASI_*` environment variables.

## Quick Reference

```php
use Vesvasi\Vesvasi;

// Configure
Vesvasi::configure([
    'api_key' => 'your-key',
    'endpoint' => 'https://otlp.example.com:4318',
    'service' => ['name' => 'my-app'],
]);

// Start tracing
$span = Vesvasi::startSpan('operation');
$span->end();

// Static helpers
Vesvasi::startSqlSpan('SELECT', $sql, 'db');
Vesvasi::recordError($exception);
Vesvasi::recordMetric('name', 1.0);
Vesvasi::incrementCounter('name');
Vesvasi::log('info', 'message');
```