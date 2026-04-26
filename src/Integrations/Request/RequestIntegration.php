<?php

declare(strict_types=1);

namespace Vesvasi\Integrations\Request;

use Vesvasi\Config\Config;
use Vesvasi\Trace\VesvasiTracer;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;

final class RequestIntegration
{
    private Config $config;
    private VesvasiTracer $tracer;
    private array $startTimes = [];
    private array $requestCounts = [];

    private const ATTR_REQUEST_METHOD = 'request.method';
    private const ATTR_REQUEST_URL = 'request.url';
    private const ATTR_REQUEST_PATH = 'request.path';
    private const ATTR_REQUEST_HOST = 'request.host';
    private const ATTR_REQUEST_SCHEME = 'request.scheme';
    private const ATTR_REQUEST_QUERY = 'request.query';
    private const ATTR_REQUEST_SIZE = 'request.size';
    private const ATTR_REQUEST_HEADERS = 'request.headers';
    private const ATTR_REQUEST_BODY = 'request.body';

    private const ATTR_RESPONSE_STATUS = 'response.status_code';
    private const ATTR_RESPONSE_SIZE = 'response.size';
    private const ATTR_RESPONSE_TIME = 'response.time_ms';
    private const ATTR_RESPONSE_HEADERS = 'response.headers';

    private const ATTR_VESVASI_REQUEST = 'vesvasi.request';
    private const ATTR_VESVASI_DURATION = 'vesvasi.duration_ms';
    private const ATTR_VESVASI_CPU_USER = 'vesvasi.cpu_user_ms';
    private const ATTR_VESVASI_MEMORY_PEAK = 'vesvasi.memory_peak_mb';
    private const ATTR_VESVASI_MEMORY_USED = 'vesvasi.memory_used_mb';

    public function __construct(Config $config, VesvasiTracer $tracer)
    {
        $this->config = $config;
        $this->tracer = $tracer;
    }

    public function startRequestSpan(
        string $method,
        string $url,
        ?array $headers = null,
        ?string $body = null,
        ?string $query = null
    ): SpanInterface {
        $parsedUrl = parse_url($url);
        $path = $parsedUrl['path'] ?? '/';
        $host = $parsedUrl['host'] ?? '';
        $scheme = strtolower($parsedUrl['scheme'] ?? 'https');

        $spanBuilder = $this->tracer->spanBuilder("HTTP {$method} {$path}")
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setAttribute(self::ATTR_VESVASI_REQUEST, true)
            ->setAttribute(self::ATTR_REQUEST_METHOD, strtoupper($method))
            ->setAttribute(self::ATTR_REQUEST_URL, $url)
            ->setAttribute(self::ATTR_REQUEST_PATH, $path)
            ->setAttribute(self::ATTR_REQUEST_HOST, $host)
            ->setAttribute(self::ATTR_REQUEST_SCHEME, $scheme)
            ->setAttribute('http.target', $path)
            ->setAttribute('http.host', $host)
            ->setAttribute('http.scheme', $scheme)
            ->setAttribute('http.method', strtoupper($method))
            ->setAttribute('http.user_agent', $headers['User-Agent'] ?? $headers['user-agent'] ?? '')
            ->setAttribute('http.request_content_length', strlen($body ?? ''));

        if ($query !== null) {
            $spanBuilder->setAttribute(self::ATTR_REQUEST_QUERY, $query);
        }

        if ($headers !== null) {
            $this->addRequestHeaders($spanBuilder, $headers);
        }

        $span = $spanBuilder->startSpan();
        $this->startTimes[$span->getContext()->getSpanId()] = [
            'start' => microtime(true),
            'start_cpu' => $this->getCpuTime(),
            'start_memory' => memory_get_usage(true),
            'start_peak' => memory_get_peak_usage(true),
        ];

        return $span;
    }

