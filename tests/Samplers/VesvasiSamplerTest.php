<?php

declare(strict_types=1);

namespace Vesvasi\Tests\Samplers;

use PHPUnit\Framework\TestCase;
use Vesvasi\Config\Sampling\SamplingConfig;
use Vesvasi\Samplers\VesvasiSampler;
use OpenTelemetry\SDK\Trace\SamplingResult;

final class VesvasiSamplerTest extends TestCase
{
    private VesvasiSampler $sampler;

    protected function setUp(): void
    {
        $config = new SamplingConfig([
            'head_percentage' => 50,
            'always_sample_errors' => true,
        ]);

        $this->sampler = new VesvasiSampler($config);
    }

    public function testGetDescription(): void
    {
        $this->assertSame('VesvasiSampler', $this->sampler->getDescription());
    }

    public function testRootSpanSampling(): void
    {
        $results = [
            SamplingResult::RECORD_AND_SAMPLE => 0,
            SamplingResult::DROP => 0,
        ];

        for ($i = 0; $i < 1000; $i++) {
            $result = $this->sampler->shouldSample(
                null,
                $this->generateTraceId(),
                'test-span',
                \OpenTelemetry\API\Trace\SpanKind::KIND_INTERNAL
            );
            $results[$result->getDecision()]++;
        }

        $sampledPercentage = ($results[SamplingResult::RECORD_AND_SAMPLE] / 1000) * 100;
        $this->assertGreaterThan(40, $sampledPercentage);
        $this->assertLessThan(60, $sampledPercentage);
    }

    public function testAlwaysSampleErrors(): void
    {
        $attributes = \OpenTelemetry\SDK\Common\Attribute\Attributes::create([
            'error' => true,
        ]);

        $result = $this->sampler->shouldSample(
            null,
            $this->generateTraceId(),
            'error-span',
            \OpenTelemetry\API\Trace\SpanKind::KIND_INTERNAL,
            $attributes
        );

        $this->assertSame(SamplingResult::RECORD_AND_SAMPLE, $result->getDecision());
    }

    public function testAlwaysSampleHttpErrors(): void
    {
        $attributes = \OpenTelemetry\SDK\Common\Attribute\Attributes::create([
            'http.status_code' => 500,
        ]);

        $result = $this->sampler->shouldSample(
            null,
            $this->generateTraceId(),
            'http-error-span',
            \OpenTelemetry\API\Trace\SpanKind::KIND_INTERNAL,
            $attributes
        );

        $this->assertSame(SamplingResult::RECORD_AND_SAMPLE, $result->getDecision());
    }

public function testNotSampleWhenBelowThreshold(): void
    {
        $config = new SamplingConfig([
            'head_percentage' => 0,
        ]);
        $sampler = new VesvasiSampler($config);

        $result = $sampler->shouldSample(
            null,
            $this->generateTraceId(),
            'test-span',
            \OpenTelemetry\API\Trace\SpanKind::KIND_INTERNAL
        );

        $this->assertSame(SamplingResult::DROP, $result->getDecision());
    }

    public function testSampleAllWhenAtHundredPercent(): void
    {
        $config = new SamplingConfig([
            'head_percentage' => 100,
        ]);
        $sampler = new VesvasiSampler($config);

        $result = $sampler->shouldSample(
            null,
            $this->generateTraceId(),
            'test-span',
            \OpenTelemetry\API\Trace\SpanKind::KIND_INTERNAL
        );

        $this->assertSame(SamplingResult::RECORD_AND_SAMPLE, $result->getDecision());
    }

    private function generateTraceId(): string
    {
        return bin2hex(random_bytes(16));
    }
}