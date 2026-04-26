<?php

declare(strict_types=1);

namespace Vesvasi;

use Vesvasi\Config\Config;
use Vesvasi\Trace\VesvasiTracer;
use Vesvasi\Trace\TracerProviderFactory;
use Vesvasi\Metrics\MetricsTracker;
use Vesvasi\Integrations\AutoInstrumentation;
use Vesvasi\Log\VesvasiLogger;
use Vesvasi\Log\VesvasiLogProcessor;
use Vesvasi\Util\ProcessIdentifier;

final class Vesvasi
{
    private static ?Vesvasi $instance = null;

    private Config $config;
    private VesvasiTracer $tracer;
    private \OpenTelemetry\SDK\Trace\TracerProvider $tracerProvider;
    private MetricsTracker $metricsTracker;
    private AutoInstrumentation $autoInstrumentation;
    private VesvasiLogProcessor $logProcessor;
    private VesvasiLogger $logger;
    private bool $initialized = false;
    private bool $enabled = true;
    private array $serviceAttributes = [];
    private array $customAttributes = [];

    private function __construct(Config $config)
    {
        $this->config = $config;
        $this->serviceAttributes = $config->getService()->getResourceAttributes();
    }

    public static function configure(array $config): self
    {
        $config = Config::load($config);
        self::$instance = new self($config);
        self::$instance->initialize();
        return self::$instance;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            throw new \RuntimeException('Vesvasi not configured. Call Vesvasi::configure() first.');
        }
        return self::$instance;
    }

    public static function isConfigured(): bool
    {
        return self::$instance !== null && self::$instance->initialized;
    }

    public static function reset(): void
    {
        if (self::$instance !== null) {
            self::$instance->shutdown();
        }
        Config::reset();
        self::$instance = null;
    }

    private function initialize(): void
    {
        $tracerProviderFactory = new TracerProviderFactory($this->config);
        $this->tracerProvider = $tracerProviderFactory->create();

        $innerTracer = $this->tracerProvider->getTracer('vesvasi', '1.0.0');
        $this->tracer = new VesvasiTracer($innerTracer);

        $this->metricsTracker = new MetricsTracker($this->config, $tracerProviderFactory->getResource());
        $this->metricsTracker->initialize();

        $this->autoInstrumentation = new AutoInstrumentation($this->config, $this->tracer);
        $this->autoInstrumentation->initialize();

        $this->logProcessor = new VesvasiLogProcessor($this->config);
        $this->logger = new VesvasiLogger($this->logProcessor);

        if ($this->config->getLogs()->isEnabled()) {
            $this->logProcessor->setExporter(new \Vesvasi\Exporters\VesvasiLogExporter($this->config));
        }

        $this->initialized = true;
    }

    public function tracer(): VesvasiTracer
    {
        return $this->tracer;
    }

    public function metrics(): MetricsTracker
    {
        return $this->metricsTracker;
    }

    public function instrumentation(): AutoInstrumentation
    {
        return $this->autoInstrumentation;
    }

    public function logger(): VesvasiLogger
    {
        return $this->logger;
    }

    public function logProcessor(): VesvasiLogProcessor
    {
        return $this->logProcessor;
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function addCustomAttribute(string $key, mixed $value): void
    {
        $this->customAttributes[$key] = $value;
    }

    public function addCustomAttributes(array $attributes): void
    {
        $this->customAttributes = array_merge($this->customAttributes, $attributes);
    }

    public function getCustomAttributes(): array
    {
        return $this->customAttributes;
    }

    public function getServiceAttributes(): array
    {
        return $this->serviceAttributes;
    }

    public function getAllAttributes(): array
    {
        return array_merge($this->serviceAttributes, $this->customAttributes);
    }

    public function getServiceName(): string
    {
        return $this->config->getService()->getName();
    }

    public function getServiceVersion(): string
    {
        return $this->config->getService()->getVersion();
    }

    public function getEnvironment(): string
    {
        return $this->config->getService()->getEnvironment();
    }

    public function enable(): void
    {
        $this->enabled = true;
        $this->autoInstrumentation->enable();
        $this->logProcessor->enable();
    }

    public function disable(): void
    {
        $this->enabled = false;
        $this->autoInstrumentation->disable();
        $this->logProcessor->disable();
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function flush(): void
    {
        $this->tracerProvider->forceFlush();
        $this->metricsTracker->flush();
        $this->logProcessor->flush();
    }

    public function shutdown(): void
    {
        $this->flush();
        $this->tracerProvider->shutdown();
        $this->autoInstrumentation->shutdown();
        $this->metricsTracker->shutdown();
        $this->logProcessor->shutdown();
    }

    public function isInitialized(): bool
    {
        return $this->initialized;
    }

    public static function startSpan(string $name, array $attributes = []): \OpenTelemetry\API\Trace\SpanInterface
    {
        return self::getInstance()->tracer()->spanBuilder($name)
            ->setAttributes($attributes)
            ->startSpan();
    }

    public static function startSqlSpan(
        string $operation,
        string $sql,
        ?string $dbName = null,
        ?string $dbDriver = null,
        ?array $bindings = null
    ): \OpenTelemetry\API\Trace\SpanInterface {
        return self::getInstance()->tracer()->createSqlSpan($operation, $sql, $dbName, $dbDriver, $bindings);
    }

    public static function startHttpSpan(
        string $method,
        string $url,
        ?int $statusCode = null,
        ?array $headers = null
    ): Vesvasi\Trace\VesvasiSpanBuilder {
        return self::getInstance()->tracer()->createHttpSpan($method, $url, $statusCode, $headers);
    }

    public static function startCommandSpan(
        string $command,
        ?array $arguments = null,
        ?int $exitCode = null
    ): Vesvasi\Trace\VesvasiSpanBuilder {
        return self::getInstance()->tracer()->createCommandSpan($command, $arguments, $exitCode);
    }

    public static function recordError(\Throwable $exception): void
    {
        self::getInstance()->tracer()->recordException($exception);
    }

    public static function recordMetric(string $name, float $value, array $attributes = []): void
    {
        self::getInstance()->metrics()->recordCustomMetric($name, $value, $attributes);
    }

    public static function incrementCounter(string $name, float $value = 1.0, array $attributes = []): void
    {
        self::getInstance()->metrics()->incrementCounter($name, $value, $attributes);
    }

    public static function log(string $level, string $message, array $context = []): void
    {
        self::getInstance()->logger()->log($level, $message, $context);
    }

    public static function startRequestSpan(
        string $method,
        string $url,
        ?array $headers = null,
        ?string $body = null
    ): \OpenTelemetry\API\Trace\SpanInterface {
        return self::getInstance()
            ->instrumentation()
            ->getRequestIntegration()
            ->startRequestSpan($method, $url, $headers, $body);
    }

    public static function endRequestSpan(
        \OpenTelemetry\API\Trace\SpanInterface $span,
        int $statusCode,
        ?array $responseHeaders = null,
        ?int $responseSize = null
    ): void {
        self::getInstance()
            ->instrumentation()
            ->getRequestIntegration()
            ->endRequestSpan($span, $statusCode, $responseHeaders, $responseSize);
    }

    public static function cacheGet(
        string $key,
        string $store = 'default',
        callable $callback = null
    ): ?array {
        return self::getInstance()
            ->instrumentation()
            ->getCacheIntegration()
            ->traceGet($key, $store, null, $callback);
    }

    public static function cacheSet(
        string $key,
        mixed $value,
        ?int $ttl = null,
        string $store = 'default',
        callable $callback = null
    ): bool {
        return self::getInstance()
            ->instrumentation()
            ->getCacheIntegration()
            ->traceSet($key, $value, $ttl, $store, null, $callback);
    }

    public static function cacheDelete(string $key, string $store = 'default'): bool {
        return self::getInstance()
            ->instrumentation()
            ->getCacheIntegration()
            ->traceDelete($key, $store);
    }

    public static function queuePush(
        string $job,
        array $payload = [],
        string $queue = 'default'
    ): ?string {
        return self::getInstance()
            ->instrumentation()
            ->getQueueIntegration()
            ->tracePush($job, $payload, $queue);
    }

    public static function queueJob(
        string $jobName,
        string $jobId,
        string $queue = 'default',
        int $attempts = 1,
        callable $callback = null
    ): mixed {
        return self::getInstance()
            ->instrumentation()
            ->getQueueIntegration()
            ->traceJob($jobName, $jobId, $queue, 'default', $attempts, null, $callback);
    }
}