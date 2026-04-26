<?php

declare(strict_types=1);

namespace Vesvasi\Exporters;

use OpenTelemetry\SDK\Common\Export\TransportInterface;
use OpenTelemetry\SDK\Common\Future\CancellationInterface;
use OpenTelemetry\SDK\Common\Future\CompletedFuture;
use OpenTelemetry\SDK\Common\Future\FutureInterface;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Common\Export\Stream\StreamTransport;

final class VerboseSpanExporter implements SpanExporterInterface
{
    private StreamTransport $transport;
    private bool $prettyPrint;

    public function __construct(TransportInterface $transport, bool $prettyPrint = true)
    {
        $this->transport = $transport;
        $this->prettyPrint = $prettyPrint;
    }

    public static function createStdoutExporter(bool $prettyPrint = true): self
    {
        $transport = new StreamTransport(
            fopen('php://stdout', 'w'),
            'application/json'
        );
        return new self($transport, $prettyPrint);
    }

    public function export(
        iterable $batch,
        ?CancellationInterface $cancellation = null
    ): FutureInterface {
        foreach ($batch as $span) {
            $data = $this->formatSpan($span);

            $content = $this->prettyPrint
                ? json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                : json_encode($data);

            $this->transport->send($content . "\n");
        }

        return new CompletedFuture(true);
    }

    private function formatSpan($span): array
    {
        return [
            'name' => $span->getName(),
            'trace_id' => $span->getTraceId(),
            'span_id' => $span->getSpanId(),
            'parent_span_id' => $span->getParent()?->getSpanId(),
            'attributes' => $span->getAttributes()->toArray(),
            'status' => $span->getContext()->isSampled() ? 'sampled' : 'not_sampled',
            'start_time' => $span->getStartEpochTimestamp(),
            'end_time' => $span->getEndEpochTimestamp(),
            'duration_ms' => ($span->getEndEpochTimestamp() - $span->getStartEpochTimestamp()) / 1000,
        ];
    }

    public function shutdown(CancellationInterface|null $cancellation = null): bool
    {
        return true;
    }

    public function forceFlush(CancellationInterface|null $cancellation = null): bool
    {
        return true;
    }
}