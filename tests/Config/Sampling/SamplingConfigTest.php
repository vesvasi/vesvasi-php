<?php

declare(strict_types=1);

namespace Vesvasi\Tests\Config\Sampling;

use PHPUnit\Framework\TestCase;
use Vesvasi\Config\Sampling\SamplingConfig;

final class SamplingConfigTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $config = new SamplingConfig([]);

        $this->assertSame(100.0, $config->getHeadPercentage());
        $this->assertTrue($config->alwaysSampleErrors());
        $this->assertSame(1, $config->getSamplingRatio()); // 100/100 = 1
        $this->assertSame(100, $config->getMaxTracesPerSecond());
        $this->assertTrue($config->useParentSampling());
        $this->assertTrue($config->useRootSpanSampling());
    }

    public function testCustomValues(): void
    {
        $config = new SamplingConfig([
            'head_percentage' => 25,
            'always_sample_errors' => false,
            'sampling_ratio' => 1000,
            'max_traces_per_second' => 50,
            'use_parent_sampling' => false,
            'use_root_span_sampling' => false,
        ]);

        $this->assertSame(25.0, $config->getHeadPercentage());
        $this->assertFalse($config->alwaysSampleErrors());
        $this->assertSame(4, $config->getSamplingRatio());
        $this->assertSame(50, $config->getMaxTracesPerSecond());
        $this->assertFalse($config->useParentSampling());
        $this->assertFalse($config->useRootSpanSampling());
    }

    public function testShouldSample(): void
    {
        $config = new SamplingConfig([
            'head_percentage' => 50,
        ]);

        $sampled = 0;
        $total = 10000;

        for ($i = 0; $i < $total; $i++) {
            if ($config->shouldSample(($i / $total) * 100)) {
                $sampled++;
            }
        }

        $percentage = ($sampled / $total) * 100;
        $this->assertGreaterThan(45, $percentage);
        $this->assertLessThan(55, $percentage);
    }

    public function testShouldSampleWithZeroPercentage(): void
    {
        $config = new SamplingConfig([
            'head_percentage' => 0,
        ]);

        $this->assertFalse($config->shouldSample(50));
    }

    public function testShouldSampleWithHundredPercentage(): void
    {
        $config = new SamplingConfig([
            'head_percentage' => 100,
        ]);

        $this->assertTrue($config->shouldSample(50));
        $this->assertTrue($config->shouldSample(0));
        $this->assertTrue($config->shouldSample(100));
    }

    public function testInvalidSamplingRatioThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('sampling_ratio must be between 1 and 10000');

        new SamplingConfig([
            'sampling_ratio' => 0,
        ]);
    }

    public function testToArray(): void
    {
        $config = new SamplingConfig([
            'head_percentage' => 30,
            'always_sample_errors' => true,
        ]);

        $array = $config->toArray();

        $this->assertSame(30.0, $array['head_percentage']);
        $this->assertTrue($array['always_sample_errors']);
    }
}