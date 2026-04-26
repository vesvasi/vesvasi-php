<?php

declare(strict_types=1);

namespace Vesvasi\Util;

final class ProcessIdentifier
{
    private static ?string $instanceId = null;

    public static function generate(): string
    {
        if (self::$instanceId !== null) {
            return self::$instanceId;
        }

        self::$instanceId = sprintf(
            '%s-%s-%s',
            self::getHostname(),
            getmypid(),
            substr(md5(uniqid((string) microtime(true), true)), 0, 8)
        );

        return self::$instanceId;
    }

    public static function getHostname(): string
    {
        return gethostname() ?: 'unknown';
    }

    public static function getProcessId(): int
    {
        return getmypid();
    }

    public static function getOsInfo(): array
    {
        return [
            'os.name' => PHP_OS,
            'os.family' => self::getOsFamily(),
            'os.version' => PHP_VERSION,
        ];
    }

    private static function getOsFamily(): string
    {
        return match (true) {
            defined('PHP_OS_FAMILY') => PHP_OS_FAMILY,
            stripos(PHP_OS, 'WIN') === 0 => 'Windows',
            stripos(PHP_OS, 'Darwin') === 0 => 'Darwin',
            stripos(PHP_OS, 'Linux') === 0 => 'Linux',
            stripos(PHP_OS, 'BSD') === 0 => 'BSD',
            default => 'Unknown',
        };
    }

    public static function getRuntimeInfo(): array
    {
        return [
            'telemetry.sdk.name' => 'vesvasi',
            'telemetry.sdk.version' => '1.0.0',
            'telemetry.sdk.language' => 'php',
            'process.runtime.name' => 'php',
            'process.runtime.version' => PHP_VERSION,
            'process.pid' => self::getProcessId(),
            'process.command_args' => $_SERVER['argv'] ?? [],
            'process.working_directory' => getcwd() ?: '',
        ];
    }
}