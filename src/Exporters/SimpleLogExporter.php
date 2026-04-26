<?php

declare(strict_types=1);

namespace Vesvasi\Exporters;

use OpenTelemetry\API\Logs\LogRecord;
use OpenTelemetry\SDK\Common\Future\CancellationInterface;
use OpenTelemetry\SDK\Common\Future\CompletedFuture;
use OpenTelemetry\SDK\Common\Future\FutureInterface;
use OpenTelemetry\SDK\Logs\LogRecordExporterInterface;

final class SimpleLogExporter implements LogRecordExporterInterface
{
    public function export(iterable $batch, ?CancellationInterface $cancellation = null): FutureInterface
    {
        $count = 0;
        foreach ($batch as $record) {
            if ($record instanceof LogRecord) {
                $count++;
                echo $this->formatLogRecord($record) . "\n";
            }
        }

        if ($count > 0) {
            error_log(sprintf('Vesvasi: Exported %d log(s)', $count));
        }

        return new CompletedFuture(true);
    }

    private function formatLogRecord(LogRecord $record): string
    {
        $timestamp = $this->getPropertyValue($record, 'timestamp');
        $timestampStr = $timestamp ? date('Y-m-d H:i:s', (int) ($timestamp / 1000000000)) : date('Y-m-d H:i:s');
        $body = $this->getPropertyValue($record, 'body');
        $severity = $this->getPropertyValue($record, 'severityText') ?? 'INFO';

        if (is_array($body)) {
            $body = json_encode($body);
        } elseif (!is_string($body)) {
            $body = (string) $body;
        }

        return sprintf("[%s] [%s] %s", $timestampStr, strtoupper($severity), $body);
    }

    private function getPropertyValue(object $object, string $property): mixed
    {
        $reflection = new \ReflectionClass($object);
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        return $prop->getValue($object);
    }

    public function forceFlush(?CancellationInterface $cancellation = null): bool
    {
        return true;
    }

    public function shutdown(?CancellationInterface $cancellation = null): bool
    {
        return true;
    }
}