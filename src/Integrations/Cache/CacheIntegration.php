<?php

declare(strict_types=1);

namespace Vesvasi\Integrations\Cache;

use Vesvasi\Config\Config;
use Vesvasi\Trace\VesvasiTracer;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;

final class CacheIntegration
{
    private Config $config;
    private VesvasiTracer $tracer;
    private array $stats = [
        'hits' => 0,
        'misses' => 0,
        'reads' => 0,
        'writes' => 0,
        'deletes' => 0,
        'hits_by_store' => [],
        'misses_by_store' => [],
    ];

    private const ATTR_VESVASI_CACHE = 'vesvasi.cache';
    private const ATTR_CACHE_OPERATION = 'cache.operation';
    private const ATTR_CACHE_STORE = 'cache.store';
    private const ATTR_CACHE_KEY = 'cache.key';
    private const ATTR_CACHE_KEY_PREFIX = 'cache.key_prefix';
    private const ATTR_CACHE_VALUE_SIZE = 'cache.value_size_bytes';
    private const ATTR_CACHE_TTL = 'cache.ttl_seconds';
    private const ATTR_CACHE_TTL_REMAINING = 'cache.ttl_remaining_seconds';
    private const ATTR_CACHE_HIT = 'cache.hit';
    private const ATTR_CACHE_MISS = 'cache.miss';
    private const ATTR_CACHE_EXISTS = 'cache.exists';
    private const ATTR_CACHE_DRIVER = 'cache.driver';
    private const ATTR_CACHE_COMMAND = 'cache.command';
    private const ATTR_CACHE_ITEMS_COUNT = 'cache.items_count';
    private const ATTR_CACHE_MEMORY_USED = 'cache.memory_used_bytes';
    private const ATTR_CACHE_HIT_RATE = 'cache.hit_rate';

    public function __construct(Config $config, VesvasiTracer $tracer)
    {
        $this->config = $config;
        $this->tracer = $tracer;
    }

    public function traceGet(
        string $key,
        string $store = 'default',
        ?string $driver = null,
        callable $callback = null
    ): ?array {
        $span = $this->createGetSpan($key, $store, $driver);
        $startTime = microtime(true);

        try {
            $value = $callback !== null ? $callback() : null;
            $isHit = $value !== null;

            $this->recordHitMiss($store, $isHit);
            $this->setHitMissAttributes($span, $isHit);

            $span->setAttribute('cache.value_size_bytes', strlen(serialize($value ?? '')));
            $span->setStatus(StatusCode::STATUS_OK);
            $span->end();

            $this->recordDuration($span, $startTime);

            return $value;
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            $span->end();
            throw $e;
        }
    }

    public function traceMultiGet(
        array $keys,
        string $store = 'default',
        ?string $driver = null,
        callable $callback = null
    ): array {
        $span = $this->createMultiGetSpan($keys, $store, $driver);
        $startTime = microtime(true);
        $startMemory = memory_get_usage();

        try {
            $values = $callback !== null ? $callback() : [];

            $hits = 0;
            $misses = 0;

            foreach ($keys as $key) {
                if (isset($values[$key])) {
                    $hits++;
                    $this->recordHitMiss($store, true);
                } else {
                    $misses++;
                    $this->recordHitMiss($store, false);
                }
            }

            $span->setAttribute(self::ATTR_CACHE_ITEMS_COUNT, count($keys));
            $span->setAttribute('cache.hits', $hits);
            $span->setAttribute('cache.misses', $misses);
            $span->setAttribute('cache.hit_rate', count($keys) > 0 ? round($hits / count($keys), 4) : 0);
            $span->setAttribute(self::ATTR_CACHE_VALUE_SIZE, memory_get_usage() - $startMemory);
            $span->setStatus(StatusCode::STATUS_OK);
            $span->end();

            $this->recordDuration($span, $startTime);

            return $values;
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            $span->end();
            throw $e;
        }
    }

