<?php

declare(strict_types=1);

namespace Vesvasi\Config\Metrics;

final class MetricsConfig
{
    private bool $enabled;
    private bool $collectCpu;
    private bool $collectMemory;
    private bool $collectDisk;
    private bool $collectNetwork;
    private int $collectionInterval;
    private int $exportInterval;
    private bool $enableRuntimeMetrics;

    public function __construct(array $config)
    {
        $this->enabled = (bool) ($config['enabled'] ?? true);
        $this->collectCpu = (bool) ($config['collect_cpu'] ?? true);
        $this->collectMemory = (bool) ($config['collect_memory'] ?? true);
        $this->collectDisk = (bool) ($config['collect_disk'] ?? false);
        $this->collectNetwork = (bool) ($config['collect_network'] ?? false);
        $this->collectionInterval = (int) ($config['collection_interval'] ?? 60000);
        $this->exportInterval = (int) ($config['export_interval'] ?? 5000);
        $this->enableRuntimeMetrics = (bool) ($config['enable_runtime_metrics'] ?? false);
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function shouldCollectCpu(): bool
    {
        return $this->collectCpu;
    }

    public function shouldCollectMemory(): bool
    {
        return $this->collectMemory;
    }

    public function shouldCollectDisk(): bool
    {
        return $this->collectDisk;
    }

    public function shouldCollectNetwork(): bool
    {
        return $this->collectNetwork;
    }

    public function getCollectionInterval(): int
    {
        return $this->collectionInterval;
    }

    public function getExportInterval(): int
    {
        return $this->exportInterval;
    }

    public function shouldEnableRuntimeMetrics(): bool
    {
        return $this->enableRuntimeMetrics;
    }

    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'collect_cpu' => $this->collectCpu,
            'collect_memory' => $this->collectMemory,
            'collect_disk' => $this->collectDisk,
            'collect_network' => $this->collectNetwork,
            'collection_interval' => $this->collectionInterval,
            'export_interval' => $this->exportInterval,
            'enable_runtime_metrics' => $this->enableRuntimeMetrics,
        ];
    }
}