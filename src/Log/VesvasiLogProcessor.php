<?php

declare(strict_types=1);

namespace Vesvasi\Log;

use OpenTelemetry\SDK\Logs\LogRecordExporterInterface;
use OpenTelemetry\API\Logs\LogRecord;
use OpenTelemetry\SDK\Common\Attribute\Attributes;
use OpenTelemetry\SDK\Resource\ResourceInfo;
use Psr\Log\LogLevel;
use Vesvasi\Config\Config;
use Vesvasi\Config\Logs\LogsConfig;

final class VesvasiLogProcessor
{
    private Config $config;
    private LogsConfig $logsConfig;
    private ?LogRecordExporterInterface $exporter = null;
    private array $pendingLogs = [];
    private int $maxBatchSize = 100;
    private bool $enabled;

    private const SEVERITY_MAP = [
        'debug' => 5,
        'info' => 9,
        'notice' => 13,
        'warning' => 14,
        'error' => 17,
        'critical' => 19,
        'alert' => 21,
        'emergency' => 23,
    ];

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->logsConfig = $config->getLogs();
        $this->enabled = $this->logsConfig->isEnabled();
        $this->maxBatchSize = $config->getMaxBatchSize();
    }

    public function setExporter(LogRecordExporterInterface $exporter): void
    {
        $this->exporter = $exporter;
    }

    public function process(
        string $message,
        string $level = 'info',
        array $context = [],
        ?\Throwable $exception = null
    ): void {
        if (!$this->enabled || !$this->logsConfig->shouldIncludeLevel($level)) {
            return;
        }

        $logRecord = $this->createLogRecord($message, $level, $context, $exception);

        $this->pendingLogs[] = $logRecord;

        if (count($this->pendingLogs) >= $this->maxBatchSize) {
            $this->flush();
        }
    }

    private function createLogRecord(
        string $message,
        string $level,
        array $context,
        ?\Throwable $exception
    ): LogRecord {
        $record = new LogRecord($this->sanitizeMessage($message));
        $record->setSeverityNumber($this->getSeverityNumber($level));

        $record->setAttributes(
            Attributes::create([
                'log.level' => strtoupper($level),
                'log.level.text' => $level,
                'log.timestamp' => (int) (microtime(true) * 1000000000),
                'vesvasi.log' => true,
            ])
        );

        if ($this->logsConfig->shouldIncludeContext() && !empty($context)) {
            $redactedContext = $this->redactContext($context);
            $record->setAttributes(
                Attributes::create([
                    'log.context' => json_encode($redactedContext, JSON_UNESCAPED_UNICODE),
                ])
            );
        }

        if ($exception !== null) {
            $record->setAttributes(
                Attributes::create([
                    'exception.type' => get_class($exception),
                    'exception.message' => $exception->getMessage(),
                    'vesvasi.error' => true,
                ])
            );
        }

        return $record;
    }

    private function sanitizeMessage(string $message): string
    {
        $maxLength = $this->logsConfig->getMaxMessageLength();

        if (strlen($message) > $maxLength) {
            return substr($message, 0, $maxLength) . '... [truncated]';
        }

        return $message;
    }

    private function getSeverityNumber(string $level): int
    {
        return self::SEVERITY_MAP[strtolower($level)] ?? 9;
    }

    private function redactContext(array $context): array
    {
        $redacted = [];
        $redactFields = $this->logsConfig->getRedactFields();

        foreach ($context as $key => $value) {
            $shouldRedact = false;

            foreach ($redactFields as $field) {
                if (stripos((string) $key, $field) !== false) {
                    $shouldRedact = true;
                    break;
                }
            }

            if ($shouldRedact) {
                $redacted[$key] = '***REDACTED***';
            } elseif (is_array($value)) {
                $redacted[$key] = $this->redactContext($value);
            } else {
                $redacted[$key] = $value;
            }
        }

        return $redacted;
    }

    public function flush(): void
    {
        if ($this->exporter === null || empty($this->pendingLogs)) {
            return;
        }

        $logs = $this->pendingLogs;
        $this->pendingLogs = [];

        try {
            $generator = (function () use ($logs) {
                foreach ($logs as $log) {
                    yield $log;
                }
            })();

            $this->exporter->export($generator);
        } catch (\Throwable $e) {
            error_log('Vesvasi: Failed to export logs: ' . $e->getMessage());
        }
    }

    public function shutdown(): void
    {
        $this->flush();
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function enable(): void
    {
        $this->enabled = true;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    public function getPendingCount(): int
    {
        return count($this->pendingLogs);
    }
}