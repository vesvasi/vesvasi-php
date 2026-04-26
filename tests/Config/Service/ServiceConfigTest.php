<?php

declare(strict_types=1);

namespace Vesvasi\Tests\Config\Service;

use PHPUnit\Framework\TestCase;
use Vesvasi\Config\Service\ServiceConfig;

final class ServiceConfigTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $config = new ServiceConfig([]);

        $this->assertSame('unknown-service', $config->getName());
        $this->assertSame('0.0.0', $config->getVersion());
        $this->assertSame('development', $config->getEnvironment());
        $this->assertSame('', $config->getNamespace());
        $this->assertNotEmpty($config->getInstanceId());
    }

    public function testCustomValues(): void
    {
        $config = new ServiceConfig([
            'name' => 'my-app',
            'version' => '2.1.0',
            'environment' => 'production',
            'namespace' => 'backend',
            'instance_id' => 'instance-123',
        ]);

        $this->assertSame('my-app', $config->getName());
        $this->assertSame('2.1.0', $config->getVersion());
        $this->assertSame('production', $config->getEnvironment());
        $this->assertSame('backend', $config->getNamespace());
        $this->assertSame('instance-123', $config->getInstanceId());
    }

    public function testGetResourceAttributes(): void
    {
        $config = new ServiceConfig([
            'name' => 'my-service',
            'version' => '1.0.0',
            'environment' => 'staging',
            'namespace' => 'staging',
            'instance_id' => 'host-1',
        ]);

        $attributes = $config->getResourceAttributes();

        $this->assertArrayHasKey('service.name', $attributes);
        $this->assertArrayHasKey('service.version', $attributes);
        $this->assertArrayHasKey('service.environment', $attributes);
        $this->assertArrayHasKey('service.namespace', $attributes);
        $this->assertArrayHasKey('service.instance.id', $attributes);

        $this->assertSame('my-service', $attributes['service.name']);
        $this->assertSame('1.0.0', $attributes['service.version']);
        $this->assertSame('staging', $attributes['service.environment']);
        $this->assertSame('staging', $attributes['service.namespace']);
        $this->assertSame('host-1', $attributes['service.instance.id']);
    }

    public function testGetResourceAttributesWithoutNamespace(): void
    {
        $config = new ServiceConfig([
            'name' => 'my-service',
            'version' => '1.0.0',
            'environment' => 'production',
        ]);

        $attributes = $config->getResourceAttributes();

        $this->assertArrayNotHasKey('service.namespace', $attributes);
        $this->assertArrayHasKey('service.name', $attributes);
        $this->assertArrayHasKey('service.instance.id', $attributes);
    }

    public function testToArray(): void
    {
        $config = new ServiceConfig([
            'name' => 'test-service',
            'version' => '1.0.0',
            'environment' => 'test',
            'instance_id' => 'test-instance',
        ]);

        $array = $config->toArray();

        $this->assertSame('test-service', $array['name']);
        $this->assertSame('1.0.0', $array['version']);
        $this->assertSame('test', $array['environment']);
        $this->assertSame('test-instance', $array['instance_id']);
    }
}