<?php

declare(strict_types=1);

namespace Vesvasi\Trace;

use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;

final class SpanContext
{
    public const ATTR_TRACE_ID = 'trace_id';
    public const ATTR_SPAN_ID = 'span_id';
    public const ATTR_PARENT_SPAN_ID = 'parent_span_id';
    public const ATTR_TRACE_FLAGS = 'trace_flags';
    public const ATTR_TRACE_STATE = 'trace_state';

    private string $traceId;
    private string $spanId;
    private ?string $parentSpanId;
    private bool $sampled;
    private array $attributes;

    public function __construct(SpanInterface $span)
    {
        $context = $span->getContext();

        $this->traceId = $context->getTraceId();
        $this->spanId = $context->getSpanId();
        $this->parentSpanId = $context->getParentSpanId();
        $this->sampled = $context->isSampled();
        $this->attributes = $span->getAttributes()->toArray();
    }

    public function getTraceId(): string
    {
        return $this->traceId;
    }

    public function getSpanId(): string
    {
        return $this->spanId;
    }

    public function getParentSpanId(): ?string
    {
        return $this->parentSpanId;
    }

    public function isSampled(): bool
    {
        return $this->sampled;
    }

    public function getAttributes(): array
    {
        return $this->attributes;
    }

    public function getVesvasiAttributes(): array
    {
        $vesvasiAttrs = [];

        foreach ($this->attributes as $key => $value) {
            if (str_starts_with($key, 'vesvasi.')) {
                $vesvasiAttrs[$key] = $value;
            }
        }

        return $vesvasiAttrs;
    }

    public function isError(): bool
    {
        return $this->attributes['error'] ?? $this->attributes['vesvasi.error'] ?? false;
    }

    public function isSql(): bool
    {
        return $this->attributes['vesvasi.sql'] ?? false;
    }

    public function isHttp(): bool
    {
        return $this->attributes['vesvasi.http'] ?? false;
    }

    public function isCommand(): bool
    {
        return $this->attributes['vesvasi.command'] ?? false;
    }

    public function toArray(): array
    {
        return [
            self::ATTR_TRACE_ID => $this->traceId,
            self::ATTR_SPAN_ID => $this->spanId,
            self::ATTR_PARENT_SPAN_ID => $this->parentSpanId,
            self::ATTR_TRACE_FLAGS => $this->sampled ? '01' : '00',
            'vesvasi' => $this->getVesvasiAttributes(),
        ];
    }
}