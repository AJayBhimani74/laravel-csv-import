<?php

namespace AjayBhimani\CsvImport\Tests;

class MakeCommandTest extends TestCase
{
    public function test_make_command_creates_an_importer_class(): void
    {
        $path = app_path('Imports/OrderImporter.php');
        @unlink($path);

        $this->artisan('make:csv-importer', ['name' => 'OrderImporter'])->assertSuccessful();

        $this->assertFileExists($path);
        $code = file_get_contents($path);
        $this->assertStringContainsString('namespace App\Imports;', $code);
        $this->assertStringContainsString('class OrderImporter extends CsvImporter', $code);

        @unlink($path);
    }
}
