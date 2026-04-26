<?php

declare(strict_types=1);

namespace Vesvasi\Trace;

use OpenTelemetry\API\Trace\SpanBuilderInterface;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;

final class VesvasiSpanBuilder implements SpanBuilderInterface
{
    private object $innerBuilder;
    private string $spanName;
    private int $spanKind;
    private array $attributes = [];
    private ?int $startTimestamp = null;
    private bool $vesvasiError = false;
    private bool $vesvasiSql = false;
    private bool $vesvasiHttp = false;
    private bool $vesvasiCommand = false;
    private ?\Throwable $exception = null;

    public function __construct(object $innerBuilder)
    {
        $this->innerBuilder = $innerBuilder;
        $this->spanKind = 0; // SpanKind::KIND_INTERNAL
    }

    public function setSpanName(string $name): self
    {
        $this->spanName = $name;
        return $this;
    }

    public function createSpanName(string $operation, ?string $resource = null): self
    {
        $this->spanName = $resource !== null
            ? "{$operation} {$resource}"
            : $operation;
        return $this;
    }

    public function setParent(\OpenTelemetry\Context\ContextInterface|false|null $context): self
    {
        if (method_exists($this->innerBuilder, 'setParent')) {
            $this->innerBuilder->setParent($context);
        }
        return $this;
    }

    public function setNoParent(): self
    {
        if (method_exists($this->innerBuilder, 'setNoParent')) {
            $this->innerBuilder->setNoParent();
        }
        return $this;
    }

    public function addLink(\OpenTelemetry\API\Trace\SpanContextInterface $context, iterable $attributes = []): self
    {
        if (method_exists($this->innerBuilder, 'addLink')) {
            $this->innerBuilder->addLink($context, $attributes);
        }
        return $this;
    }

    public function setStartTimestamp(int $startTimestamp): self
    {
        $this->startTimestamp = $startTimestamp;
        return $this;
    }

    public function setSpanKind(int $spanKind): self
    {
        $this->spanKind = $spanKind;
        if (method_exists($this->innerBuilder, 'setSpanKind')) {
            $this->innerBuilder->setSpanKind($spanKind);
        }
        return $this;
    }

    public function setAttribute(string $key, mixed $value): self
    {
        $this->attributes[$key] = $value;
        if (method_exists($this->innerBuilder, 'setAttribute')) {
            $this->innerBuilder->setAttribute($key, $value);
        }
        return $this;
    }

