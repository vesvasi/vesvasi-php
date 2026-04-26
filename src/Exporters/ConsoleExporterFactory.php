<?php

declare(strict_types=1);

namespace Vesvasi\Exporters;

use OpenTelemetry\SDK\Common\Export\Stream\StreamTransportFactory;
use OpenTelemetry\SDK\Trace\SpanExporterInterface;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Common\Export\TransportInterface;

final class ConsoleExporterFactory
{
    private bool $verbose;
    private bool $prettyPrint;

    public function __construct(bool $verbose = false, bool $prettyPrint = true)
    {
        $this->verbose = $verbose;
        $this->prettyPrint = $prettyPrint;
    }

    public function createSpanExporter(): SpanExporterInterface
    {
        if ($this->verbose) {
            $stream = fopen('php://stdout', 'w');
            $transport = (new StreamTransportFactory())->create(
                $stream,
                'application/json',
                []
            );
            return new VerboseSpanExporter($transport, $this->prettyPrint);
        }

        return new InMemoryExporter();
    }

    private function createTransport(): TransportInterface
    {
        $stream = fopen('php://stdout', 'w');
        return (new StreamTransportFactory())->create(
            $stream,
            'application/json',
            []
        );
    }
}