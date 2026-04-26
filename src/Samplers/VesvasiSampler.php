<?php

declare(strict_types=1);

namespace Vesvasi\Samplers;

use OpenTelemetry\SDK\Trace\SamplerInterface;
use OpenTelemetry\SDK\Trace\SamplingResult;
use OpenTelemetry\SDK\Trace\TraceConfig;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\Context\Context;
use OpenTelemetry\Context\ContextInterface;
use OpenTelemetry\SDK\Common\Attribute\AttributesInterface;
use Vesvasi\Config\Sampling\SamplingConfig;

final class VesvasiSampler implements SamplerInterface
{
    private SamplingConfig $config;
    private array $attributes;

    public function __construct(SamplingConfig $config)
    {
        $this->config = $config;
        $this->attributes = [];
    }

    public function shouldSample(
        ?ContextInterface $parentContext,
        string $traceId,
        string $spanName,
        int $spanKind,
        ?AttributesInterface $attributes = null,
        array $links = []
    ): SamplingResult {
        $isRootSpan = $this->isRootSpan($parentContext);
        $hasParent = $this->hasParent($parentContext);
        $isError = $this->isErrorSpan($attributes);

        if ($this->config->alwaysSampleErrors() && $isError) {
            $decision = SamplingResult::RECORD_AND_SAMPLE;
        } elseif ($isRootSpan && $this->config->useRootSpanSampling()) {
            $decision = $this->decideSampling();
        } elseif ($hasParent && $this->config->useParentSampling()) {
            $decision = $this->getParentDecision($parentContext);
        } else {
            $decision = $this->decideSampling();
        }

        return new SamplingResult(
            $decision,
            $this->attributes,
            $this->getTraceState()
        );
    }

    public function getDescription(): string
    {
        return 'VesvasiSampler';
    }

    private function isRootSpan(?ContextInterface $parentContext): bool
    {
        return !$this->hasParent($parentContext);
    }

    private function hasParent(?ContextInterface $parentContext): bool
    {
        if ($parentContext === null) {
            return false;
        }

        $parentSpan = \OpenTelemetry\API\Trace\Span::fromContext($parentContext);
        return $parentSpan->getContext()->isValid();
    }

    private function isErrorSpan(?AttributesInterface $attributes): bool
    {
        if ($attributes === null) {
            return false;
        }

        foreach ($attributes as $key => $value) {
            if ($key === 'error' && $value === true) {
                return true;
            }
            if ($key === 'http.status_code' && ((int) $value) >= 400) {
                return true;
            }
            if ($key === 'http.response_status_code' && ((int) $value) >= 400) {
                return true;
            }
        }

        return false;
    }

    private function decideSampling(): int
    {
        $percentage = $this->config->getHeadPercentage();

        if ($percentage >= 100) {
            return SamplingResult::RECORD_AND_SAMPLE;
        }

        if ($percentage <= 0) {
            return SamplingResult::DROP;
        }

        if ($this->config->shouldSample((float) (mt_rand() / mt_getrandmax()) * 100)) {
            return SamplingResult::RECORD_AND_SAMPLE;
        }

        return SamplingResult::DROP;
    }

    private function getParentDecision(?ContextInterface $parentContext): int
    {
        $parentSpan = \OpenTelemetry\API\Trace\Span::fromContext($parentContext ?? Context::getCurrent());
        $parentSampled = $parentSpan->getContext()->isSampled();

        return $parentSampled
            ? SamplingResult::RECORD_AND_SAMPLE
            : SamplingResult::NOT_SAMPLED;
    }

    private function getTraceState(): ?\OpenTelemetry\SDK\Trace\TraceState
    {
        return null;
    }
}