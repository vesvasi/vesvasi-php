<?php

declare(strict_types=1);

namespace Vesvasi\Integrations;

use Vesvasi\Config\Config;
use Vesvasi\Trace\VesvasiTracer;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;

final class HttpIntegration
{
    private Config $config;
    private VesvasiTracer $tracer;
    private array $activeSpans = [];

    private const ATTR_HTTP_METHOD = 'http.method';
    private const ATTR_HTTP_URL = 'http.url';
    private const ATTR_HTTP_TARGET = 'http.target';
    private const ATTR_HTTP_HOST = 'net.peer.name';
    private const ATTR_HTTP_PORT = 'net.peer.port';
    private const ATTR_HTTP_SCHEME = 'http.scheme';
    private const ATTR_HTTP_STATUS_CODE = 'http.status_code';
    private const ATTR_HTTP_RESPONSE_STATUS_CODE = 'http.response_status_code';
    private const ATTR_HTTP_REQUEST_HEADER = 'http.request.header.';
    private const ATTR_HTTP_RESPONSE_HEADER = 'http.response.header.';
    private const ATTR_HTTP_USER_AGENT = 'http.user_agent';
    private const ATTR_VESVASI_HTTP = 'vesvasi.http';

    private const DEFAULT_TIMEOUT = 30;

    public function __construct(Config $config, VesvasiTracer $tracer)
    {
        $this->config = $config;
        $this->tracer = $tracer;
    }

    public function traceRequest(
        string $method,
        string $url,
        ?array $headers = null,
        ?string $body = null,
        ?callable $callback = null
    ): mixed {
        $span = $this->createHttpSpan($method, $url, $headers);

        try {
            if ($body !== null && $this->shouldIncludeBody($method)) {
                $span->setAttribute('http.request.body', $this->sanitizeBody($body));
            }

            $result = $callback !== null ? $callback() : null;

            $this->endSpanSuccess($span);
            return $result;
        } catch (\Throwable $e) {
            $this->endSpanError($span, $e);
            throw $e;
        }
    }

    public function createHttpSpan(
        string $method,
        string $url,
        ?array $headers = null,
        ?int $statusCode = null
    ): SpanInterface {
        $spanName = "HTTP {$method}";

        $parsedUrl = parse_url($url);
        $path = $parsedUrl['path'] ?? '/';
        $host = $parsedUrl['host'] ?? '';
        $port = $parsedUrl['port'] ?? null;
        $scheme = strtolower($parsedUrl['scheme'] ?? 'https');

        $spanBuilder = $this->tracer->spanBuilder($spanName)
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute(self::ATTR_VESVASI_HTTP, true)
            ->setAttribute(self::ATTR_HTTP_METHOD, strtoupper($method))
            ->setAttribute(self::ATTR_HTTP_URL, $url)
            ->setAttribute(self::ATTR_HTTP_TARGET, $path)
            ->setAttribute(self::ATTR_HTTP_SCHEME, $scheme)
            ->setAttribute(self::ATTR_HTTP_HOST, $host);

        if ($port !== null) {
            $spanBuilder->setAttribute(self::ATTR_HTTP_PORT, $port);
        }

        if ($statusCode !== null) {
            $spanBuilder->setAttribute(self::ATTR_HTTP_STATUS_CODE, $statusCode);
            $spanBuilder->setAttribute(self::ATTR_HTTP_RESPONSE_STATUS_CODE, $statusCode);
        }

        if ($headers !== null) {
            $this->addHeadersToSpan($spanBuilder, $headers);
        }

        return $spanBuilder->startSpan();
    }

    public function createServerSpan(
        string $method,
        string $url,
        ?array $headers = null,
        ?int $statusCode = null
    ): SpanInterface {
        $spanName = "Server HTTP {$method}";

        $spanBuilder = $this->tracer->spanBuilder($spanName)
            ->setSpanKind(SpanKind::KIND_SERVER)
            ->setAttribute(self::ATTR_VESVASI_HTTP, true)
            ->setAttribute(self::ATTR_HTTP_METHOD, strtoupper($method))
            ->setAttribute(self::ATTR_HTTP_URL, $url)
            ->setAttribute(self::ATTR_HTTP_SCHEME, parse_url($url, PHP_URL_SCHEME) ?? 'https')
            ->setAttribute('http.host', parse_url($url, PHP_URL_HOST) ?? '')
            ->setAttribute('http.target', parse_url($url, PHP_URL_PATH) ?? '/');

        if ($headers !== null) {
            $this->addHeadersToSpan($spanBuilder, $headers);
        }

        if ($statusCode !== null) {
            $spanBuilder->setAttribute(self::ATTR_HTTP_STATUS_CODE, $statusCode);
        }

        return $spanBuilder->startSpan();
    }

    private function addHeadersToSpan($spanBuilder, array $headers): void
    {
        $redactedHeaders = ['authorization', 'cookie', 'set-cookie', 'x-api-key', 'x-auth-token'];

        foreach ($headers as $name => $value) {
            $lowerName = strtolower($name);

            if (in_array($lowerName, $redactedHeaders, true)) {
                $spanBuilder->setAttribute(self::ATTR_HTTP_REQUEST_HEADER . $lowerName, '[REDACTED]');
            } else {
                $spanBuilder->setAttribute(self::ATTR_HTTP_REQUEST_HEADER . $lowerName, $value);
            }
        }
    }

    private function shouldIncludeBody(string $method): bool
    {
        return in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true);
    }

    private function sanitizeBody(string $body): string
    {
        $maxLength = 10000;

        if (strlen($body) > $maxLength) {
            return substr($body, 0, $maxLength) . '... [truncated]';
        }

        if ($this->isJson($body)) {
            $decoded = json_decode($body, true);
            if ($decoded !== null) {
                return $this->redactSensitiveFields($decoded);
            }
        }

        return $body;
    }

    private function isJson(string $body): bool
    {
        json_decode($body);
        return json_last_error() === JSON_ERROR_NONE;
    }

    private function redactSensitiveFields(array $data): string
    {
        $sensitiveKeys = ['password', 'token', 'secret', 'api_key', 'authorization', 'credit_card'];
        $result = [];

        foreach ($data as $key => $value) {
            $lowerKey = strtolower($key);

            if (in_array($lowerKey, $sensitiveKeys, true)) {
                $result[$key] = '[REDACTED]';
            } elseif (is_array($value)) {
                $result[$key] = $this->redactSensitiveFields($value);
            } else {
                $result[$key] = $value;
            }
        }

        return json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    private function endSpanSuccess(SpanInterface $span): void
    {
        $span->setStatus(StatusCode::STATUS_OK);
        $span->end();
    }

    private function endSpanError(SpanInterface $span, \Throwable $exception): void
    {
        $span->recordException($exception);
        $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
        $span->end();
    }

    public function getActiveSpansCount(): int
    {
        return count($this->activeSpans);
    }

    public function shutdown(): void
    {
        foreach ($this->activeSpans as $span) {
            $span->end();
        }
        $this->activeSpans = [];
    }
}