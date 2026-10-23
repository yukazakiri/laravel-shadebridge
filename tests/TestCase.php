<?php

namespace Tests;

use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase as BaseTestCase;
use Yukazakiri\Shadebridge\ShadebridgeServiceProvider;

abstract class TestCase extends BaseTestCase
{
    protected string $appBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->appBasePath = sys_get_temp_dir().DIRECTORY_SEPARATOR.'theme-tools-test-'.bin2hex(random_bytes(6));

        File::ensureDirectoryExists($this->appBasePath);
        $this->app->setBasePath($this->appBasePath);
    }

    protected function tearDown(): void
    {
        if (isset($this->appBasePath) && File::exists($this->appBasePath)) {
            File::deleteDirectory($this->appBasePath);
        }

        parent::tearDown();
    }

    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            ShadebridgeServiceProvider::class,
        ];
    }

    protected function writeResourceFile(string $path, string $contents): void
    {
        $fullPath = resource_path($path);

        File::ensureDirectoryExists(dirname($fullPath));
        File::put($fullPath, $contents);
    }

    protected function packagePath(string $path): string
    {
        return dirname(__DIR__).DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $path);
    }

    protected function assertResourceFileContains(string $path, string $expected): void
    {
        $this->assertStringContainsString($expected, File::get(resource_path($path)));
    }
}
