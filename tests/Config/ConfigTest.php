<?php

declare(strict_types=1);

namespace Vesvasi\Tests\Config;

use PHPUnit\Framework\TestCase;
use Vesvasi\Config\Config;
use Vesvasi\Config\Service\ServiceConfig;
use Vesvasi\Config\Sampling\SamplingConfig;
use Vesvasi\Config\Filters\FilterConfig;

final class ConfigTest extends TestCase
{
    protected function setUp(): void
    {
        Config::reset();
    }

    public function testLoadConfig(): void
    {
        $config = Config::load([
            'api_key' => 'test-key',
            'endpoint' => 'https://otlp.example.com:4318',
            'protocol' => 'http/protobuf',
            'service' => [
                'name' => 'test-service',
                'version' => '1.0.0',
                'environment' => 'testing',
            ],
        ]);

        $this->assertSame('test-key', $config->getApiKey());
        $this->assertSame('https://otlp.example.com:4318', $config->getEndpoint());
        $this->assertSame('http/protobuf', $config->getProtocol());
    }

    public function testLoadConfigWithoutApiKey(): void
    {
        $config = Config::load([
            'endpoint' => 'https://otlp.example.com:4318',
        ]);

        $this->assertFalse($config->hasApiKey());
        $this->assertSame('', $config->getApiKey());
    }

    public function testGetServiceConfig(): void
    {
        $config = Config::load([
            'endpoint' => 'https://otlp.example.com:4318',
            'service' => [
                'name' => 'my-service',
                'version' => '2.0.0',
                'environment' => 'staging',
                'namespace' => 'staging',
            ],
        ]);

        $serviceConfig = $config->getService();

        $this->assertSame('my-service', $serviceConfig->getName());
        $this->assertSame('2.0.0', $serviceConfig->getVersion());
        $this->assertSame('staging', $serviceConfig->getEnvironment());
        $this->assertSame('staging', $serviceConfig->getNamespace());
    }

    public function testGetSamplingConfig(): void
    {
        $config = Config::load([
            'endpoint' => 'https://otlp.example.com:4318',
            'sampling' => [
                'head_percentage' => 50,
                'always_sample_errors' => true,
            ],
        ]);

        $samplingConfig = $config->getSampling();

        $this->assertSame(50.0, $samplingConfig->getHeadPercentage());
        $this->assertTrue($samplingConfig->alwaysSampleErrors());
    }

    public function testGetFiltersConfig(): void
    {
        $config = Config::load([
            'endpoint' => 'https://otlp.example.com:4318',
            'filters' => [
                'cpu_threshold' => 10,
                'memory_threshold' => 100,
                'include_files' => ['/app/*'],
                'exclude_files' => ['/app/vendor/*'],
            ],
        ]);

        $filtersConfig = $config->getFilters();

        $this->assertSame(10.0, $filtersConfig->getCpuThreshold());
        $this->assertSame(100.0, $filtersConfig->getMemoryThreshold());
        $this->assertTrue($filtersConfig->shouldIncludeFile('/app/src/Controller.php'));
        $this->assertFalse($filtersConfig->shouldIncludeFile('/app/vendor/SomeClass.php'));
    }

    public function testInvalidEndpointThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Endpoint is required');

        Config::load([
            'endpoint' => '',
        ]);
    }

    public function testInvalidProtocolThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported protocol');

        Config::load([
            'endpoint' => 'https://otlp.example.com:4318',
            'protocol' => 'invalid/protocol',
        ]);
    }

    public function testConfigAlreadyLoadedThrowsException(): void
    {
        Config::load([
            'endpoint' => 'https://otlp.example.com:4318',
        ]);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Config already loaded');

        Config::load([
            'endpoint' => 'https://otlp.example.com:4318',
        ]);
    }

    public function testGetConfigReturnsLoadedConfig(): void
    {
        $config1 = Config::load([
            'endpoint' => 'https://otlp.example.com:4318',
        ]);

        $config2 = Config::get();

        $this->assertSame($config1, $config2);
    }

    public function testGetConfigWithoutLoadThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Config not loaded');

        Config::get();
    }

    public function testConfigToArray(): void
    {
        $config = Config::load([
            'api_key' => 'test-key',
            'endpoint' => 'https://otlp.example.com:4318',
            'protocol' => 'http/protobuf',
        ]);

        $array = $config->toArray();

        $this->assertArrayHasKey('api_key', $array);
        $this->assertArrayHasKey('endpoint', $array);
        $this->assertArrayHasKey('protocol', $array);
        $this->assertArrayHasKey('service', $array);
        $this->assertArrayHasKey('sampling', $array);
        $this->assertArrayHasKey('filters', $array);
    }
}