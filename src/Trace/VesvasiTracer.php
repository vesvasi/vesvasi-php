<?php

declare(strict_types=1);

namespace Vesvasi\Trace;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\TracerProviderInterface;
use OpenTelemetry\Context\Context;

class VesvasiTracer implements TracerInterface
{
    private TracerInterface $innerTracer;
    private VesvasiSpanBuilder $spanBuilder;

    public function __construct(TracerInterface $innerTracer)
    {
        $this->innerTracer = $innerTracer;
        $this->spanBuilder = new VesvasiSpanBuilder($innerTracer);
    }

    public function tracerProvider(): TracerProviderInterface
    {
        return $this->innerTracer->tracerProvider();
    }

    public function getVersion(): string
    {
        return $this->innerTracer->getVersion();
    }

    public function getSchemaUrl(): ?string
    {
        return $this->innerTracer->getSchemaUrl();
    }

    public function getInstrumentationLibrary(): \OpenTelemetry\SDK\Instrumentation\InstrumentationLibrary
    {
        return $this->innerTracer->getInstrumentationLibrary();
    }

    public function isEnabled(): bool
    {
        return $this->innerTracer->isEnabled();
    }

    public function spanBuilder(string $spanName): VesvasiSpanBuilder
    {
        $this->spanBuilder = new VesvasiSpanBuilder(
            $this->innerTracer->spanBuilder($spanName)
        );
        $this->spanBuilder->setSpanName($spanName);
        return $this->spanBuilder;
    }

    public function createSqlSpan(
        string $operation,
        string $sql,
        ?string $dbName = null,
        ?string $dbDriver = null,
        ?array $bindings = null
    ): SpanInterface {
        $span = $this->spanBuilder
            ->createSpanName('SQL ' . $operation, $dbName)
            ->setVesvasiSql(true)
            ->setSqlAttributes($sql, $dbName, $dbDriver, $bindings)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->startSpan();

        return $span;
    }

    public function createHttpSpan(
        string $method,
        string $url,
        ?int $statusCode = null,
        ?array $requestHeaders = null
    ): VesvasiSpanBuilder {
        return $this->spanBuilder
            ->createSpanName('HTTP ' . $method, $url)
            ->setVesvasiHttp(true)
            ->setHttpAttributes($method, $url, $statusCode, $requestHeaders)
            ->setSpanKind(SpanKind::KIND_CLIENT);
    }

    public function createCommandSpan(
        string $command,
        ?array $arguments = null,
        ?int $exitCode = null
    ): VesvasiSpanBuilder {
        return $this->spanBuilder
            ->createSpanName('Command', $command)
            ->setVesvasiCommand(true)
            ->setCommandAttributes($command, $arguments, $exitCode)
            ->setSpanKind(SpanKind::KIND_INTERNAL);
    }

    public function createErrorSpan(
        string $message,
        \Throwable $exception,
        ?int $kind = null
    ): VesvasiSpanBuilder {
        return $this->spanBuilder
            ->createSpanName('Error', $message)
            ->setVesvasiError(true)
            ->setErrorAttributes($exception)
            ->setSpanKind($kind ?? SpanKind::KIND_INTERNAL);
    }

    public function recordException(\Throwable $exception, ?SpanInterface $span = null): void
    {
        $span ??= $this->spanBuilder
            ->createSpanName('Exception', get_class($exception))
            ->setVesvasiError(true)
            ->setErrorAttributes($exception)
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->startSpan();

        $span->recordException($exception);
        $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
    }
}