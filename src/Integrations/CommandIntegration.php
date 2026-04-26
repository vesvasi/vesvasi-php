<?php

declare(strict_types=1);

namespace Vesvasi\Integrations;

use Vesvasi\Config\Config;
use Vesvasi\Trace\VesvasiTracer;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;

final class CommandIntegration
{
    private Config $config;
    private VesvasiTracer $tracer;
    private array $activeSpans = [];

    private const ATTR_COMMAND = 'command';
    private const ATTR_COMMAND_ARGUMENTS = 'command.arguments';
    private const ATTR_COMMAND_EXIT_CODE = 'command.exit_code';
    private const ATTR_COMMAND_WORKING_DIR = 'command.working_directory';
    private const ATTR_COMMAND_TIMEOUT = 'command.timeout';
    private const ATTR_VESVASI_COMMAND = 'vesvasi.command';
    private const ATTR_VESVASI_EXIT_CODE = 'vesvasi.exit_code';

    public function __construct(Config $config, VesvasiTracer $tracer)
    {
        $this->config = $config;
        $this->tracer = $tracer;
    }

    public function trace(
        string $command,
        ?array $arguments = null,
        ?callable $callback = null
    ): CommandResult {
        $span = $this->createCommandSpan($command, $arguments);

        $startTime = microtime(true);
        $result = null;
        $exception = null;

        try {
            if ($callback !== null) {
                $result = $callback();
            }

            $duration = (microtime(true) - $startTime) * 1000;
            $this->endSpanSuccess($span, $duration);

            return new CommandResult(
                output: $result,
                exitCode: 0,
                duration: $duration,
                span: $span
            );
        } catch (\Throwable $e) {
            $duration = (microtime(true) - $startTime) * 1000;
            $this->endSpanError($span, $e, $duration);

            return new CommandResult(
                output: null,
                exitCode: $this->getExitCodeFromException($e),
                duration: $duration,
                span: $span,
                exception: $e
            );
        }
    }

    public function traceExec(
        string $command,
        ?string $workingDir = null,
        ?array $env = null
    ): CommandResult {
        $span = $this->createCommandSpan($command, ['mode' => 'exec']);

        if ($workingDir !== null) {
            $span->setAttribute(self::ATTR_COMMAND_WORKING_DIR, $workingDir);
        }

        $startTime = microtime(true);

        try {
            $output = [];
            $exitCode = 0;

            $commandToRun = $workingDir !== null
                ? "cd {$workingDir} && {$command}"
                : $command;

            exec($commandToRun, $output, $exitCode);

            $duration = (microtime(true) - $startTime) * 1000;
            $this->endSpanWithExitCode($span, $exitCode, $duration);

            return new CommandResult(
                output: implode("\n", $output),
                exitCode: $exitCode,
                duration: $duration,
                span: $span
            );
        } catch (\Throwable $e) {
            $duration = (microtime(true) - $startTime) * 1000;
            $this->endSpanError($span, $e, $duration);

            return new CommandResult(
                output: null,
                exitCode: 1,
                duration: $duration,
                span: $span,
                exception: $e
            );
        }
    }

    public function traceProcess(
        string $command,
        ?string $workingDir = null
    ): CommandResult {
        $span = $this->createCommandSpan($command, ['mode' => 'process']);

        if ($workingDir !== null) {
            $span->setAttribute(self::ATTR_COMMAND_WORKING_DIR, $workingDir);
        }

        $startTime = microtime(true);

        try {
            $descriptorSpec = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'e'],
            ];

            $process = proc_open($command, $descriptorSpec, $pipes, $workingDir);

            if (is_resource($process)) {
                $output = stream_get_contents($pipes[1]);
                $errors = stream_get_contents($pipes[2]);

                foreach ($pipes as $pipe) {
                    fclose($pipe);
                }

                $exitCode = proc_close($process);
                $duration = (microtime(true) - $startTime) * 1000;

                $this->endSpanWithExitCode($span, $exitCode, $duration);

                return new CommandResult(
                    output: $output,
                    errors: $errors,
                    exitCode: $exitCode,
                    duration: $duration,
                    span: $span
                );
            }