    public function traceSet(
        string $key,
        mixed $value,
        ?int $ttl = null,
        string $store = 'default',
        ?string $driver = null,
        callable $callback = null
    ): bool {
        $span = $this->createSetSpan($key, $store, $driver, $ttl);
        $startTime = microtime(true);
        $valueSize = strlen(serialize($value));

        try {
            $result = $callback !== null ? $callback() : true;

            $this->stats['writes']++;
            $span->setAttribute(self::ATTR_CACHE_VALUE_SIZE, $valueSize);

            if ($ttl !== null) {
                $span->setAttribute(self::ATTR_CACHE_TTL, $ttl);
            }

            $span->setStatus(StatusCode::STATUS_OK);
            $span->end();

            $this->recordDuration($span, $startTime);

            return $result;
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            $span->end();
            throw $e;
        }
    }

    public function traceMultiSet(
        array $items,
        ?int $ttl = null,
        string $store = 'default',
        ?string $driver = null,
        callable $callback = null
    ): bool {
        $span = $this->createMultiSetSpan($items, $store, $driver, $ttl);
        $startTime = microtime(true);
        $totalSize = 0;

        foreach ($items as $value) {
            $totalSize += strlen(serialize($value));
        }

        try {
            $result = $callback !== null ? $callback() : true;

            $this->stats['writes'] += count($items);
            $span->setAttribute(self::ATTR_CACHE_ITEMS_COUNT, count($items));
            $span->setAttribute(self::ATTR_CACHE_VALUE_SIZE, $totalSize);

            if ($ttl !== null) {
                $span->setAttribute(self::ATTR_CACHE_TTL, $ttl);
            }

            $span->setStatus(StatusCode::STATUS_OK);
            $span->end();

            $this->recordDuration($span, $startTime);

            return $result;
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            $span->end();
            throw $e;
        }
    }

    public function traceDelete(
        string $key,
        string $store = 'default',
        ?string $driver = null,
        callable $callback = null
    ): bool {
        $span = $this->createDeleteSpan($key, $store, $driver);
        $startTime = microtime(true);

        try {
            $result = $callback !== null ? $callback() : true;

            $this->stats['deletes']++;
            $span->setStatus(StatusCode::STATUS_OK);
            $span->end();

            $this->recordDuration($span, $startTime);

            return $result;
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            $span->end();
            throw $e;
        }
    }

    public function traceHas(
        string $key,
        string $store = 'default',
        ?string $driver = null,
        callable $callback = null
    ): bool {
        $span = $this->createHasSpan($key, $store, $driver);
        $startTime = microtime(true);

        try {
            $exists = $callback !== null ? $callback() : false;

            $span->setAttribute(self::ATTR_CACHE_EXISTS, $exists);
            $span->setStatus(StatusCode::STATUS_OK);
            $span->end();

            $this->recordDuration($span, $startTime);

            return $exists;
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            $span->end();
            throw $e;
        }
    }

    public function traceFlush(
        string $store = 'default',
        ?string $driver = null,
        callable $callback = null
    ): bool {
        $span = $this->createFlushSpan($store, $driver);
        $startTime = microtime(true);

        try {
            $result = $callback !== null ? $callback() : true;

            $span->setStatus(StatusCode::STATUS_OK);
            $span->end();

            $this->recordDuration($span, $startTime);

            return $result;
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            $span->end();
            throw $e;
        }
    }

    public function traceRemember(
        string $key,
        ?int $ttl,
        callable $callback,
        string $store = 'default',
        ?string $driver = null
    ): mixed {
        $span = $this->createGetSpan($key, $store, $driver);
        $startTime = microtime(true);

        try {
            $value = $callback !== null ? $callback() : null;

            if ($value !== null) {
                $this->setSpanTtlAttributes($span, $ttl);
            }

            $span->setAttribute(self::ATTR_CACHE_MISS, true);
            $span->setAttribute(self::ATTR_CACHE_HIT, false);
            $this->recordHitMiss($store, false);
            $this->stats['misses']++;

            $span->setStatus(StatusCode::STATUS_OK);
            $span->end();

            $this->recordDuration($span, $startTime);

            return $value;
        } catch (\Throwable $e) {
            $span->recordException($e);
            $span->setStatus(StatusCode::STATUS_ERROR, $e->getMessage());
            $span->end();
            throw $e;
        }
    }

