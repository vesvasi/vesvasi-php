<?php

declare(strict_types=1);

namespace Vesvasi\Tests\Metrics\Collectors;

use PHPUnit\Framework\TestCase;
use Vesvasi\Metrics\Collectors\CpuCollector;

final class CpuCollectorTest extends TestCase
{
    private CpuCollector $collector;

    protected function setUp(): void
    {
        $this->collector = new CpuCollector();
    }

    public function testRecord(): void
    {
        $this->collector->record(75.5);
        $this->assertSame(75.5, $this->collector->getLastValue());
    }

    public function testRecordClampsToValidRange(): void
    {
        $this->collector->record(150.0);
        $this->assertSame(100.0, $this->collector->getLastValue());

        $this->collector->record(-10.0);
        $this->assertSame(0.0, $this->collector->getLastValue());
    }

    public function testGetCurrentUsageReturnsRecordedValue(): void
    {
        $this->collector->record(50.0);
        $this->assertSame(50.0, $this->collector->getCurrentUsage());
    }

    public function testGetLastValue(): void
    {
        $this->assertSame(0.0, $this->collector->getLastValue());

        $this->collector->record(25.0);
        $this->assertSame(25.0, $this->collector->getLastValue());
    }
}