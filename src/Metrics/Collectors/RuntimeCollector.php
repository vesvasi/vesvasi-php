<?php

declare(strict_types=1);

namespace Vesvasi\Metrics\Collectors;

use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Metrics\ObservableGauge;
use OpenTelemetry\SDK\Metrics\Counter;
use OpenTelemetry\SDK\Metrics\Histogram;

final class RuntimeCollector
{
    private array $counters = [];
    private array $histograms = [];
    private int $gcRuns = 0;
    private int $gcCollected = 0;
    private float $lastGcTime = 0;

    public function initialize(MeterProvider $meterProvider): void
    {
        $meter = $meterProvider->getMeter('vesvasi.runtime');

        $meter->createObservableGauge(
            'runtime.memory.heap_used',
            'bytes',
            'Used heap memory in bytes',
            function ($observer) {
                $observer->observe(memory_get_usage(true));
            }
        );

        $meter->createObservableGauge(
            'runtime.memory.heap_usage',
            'bytes',
            'Peak heap memory usage in bytes',
            function ($observer) {
                $observer->observe(memory_get_peak_usage(true));
            }
        );

        $meter->createObservableGauge(
            'runtime.gc.runs',
            null,
            'Number of garbage collection runs',
            function ($observer) {
                $observer->observe($this->gcRuns);
            }
        );

        $meter->createObservableGauge(
            'runtime.gc.collected',
            null,
            'Number of collected garbage',
            function ($observer) {
                $observer->observe($this->gcCollected);
            }
        );

        $meter->createObservableGauge(
            'runtime.classes.count',
            null,
            'Number of loaded classes',
            function ($observer) {
                $observer->observe(count(get_declared_classes()));
            }
        );

        $meter->createObservableGauge(
            'runtime.interfaces.count',
            null,
            'Number of loaded interfaces',
            function ($observer) {
                $observer->observe(count(get_declared_interfaces()));
            }
        );

        $meter->createObservableGauge(
            'runtime.constants.count',
            null,
            'Number of defined constants',
            function ($observer) {
                $observer->observe(count(get_defined_constants()));
            }
        );
    }

    public function recordGcStats(): void
    {
        if (function_exists('gc_collect_cycles')) {
            $collected = gc_collect_cycles();
            $this->gcRuns++;
            $this->gcCollected += $collected;
        }
    }

    public function getCounter(string $name): int
    {
        return $this->counters[$name] ?? 0;
    }

    public function incrementCounter(string $name, int $value = 1): void
    {
        if (!isset($this->counters[$name])) {
            $this->counters[$name] = 0;
        }
        $this->counters[$name] += $value;
    }

    public function recordValue(string $name, float $value): void
    {
        if (!isset($this->histograms[$name])) {
            $this->histograms[$name] = [];
        }
        $this->histograms[$name][] = $value;
    }

    public function getHistogramStats(string $name): array
    {
        if (!isset($this->histograms[$name]) || empty($this->histograms[$name])) {
            return ['count' => 0, 'min' => 0, 'max' => 0, 'avg' => 0];
        }

        $values = $this->histograms[$name];
        $count = count($values);

        return [
            'count' => $count,
            'min' => min($values),
            'max' => max($values),
            'avg' => array_sum($values) / $count,
        ];
    }
}