    public function createGetSpan(string $key, string $store, ?string $driver): SpanInterface
    {
        return $this->tracer->spanBuilder("Cache GET {$key}")
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute(self::ATTR_VESVASI_CACHE, true)
            ->setAttribute(self::ATTR_CACHE_OPERATION, 'get')
            ->setAttribute(self::ATTR_CACHE_STORE, $store)
            ->setAttribute(self::ATTR_CACHE_KEY, $key)
            ->setAttribute(self::ATTR_CACHE_COMMAND, 'GET')
            ->setAttribute(self::ATTR_CACHE_DRIVER, $driver ?? $this->detectDriver($store))
            ->startSpan();
    }

    private function createMultiGetSpan(array $keys, string $store, ?string $driver): SpanInterface
    {
        $firstKey = $keys[0] ?? 'unknown';
        return $this->tracer->spanBuilder("Cache MGET [{$firstKey}] + " . (count($keys) - 1) . " more")
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute(self::ATTR_VESVASI_CACHE, true)
            ->setAttribute(self::ATTR_CACHE_OPERATION, 'multi_get')
            ->setAttribute(self::ATTR_CACHE_STORE, $store)
            ->setAttribute(self::ATTR_CACHE_KEY_PREFIX, $firstKey)
            ->setAttribute(self::ATTR_CACHE_ITEMS_COUNT, count($keys))
            ->setAttribute(self::ATTR_CACHE_COMMAND, 'MGET')
            ->setAttribute(self::ATTR_CACHE_DRIVER, $driver ?? $this->detectDriver($store))
            ->startSpan();
    }

    private function createSetSpan(string $key, string $store, ?string $driver, ?int $ttl): SpanInterface
    {
        return $this->tracer->spanBuilder("Cache SET {$key}")
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute(self::ATTR_VESVASI_CACHE, true)
            ->setAttribute(self::ATTR_CACHE_OPERATION, 'set')
            ->setAttribute(self::ATTR_CACHE_STORE, $store)
            ->setAttribute(self::ATTR_CACHE_KEY, $key)
            ->setAttribute(self::ATTR_CACHE_COMMAND, 'SET')
            ->setAttribute(self::ATTR_CACHE_DRIVER, $driver ?? $this->detectDriver($store))
            ->startSpan();
    }

    private function createMultiSetSpan(array $items, string $store, ?string $driver, ?int $ttl): SpanInterface
    {
        $firstKey = array_key_first($items) ?? 'unknown';
        return $this->tracer->spanBuilder("Cache MSET [{$firstKey}] + " . (count($items) - 1) . " more")
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute(self::ATTR_VESVASI_CACHE, true)
            ->setAttribute(self::ATTR_CACHE_OPERATION, 'multi_set')
            ->setAttribute(self::ATTR_CACHE_STORE, $store)
            ->setAttribute(self::ATTR_CACHE_ITEMS_COUNT, count($items))
            ->setAttribute(self::ATTR_CACHE_COMMAND, 'MSET')
            ->setAttribute(self::ATTR_CACHE_DRIVER, $driver ?? $this->detectDriver($store))
            ->startSpan();
    }

    private function createDeleteSpan(string $key, string $store, ?string $driver): SpanInterface
    {
        return $this->tracer->spanBuilder("Cache DELETE {$key}")
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute(self::ATTR_VESVASI_CACHE, true)
            ->setAttribute(self::ATTR_CACHE_OPERATION, 'delete')
            ->setAttribute(self::ATTR_CACHE_STORE, $store)
            ->setAttribute(self::ATTR_CACHE_KEY, $key)
            ->setAttribute(self::ATTR_CACHE_COMMAND, 'DELETE')
            ->setAttribute(self::ATTR_CACHE_DRIVER, $driver ?? $this->detectDriver($store))
            ->startSpan();
    }

