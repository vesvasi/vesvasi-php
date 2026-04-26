<?php

declare(strict_types=1);

namespace Vesvasi\Integrations;

use Vesvasi\Config\Config;
use Vesvasi\Trace\VesvasiTracer;
use Vesvasi\Integrations\Request\RequestIntegration;
use Vesvasi\Integrations\Cache\CacheIntegration;
use Vesvasi\Integrations\Queue\QueueIntegration;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;

final class AutoInstrumentation
{
    private Config $config;
    private VesvasiTracer $tracer;
    private SqlIntegration $sqlIntegration;
    private HttpIntegration $httpIntegration;
    private CommandIntegration $commandIntegration;
    private ExceptionIntegration $exceptionIntegration;
    private RequestIntegration $requestIntegration;
    private CacheIntegration $cacheIntegration;
    private QueueIntegration $queueIntegration;
    private bool $enabled = true;
    private bool $initialized = false;

    public function __construct(Config $config, VesvasiTracer $tracer)
    {
        $this->config = $config;
        $this->tracer = $tracer;
    }

    public function initialize(): void
    {
        if ($this->initialized) {
            return;
        }

        $this->sqlIntegration = new SqlIntegration($this->config, $this->tracer);
        $this->httpIntegration = new HttpIntegration($this->config, $this->tracer);
        $this->commandIntegration = new CommandIntegration($this->config, $this->tracer);
        $this->exceptionIntegration = new ExceptionIntegration($this->config, $this->tracer);
        $this->requestIntegration = new RequestIntegration($this->config, $this->tracer);
        $this->cacheIntegration = new CacheIntegration($this->config, $this->tracer);
        $this->queueIntegration = new QueueIntegration($this->config, $this->tracer);

        if ($this->enabled) {
            $this->registerHandlers();
        }

        $this->initialized = true;
    }

    private function registerHandlers(): void
    {
        if ($this->config->getFilters()->shouldIncludeClass('*')) {
            $this->sqlIntegration->register();
        }
    }

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getSqlIntegration(): SqlIntegration
    {
        return $this->sqlIntegration;
    }

    public function getHttpIntegration(): HttpIntegration
    {
        return $this->httpIntegration;
    }

    public function getCommandIntegration(): CommandIntegration
    {
        return $this->commandIntegration;
    }

    public function getExceptionIntegration(): ExceptionIntegration
    {
        return $this->exceptionIntegration;
    }

    public function getRequestIntegration(): RequestIntegration
    {
        return $this->requestIntegration;
    }

    public function getCacheIntegration(): CacheIntegration
    {
        return $this->cacheIntegration;
    }

    public function getQueueIntegration(): QueueIntegration
    {
        return $this->queueIntegration;
    }

    public function shutdown(): void
    {
        $this->sqlIntegration?->shutdown();
        $this->httpIntegration?->shutdown();
        $this->commandIntegration?->shutdown();
        $this->exceptionIntegration?->shutdown();
        $this->requestIntegration?->shutdown();
        $this->cacheIntegration?->shutdown();
        $this->queueIntegration?->shutdown();
    }
}