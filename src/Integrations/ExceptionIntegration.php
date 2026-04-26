<?php

declare(strict_types=1);

namespace Vesvasi\Integrations;

use Vesvasi\Config\Config;
use Vesvasi\Trace\VesvasiTracer;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;

final class ExceptionIntegration
{
    private Config $config;
    private VesvasiTracer $tracer;
    private array $exceptionHandlers = [];
    private bool $registered = false;

    private const ATTR_EXCEPTION_TYPE = 'exception.type';
    private const ATTR_EXCEPTION_MESSAGE = 'exception.message';
    private const ATTR_EXCEPTION_STACKTRACE = 'exception.stacktrace';
    private const ATTR_VESVASI_ERROR = 'vesvasi.error';

    public function __construct(Config $config, VesvasiTracer $tracer)
    {
        $this->config = $config;
        $this->tracer = $tracer;
    }

    public function register(): void
    {
        if ($this->registered) {
            return;
        }

        set_exception_handler([$this, 'handleException']);

        if (function_exists('set_error_handler')) {
            set_error_handler([$this, 'handleError']);
        }

        $this->registered = true;
    }

    public function handleException(\Throwable $exception): void
    {
        $span = $this->createExceptionSpan($exception);
        $span->recordException($exception);
        $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
        $span->end();

        foreach ($this->exceptionHandlers as $handler) {
            call_user_func($handler, $exception);
        }

        if (!$this->isPhpUnit()) {
            $this->reThrow($exception);
        }
    }

    public function handleError(int $severity, string $message, string $file, int $line): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }

        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    public function createExceptionSpan(\Throwable $exception): SpanInterface
    {
        $className = $this->getShortClassName($exception);

        return $this->tracer->spanBuilder("Exception {$className}")
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setAttribute(self::ATTR_VESVASI_ERROR, true)
            ->setAttribute(self::ATTR_EXCEPTION_TYPE, get_class($exception))
            ->setAttribute(self::ATTR_EXCEPTION_MESSAGE, $this->sanitizeMessage($exception->getMessage()))
            ->setAttribute(self::ATTR_EXCEPTION_STACKTRACE, $this->sanitizeStackTrace($exception))
            ->setAttribute('error', true)
            ->setAttribute('error.kind', $className)
            ->startSpan();
    }

    public function recordException(\Throwable $exception, ?SpanInterface $span = null): void
    {
        $span ??= $this->createExceptionSpan($exception);
        $span->recordException($exception);
        $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
    }

    public function addHandler(callable $handler): void
    {
        $this->exceptionHandlers[] = $handler;
    }

    public function removeHandler(callable $handler): void
    {
        $index = array_search($handler, $this->exceptionHandlers, true);
        if ($index !== false) {
            unset($this->exceptionHandlers[$index]);
        }
    }

    private function getShortClassName(\Throwable $exception): string
    {
        $className = get_class($exception);
        $parts = explode('\\', $className);
        return end($parts);
    }

    private function sanitizeMessage(string $message): string
    {
        $maxLength = 1000;

        if (strlen($message) > $maxLength) {
            return substr($message, 0, $maxLength) . '... [truncated]';
        }

        return $message;
    }

    private function sanitizeStackTrace(\Throwable $exception): string
    {
        $trace = $exception->getTraceAsString();
        $maxLength = $this->config->getLogs()->getMaxMessageLength();

        if (strlen($trace) > $maxLength) {
            $lines = explode("\n", $trace);
            $truncated = array_slice($lines, 0, 100);
            return implode("\n", $truncated) . "\n... [truncated]";
        }

        return $trace;
    }

    private function reThrow(\Throwable $exception): void
    {
        if (PHP_VERSION_ID >= 80000) {
            throw $exception;
        }

       restore_error_handler();
        restore_exception_handler();

        throw $exception;
    }

    private function isPhpUnit(): bool
    {
        return defined('PHPUNIT_COMPOSER_INSTALL')
            || defined('__PHPUNIT_PHAR__')
            || str_contains(($_SERVER['argv'][0] ?? ''), 'phpunit');
    }

    public function isRegistered(): bool
    {
        return $this->registered;
    }

    public function getHandlerCount(): int
    {
        return count($this->exceptionHandlers);
    }

    public function shutdown(): void
    {
        restore_exception_handler();
        restore_error_handler();
        $this->exceptionHandlers = [];
        $this->registered = false;
    }
}