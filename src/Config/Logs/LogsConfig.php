<?php

declare(strict_types=1);

namespace Vesvasi\Config\Logs;

final class LogsConfig
{
    private bool $enabled;
    private array $levels;
    private int $maxMessageLength;
    private bool $includeStackTrace;
    private bool $includeContext;
    private array $redactFields;

    public function __construct(array $config)
    {
        $this->enabled = (bool) ($config['enabled'] ?? true);
        $this->levels = $config['levels'] ?? ['debug', 'info', 'warning', 'error', 'critical'];
        $this->maxMessageLength = (int) ($config['max_message_length'] ?? 10000);
        $this->includeStackTrace = (bool) ($config['include_stack_trace'] ?? true);
        $this->includeContext = (bool) ($config['include_context'] ?? true);
        $this->redactFields = $config['redact_fields'] ?? ['password', 'token', 'secret', 'api_key'];
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function getLevels(): array
    {
        return $this->levels;
    }

    public function shouldIncludeLevel(string $level): bool
    {
        return in_array(strtolower($level), $this->levels, true);
    }

    public function getMaxMessageLength(): int
    {
        return $this->maxMessageLength;
    }

    public function shouldIncludeStackTrace(): bool
    {
        return $this->includeStackTrace;
    }

    public function shouldIncludeContext(): bool
    {
        return $this->includeContext;
    }

    public function getRedactFields(): array
    {
        return $this->redactFields;
    }

    public function redactValue(string $key, mixed $value): mixed
    {
        if (!is_string($value)) {
            return $value;
        }

        foreach ($this->redactFields as $field) {
            if (stripos($key, $field) !== false) {
                return '***REDACTED***';
            }
        }

        return $value;
    }

    public function toArray(): array
    {
        return [
            'enabled' => $this->enabled,
            'levels' => $this->levels,
            'max_message_length' => $this->maxMessageLength,
            'include_stack_trace' => $this->includeStackTrace,
            'include_context' => $this->includeContext,
            'redact_fields' => $this->redactFields,
        ];
    }
}