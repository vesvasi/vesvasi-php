<?php

declare(strict_types=1);

namespace Vesvasi\Exporters;

use GuzzleHttp\Client;
use OpenTelemetry\API\Logs\LogRecord;
use OpenTelemetry\SDK\Common\Future\CancellationInterface;
use OpenTelemetry\SDK\Common\Future\CompletedFuture;
use OpenTelemetry\SDK\Common\Future\FutureInterface;
use OpenTelemetry\SDK\Logs\LogRecordExporterInterface;
use Vesvasi\Config\Config;
use Vesvasi\Util\ProcessIdentifier;

final class VesvasiLogExporter implements LogRecordExporterInterface
{
    private Config $config;
    private Client $client;
    private array $resourceAttributes;

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->client = new Client(['timeout' => $config->getTimeout()]);
        $this->resourceAttributes = $this->buildResourceAttributes($config);
    }

    public function export(iterable $batch, ?CancellationInterface $cancellation = null): FutureInterface
    {
        $logRecords = [];

        foreach ($batch as $record) {
            if ($record instanceof LogRecord) {
                $logRecords[] = $this->convertLogRecord($record);
            }
        }

        if (empty($logRecords)) {
            return new CompletedFuture(true);
        }

        $payload = [
            'resourceLogs' => [
                [
                    'resource' => [
                        'attributes' => $this->convertAttributes($this->resourceAttributes),
                    ],
                    'scopeLogs' => [
                        [
                            'scope' => [
                                'name' => 'vesvasi',
                                'version' => '1.0.0',
                            ],
                            'logRecords' => $logRecords,
                        ],
                    ],
                ],
            ],
        ];

        try {
            $headers = ['Content-Type' => 'application/json'];
            if ($this->config->hasApiKey()) {
                $headers['x-api-key'] = $this->config->getApiKey();
            }

            $endpoint = $this->config->getEndpoint() . '/v1/logs';
            $response = $this->client->post($endpoint, [
                'headers' => $headers,
                'json' => $payload,
            ]);

            if ($response->getStatusCode() === 200) {
                return new CompletedFuture(true);
            }

            return new CompletedFuture(false);
        } catch (\Throwable $e) {
            error_log('Vesvasi: Failed to export logs: ' . $e->getMessage());
            return new CompletedFuture(false);
        }
    }

    public function forceFlush(?CancellationInterface $cancellation = null): bool
    {
        return true;
    }

    public function shutdown(?CancellationInterface $cancellation = null): bool
    {
        return true;
    }

    private function convertLogRecord(LogRecord $record): array
    {
        $timestamp = $this->getPropertyValue($record, 'timestamp');
        $body = $this->getPropertyValue($record, 'body');
        $severityNumber = $this->getPropertyValue($record, 'severityNumber') ?? 9;
        $severityText = $this->getPropertyValue($record, 'severityText') ?? 'INFO';
        $attributes = $this->getPropertyValue($record, 'attributes');

        $logRecord = [
            'timeUnixNano' => (string) ($timestamp ?? (int) (microtime(true) * 1000000000)),
            'severityNumber' => $severityNumber,
            'severityText' => $severityText,
        ];

        if ($body !== null) {
            $logRecord['body'] = [
                'stringValue' => is_string($body) ? $body : (string) $body,
            ];
        }

        if (!empty($attributes)) {
            $logRecord['attributes'] = $this->convertAttributes($this->flattenAttributes($attributes));
        }

        return $logRecord;
    }

    private function getPropertyValue(object $object, string $property): mixed
    {
        $reflection = new \ReflectionClass($object);
        $prop = $reflection->getProperty($property);
        $prop->setAccessible(true);
        return $prop->getValue($object);
    }

    private function flattenAttributes(iterable $attributes): array
    {
        $result = [];
        foreach ($attributes as $key => $value) {
            $result[$key] = $value;
        }
        return $result;
    }

    private function convertAttributes(array $attributes): array
    {
        $result = [];
        foreach ($attributes as $key => $value) {
            $result[] = [
                'key' => $key,
                'value' => $this->convertValue($value),
            ];
        }
        return $result;
    }

    private function convertValue(mixed $value): array
    {
        if (is_string($value)) {
            return ['stringValue' => $value];
        }
        if (is_int($value)) {
            return ['intValue' => (string) $value];
        }
        if (is_float($value)) {
            return ['doubleValue' => $value];
        }
        if (is_bool($value)) {
            return ['boolValue' => $value];
        }
        return ['stringValue' => json_encode($value)];
    }

    private function buildResourceAttributes(Config $config): array
    {
        $serviceConfig = $config->getService();
        $runtimeInfo = ProcessIdentifier::getRuntimeInfo();
        $osInfo = ProcessIdentifier::getOsInfo();

        return array_merge(
            $serviceConfig->getResourceAttributes(),
            $runtimeInfo,
            $osInfo
        );
    }
}