    private function createHasSpan(string $key, string $store, ?string $driver): SpanInterface
    {
        return $this->tracer->spanBuilder("Cache EXISTS {$key}")
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute(self::ATTR_VESVASI_CACHE, true)
            ->setAttribute(self::ATTR_CACHE_OPERATION, 'exists')
            ->setAttribute(self::ATTR_CACHE_STORE, $store)
            ->setAttribute(self::ATTR_CACHE_KEY, $key)
            ->setAttribute(self::ATTR_CACHE_COMMAND, 'EXISTS')
            ->setAttribute(self::ATTR_CACHE_DRIVER, $driver ?? $this->detectDriver($store))
            ->startSpan();
    }

    private function createFlushSpan(string $store, ?string $driver): SpanInterface
    {
        return $this->tracer->spanBuilder("Cache FLUSH {$store}")
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute(self::ATTR_VESVASI_CACHE, true)
            ->setAttribute(self::ATTR_CACHE_OPERATION, 'flush')
            ->setAttribute(self::ATTR_CACHE_STORE, $store)
            ->setAttribute(self::ATTR_CACHE_COMMAND, 'FLUSHDB')
            ->setAttribute(self::ATTR_CACHE_DRIVER, $driver ?? $this->detectDriver($store))
            ->startSpan();
    }

    private function setHitMissAttributes(SpanInterface $span, bool $isHit): void
    {
        $span->setAttribute(self::ATTR_CACHE_HIT, $isHit);
        $span->setAttribute(self::ATTR_CACHE_MISS, !$isHit);
        $span->setAttribute(self::ATTR_CACHE_EXISTS, $isHit);
    }

    private function setSpanTtlAttributes(SpanInterface $span, ?int $ttl): void
    {
        if ($ttl !== null) {
            $span->setAttribute(self::ATTR_CACHE_TTL, $ttl);
            $span->setAttribute(self::ATTR_CACHE_TTL_REMAINING, $ttl);
        }
    }

    private function recordHitMiss(string $store, bool $isHit): void
    {
        if ($isHit) {
            $this->stats['hits']++;
            if (!isset($this->stats['hits_by_store'][$store])) {
                $this->stats['hits_by_store'][$store] = 0;
            }
            $this->stats['hits_by_store'][$store]++;
        } else {
            $this->stats['misses']++;
            if (!isset($this->stats['misses_by_store'][$store])) {
                $this->stats['misses_by_store'][$store] = 0;
            }
            $this->stats['misses_by_store'][$store]++;
        }
        $this->stats['reads']++;
    }

    private function recordDuration(SpanInterface $span, float $startTime): void
    {
        $duration = (microtime(true) - $startTime) * 1000;
        $span->setAttribute('vesvasi.cache.duration_ms', round($duration, 2));
    }

    private function detectDriver(string $store): string
    {
        $storeDrivers = [
            'redis' => 'redis',
            'memcached' => 'memcached',
            'file' => 'file',
            'array' => 'array',
        ];

        foreach ($storeDrivers as $prefix => $driver) {
            if (str_starts_with($store, $prefix)) {
                return $driver;
            }
        }

        return 'unknown';
    }

    public function getStats(): array
    {
        $total = $this->stats['hits'] + $this->stats['misses'];
        $hitRate = $total > 0 ? round($this->stats['hits'] / $total, 4) : 0;

        return array_merge($this->stats, [
            'total_reads' => $this->stats['reads'],
            'total_writes' => $this->stats['writes'],
            'total_deletes' => $this->stats['deletes'],
            'hit_rate' => $hitRate,
            'miss_rate' => 1 - $hitRate,
        ]);
    }

    public function resetStats(): void
    {
        $this->stats = [
            'hits' => 0,
            'misses' => 0,
            'reads' => 0,
            'writes' => 0,
            'deletes' => 0,
            'hits_by_store' => [],
            'misses_by_store' => [],
        ];
    }

    public function shutdown(): void
    {
        $this->resetStats();
    }
}