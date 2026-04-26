<?php

declare(strict_types=1);

namespace Vesvasi\Metrics\Collectors;

use OpenTelemetry\SDK\Metrics\MeterProvider;
use OpenTelemetry\SDK\Metrics\ObservableGauge;

final class MemoryCollector
{
    private float $usedMb = 0.0;
    private float $availableMb = 0.0;
    private float $peakUsedMb = 0.0;
    private float $limitMb = 0.0;
    private string $unit = 'MB';
    private string $description = 'Memory usage in megabytes';

    public function __construct()
    {
    }

    public function initialize(MeterProvider $meterProvider): void
    {
        $meter = $meterProvider->getMeter('vesvasi.system');
        $meter->createObservableGauge(
            'system.memory.usage',
            $this->unit,
            $this->description,
            function ($observer) {
                $observer->observe($this->getCurrentUsage());
            }
        );

        $meter->createObservableGauge(
            'system.memory.available',
            $this->unit,
            'Available memory in megabytes',
            function ($observer) {
                $observer->observe($this->availableMb);
            }
        );

        $meter->createObservableGauge(
            'system.memory.used',
            $this->unit,
            'Used memory in megabytes',
            function ($observer) {
                $observer->observe($this->usedMb);
            }
        );

        if ($this->limitMb > 0) {
            $meter->createObservableGauge(
                'system.memory.limit',
                $this->unit,
                'Memory limit in megabytes',
                function ($observer) {
                    $observer->observe($this->limitMb);
                }
            );
        }
    }

    public function record(float $usedMb, float $availableMb = 0): void
    {
        $this->usedMb = $usedMb;
        $this->availableMb = $availableMb > 0 ? $availableMb : $this->getSystemAvailableMemory();
        $this->peakUsedMb = max($this->peakUsedMb, $usedMb);

        if ($this->limitMb === 0.0) {
            $this->limitMb = $this->getMemoryLimit();
        }
    }

    public function getCurrentUsage(): float
    {
        if ($this->usedMb > 0) {
            return $this->usedMb;
        }

        return $this->measureMemoryUsage();
    }

    private function measureMemoryUsage(): float
    {
        if (PHP_OS_FAMILY === 'Linux') {
            return $this->measureLinuxMemory();
        }

        if (PHP_OS_FAMILY === 'Darwin') {
            return $this->measureMacMemory();
        }

        return $this->getPhpMemory();
    }

    private function measureLinuxMemory(): array
    {
        $memInfoFile = '/proc/meminfo';
        if (!file_exists($memInfoFile)) {
            return ['used' => $this->getPhpMemory(), 'available' => 0];
        }

        $content = file_get_contents($memInfoFile);
        if ($content === false) {
            return ['used' => 0, 'available' => 0];
        }

        $memInfo = [];
        preg_match_all('/^(\w+):\s+(\d+)\s+kB$/m', $content, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $memInfo[$match[1]] = (int) $match[2];
        }

        $totalKb = $memInfo['MemTotal'] ?? 0;
        $availableKb = $memInfo['MemAvailable'] ?? $memInfo['MemFree'] ?? 0;
        $cachedKb = $memInfo['Cached'] ?? 0;
        $buffersKb = $memInfo['Buffers'] ?? 0;

        $usedKb = $totalKb - $availableKb;
        $totalMb = $totalKb / 1024;
        $usedMb = $usedKb / 1024;
        $availableMb = $availableKb / 1024;

        $this->limitMb = $totalMb;

        return [
            'used' => $usedMb,
            'available' => $availableMb,
        ];
    }

    private function measureMacMemory(): float
    {
        $output = [];
        exec('vm_stat', $output);

        $pagesize = 4096;
        $free = 0;
        $active = 0;
        $inactive = 0;
        $wired = 0;

        foreach ($output as $line) {
            if (str_contains($line, 'Pages free:')) {
                preg_match('/(\d+)/', $line, $matches);
                $free = (int) ($matches[1] ?? 0);
            } elseif (str_contains($line, 'Pages active:')) {
                preg_match('/(\d+)/', $line, $matches);
                $active = (int) ($matches[1] ?? 0);
            } elseif (str_contains($line, 'Pages inactive:')) {
                preg_match('/(\d+)/', $line, $matches);
                $inactive = (int) ($matches[1] ?? 0);
            } elseif (str_contains($line, 'Pages wired down:')) {
                preg_match('/(\d+)/', $line, $matches);
                $wired = (int) ($matches[1] ?? 0);
            } elseif (str_contains($line, 'page size of')) {
                preg_match('/(\d+)/', $line, $matches);
                $pagesize = (int) ($matches[1] ?? 4096);
            }
        }

        $usedPages = $active + $inactive + $wired;
        return ($usedPages * $pagesize) / (1024 * 1024);
    }

    private function getPhpMemory(): float
    {
        $memory = memory_get_usage(true);
        return $memory / (1024 * 1024);
    }

    private function getSystemAvailableMemory(): float
    {
        if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/meminfo')) {
            $content = file_get_contents('/proc/meminfo');
            if (preg_match('/MemAvailable:\s+(\d+)\s+kB/', $content, $matches)) {
                return ((int) $matches[1]) / 1024;
            }
        }

        return $this->getPhpMemory();
    }

    private function getMemoryLimit(): float
    {
        $phpLimit = $this->parsePhpMemoryLimit();
        if ($phpLimit > 0) {
            return $phpLimit;
        }

        if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/meminfo')) {
            $content = file_get_contents('/proc/meminfo');
            if (preg_match('/MemTotal:\s+(\d+)\s+kB/', $content, $matches)) {
                return ((int) $matches[1]) / 1024;
            }
        }

        return 0.0;
    }

    private function parsePhpMemoryLimit(): float
    {
        $limit = ini_get('memory_limit');
        if ($limit === '-1') {
            return -1;
        }

        $limit = trim($limit);
        $lastChar = strtoupper(substr($limit, -1));
        $value = (int) $limit;

        if ($lastChar === 'G') {
            return $value * 1024;
        }
        if ($lastChar === 'M') {
            return $value;
        }
        if ($lastChar === 'K') {
            return $value / 1024;
        }

        return $value / (1024 * 1024);
    }

    public function getUsedMb(): float
    {
        return $this->usedMb;
    }

    public function getAvailableMb(): float
    {
        return $this->availableMb;
    }

    public function getPeakUsedMb(): float
    {
        return $this->peakUsedMb;
    }

    public function getUsagePercentage(): float
    {
        if ($this->limitMb <= 0) {
            return 0.0;
        }
        return ($this->usedMb / $this->limitMb) * 100;
    }
}