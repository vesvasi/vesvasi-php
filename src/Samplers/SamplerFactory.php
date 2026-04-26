<?php

declare(strict_types=1);

namespace Vesvasi\Samplers;

use OpenTelemetry\SDK\Trace\SamplerInterface;
use OpenTelemetry\SDK\Trace\TracingSettings;

final class SamplerFactory
{
    public static function create(array $config): SamplerInterface
    {
        $samplingConfig = new \Vesvasi\Config\Sampling\SamplingConfig($config);
        return new VesvasiSampler($samplingConfig);
    }

    public static function createAlwaysOn(): SamplerInterface
    {
        return new class implements SamplerInterface {
            public function shouldSample(
                $parentContext,
                $traceId,
                $spanName,
                $spanKind,
                $attributes = null,
                $links = null
            ) {
                return new \OpenTelemetry\SDK\Trace\SamplingResult(
                    \OpenTelemetry\SDK\Trace\SamplingResult::RECORD_AND_SAMPLE
                );
            }

            public function getDescription(): string
            {
                return 'AlwaysOnSampler';
            }
        };
    }

    public static function createAlwaysOff(): SamplerInterface
    {
        return new class implements SamplerInterface {
            public function shouldSample(
                $parentContext,
                $traceId,
                $spanName,
                $spanKind,
                $attributes = null,
                $links = null
            ) {
                return new \OpenTelemetry\SDK\Trace\SamplingResult(
                    \OpenTelemetry\SDK\Trace\SamplingResult::NOT_SAMPLED
                );
            }

            public function getDescription(): string
            {
                return 'AlwaysOffSampler';
            }
        };
    }

    public static function createRatio(float $ratio): SamplerInterface
    {
        return new class($ratio) implements SamplerInterface {
            private float $ratio;

            public function __construct(float $ratio)
            {
                $this->ratio = $ratio;
            }

            public function shouldSample(
                $parentContext,
                $traceId,
                $spanName,
                $spanKind,
                $attributes = null,
                $links = null
            ) {
                $decision = (mt_rand() / mt_getrandmax()) < $this->ratio
                    ? \OpenTelemetry\SDK\Trace\SamplingResult::RECORD_AND_SAMPLE
                    : \OpenTelemetry\SDK\Trace\SamplingResult::NOT_SAMPLED;

                return new \OpenTelemetry\SDK\Trace\SamplingResult($decision);
            }

            public function getDescription(): string
            {
                return "RatioSampler({$this->ratio})";
            }
        };
    }
}