<?php

declare(strict_types=1);

namespace Vesvasi\Metrics\Collectors;

use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Metrics\MetricExporterInterface;
use OpenTelemetry\SDK\Metrics\ObservableGauge;

final class CpuCollector
{
    private ?object $gauge = null;
    private float $lastValue = 0.0;
    private string $unit = 'percent';
    private string $description = 'CPU usage percentage';

    public function __construct()
    {
    }

    public function initialize(MeterProvider $meterProvider): void
    {
        $meter = $meterProvider->getMeter('vesvasi.system');
        $this->gauge = $meter->createObservableGauge(
            'system.cpu.usage',
            $this->unit,
            $this->description,
            function ($observer) {
                $observer->observe($this->getCurrentUsage());
            }
        );
    }

    public function record(float $usage): void
    {
        $this->lastValue = max(0.0, min(100.0, $usage));
    }

    public function getCurrentUsage(): float
    {
        if ($this->lastValue > 0) {
            return $this->lastValue;
        }

        return $this->measureCpuUsage();
    }

    private function measureCpuUsage(): float
    {
        if (PHP_OS_FAMILY === 'Linux') {
            return $this->measureLinuxCpu();
        }

        if (PHP_OS_FAMILY === 'Darwin') {
            return $this->measureMacCpu();
        }

        return $this->estimateCpuUsage();
    }

    private function measureLinuxCpu(): float
    {
        $statFile = '/proc/stat';
        if (!file_exists($statFile)) {
            return $this->estimateCpuUsage();
        }

        $content = file_get_contents($statFile);
        if ($content === false) {
            return 0.0;
        }

        preg_match('/^cpu\s+(.*)$/m', $content, $matches);
        if (!isset($matches[1])) {
            return 0.0;
        }

        $values = array_map('intval', explode(' ', trim($matches[1])));
        if (count($values) < 4) {
            return 0.0;
        }

        $total = array_sum($values);
        $idle = $values[3];

        static $lastTotal = null;
        static $lastIdle = null;

        if ($lastTotal !== null && $lastIdle !== null) {
            $totalDiff = $total - $lastTotal;
            $idleDiff = $idle - $lastIdle;

            if ($totalDiff > 0) {
                $usage = (($totalDiff - $idleDiff) / $totalDiff) * 100;
                $lastTotal = $total;
                $lastIdle = $idle;
                return max(0.0, min(100.0, $usage));
            }
        }

        $lastTotal = $total;
        $lastIdle = $idle;

        return 0.0;
    }

    private function measureMacCpu(): float
    {
        $output = [];
        exec('top -l 1 -n 1', $output);

        foreach ($output as $line) {
            if (str_contains($line, 'CPU usage:')) {
                preg_match('/(\d+\.?\d*)%/', $line, $matches);
                if (isset($matches[1])) {
                    return (float) $matches[1];
                }
            }
        }

        return $this->estimateCpuUsage();
    }

    private function estimateCpuUsage(): float
    {
        $load = sys_getloadavg();
        if ($load !== false && isset($load[0])) {
            $numCpus = $this->getNumberOfCpus();
            if ($numCpus > 0) {
                return min(100.0, ($load[0] / $numCpus) * 100);
            }
            return min(100.0, $load[0] * 25);
        }

        return 0.0;
    }

    private function getNumberOfCpus(): int
    {
        if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/cpuinfo')) {
            $content = file_get_contents('/proc/cpuinfo');
            preg_match_all('/^processor\s*:/m', $content, $matches);
            if (!empty($matches[0])) {
                return count($matches[0]);
            }
        }

        return (int) shell_exec('nproc') ?: 1;
    }

    public function getLastValue(): float
    {
        return $this->lastValue;
    }
}