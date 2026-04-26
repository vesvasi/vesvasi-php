# Vesvasi PHP SDK

Production-grade PHP APM SDK built on OpenTelemetry with custom vesvasi attributes for deep observability.

## Features

- **OpenTelemetry Native**: Built on OpenTelemetry SDK with full OTLP support
- **Custom Attributes**: `vesvasi.error`, `vesvasi.sql`, `vesvasi.http`, `vesvasi.command` for easy filtering
- **Multi-Protocol Support**: HTTP/protobuf, HTTP/JSON, gRPC exporters
- **Configurable Sampling**: Head-based and tail-based (error-focused) sampling
- **Resource Monitoring**: CPU, memory, and custom metrics collection
- **Auto-Instrumentation**: SQL, HTTP, commands, and exception tracking
- **Flexible Filtering**: Include/exclude by files, classes, methods, URLs, commands
- **API Key Support**: `x-api-key` header for authentication
- **Auto-Instrumentation**: SQL, HTTP, commands, and exception tracking

## Installation

```bash
composer require vesvasi/vesvasi
```

## Quick Start

```php
<?php

require_once 'vendor/autoload.php';

use Vesvasi\Vesvasi;

// Configure Vesvasi
Vesvasi::configure([
    'api_key' => 'your-api-key',
    'endpoint' => 'https://otlp.example.com:4318',
    'protocol' => 'http/protobuf',
    'service' => [
        'name' => 'my-application',
        'version' => '1.0.0',
        'environment' => 'production',
    ],
]);

// Start tracing
$span = Vesvasi::startSpan('my-operation', ['user.id' => 123]);
// ... your code ...
$span->end();
```

## Configuration

### Full Configuration Example

```php
$config = [
    'api_key' => 'your-api-key',
    'endpoint' => 'https://otlp.example.com:4318',
    'protocol' => 'http/protobuf',
    'timeout' => 30,
    'max_queue_size' => 2048,
    'max_batch_size' => 512,
    'debug' => false,

    'service' => [
        'name' => 'my-application',
        'version' => '1.0.0',
        'environment' => 'production',
        'namespace' => 'production',
        'instance_id' => '',
    ],

    'sampling' => [
        'head_percentage' => 10,
        'always_sample_errors' => true,
        'sampling_ratio' => 1000,
        'max_traces_per_second' => 100,
        'use_parent_sampling' => true,
        'use_root_span_sampling' => true,
    ],

    'filters' => [
        'cpu_threshold' => 5,
        'memory_threshold' => 10,
        'duration_threshold' => 100,
        'include_files' => ['*'],
        'exclude_files' => ['/vendor/*'],
        'include_classes' => ['App\\*'],
        'exclude_classes' => ['Vendor\\*'],
        'include_methods' => ['*'],
        'exclude_methods' => [],
        'include_urls' => ['*'],
        'exclude_urls' => ['/health'],
        'include_commands' => ['*'],
        'exclude_commands' => [],
    ],

    'metrics' => [
        'enabled' => true,
        'collect_cpu' => true,
        'collect_memory' => true,
        'collect_disk' => false,
        'collect_network' => false,
        'collection_interval' => 60000,
        'export_interval' => 60000,
        'enable_runtime_metrics' => true,
    ],

    'logs' => [
        'enabled' => true,
        'levels' => ['error', 'critical'],
        'max_message_length' => 10000,
        'include_stack_trace' => true,
        'include_context' => true,
        'redact_fields' => ['password', 'token', 'secret'],
    ],

    'network' => [
        'proxy_url' => '',
        'connect_timeout' => 10,
        'read_timeout' => 30,
        'write_timeout' => 30,
        'verify_ssl' => true,
        'ca_bundle_path' => null,
        'extra_headers' => [],
    ],
];

Vesvasi::configure($config);
```

### Configuration Options

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `api_key` | string | - | API key for authentication |
| `endpoint` | string | required | OTLP endpoint URL |
| `protocol` | string | `http/protobuf` | Export protocol |
| `timeout` | int | 30 | Request timeout in seconds |
| `max_queue_size` | int | 2048 | Max spans in queue |
| `max_batch_size` | int | 512 | Batch export size |

## Service Attributes

Standard OpenTelemetry service attributes:

- `service.name` - Service identifier
- `service.version` - Service version
- `service.environment` - Environment (prod/staging/dev)
- `service.namespace` - Service namespace
- `service.instance.id` - Unique instance identifier

## Vesvasi Custom Attributes

### Error Tracking

```php
// Automatically sets vesvasi.error = true
Vesvasi::recordError(new \RuntimeException('Something went wrong'));

// Or via tracer
$span = Vesvasi::startHttpSpan('GET', 'https://api.example.com');
$span->setAttribute('error', true);
```

### SQL/Database Tracking

