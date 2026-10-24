<?php

namespace AjayBhimani\CsvImport\Tests;

use AjayBhimani\CsvImport\CsvImportServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    private array $files = [];

    protected function getPackageProviders($app): array
    {
        return [CsvImportServiceProvider::class];
    }

    protected function csv(string $content): string
    {
        $path = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($path, $content);
        $this->files[] = $path;

        return $path;
    }

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }
}
