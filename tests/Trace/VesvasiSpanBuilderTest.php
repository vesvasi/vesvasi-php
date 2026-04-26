<?php

declare(strict_types=1);

namespace Vesvasi\Tests\Trace;

use PHPUnit\Framework\TestCase;
use Vesvasi\Trace\VesvasiSpanBuilder;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\SpanInterface;

final class VesvasiSpanBuilderTest extends TestCase
{
    public function testSetSpanName(): void
    {
        $innerBuilder = $this->createMock(\OpenTelemetry\API\Trace\SpanBuilderInterface::class);
        $builder = new VesvasiSpanBuilder($innerBuilder);

        $result = $builder->setSpanName('TestSpan');

        $this->assertSame($builder, $result);
    }

    public function testCreateSpanName(): void
    {
        $innerBuilder = $this->createMock(\OpenTelemetry\API\Trace\SpanBuilderInterface::class);
        $builder = new VesvasiSpanBuilder($innerBuilder);

        $result = $builder->createSpanName('Operation', 'resource');

        $this->assertSame($builder, $result);
    }

    public function testSetSpanKind(): void
    {
        $innerBuilder = $this->createMock(\OpenTelemetry\API\Trace\SpanBuilderInterface::class);
        $innerBuilder->expects($this->once())->method('setSpanKind')->with(SpanKind::KIND_SERVER);

        $builder = new VesvasiSpanBuilder($innerBuilder);
        $builder->setSpanKind(SpanKind::KIND_SERVER);
    }

    public function testSetAttribute(): void
    {
        $innerBuilder = $this->createMock(\OpenTelemetry\API\Trace\SpanBuilderInterface::class);
        $innerBuilder->expects($this->exactly(2))->method('setAttribute');

        $builder = new VesvasiSpanBuilder($innerBuilder);
        $builder->setAttribute('key1', 'value1')
            ->setAttribute('key2', 'value2');
    }

    public function testSetVesvasiError(): void
    {
        $innerBuilder = $this->createMock(\OpenTelemetry\API\Trace\SpanBuilderInterface::class);
        $builder = new VesvasiSpanBuilder($innerBuilder);

        $result = $builder->setVesvasiError(true);

        $this->assertSame($builder, $result);
    }

    public function testSetVesvasiSql(): void
    {
        $innerBuilder = $this->createMock(\OpenTelemetry\API\Trace\SpanBuilderInterface::class);
        $builder = new VesvasiSpanBuilder($innerBuilder);

        $result = $builder->setVesvasiSql(true);

        $this->assertSame($builder, $result);
    }

    public function testSetVesvasiHttp(): void
    {
        $innerBuilder = $this->createMock(\OpenTelemetry\API\Trace\SpanBuilderInterface::class);
        $builder = new VesvasiSpanBuilder($innerBuilder);

        $result = $builder->setVesvasiHttp(true);

        $this->assertSame($builder, $result);
    }

    public function testSetVesvasiCommand(): void
    {
        $innerBuilder = $this->createMock(\OpenTelemetry\API\Trace\SpanBuilderInterface::class);
        $builder = new VesvasiSpanBuilder($innerBuilder);

        $result = $builder->setVesvasiCommand(true);

        $this->assertSame($builder, $result);
    }

    public function testSetSqlAttributes(): void
    {
        $innerBuilder = $this->createMock(\OpenTelemetry\API\Trace\SpanBuilderInterface::class);
        $innerBuilder->expects($this->atLeast(1))->method('setAttribute');

        $builder = new VesvasiSpanBuilder($innerBuilder);
        $builder->setSqlAttributes(
            'SELECT * FROM users WHERE id = ?',
            'my_database',
            'mysql',
            [1]
        );
    }

    public function testSetHttpAttributes(): void
    {
        $innerBuilder = $this->createMock(\OpenTelemetry\API\Trace\SpanBuilderInterface::class);
        $innerBuilder->expects($this->atLeast(1))->method('setAttribute');

        $builder = new VesvasiSpanBuilder($innerBuilder);
        $builder->setHttpAttributes('POST', 'https://example.com/api/users', 201, ['Content-Type' => 'application/json']);
    }

    public function testSetHttpAttributesWithErrorStatus(): void
    {
        $innerBuilder = $this->createMock(\OpenTelemetry\API\Trace\SpanBuilderInterface::class);
        $innerBuilder->expects($this->atLeastOnce())->method('setAttribute');

        $builder = new VesvasiSpanBuilder($innerBuilder);
        $builder->setHttpAttributes('GET', 'https://example.com/api/users', 500, null);

        $this->assertInstanceOf(VesvasiSpanBuilder::class, $builder->setVesvasiError(true));
    }

    public function testSetCommandAttributes(): void
    {
        $innerBuilder = $this->createMock(\OpenTelemetry\API\Trace\SpanBuilderInterface::class);
        $innerBuilder->expects($this->atLeast(1))->method('setAttribute');

        $builder = new VesvasiSpanBuilder($innerBuilder);
        $builder->setCommandAttributes('artisan migrate', ['--force'], 0);
    }

    public function testSetCommandAttributesWithError(): void
    {
        $innerBuilder = $this->createMock(\OpenTelemetry\API\Trace\SpanBuilderInterface::class);
        $innerBuilder->expects($this->atLeast(1))->method('setAttribute');

        $builder = new VesvasiSpanBuilder($innerBuilder);
        $builder->setCommandAttributes('artisan migrate', null, 1);
    }

    public function testSetErrorAttributes(): void
    {
        $innerBuilder = $this->createMock(\OpenTelemetry\API\Trace\SpanBuilderInterface::class);
        $innerBuilder->expects($this->atLeast(3))->method('setAttribute');

        $builder = new VesvasiSpanBuilder($innerBuilder);
        $exception = new \RuntimeException('Test error');
        $builder->setErrorAttributes($exception);
    }

    public function testRecordException(): void
    {
        $innerBuilder = $this->createMock(\OpenTelemetry\API\Trace\SpanBuilderInterface::class);
        $innerBuilder->expects($this->atLeastOnce())->method('setAttribute');

        $builder = new VesvasiSpanBuilder($innerBuilder);
        $exception = new \RuntimeException('Test error');
        $builder->setErrorAttributes($exception);
    }
}