    public function setAttributes(iterable $attributes): self
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }
        return $this;
    }

    public function addEvent(string $name, iterable $attributes = [], ?int $timestamp = null): self
    {
        if (method_exists($this->innerBuilder, 'addEvent')) {
            $this->innerBuilder->addEvent($name, $attributes, $timestamp);
        }
        return $this;
    }

    public function recordException(\Throwable $exception): self
    {
        $this->exception = $exception;
        $this->vesvasiError = true;

        if (method_exists($this->innerBuilder, 'recordException')) {
            $this->innerBuilder->recordException($exception);
        } else {
            $this->setAttribute('exception.type', get_class($exception));
            $this->setAttribute('exception.message', $exception->getMessage());
            $this->setAttribute('exception.stacktrace', $exception->getTraceAsString());
        }
        return $this;
    }

    public function startSpan(): SpanInterface
    {
        $this->addVesvasiAttributes();

        if ($this->startTimestamp !== null && method_exists($this->innerBuilder, 'setStartTimestamp')) {
            $this->innerBuilder->setStartTimestamp($this->startTimestamp);
        }

        if (method_exists($this->innerBuilder, 'setSpanKind')) {
            $this->innerBuilder->setSpanKind($this->spanKind);
        }

        foreach ($this->attributes as $key => $value) {
            if (method_exists($this->innerBuilder, 'setAttribute')) {
                $this->innerBuilder->setAttribute($key, $value);
            }
        }

        $span = $this->innerBuilder->startSpan();

        if ($this->exception !== null) {
            $span->setStatus(StatusCode::STATUS_ERROR, $this->exception->getMessage());
        }

        return $span;
    }

    private function addVesvasiAttributes(): void
    {
        if ($this->vesvasiError) {
            $this->setAttribute('vesvasi.error', true);
        }

        if ($this->vesvasiSql) {
            $this->setAttribute('vesvasi.sql', true);
        }

        if ($this->vesvasiHttp) {
            $this->setAttribute('vesvasi.http', true);
        }

        if ($this->vesvasiCommand) {
            $this->setAttribute('vesvasi.command', true);
        }
    }

    public function setVesvasiError(bool $value = true): self
    {
        $this->vesvasiError = $value;
        return $this;
    }

    public function setVesvasiSql(bool $value = true): self
    {
        $this->vesvasiSql = $value;
        return $this;
    }

    public function setVesvasiHttp(bool $value = true): self
    {
        $this->vesvasiHttp = $value;
        return $this;
    }

    public function setVesvasiCommand(bool $value = true): self
    {
        $this->vesvasiCommand = $value;
        return $this;
    }

    public function setSqlAttributes(string $sql, ?string $dbName, ?string $dbDriver, ?array $bindings): self
    {
        $this->setAttribute('db.system', 'sql');
        $this->setAttribute('db.statement', $sql);

        if ($dbName !== null) {
            $this->setAttribute('db.name', $dbName);
            $this->setAttribute('vesvasi.db.name', $dbName);
        }

        if ($dbDriver !== null) {
            $this->setAttribute('db.driver', $dbDriver);
        }

        if ($bindings !== null) {
            $this->setAttribute('db.statement.bindings', json_encode($bindings));
        }

        return $this;
    }

    public function setHttpAttributes(string $method, string $url, ?int $statusCode, ?array $headers): self
    {
        $this->setAttribute('http.method', $method);
        $this->setAttribute('http.url', $url);
        $this->setAttribute('http.target', parse_url($url, PHP_URL_PATH) ?: '/');

        if ($statusCode !== null) {
            $this->setAttribute('http.status_code', $statusCode);
            $this->setAttribute('http.response_status_code', $statusCode);

            if ($statusCode >= 400) {
                $this->setAttribute('error', true);
                $this->vesvasiError = true;
            }
        }

        if ($headers !== null) {
            $this->setAttribute('http.request.header.content_type', $headers['Content-Type'] ?? $headers['content-type'] ?? '');
            $this->setAttribute('http.request.header.accept', $headers['Accept'] ?? $headers['accept'] ?? '');
        }

        $host = parse_url($url, PHP_URL_HOST);
        if ($host !== null) {
            $this->setAttribute('net.peer.name', $host);
        }

        $port = parse_url($url, PHP_URL_PORT);
        if ($port !== null) {
            $this->setAttribute('net.peer.port', $port);
        }

        return $this;
    }

    public function setCommandAttributes(string $command, ?array $arguments, ?int $exitCode): self
    {
        $this->setAttribute('command', $command);

        if ($arguments !== null) {
            $this->setAttribute('command.arguments', json_encode($arguments));
        }

        if ($exitCode !== null) {
            $this->setAttribute('command.exit_code', $exitCode);
            $this->setAttribute('vesvasi.exit_code', $exitCode);

            if ($exitCode !== 0) {
                $this->setAttribute('error', true);
                $this->vesvasiError = true;
            }
        }

        return $this;
    }

    public function setErrorAttributes(\Throwable $exception): self
    {
        $this->setAttribute('exception.type', get_class($exception));
        $this->setAttribute('exception.message', $exception->getMessage());
        $this->setAttribute('exception.stacktrace', $exception->getTraceAsString());
        $this->setAttribute('error', true);
        $this->vesvasiError = true;

        return $this;
    }

    public function getInnerBuilder(): object
    {
        return $this->innerBuilder;
    }
}