<?php

declare(strict_types=1);

namespace Vesvasi\Contract;

interface ExporterAdapterInterface
{
    public function export(iterable $batch): iterable;

    public function shutdown(): bool;

    public function forceFlush(): bool;
}