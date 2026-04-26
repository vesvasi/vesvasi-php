<?php

declare(strict_types=1);

namespace Vesvasi\Tests\Metrics\Collectors;

use PHPUnit\Framework\TestCase;
use Vesvasi\Metrics\Collectors\MemoryCollector;

final class MemoryCollectorTest extends TestCase
{
    private MemoryCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new MemoryCollector();
    }

    public function testRecord(): void
    {
        $this->collector->record(512.0, 2048.0);

        $this->assertSame(512.0, $this->collector->getUsedMb());
        $this->assertSame(2048.0, $this->collector->getAvailableMb());
        $this->assertSame(512.0, $this->collector->getPeakUsedMb());
    }

    public function testPeakTracking(): void
    {
        $this->collector->record(256.0);
        $this->assertSame(256.0, $this->collector->getPeakUsedMb());

        $this->collector->record(512.0);
        $this->assertSame(512.0, $this->collector->getPeakUsedMb());

        $this->collector->record(128.0);
        $this->assertSame(512.0, $this->collector->getPeakUsedMb());
    }

    public function testUsagePercentage(): void
    {
        $this->collector->record(512.0, 2048.0);

        // Percentage depends on system memory limit
        $percentage = $this->collector->getUsagePercentage();
        $this->assertGreaterThan(0, $percentage);
    }

    public function testUsagePercentageWithNoLimit(): void
    {
        $this->collector->record(512.0, 0);

        // Percentage depends on system memory limit
        $percentage = $this->collector->getUsagePercentage();
        $this->assertGreaterThan(0, $percentage);
    }

    public function testGetCurrentUsage(): void
    {
        $this->collector->record(1024.0);
        $this->assertSame(1024.0, $this->collector->getCurrentUsage());
    }
}