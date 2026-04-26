<?php

declare(strict_types=1);

/**
 * Full Observability Example
 *
 * This example demonstrates all integrations working together
 * for complete application observability.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Vesvasi\Vesvasi;
use Vesvasi\Config\Config;

Config::reset();

Vesvasi::configure([
    'api_key' => 'your-api-key',
    'endpoint' => 'https://otlp.example.com:4318',
    'service' => [
        'name' => 'example-observability',
        'version' => '1.0.0',
        'environment' => 'production',
    ],
    'sampling' => [
        'head_percentage' => 10,
        'always_sample_errors' => true,
    ],
    'metrics' => [
        'enabled' => true,
        'collect_cpu' => true,
        'collect_memory' => true,
    ],
    'logs' => [
        'enabled' => true,
        'levels' => ['error', 'warning', 'info'],
    ],
]);

echo "Full Observability Example\n";
echo "==========================\n";

$vesvasi = Vesvasi::getInstance();
$requestIntegration = $vesvasi->instrumentation()->getRequestIntegration();
$sqlIntegration = $vesvasi->instrumentation()->getSqlIntegration();
$cacheIntegration = $vesvasi->instrumentation()->getCacheIntegration();
$queueIntegration = $vesvasi->instrumentation()->getQueueIntegration();
$logger = $vesvasi->logger();

$logger->info('User registration flow started', ['user_id' => 123]);

echo "\n=== Simulating User Registration with Full Tracing ===\n\n";

echo "1. Incoming HTTP Request:\n";
$requestSpan = $requestIntegration->startRequestSpan(
    'POST',
    'https://api.example.com/v1/users',
    [
        'Content-Type' => 'application/json',
        'User-Agent' => 'Client/1.0',
        'X-Request-Id' => 'req_123',
    ],
    '{"email":"newuser@example.com","name":"New User","password":"secret"}'
);
echo "   Request tracked with performance metrics\n";

echo "\n2. Database: Check existing user:\n";
$sqlIntegration->traceQuery(
    'SELECT id FROM users WHERE email = ?',
    'app_db',
    'mysql',
    ['newuser@example.com'],
    fn() => null
);
echo "   Existing user check query tracked\n";

echo "\n3. Cache: Check rate limit:\n";
$cacheIntegration->traceGet(
    'rate_limit:registration:192.168.1.1',
    'redis',
    'redis',
    fn() => null
);
$hitRateLimit = false;
echo "   Rate limit check: " . ($hitRateLimit ? 'HIT (blocked)' : 'MISS (allowed)') . "\n";

if (!$hitRateLimit) {
    echo "\n4. Database: Create user:\n";
    $sqlIntegration->traceExecute(
        'INSERT INTO users (email, name, password_hash, created_at) VALUES (?, ?, ?, NOW())',
        ['newuser@example.com', 'New User', 'hashed_password'],
        'app_db',
        'mysql',
        fn() => 123
    );
    echo "   User creation query tracked\n";

    echo "\n5. Cache: Store new user data:\n";
    $cacheIntegration->traceSet(
        'user:123',
        ['id' => 123, 'email' => 'newuser@example.com', 'name' => 'New User'],
        3600,
        'redis',
        'redis',
        fn() => true
    );
    echo "   User data cached\n";

    echo "\n6. Queue: Schedule welcome email:\n";
    $queueIntegration->tracePush(
        'SendWelcomeEmail',
        ['user_id' => 123, 'email' => 'newuser@example.com', 'name' => 'New User'],
        'emails',
        'redis'
    );
    echo "   Welcome email job queued\n";

    echo "\n7. Queue: Schedule analytics event:\n";
    $queueIntegration->tracePush(
        'TrackAnalyticsEvent',
        ['event' => 'user_registered', 'user_id' => 123, 'timestamp' => time()],
        'analytics',
        'database'
    );
    echo "   Analytics event queued\n";
}

echo "\n8. Request completed:\n";
$requestIntegration->endRequestSpan(
    $requestSpan,
    201,
    ['Content-Type' => 'application/json', 'X-Request-Id' => 'req_123'],
    128
);
echo "   Request tracked with duration/memory metrics\n";

echo "\n9. Logging registration:\n";
$logger->info('User registered successfully', [
    'user_id' => 123,
    'email' => 'newuser@example.com',
    'registration_time_ms' => 245,
]);

echo "\n=== Complete Statistics ===\n\n";

echo "Cache Stats:\n";
$cacheStats = $cacheIntegration->getStats();
echo "   Hits: {$cacheStats['hits']}, Misses: {$cacheStats['misses']}, Hit Rate: " . ($cacheStats['hit_rate'] * 100) . "%\n";

echo "\nQueue Stats:\n";
$queueStats = $queueIntegration->getStats();
echo "   Queued: {$queueStats['queued']}, Completed: {$queueStats['completed']}, Failed: {$queueStats['failed']}\n";

echo "\nAll traces include:\n";
echo "   - vesvasi.request/sql/cache/queue = true\n";
echo "   - Performance metrics (duration, CPU, memory)\n";
echo "   - Status codes and error states\n";
echo "   - Store/queue/connection/driver details\n";
echo "   - TTL, size, payload metrics\n";
echo "   - Hit/miss rates for cache\n";
echo "   - Queue job counts and success rates\n";

$vesvasi->logProcessor()->flush();
$vesvasi->shutdown();

echo "\nDone! Check your OTLP backend for complete observability data.\n";