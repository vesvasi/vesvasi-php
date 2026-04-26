<?php

declare(strict_types=1);

namespace Vesvasi\Config\Sampling;

final class SamplingConfig
{
    private float $headPercentage;
    private bool $alwaysSampleErrors;
    private int $samplingRatio;
    private int $maxTracesPerSecond;
    private bool $useParentSampling;
    private bool $useRootSpanSampling;

    public function __construct(array $config)
    {
        $this->headPercentage = (float) ($config['head_percentage'] ?? 100.0);
        $this->alwaysSampleErrors = (bool) ($config['always_sample_errors'] ?? true);
        $this->samplingRatio = $this->calculateSamplingRatio($config['sampling_ratio'] ?? null);
        $this->maxTracesPerSecond = (int) ($config['max_traces_per_second'] ?? 100);
        $this->useParentSampling = (bool) ($config['use_parent_sampling'] ?? true);
        $this->useRootSpanSampling = (bool) ($config['use_root_span_sampling'] ?? true);
    }

    private function calculateSamplingRatio(?int $ratio): int
    {
        if ($ratio === null) {
            return (int) (100 / max(1, $this->headPercentage));
        }

        if ($ratio < 1 || $ratio > 10000) {
            throw new \InvalidArgumentException('sampling_ratio must be between 1 and 10000');
        }

        return $ratio;
    }

    public function getHeadPercentage(): float
    {
        return $this->headPercentage;
    }

    public function alwaysSampleErrors(): bool
    {
        return $this->alwaysSampleErrors;
    }

    public function getSamplingRatio(): int
    {
        // Return computed ratio based on head percentage
        return (int) (100 / max(1, $this->headPercentage));
    }

    public function getMaxTracesPerSecond(): int
    {
        return $this->maxTracesPerSecond;
    }

    public function useParentSampling(): bool
    {
        return $this->useParentSampling;
    }

    public function useRootSpanSampling(): bool
    {
        return $this->useRootSpanSampling;
    }

    public function shouldSample(float $randomValue): bool
    {
        return $randomValue <= $this->headPercentage;
    }

    public function toArray(): array
    {
        return [
            'head_percentage' => $this->headPercentage,
            'always_sample_errors' => $this->alwaysSampleErrors,
            'sampling_ratio' => $this->samplingRatio,
            'max_traces_per_second' => $this->maxTracesPerSecond,
            'use_parent_sampling' => $this->useParentSampling,
            'use_root_span_sampling' => $this->useRootSpanSampling,
        ];
    }
}