```php
$vesvasi = Vesvasi::getInstance();
$sqlIntegration = $vesvasi->instrumentation()->getSqlIntegration();

// Trace a query
$result = $sqlIntegration->traceQuery(
    'SELECT * FROM users WHERE id = ?',
    'my_database',
    'mysql',
    [1],
    fn() => $db->query($sql)
);

// Trace a transaction
$sqlIntegration->traceTransaction(
    function () use ($db) {
        $db->beginTransaction();
        $db->query('INSERT INTO logs (message) VALUES (?)', [$msg]);
        $db->commit();
    },
    'my_database',
    'mysql'
);
```

Attributes set:
- `vesvasi.sql = true`
- `vesvasi.db.name = <database>`
- `db.system`, `db.statement`, `db.operation`

### HTTP Tracking

```php
$vesvasi = Vesvasi::getInstance();
$httpIntegration = $vesvasi->instrumentation()->getHttpIntegration();

// Trace HTTP request
$result = $httpIntegration->traceRequest(
    'POST',
    'https://api.example.com/users',
    ['Content-Type' => 'application/json'],
    '{"name":"John"}',
    fn() => $client->post($url, $body)
);
```

Attributes set:
- `vesvasi.http = true`
- `http.method`, `http.url`, `http.status_code`

### Command Tracking

```php
$vesvasi = Vesvasi::getInstance();
$commandIntegration = $vesvasi->instrumentation()->getCommandIntegration();

// Trace command execution
$result = $commandIntegration->trace('artisan migrate', ['--force']);

// Trace process
$result = $commandIntegration->traceProcess('python script.py');
```

Attributes set:
- `vesvasi.command = true`
- `command`, `command.arguments`, `vesvasi.exit_code`

### Request/Performance Tracking

```php
$vesvasi = Vesvasi::getInstance();
$requestIntegration = $vesvasi->instrumentation()->getRequestIntegration();

// Start a request span with full tracking
$span = $requestIntegration->startRequestSpan(
    'POST',
    'https://api.example.com/users',
    ['Content-Type' => 'application/json'],
    '{"name":"John"}'
);

// ... process request ...

// End the span with response details
$requestIntegration->endRequestSpan($span, 201, ['Content-Type' => 'application/json'], 128);
```

Attributes set:
- `vesvasi.request = true`
- `vesvasi.duration_ms` - Total request duration
- `vesvasi.cpu_user_ms` - CPU user time
- `vesvasi.memory_used_mb` - Memory used
- `vesvasi.memory_peak_mb` - Peak memory
- `request.method`, `request.url`, `request.path`, `request.headers`
- `response.status_code`, `response.time_ms`, `response.size`

### Cache Tracking

```php
$vesvasi = Vesvasi::getInstance();
$cacheIntegration = $vesvasi->instrumentation()->getCacheIntegration();

// Trace a cache GET
$value = $cacheIntegration->traceGet(
    'user:123',
    'redis',
    'redis',
    fn() => $cache->get('user:123')
);

// Trace a cache SET with TTL
$cacheIntegration->traceSet(
    'user:123',
    ['id' => 123, 'name' => 'John'],
    3600,
    'redis',
    'redis',
    fn() => $cache->set('user:123', $data, 3600)
);

// Trace multi-get
$results = $cacheIntegration->traceMultiGet(
    ['user:123', 'product:456'],
    'redis',
    'redis',
    fn() => $cache->getMultiple(['user:123', 'product:456'])
);

// Get cache statistics
$stats = $cacheIntegration->getStats();
```

Attributes set:
- `vesvasi.cache = true`
- `cache.operation = get/set/delete/multi_get/multi_set`
- `cache.store`, `cache.key`, `cache.driver`, `cache.command`
- `cache.hit`, `cache.miss`, `cache.exists`
- `cache.ttl_seconds`, `cache.value_size_bytes`
- `cache.hit_rate`

### Queue Tracking

```php
$vesvasi = Vesvasi::getInstance();
$queueIntegration = $vesvasi->instrumentation()->getQueueIntegration();

// Push a job to queue
$jobId = $queueIntegration->tracePush(
    'SendWelcomeEmail',
    ['user_id' => 123, 'email' => 'john@example.com'],
    'emails',
    'redis'
);

// Push a delayed job
$queueIntegration->traceLater(
    'SendReminderEmail',
    ['user_id' => 123],
    3600,
    'emails',
    'redis'
);

// Process a job with tracking
$result = $queueIntegration->traceJob(
    'SendWelcomeEmail',
    $jobId,
    'emails',
    'redis',
    1,
    3,
    function () {
        // Job logic
        return ['sent' => true];
    }
);

// Get queue statistics
$stats = $queueIntegration->getStats();
$queueStats = $queueIntegration->getQueueStats('emails');
```

Attributes set:
- `vesvasi.queue = true`
- `queue.operation = push/later/bulk/process/pop`
- `queue.name`, `queue.connection`, `queue.driver`
- `queue.job_name`, `queue.job_id`
- `queue.status = queued/processing/completed/failed/delayed`
- `queue.delay_seconds`, `queue.attempts`, `queue.max_attempts`
- `queue.payload_size_bytes`, `queue.duration_ms`
- `queue.failed_reason`

