<?php

namespace AjayBhimani\CsvImport\Console;

use Illuminate\Console\GeneratorCommand;

class MakeImporterCommand extends GeneratorCommand
{
    protected $name = 'make:csv-importer';

    protected $description = 'Create a new CSV importer class';

    protected $type = 'Importer';

    protected function getStub(): string
    {
        return __DIR__.'/../../stubs/importer.stub';
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\\Imports';
    }
}
