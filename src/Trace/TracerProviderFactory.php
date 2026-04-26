<?php

declare(strict_types=1);

namespace Vesvasi\Trace;

use OpenTelemetry\SDK\Trace\TracerProviderBuilder;
use OpenTelemetry\SDK\Trace\TracerProvider;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\SpanProcessor\BatchSpanProcessor;
use OpenTelemetry\SDK\Common\Export\TransportInterface;
use OpenTelemetry\API\Common\Time\Clock;
use Vesvasi\Config\Config;
use Vesvasi\Samplers\VesvasiSampler;
use Vesvasi\Exporters\OtlpExporterFactory;

final class TracerProviderFactory
{
    private Config $config;
    private OtlpExporterFactory $exporterFactory;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->exporterFactory = new OtlpExporterFactory($config);
    }

    public function create(): TracerProvider
    {
        $sampler = new VesvasiSampler($this->config->getSampling());

        $builder = (new TracerProviderBuilder())
            ->setResource($this->exporterFactory->getResource())
            ->setSampler($sampler)
            ->addSpanProcessor(
                $this->createSpanProcessor()
            );

        return $builder->build();
    }

    private function createSpanProcessor(): \OpenTelemetry\SDK\Trace\SpanProcessorInterface
    {
        $exporter = $this->exporterFactory->createSpanExporter();
        $maxQueueSize = $this->config->getMaxQueueSize();
        $maxBatchSize = $this->config->getMaxBatchSize();
        $exportTimeout = $this->config->getTimeout() * 1000;

        return new BatchSpanProcessor(
            $exporter,
            Clock::getDefault(),
            $maxQueueSize,
            1000,
            $exportTimeout,
            $maxBatchSize,
            true
        );
    }

    public function getResource(): \OpenTelemetry\SDK\Resource\ResourceInfo
    {
        return $this->exporterFactory->getResource();
    }
}