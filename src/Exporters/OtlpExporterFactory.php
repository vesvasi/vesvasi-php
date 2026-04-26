<?php

declare(strict_types=1);

namespace Vesvasi\Exporters;

use OpenTelemetry\Contrib\Otlp\OtlpHttpTransportFactory;
use OpenTelemetry\Contrib\Otlp\SpanExporter;
use OpenTelemetry\Contrib\Otlp\MetricExporter;
use OpenTelemetry\Contrib\Otlp\LogsExporter;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Common\Export\TransportInterface;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use OpenTelemetry\SDK\Resource\ResourceInfoFactory;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Metrics\MetricExporterInterface;
use OpenTelemetry\SDK\Logs\LogRecordExporterInterface;
use Vesvasi\Config\Config;
use Vesvasi\Util\ProcessIdentifier;

interface ExporterFactoryInterface
{
    public function createSpanExporter(): SpanExporterInterface;
    public function createMetricExporter(): MetricExporterInterface;
    public function createLogExporter(): LogRecordExporterInterface;
    public function getResource(): ResourceInfo;
}

final class OtlpExporterFactory implements ExporterFactoryInterface
{
    private Config $config;
    private ResourceInfo $resource;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->resource = $this->createResource();
    }

    public function createSpanExporter(): SpanExporterInterface
    {
        $transport = $this->createTransport('traces');
        return new SpanExporter($transport);
    }

    public function createMetricExporter(): MetricExporterInterface
    {
        $transport = $this->createTransport('metrics');
        return new MetricExporter($transport);
    }

    public function createLogExporter(): LogRecordExporterInterface
    {
        $transport = $this->createTransport('logs');
        return new LogsExporter($transport);
    }

    public function getResource(): ResourceInfo
    {
        return $this->resource;
    }

    private function createTransport(string $signal): TransportInterface
    {
        $endpoint = $this->buildEndpoint($signal);
        $headers = $this->buildHeaders();

        $config = [
            'endpoint' => $endpoint,
            'headers' => $headers,
        ];

        if (!$this->config->getNetwork()->shouldVerifySsl()) {
            $config['verify_peer'] = false;
            $config['verify_host'] = false;
        }

        if ($this->config->getNetwork()->getProxyUrl() !== '') {
            $config['proxy'] = $this->config->getNetwork()->getProxyUrl();
        }

        $timeout = $this->config->getTimeout();
        $contentType = $this->config->getProtocol() === 'http/json' ? 'application/json' : 'application/x-protobuf';

        $endpoint = $this->buildEndpoint($signal);
        $headers = $this->buildHeaders();

        $factory = new OtlpHttpTransportFactory();
        return $factory->create($endpoint, $contentType, $headers, null, $timeout);
    }

    private function buildEndpoint(string $signal): string
    {
        $baseEndpoint = $this->config->getEndpoint();

        return match ($signal) {
            'traces' => $baseEndpoint . '/v1/traces',
            'metrics' => $baseEndpoint . '/v1/metrics',
            'logs' => $baseEndpoint . '/v1/logs',
            default => $baseEndpoint . '/v1/' . $signal,
        };
    }

    private function buildHeaders(): array
    {
        $headers = [];

        if ($this->config->hasApiKey()) {
            $headers['x-api-key'] = $this->config->getApiKey();
        }

        foreach ($this->config->getNetwork()->getExtraHeaders() as $key => $value) {
            $headers[$key] = $value;
        }

        return $headers;
    }

    private function createResource(): ResourceInfo
    {
        $serviceConfig = $this->config->getService();
        $runtimeInfo = ProcessIdentifier::getRuntimeInfo();
        $osInfo = ProcessIdentifier::getOsInfo();

        $attributes = array_merge(
            $serviceConfig->getResourceAttributes(),
            $runtimeInfo,
            $osInfo
        );

        return ResourceInfoFactory::defaultResource()->merge(
            ResourceInfo::create(Attributes::create($attributes))
        );
    }
}