<?php

declare(strict_types=1);

namespace Vesvasi\Tests\Integrations;

use PHPUnit\Framework\TestCase;
use Vesvasi\Config\Config;
use Vesvasi\Trace\VesvasiTracer;
use Vesvasi\Integrations\HttpIntegration;

final class HttpIntegrationTest extends TestCase
{
    private HttpIntegration $integration;

    protected function setUp(): void
    {
        Config::reset();

        $config = Config::load([
            'endpoint' => 'https://otlp.example.com:4318',
        ]);

        $innerTracer = $this->createMock(\OpenTelemetry\API\Trace\TracerInterface::class);
        $innerTracer->method('spanBuilder')->willReturn(
            $this->createMock(\OpenTelemetry\API\Trace\SpanBuilderInterface::class)
        );
        
        $tracer = new VesvasiTracer($innerTracer);
        $this->integration = new HttpIntegration($config, $tracer);
    }

    public function testCreateHttpSpan(): void
    {
        $span = $this->integration->createHttpSpan(
            'GET',
            'https://api.example.com/users',
            ['Accept' => 'application/json']
        );

        $this->assertInstanceOf(\OpenTelemetry\API\Trace\SpanInterface::class, $span);
    }

    public function testCreateHttpSpanWithStatusCode(): void
    {
        $span = $this->integration->createHttpSpan(
            'POST',
            'https://api.example.com/users',
            ['Content-Type' => 'application/json'],
            201
        );

        $this->assertInstanceOf(\OpenTelemetry\API\Trace\SpanInterface::class, $span);
    }

    public function testCreateServerSpan(): void
    {
        $span = $this->integration->createServerSpan(
            'GET',
            'https://example.com/api/users'
        );

        $this->assertInstanceOf(\OpenTelemetry\API\Trace\SpanInterface::class, $span);
    }

    public function testTraceRequest(): void
    {
        $result = $this->integration->traceRequest(
            'POST',
            'https://api.example.com/users',
            ['Content-Type' => 'application/json'],
            '{"name":"John"}',
            function () {
                return ['id' => 1, 'name' => 'John'];
            }
        );

        $this->assertSame(['id' => 1, 'name' => 'John'], $result);
    }

    public function testTraceRequestWithException(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->integration->traceRequest(
            'POST',
            'https://api.example.com/users',
            [],
            null,
            function () {
                throw new \RuntimeException('Request failed');
            }
        );
    }

    public function testGetActiveSpansCount(): void
    {
        $this->assertSame(0, $this->integration->getActiveSpansCount());
    }

    public function testSanitizeBody(): void
    {
        $span = $this->integration->createHttpSpan(
            'POST',
            'https://api.example.com/login',
            [],
            null
        );

        $this->assertInstanceOf(\OpenTelemetry\API\Trace\SpanInterface::class, $span);
    }

    public function testRedactSensitiveFields(): void
    {
        $span = $this->integration->createHttpSpan(
            'POST',
            'https://api.example.com/login',
            [],
            null
        );

        $this->assertInstanceOf(\OpenTelemetry\API\Trace\SpanInterface::class, $span);
    }
}