<?php

declare(strict_types=1);

namespace Vesvasi\Config\Network;

final class NetworkConfig
{
    private string $proxyUrl;
    private int $connectTimeout;
    private int $readTimeout;
    private int $writeTimeout;
    private bool $verifySsl;
    private ?string $caBundlePath;
    private array $extraHeaders;

    public function __construct(array $config)
    {
        $this->proxyUrl = $config['proxy_url'] ?? '';
        $this->connectTimeout = (int) ($config['connect_timeout'] ?? 10);
        $this->readTimeout = (int) ($config['read_timeout'] ?? 30);
        $this->writeTimeout = (int) ($config['write_timeout'] ?? 30);
        $this->verifySsl = (bool) ($config['verify_ssl'] ?? true);
        $this->caBundlePath = $config['ca_bundle_path'] ?? null;
        $this->extraHeaders = $config['extra_headers'] ?? [];
    }

    public function getProxyUrl(): string
    {
        return $this->proxyUrl;
    }

    public function getConnectTimeout(): int
    {
        return $this->connectTimeout;
    }

    public function getReadTimeout(): int
    {
        return $this->readTimeout;
    }

    public function getWriteTimeout(): int
    {
        return $this->writeTimeout;
    }

    public function shouldVerifySsl(): bool
    {
        return $this->verifySsl;
    }

    public function getCaBundlePath(): ?string
    {
        return $this->caBundlePath;
    }

    public function getExtraHeaders(): array
    {
        return $this->extraHeaders;
    }

    public function toArray(): array
    {
        return [
            'proxy_url' => $this->proxyUrl,
            'connect_timeout' => $this->connectTimeout,
            'read_timeout' => $this->readTimeout,
            'write_timeout' => $this->writeTimeout,
            'verify_ssl' => $this->verifySsl,
            'ca_bundle_path' => $this->caBundlePath,
            'extra_headers' => $this->extraHeaders,
        ];
    }
}