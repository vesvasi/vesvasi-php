<?php

declare(strict_types=1);

namespace Vesvasi\Metrics\Collectors;

use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Metrics\Counter;
use OpenTelemetry\SDK\Metrics\Histogram;
use OpenTelemetry\SDK\Metrics\ObservableGauge;

final class CustomMetricsCollector
{
    private array $counters = [];
    private array $histograms = [];
    private array $gauges = [];
    private array $gaugeValues = [];
    private ?MeterProvider $meterProvider = null;
    private ?object $meter = null;
    private array $meterHandles = [];

    public function initialize(MeterProvider $meterProvider): void
    {
        $this->meterProvider = $meterProvider;
        $this->meter = $meterProvider->getMeter('vesvasi.custom');
    }

    public function createCounter(string $name, string $unit = '', string $description = ''): void
    {
        if ($this->meter !== null && !isset($this->counters[$name])) {
            $this->counters[$name] = $this->meter->createCounter($name, $unit, $description);
        }
    }

    public function createHistogram(string $name, string $unit = '', string $description = ''): void
    {
        if ($this->meter !== null && !isset($this->histograms[$name])) {
            $this->histograms[$name] = $this->meter->createHistogram($name, $unit, $description);
        }
    }

    public function createObservableGauge(
        string $name,
        callable $callback,
        string $unit = '',
        string $description = ''
    ): void {
        if ($this->meter !== null && !isset($this->gauges[$name])) {
            $this->gauges[$name] = $this->meter->createObservableGauge(
                $name,
                $unit,
                $description,
                function ($observer) use ($callback) {
                    $observer->observe(call_user_func($callback));
                }
            );
            $this->gaugeValues[$name] = 0;
        }
    }

    public function increment(string $name, float $value = 1.0, array $attributes = []): void
    {
        if (!isset($this->counters[$name])) {
            $this->createCounter($name);
        }

        if (isset($this->counters[$name])) {
            $this->counters[$name]->add($value, $attributes);
        }
    }

    public function record(string $name, float $value, array $attributes = []): void
    {
        if (!isset($this->histograms[$name])) {
            $this->createHistogram($name);
        }

        if (isset($this->histograms[$name])) {
            $this->histograms[$name]->record($value, $attributes);
        }
    }

    public function recordHistogram(string $name, float $value, array $attributes = []): void
    {
        $this->record($name, $value, $attributes);
    }

    public function setGauge(string $name, float $value): void
    {
        $this->gaugeValues[$name] = $value;
    }

    public function getCounterValue(string $name): float
    {
        return $this->counters[$name] ?? 0;
    }

    public function getHistogramValues(string $name): array
    {
        return $this->histograms[$name] ?? [];
    }

    public function getGaugeValue(string $name): float
    {
        return $this->gaugeValues[$name] ?? 0;
    }

    public function getAllCounterNames(): array
    {
        return array_keys($this->counters);
    }

    public function getAllHistogramNames(): array
    {
        return array_keys($this->histograms);
    }

    public function getAllGaugeNames(): array
    {
        return array_keys($this->gauges);
    }

    public function getMetricsSummary(): array
    {
        return [
            'counters' => count($this->counters),
            'histograms' => count($this->histograms),
            'gauges' => count($this->gauges),
            'counter_names' => $this->getAllCounterNames(),
            'histogram_names' => $this->getAllHistogramNames(),
            'gauge_names' => $this->getAllGaugeNames(),
        ];
    }

    public function reset(): void
    {
        $this->counters = [];
        $this->histograms = [];
        $this->gauges = [];
        $this->gaugeValues = [];
        $this->meterHandles = [];
    }
}