    public function endRequestSpan(
        SpanInterface $span,
        int $statusCode,
        ?array $responseHeaders = null,
        ?int $responseSize = null
    ): void {
        $spanId = $span->getContext()->getSpanId();

        if (!isset($this->startTimes[$spanId])) {
            $span->end();
            return;
        }

        $startData = $this->startTimes[$spanId];
        $endTime = microtime(true);
        $endCpu = $this->getCpuTime();
        $endMemory = memory_get_usage(true);
        $endPeak = memory_get_peak_usage(true);

        $durationMs = ($endTime - $startData['start']) * 1000;
        $cpuUserMs = $endCpu - $startData['start_cpu'];
        $memoryUsedMb = $endMemory / (1024 * 1024);
        $memoryPeakMb = $endPeak / (1024 * 1024);

        $span->setAttribute(self::ATTR_VESVASI_DURATION, round($durationMs, 2));
        $span->setAttribute(self::ATTR_VESVASI_CPU_USER, round($cpuUserMs, 2));
        $span->setAttribute(self::ATTR_VESVASI_MEMORY_USED, round($memoryUsedMb, 2));
        $span->setAttribute(self::ATTR_VESVASI_MEMORY_PEAK, round($memoryPeakMb, 2));
        $span->setAttribute(self::ATTR_RESPONSE_STATUS, $statusCode);
        $span->setAttribute(self::ATTR_RESPONSE_TIME, round($durationMs, 2));
        $span->setAttribute('http.status_code', $statusCode);

        if ($responseHeaders !== null) {
            $this->addResponseHeaders($span, $responseHeaders);
        }

        if ($responseSize !== null) {
            $span->setAttribute(self::ATTR_RESPONSE_SIZE, $responseSize);
            $span->setAttribute('http.response_content_length', $responseSize);
        }

        if ($statusCode >= 500) {
            $span->setAttribute('error', true);
            $span->setAttribute('vesvasi.error', true);
            $span->setStatus(StatusCode::STATUS_ERROR, "HTTP {$statusCode}");
        } elseif ($statusCode >= 400) {
            $span->setAttribute('error', true);
            $span->setStatus(StatusCode::STATUS_ERROR, "HTTP {$statusCode}");
        } else {
            $span->setStatus(StatusCode::STATUS_OK);
        }

        $span->end();
        unset($this->startTimes[$spanId]);
    }

    public function traceRequest(
        string $method,
        string $url,
        ?array $headers = null,
        ?string $body = null,
        callable $callback = null
    ): mixed {
        $span = $this->startRequestSpan($method, $url, $headers, $body);
        $result = null;
        $statusCode = 200;
        $exception = null;

        try {
            if ($callback !== null) {
                $result = $callback();
            }
        } catch (\Throwable $e) {
            $exception = $e;
            $statusCode = $this->getStatusCodeFromException($e);
            $span->recordException($e);
        }

        $this->endRequestSpan($span, $statusCode);

        if ($exception !== null) {
            throw $exception;
        }

        return $result;
    }

    private function addRequestHeaders($spanBuilder, array $headers): void
    {
        $redactedHeaders = ['authorization', 'cookie', 'x-api-key', 'x-auth-token', 'x-csrf-token'];

        foreach ($headers as $name => $value) {
            $lowerName = strtolower($name);

            if (in_array($lowerName, $redactedHeaders, true)) {
                $spanBuilder->setAttribute("request.header.{$lowerName}", '[REDACTED]');
            } else {
                $spanBuilder->setAttribute("request.header.{$lowerName}", $value);
            }
        }
    }

    private function addResponseHeaders(SpanInterface $span, array $headers): void
    {
        foreach ($headers as $name => $value) {
            $lowerName = strtolower($name);
            $span->setAttribute("response.header.{$lowerName}", $value);
        }
    }

    private function getCpuTime(): float
    {
        $usage = getrusage();
        return ($usage['ru_utime.tv_sec'] + $usage['ru_utime.tv_usec'] / 1000000);
    }

    private function getStatusCodeFromException(\Throwable $e): int
    {
        if ($e instanceof \InvalidArgumentException) {
            return 400;
        }
        if ($e instanceof \AuthenticationException) {
            return 401;
        }
        if ($e instanceof \AuthorizationException) {
            return 403;
        }
        if ($e instanceof \NotFoundException) {
            return 404;
        }
        if ($e instanceof \ValidationException) {
            return 422;
        }

        return 500;
    }

    public function incrementRequestCount(string $method, int $statusCode): void
    {
        $key = "{$method}_{$statusCode}";
        if (!isset($this->requestCounts[$key])) {
            $this->requestCounts[$key] = 0;
        }
        $this->requestCounts[$key]++;
    }

    public function getRequestCounts(): array
    {
        return $this->requestCounts;
    }

    public function shutdown(): void
    {
        $this->startTimes = [];
        $this->requestCounts = [];
    }
}