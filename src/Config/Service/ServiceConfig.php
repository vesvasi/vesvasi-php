<?php

declare(strict_types=1);

namespace Vesvasi\Config\Service;

use Vesvasi\Util\ProcessIdentifier;

final class ServiceConfig
{
    private string $name;
    private string $version;
    private string $environment;
    private string $namespace;
    private string $instanceId;

    public function __construct(array $config)
    {
        $this->name = $config['name'] ?? 'unknown-service';
        $this->version = $config['version'] ?? '0.0.0';
        $this->environment = $config['environment'] ?? 'development';
        $this->namespace = $config['namespace'] ?? '';
        $this->instanceId = $config['instance_id'] ?? ProcessIdentifier::generate();
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getVersion(): string
    {
        return $this->version;
    }

    public function getEnvironment(): string
    {
        return $this->environment;
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function getInstanceId(): string
    {
        return $this->instanceId;
    }

    public function getResourceAttributes(): array
    {
        $attributes = [
            'service.name' => $this->name,
            'service.version' => $this->version,
            'service.environment' => $this->environment,
        ];

        if ($this->namespace !== '') {
            $attributes['service.namespace'] = $this->namespace;
        }

        $attributes['service.instance.id'] = $this->instanceId;

        return $attributes;
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'version' => $this->version,
            'environment' => $this->environment,
            'namespace' => $this->namespace,
            'instance_id' => $this->instanceId,
        ];
    }
}