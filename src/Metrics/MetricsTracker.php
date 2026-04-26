<?php

declare(strict_types=1);

namespace Vesvasi\Metrics;

use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Metrics\MeterProviderBuilder;
use OpenTelemetry\SDK\Metrics\MetricReader\ExportingReader;
use OpenTelemetry\SDK\Metrics\View\Aggregation\SumAggregation;
use OpenTelemetry\SDK\Metrics\View\Criteria;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use Vesvasi\Config\Config;
use Vesvasi\Metrics\Collectors\CpuCollector;
use Vesvasi\Metrics\Collectors\MemoryCollector;
use Vesvasi\Metrics\Collectors\RuntimeCollector;
use Vesvasi\Metrics\Collectors\CustomMetricsCollector;

final class MetricsTracker
{
    private Config $config;
    private ResourceInfo $resource;
    private MeterProvider $meterProvider;
    private CpuCollector $cpuCollector;
    private MemoryCollector $memoryCollector;
    private RuntimeCollector $runtimeCollector;
    private CustomMetricsCollector $customCollector;
    private bool $started = false;

    public function __construct(Config $config, ResourceInfo $resource)
    {
        $this->config = $config;
        $this->resource = $resource;
    }

    public function initialize(): void
    {
        if (!$this->config->getMetrics()->isEnabled()) {
            return;
        }

        $metricsConfig = $this->config->getMetrics();

        $this->initializeCollectors();
        $this->initializeMeterProvider();
        $this->started = true;
    }

    private function initializeCollectors(): void
    {
        $metricsConfig = $this->config->getMetrics();

        if ($metricsConfig->shouldCollectCpu()) {
            $this->cpuCollector = new CpuCollector();
        }

        if ($metricsConfig->shouldCollectMemory()) {
            $this->memoryCollector = new MemoryCollector();
        }

        if ($metricsConfig->shouldEnableRuntimeMetrics()) {
            $this->runtimeCollector = new RuntimeCollector();
        }

        $this->customCollector = new CustomMetricsCollector();
    }

    private function initializeMeterProvider(): void
    {
        $exporterFactory = new \Vesvasi\Exporters\OtlpExporterFactory($this->config);
        $metricExporter = $exporterFactory->createMetricExporter();

        $exportInterval = $this->config->getMetrics()->getExportInterval();

        $reader = new ExportingReader($metricExporter, $exportInterval);

        $this->meterProvider = (new MeterProviderBuilder())
            ->setResource($this->resource)
            ->addReader($reader)
            ->build();
    }

    public function recordCpuUsage(float $usage): void
    {
        if ($this->cpuCollector !== null) {
            $this->cpuCollector->record($usage);
        }
    }

    public function recordMemoryUsage(float $usedMb, float $availableMb = 0): void
    {
        if ($this->memoryCollector !== null) {
            $this->memoryCollector->record($usedMb, $availableMb);
        }
    }

    public function recordCustomMetric(string $name, float $value, array $attributes = []): void
    {
        $this->customCollector->record($name, $value, $attributes);
    }

    public function incrementCounter(string $name, float $value = 1.0, array $attributes = []): void
    {
        $this->customCollector->increment($name, $value, $attributes);
    }

    public function recordHistogram(string $name, float $value, array $attributes = []): void
    {
        $this->customCollector->recordHistogram($name, $value, $attributes);
    }

    public function getMeterProvider(): MeterProvider
    {
        return $this->meterProvider;
    }

    public function shutdown(): void
    {
        if (isset($this->meterProvider)) {
            $this->meterProvider->shutdown();
        }
    }

    public function flush(): void
    {
        if (isset($this->meterProvider)) {
            $this->meterProvider->forceFlush();
        }
    }

    public function isStarted(): bool
    {
        return $this->started;
    }

    public function getCpuCollector(): ?CpuCollector
    {
        return $this->cpuCollector ?? null;
    }

    public function getMemoryCollector(): ?MemoryCollector
    {
        return $this->memoryCollector ?? null;
    }

    public function getRuntimeCollector(): ?RuntimeCollector
    {
        return $this->runtimeCollector ?? null;
    }

    public function getCustomCollector(): CustomMetricsCollector
    {
        return $this->customCollector;
    }
}