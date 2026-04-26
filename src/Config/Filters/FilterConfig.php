<?php

declare(strict_types=1);

namespace Vesvasi\Config\Filters;

use Vesvasi\Util\PatternMatcher;

final class FilterConfig
{
    private float $cpuThreshold;
    private float $memoryThreshold;
    private float $durationThreshold;
    private array $includeFiles;
    private array $excludeFiles;
    private array $includeClasses;
    private array $excludeClasses;
    private array $includeMethods;
    private array $excludeMethods;
    private array $includeUrls;
    private array $excludeUrls;
    private array $includeCommands;
    private array $excludeCommands;

    public function __construct(array $config)
    {
        $this->cpuThreshold = (float) ($config['cpu_threshold'] ?? 0.0);
        $this->memoryThreshold = (float) ($config['memory_threshold'] ?? 0.0);
        $this->durationThreshold = (float) ($config['duration_threshold'] ?? 0.0);

        $this->includeFiles = $config['include_files'] ?? ['*'];
        $this->excludeFiles = $config['exclude_files'] ?? [];
        $this->includeClasses = $config['include_classes'] ?? ['*'];
        $this->excludeClasses = $config['exclude_classes'] ?? [];
        $this->includeMethods = $config['include_methods'] ?? ['*'];
        $this->excludeMethods = $config['exclude_methods'] ?? [];
        $this->includeUrls = $config['include_urls'] ?? ['*'];
        $this->excludeUrls = $config['exclude_urls'] ?? [];
        $this->includeCommands = $config['include_commands'] ?? ['*'];
        $this->excludeCommands = $config['exclude_commands'] ?? [];
    }

    public function getCpuThreshold(): float
    {
        return $this->cpuThreshold;
    }

    public function getMemoryThreshold(): float
    {
        return $this->memoryThreshold;
    }

    public function getDurationThreshold(): float
    {
        return $this->durationThreshold;
    }

    public function shouldIncludeFile(string $filePath): bool
    {
        return PatternMatcher::shouldInclude($filePath, $this->includeFiles, $this->excludeFiles);
    }

    public function shouldIncludeClass(string $className): bool
    {
        return PatternMatcher::shouldInclude($className, $this->includeClasses, $this->excludeClasses);
    }

    public function shouldIncludeMethod(string $methodName): bool
    {
        return PatternMatcher::shouldInclude($methodName, $this->includeMethods, $this->excludeMethods);
    }

    public function shouldIncludeUrl(string $url): bool
    {
        return PatternMatcher::shouldInclude($url, $this->includeUrls, $this->excludeUrls);
    }

    public function shouldIncludeCommand(string $command): bool
    {
        return PatternMatcher::shouldInclude($command, $this->includeCommands, $this->excludeCommands);
    }

    public function meetsResourceThresholds(float $cpuUsage, float $memoryUsageMb): bool
    {
        if ($this->cpuThreshold > 0 && $cpuUsage < $this->cpuThreshold) {
            return false;
        }

        if ($this->memoryThreshold > 0 && $memoryUsageMb < $this->memoryThreshold) {
            return false;
        }

        return true;
    }

    public function toArray(): array
    {
        return [
            'cpu_threshold' => $this->cpuThreshold,
            'memory_threshold' => $this->memoryThreshold,
            'duration_threshold' => $this->durationThreshold,
            'include_files' => $this->includeFiles,
            'exclude_files' => $this->excludeFiles,
            'include_classes' => $this->includeClasses,
            'exclude_classes' => $this->excludeClasses,
            'include_methods' => $this->includeMethods,
            'exclude_methods' => $this->excludeMethods,
            'include_urls' => $this->includeUrls,
            'exclude_urls' => $this->excludeUrls,
            'include_commands' => $this->includeCommands,
            'exclude_commands' => $this->excludeCommands,
        ];
    }
}