<?php

declare(strict_types=1);

namespace Vesvasi\Config;

use InvalidArgumentException;
use Vesvasi\Config\Filters\FilterConfig;
use Vesvasi\Config\Logs\LogsConfig;
use Vesvasi\Config\Metrics\MetricsConfig;
use Vesvasi\Config\Network\NetworkConfig;
use Vesvasi\Config\Sampling\SamplingConfig;
use Vesvasi\Config\Service\ServiceConfig;

final class Config
{
    private const DEFAULT_TIMEOUT = 30;
    private const DEFAULT_MAX_QUEUE_SIZE = 2048;
    private const DEFAULT_MAX_BATCH_SIZE = 512;

    private string $apiKey;
    private string $endpoint;
    private string $protocol;
    private int $timeout;
    private ServiceConfig $service;
    private SamplingConfig $sampling;
    private FilterConfig $filters;
    private MetricsConfig $metrics;
    private LogsConfig $logs;
    private NetworkConfig $network;
    private int $maxQueueSize;
    private int $maxBatchSize;
    private bool $debug;

    private static ?Config $instance = null;

    private function __construct(array $config)
    {
        $this->apiKey = $config['api_key'] ?? '';
        $this->endpoint = $this->validateEndpoint($config['endpoint'] ?? '');
        $this->protocol = $this->validateProtocol($config['protocol'] ?? 'http/protobuf');
        $this->timeout = (int) ($config['timeout'] ?? self::DEFAULT_TIMEOUT);
        $this->maxQueueSize = (int) ($config['max_queue_size'] ?? self::DEFAULT_MAX_QUEUE_SIZE);
        $this->maxBatchSize = (int) ($config['max_batch_size'] ?? self::DEFAULT_MAX_BATCH_SIZE);
        $this->debug = (bool) ($config['debug'] ?? false);

        $this->service = new ServiceConfig($config['service'] ?? []);
        $this->sampling = new SamplingConfig($config['sampling'] ?? []);
        $this->filters = new FilterConfig($config['filters'] ?? []);
        $this->metrics = new MetricsConfig($config['metrics'] ?? []);
        $this->logs = new LogsConfig($config['logs'] ?? []);
        $this->network = new NetworkConfig($config['network'] ?? []);
    }

    public static function load(array $config): self
    {
        if (self::$instance !== null) {
            throw new InvalidArgumentException('Config already loaded. Use reset() first.');
        }

        self::$instance = new self($config);
        return self::$instance;
    }

    public static function get(): self
    {
        if (self::$instance === null) {
            throw new InvalidArgumentException('Config not loaded. Call load() first.');
        }

        return self::$instance;
    }

    public static function reset(): void
    {
        self::$instance = null;
    }

    private function validateEndpoint(string $endpoint): string
    {
        if (empty($endpoint)) {
            throw new InvalidArgumentException('Endpoint is required');
        }

        $parsed = parse_url($endpoint);
        if ($parsed === false || !isset($parsed['scheme']) || !isset($parsed['host'])) {
            throw new InvalidArgumentException("Invalid endpoint URL: {$endpoint}");
        }

        return rtrim($endpoint, '/');
    }

    private function validateProtocol(string $protocol): string
    {
        $supported = ['http/protobuf', 'http/json', 'grpc'];

        if (!in_array($protocol, $supported, true)) {
            throw new InvalidArgumentException(
                "Unsupported protocol: {$protocol}. Supported: " . implode(', ', $supported)
            );
        }

        return $protocol;
    }

    public function getApiKey(): string
    {
        return $this->apiKey;
    }

    public function hasApiKey(): bool
    {
        return $this->apiKey !== '';
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function getProtocol(): string
    {
        return $this->protocol;
    }

    public function getTimeout(): int
    {
        return $this->timeout;
    }

    public function getService(): ServiceConfig
    {
        return $this->service;
    }

    public function getSampling(): SamplingConfig
    {
        return $this->sampling;
    }

    public function getFilters(): FilterConfig
    {
        return $this->filters;
    }

    public function getMetrics(): MetricsConfig
    {
        return $this->metrics;
    }

    public function getLogs(): LogsConfig
    {
        return $this->logs;
    }

    public function getNetwork(): NetworkConfig
    {
        return $this->network;
    }

    public function getMaxQueueSize(): int
    {
        return $this->maxQueueSize;
    }

    public function getMaxBatchSize(): int
    {
        return $this->maxBatchSize;
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    public function toArray(): array
    {
        return [
            'api_key' => $this->apiKey,
            'endpoint' => $this->endpoint,
            'protocol' => $this->protocol,
            'timeout' => $this->timeout,
            'max_queue_size' => $this->maxQueueSize,
            'max_batch_size' => $this->maxBatchSize,
            'debug' => $this->debug,
            'service' => $this->service->toArray(),
            'sampling' => $this->sampling->toArray(),
            'filters' => $this->filters->toArray(),
            'metrics' => $this->metrics->toArray(),
            'logs' => $this->logs->toArray(),
            'network' => $this->network->toArray(),
        ];
    }
}