            throw new \RuntimeException('Failed to create process');
        } catch (\Throwable $e) {
            $duration = (microtime(true) - $startTime) * 1000;
            $this->endSpanError($span, $e, $duration);

            return new CommandResult(
                output: null,
                exitCode: 1,
                duration: $duration,
                span: $span,
                exception: $e
            );
        }
    }

    public function createCommandSpan(
        string $command,
        ?array $arguments = null,
        ?int $exitCode = null
    ): SpanInterface {
        $spanBuilder = $this->tracer->spanBuilder("Command {$command}")
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setAttribute(self::ATTR_VESVASI_COMMAND, true)
            ->setAttribute(self::ATTR_COMMAND, $command)
            ->setAttribute('process.command', $command)
            ->setAttribute('process.working_directory', getcwd() ?: '');

        if ($arguments !== null) {
            $spanBuilder->setAttribute(
                self::ATTR_COMMAND_ARGUMENTS,
                $this->formatArguments($arguments)
            );
        }

        if ($exitCode !== null) {
            $spanBuilder->setAttribute(self::ATTR_COMMAND_EXIT_CODE, $exitCode);
            $spanBuilder->setAttribute(self::ATTR_VESVASI_EXIT_CODE, $exitCode);

            if ($exitCode !== 0) {
                $spanBuilder->setAttribute('error', true);
            }
        }

        $span = $spanBuilder->startSpan();
        $this->activeSpans[$span->getContext()->getSpanId()] = $span;

        return $span;
    }

    private function formatArguments(array $arguments): string
    {
        $formatted = [];

        foreach ($arguments as $key => $value) {
            if (is_numeric($key)) {
                $formatted[] = $this->sanitizeArgument((string) $value);
            } else {
                $formatted[] = "--{$key}=" . $this->sanitizeArgument((string) $value);
            }
        }

        return implode(' ', $formatted);
    }

    private function sanitizeArgument(string $argument): string
    {
        if (str_contains($argument, ' ') && !str_starts_with($argument, '"')) {
            return '"' . $argument . '"';
        }

        return $argument;
    }

    private function endSpanSuccess(SpanInterface $span, float $durationMs): void
    {
        $span->setStatus(StatusCode::STATUS_OK);
        $span->end();
        unset($this->activeSpans[$span->getContext()->getSpanId()]);
    }

    private function endSpanWithExitCode(SpanInterface $span, int $exitCode, float $durationMs): void
    {
        $span->setAttribute(self::ATTR_COMMAND_EXIT_CODE, $exitCode);
        $span->setAttribute(self::ATTR_VESVASI_EXIT_CODE, $exitCode);

        if ($exitCode === 0) {
            $span->setStatus(StatusCode::STATUS_OK);
        } else {
            $span->setAttribute('error', true);
            $span->setStatus(StatusCode::STATUS_ERROR, "Exit code: {$exitCode}");
        }

        $span->end();
        unset($this->activeSpans[$span->getContext()->getSpanId()]);
    }

    private function endSpanError(SpanInterface $span, \Throwable $exception, float $durationMs): void
    {
        $span->recordException($exception);
        $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
        $span->end();
        unset($this->activeSpans[$span->getContext()->getSpanId()]);
    }

    private function getExitCodeFromException(\Throwable $e): int
    {
        if ($e instanceof \RuntimeException) {
            return 1;
        }

        return 1;
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

final class CommandResult
{
    public function __construct(
        public readonly mixed $output,
        public readonly ?string $errors = null,
        public readonly int $exitCode = 0,
        public readonly float $duration = 0,
        public readonly ?SpanInterface $span = null,
        public readonly ?\Throwable $exception = null
    ) {}

    public function isSuccessful(): bool
    {
        return $this->exitCode === 0 && $this->exception === null;
    }

    public function getOutput(): mixed
    {
        return $this->output;
    }

    public function getErrors(): ?string
    {
        return $this->errors;
    }

    public function getExitCode(): int
    {
        return $this->exitCode;
    }

    public function getDurationMs(): float
    {
        return $this->duration;
    }

    public function getSpan(): ?SpanInterface
    {
        return $this->span;
    }

    public function getException(): ?\Throwable
    {
        return $this->exception;
    }
}