## Metrics

### System Metrics

```php
$vesvasi = Vesvasi::getInstance();
$metrics = $vesvasi->metrics();

// Record CPU usage
$metrics->recordCpuUsage(75.5);

// Record memory usage
$metrics->recordMemoryUsage(512.0, 2048.0);

// Record custom metric
$metrics->recordCustomMetric('requests.count', 1000, ['endpoint' => '/api/users']);

// Increment counter
$metrics->incrementCounter('errors.total', 1.0, ['type' => 'timeout']);
```

### Available Metrics

- `system.cpu.usage` - CPU usage percentage
- `system.memory.usage` - Memory usage in MB
- `system.memory.available` - Available memory
- `runtime.memory.heap_used` - PHP heap usage
- `runtime.gc.runs` - Garbage collection runs

## Logging

```php
$vesvasi = Vesvasi::getInstance();
$logger = $vesvasi->logger();

// Basic logging
$logger->info('User logged in', ['user_id' => 123]);
$logger->error('Request failed', ['url' => '/api/users', 'status' => 500]);

// With channel
$logger->withChannel('auth')->warning('Invalid token');

// With context
$logger->withContext(['request_id' => 'abc123'])->error('Error occurred');
```

## Filtering

### File Filtering

```php
$config = Config::load([
    'endpoint' => 'https://otlp.example.com:4318',
    'filters' => [
        'include_files' => ['/app/src/*', '/app/controllers/*'],
        'exclude_files' => ['/app/vendor/*', '/app/tests/*'],
    ],
]);
```

### Class Filtering

```php
$config = [
    'filters' => [
        'include_classes' => ['App\\*', 'Domain\\*'],
        'exclude_classes' => ['App\\Controller\\Base*', 'Vendor\\*'],
    ],
];
```

### Resource Thresholds

```php
$config = [
    'filters' => [
        'cpu_threshold' => 5,       // Only trace if CPU > 5%
        'memory_threshold' => 10,   // Only trace if memory > 10MB
        'duration_threshold' => 100, // Only trace if duration > 100ms
    ],
];
```

## Sampling

### Head-Based Sampling

```php
$config = [
    'sampling' => [
        'head_percentage' => 10,  // Sample 10% of spans
    ],
];
```

### Error-Focused Sampling (Tail-Based)

```php
$config = [
    'sampling' => [
        'head_percentage' => 10,
        'always_sample_errors' => true,  // Always sample error spans
    ],
];
```

## OTLP Protocols

### HTTP/protobuf (Default)

```php
$config = [
    'protocol' => 'http/protobuf',
    'endpoint' => 'https://otlp.example.com:4318/v1/traces',
];
```

### HTTP/JSON

```php
$config = [
    'protocol' => 'http/json',
    'endpoint' => 'https://otlp.example.com:4318/v1/traces',
];
```

### gRPC

```php
$config = [
    'protocol' => 'grpc',
    'endpoint' => 'https://otlp.example.com:4317',
];
```

## Network Configuration

```php
$config = [
    'network' => [
        'proxy_url' => 'http://proxy:8080',
        'connect_timeout' => 10,
        'read_timeout' => 30,
        'write_timeout' => 30,
        'verify_ssl' => true,
        'ca_bundle_path' => '/path/to/ca-bundle.crt',
        'extra_headers' => [
            'X-Custom-Header' => 'value',
        ],
    ],
];
```

## Static Helpers

```php
use Vesvasi\Vesvasi;

// Start a span
$span = Vesvasi::startSpan('operation');

// SQL span
$span = Vesvasi::startSqlSpan('SELECT', 'SELECT * FROM users', 'db_name', 'mysql');

// HTTP span
$builder = Vesvasi::startHttpSpan('GET', 'https://api.example.com');

// Command span
$builder = Vesvasi::startCommandSpan('artisan migrate');

// Record error
Vesvasi::recordError($exception);

// Record metric
Vesvasi::recordMetric('requests.count', 1000);

// Increment counter
Vesvasi::incrementCounter('errors.total', 1);

// Log
Vesvasi::log('error', 'Something went wrong', ['context' => 'value']);
```

## Exception Handling

```php
use Vesvasi\Vesvasi;

Vesvasi::configure([/* config */]);

// Enable exception tracking
$vesvasi = Vesvasi::getInstance();
$exceptionIntegration = $vesvasi->instrumentation()->getExceptionIntegration();
$exceptionIntegration->register();

// Now all uncaught exceptions are automatically tracked
throw new \RuntimeException('This will be tracked');
```

## Shutdown

```php
// Flush pending spans and shutdown
Vesvasi::getInstance()->shutdown();

// Or reset entirely
Vesvasi::reset();
```

## Requirements

- PHP 8.2+
- OpenTelemetry SDK
- ext-json
- ext-pdo (optional, for SQL integration)

## License

MIT