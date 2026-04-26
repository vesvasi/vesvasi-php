<?php

declare(strict_types=1);

namespace Vesvasi\Contract;

use Vesvasi\Vesvasi;

interface InstrumentationInterface
{
    public function register(Vesvasi $vesvasi): void;

    public function unregister(): void;

    public function isEnabled(): bool;
}