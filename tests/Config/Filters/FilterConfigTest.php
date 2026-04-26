<?php

declare(strict_types=1);

namespace Vesvasi\Tests\Config\Filters;

use PHPUnit\Framework\TestCase;
use Vesvasi\Config\Filters\FilterConfig;

final class FilterConfigTest extends TestCase
{
    public function testDefaultValues(): void
    {
        $config = new FilterConfig([]);

        $this->assertSame(0.0, $config->getCpuThreshold());
        $this->assertSame(0.0, $config->getMemoryThreshold());
        $this->assertSame(0.0, $config->getDurationThreshold());
    }

    public function testShouldIncludeFileWithWildcard(): void
    {
        $config = new FilterConfig([
            'include_files' => ['*'],
            'exclude_files' => [],
        ]);

        $this->assertTrue($config->shouldIncludeFile('/any/path/file.php'));
        $this->assertTrue($config->shouldIncludeFile('/app/src/Controller.php'));
    }

    public function testShouldIncludeFileWithPattern(): void
    {
        $config = new FilterConfig([
            'include_files' => ['/app/src/*'],
            'exclude_files' => [],
        ]);

        $this->assertTrue($config->shouldIncludeFile('/app/src/Controller.php'));
        $this->assertFalse($config->shouldIncludeFile('/app/vendor/SomeClass.php'));
    }

    public function testShouldIncludeFileWithExclusion(): void
    {
        $config = new FilterConfig([
            'include_files' => ['*'],
            'exclude_files' => ['/vendor/*'],
        ]);

        $this->assertTrue($config->shouldIncludeFile('/app/src/Controller.php'));
        $this->assertFalse($config->shouldIncludeFile('/vendor/autoload.php'));
    }

    public function testShouldIncludeClass(): void
    {
        $config = new FilterConfig([
            'include_classes' => ['App\\*'],
            'exclude_classes' => ['App\\Controller\\Base*'],
        ]);

        $this->assertTrue($config->shouldIncludeClass('App\\Controller\\HomeController'));
        $this->assertTrue($config->shouldIncludeClass('App\\Service\\UserService'));
        $this->assertFalse($config->shouldIncludeClass('App\\Controller\\BaseController'));
        $this->assertFalse($config->shouldIncludeClass('Vendor\\SomeClass'));
    }

    public function testShouldIncludeUrl(): void
    {
        $config = new FilterConfig([
            'include_urls' => ['/api/*'],
            'exclude_urls' => ['/api/health'],
        ]);

        $this->assertTrue($config->shouldIncludeUrl('/api/users'));
        $this->assertFalse($config->shouldIncludeUrl('/api/health'));
        $this->assertFalse($config->shouldIncludeUrl('/webhook'));
    }

    public function testShouldIncludeCommand(): void
    {
        $config = new FilterConfig([
            'include_commands' => ['artisan *'],
            'exclude_commands' => ['artisan schedule:run'],
        ]);

        $this->assertTrue($config->shouldIncludeCommand('artisan migrate'));
        $this->assertFalse($config->shouldIncludeCommand('artisan schedule:run'));
    }

    public function testMeetsResourceThresholds(): void
    {
        $config = new FilterConfig([
            'cpu_threshold' => 10,
            'memory_threshold' => 100,
        ]);

        $this->assertTrue($config->meetsResourceThresholds(15, 150));
        $this->assertFalse($config->meetsResourceThresholds(5, 150));
        $this->assertFalse($config->meetsResourceThresholds(15, 50));
        $this->assertFalse($config->meetsResourceThresholds(5, 50));
    }

    public function testMeetsResourceThresholdsWithZeroThreshold(): void
    {
        $config = new FilterConfig([]);

        $this->assertTrue($config->meetsResourceThresholds(0, 0));
        $this->assertTrue($config->meetsResourceThresholds(100, 1000));
    }

    public function testToArray(): void
    {
        $config = new FilterConfig([
            'cpu_threshold' => 5,
            'memory_threshold' => 50,
            'include_files' => ['/app/*'],
            'exclude_files' => ['/vendor/*'],
        ]);

        $array = $config->toArray();

        $this->assertSame(5.0, $array['cpu_threshold']);
        $this->assertSame(50.0, $array['memory_threshold']);
        $this->assertSame(['/app/*'], $array['include_files']);
        $this->assertSame(['/vendor/*'], $array['exclude_files']);
    }
}