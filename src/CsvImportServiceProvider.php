<?php

namespace AjayBhimani\CsvImport;

use AjayBhimani\CsvImport\Console\MakeImporterCommand;
use Illuminate\Support\ServiceProvider;

class CsvImportServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([MakeImporterCommand::class]);
        }
    }
}
