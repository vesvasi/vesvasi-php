<?php

declare(strict_types=1);

/**
 * Cache Integration Example
 *
 * This example demonstrates how to track cache operations with
 * detailed metrics (hits, misses, TTL, size, etc.).
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Vesvasi\Vesvasi;
use Vesvasi\Config\Config;

Config::reset();

Vesvasi::configure([
    'api_key' => 'your-api-key',
    'endpoint' => 'https://otlp.example.com:4318',
    'service' => [
        'name' => 'example-cache',
        'version' => '1.0.0',
        'environment' => 'development',
    ],
]);

echo "Cache Integration Example\n";
echo "=======================\n";

$vesvasi = Vesvasi::getInstance();
$cacheIntegration = $vesvasi->instrumentation()->getCacheIntegration();

$simulatedCache = new class {
    private array $store = [];
    private array $ttls = [];

    public function get(string $key): ?array {
        if (!isset($this->store[$key])) {
            return null;
        }
        if (isset($this->ttls[$key]) && $this->ttls[$key] < time()) {
            unset($this->store[$key], $this->ttls[$key]);
            return null;
        }
        return $this->store[$key];
    }

    public function set(string $key, $value, ?int $ttl = null): bool {
        $this->store[$key] = $value;
        if ($ttl !== null) {
            $this->ttls[$key] = time() + $ttl;
        }
        return true;
    }

    public function delete(string $key): bool {
        unset($this->store[$key], $this->ttls[$key]);
        return true;
    }

    public function has(string $key): bool {
        return isset($this->store[$key]);
    }

    public function flush(): bool {
        $this->store = [];
        $this->ttls = [];
        return true;
    }
};

echo "\n1. Tracing single GET (cache miss):\n";
$result = $cacheIntegration->traceGet(
    'user:123',
    'redis',
    'redis',
    fn() => $simulatedCache->get('user:123')
);
echo "   Result: " . ($result === null ? 'MISS' : 'HIT') . "\n";

echo "\n2. Setting cache value with TTL:\n";
$cacheIntegration->traceSet(
    'user:123',
    ['id' => 123, 'name' => 'John', 'email' => 'john@example.com'],
    3600,
    'redis',
    'redis',
    fn() => $simulatedCache->set('user:123', ['id' => 123, 'name' => 'John'], 3600)
);
echo "   Cached with TTL: 3600s\n";

echo "\n3. Tracing GET (cache hit):\n";
$cacheIntegration->traceGet(
    'user:123',
    'redis',
    'redis',
    fn() => $simulatedCache->get('user:123')
);
echo "   Result: HIT\n";

echo "\n4. Tracing multi-get:\n";
$simulatedCache->set('product:456', ['id' => 456, 'name' => 'Widget']);
$simulatedCache->set('category:789', ['id' => 789, 'name' => 'Electronics']);

$results = $cacheIntegration->traceMultiGet(
    ['user:123', 'product:456', 'category:789', 'nonexistent'],
    'redis',
    'redis',
    fn() => [
        'user:123' => $simulatedCache->get('user:123'),
        'product:456' => $simulatedCache->get('product:456'),
        'category:789' => $simulatedCache->get('category:789'),
    ]
);
echo "   Fetched " . count($results) . " items\n";

echo "\n5. Tracing SET:\n";
$cacheIntegration->traceSet(
    'session:abc',
    ['user_id' => 123, 'token' => 'token_xyz'],
    1800,
    'redis',
    'redis',
    fn() => $simulatedCache->set('session:abc', ['user_id' => 123], 1800)
);
echo "   Session cached\n";

echo "\n6. Tracing EXISTS check:\n";
$exists = $cacheIntegration->traceHas(
    'user:123',
    'redis',
    'redis',
    fn() => $simulatedCache->has('user:123')
);
echo "   Key exists: " . ($exists ? 'YES' : 'NO') . "\n";

echo "\n7. Tracing DELETE:\n";
$cacheIntegration->traceDelete(
    'session:abc',
    'redis',
    'redis',
    fn() => $simulatedCache->delete('session:abc')
);
echo "   Key deleted\n";

echo "\n8. Tracing DELETE (non-existent key):\n";
$cacheIntegration->traceDelete(
    'nonexistent:key',
    'redis',
    'redis',
    fn() => $simulatedCache->delete('nonexistent:key')
);
echo "   Non-existent key delete tracked\n";

echo "\n9. Tracing FLUSH:\n";
$cacheIntegration->traceFlush(
    'redis',
    'redis',
    fn() => $simulatedCache->flush()
);
echo "   Cache flushed\n";

echo "\n10. Cache statistics:\n";
$stats = $cacheIntegration->getStats();
echo "   Hits: " . $stats['hits'] . "\n";
echo "   Misses: " . $stats['misses'] . "\n";
echo "   Reads: " . $stats['reads'] . "\n";
echo "   Writes: " . $stats['writes'] . "\n";
echo "   Deletes: " . $stats['deletes'] . "\n";
echo "   Hit Rate: " . ($stats['hit_rate'] * 100) . "%\n";

echo "\nCache attributes captured:\n";
echo "   - vesvasi.cache = true\n";
echo "   - cache.operation = get/set/delete/etc\n";
echo "   - cache.store = store name\n";
echo "   - cache.key = cache key\n";
echo "   - cache.command = GET/SET/DELETE/MGET/etc\n";
echo "   - cache.driver = redis/memcached/file\n";
echo "   - cache.hit = true/false\n";
echo "   - cache.miss = true/false\n";
echo "   - cache.ttl_seconds = time to live\n";
echo "   - cache.value_size_bytes = serialized value size\n";
echo "   - cache.hit_rate = overall hit rate\n";

$vesvasi->shutdown();