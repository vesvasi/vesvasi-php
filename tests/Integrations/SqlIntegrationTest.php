<?php

declare(strict_types=1);

namespace Vesvasi\Tests\Integrations;

use PHPUnit\Framework\TestCase;
use Vesvasi\Config\Config;
use Vesvasi\Trace\VesvasiTracer;
use Vesvasi\Integrations\SqlIntegration;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;

final class SqlIntegrationTest extends TestCase
{
    private SqlIntegration $integration;

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
        $this->integration = new SqlIntegration($config, $tracer);
    }

    public function testCreateSqlSpan(): void
    {
        $span = $this->integration->createSqlSpan(
            'SELECT',
            'SELECT * FROM users WHERE id = ?',
            'my_database',
            'mysql',
            [1]
        );

        $this->assertInstanceOf(SpanInterface::class, $span);
    }

    public function testTraceQuery(): void
    {
        $result = $this->integration->traceQuery(
            'SELECT * FROM users',
            'test_db',
            'mysql',
            [],
            function () {
                return ['user1', 'user2'];
            }
        );

        $this->assertSame(['user1', 'user2'], $result);
    }

    public function testTraceQueryWithException(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Query failed');

        $this->integration->traceQuery(
            'SELECT * FROM users',
            'test_db',
            'mysql',
            [],
            function () {
                throw new \RuntimeException('Query failed');
            }
        );
    }

    public function testTraceTransactionSuccess(): void
    {
        $executed = false;

        $result = $this->integration->traceTransaction(
            function () use (&$executed) {
                $executed = true;
                return 'transaction_result';
            },
            'test_db',
            'mysql'
        );

        $this->assertTrue($executed);
        $this->assertSame('transaction_result', $result);
    }

    public function testTraceTransactionRollback(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->integration->traceTransaction(
            function () {
                throw new \RuntimeException('Transaction failed');
            },
            'test_db',
            'mysql'
        );
    }

    public function testTraceExecute(): void
    {
        $result = $this->integration->traceExecute(
            'INSERT INTO users (name) VALUES (?)',
            ['John'],
            'test_db',
            'mysql',
            function () {
                return 1;
            }
        );

        $this->assertSame(1, $result);
    }

    public function testSanitizeSql(): void
    {
        $span = $this->integration->createSqlSpan(
            'SELECT',
            str_repeat('a', 20000),
            'test_db'
        );

        $this->assertInstanceOf(SpanInterface::class, $span);
    }

    public function testGetActiveSpansCount(): void
    {
        $this->assertSame(0, $this->integration->getActiveSpansCount());
    }
}