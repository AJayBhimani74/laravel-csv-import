<?php

namespace AjayBhimani\CsvImport\Exceptions;

use RuntimeException;

class InvalidCsv extends RuntimeException
{
    public static function unreadable(string $path): self
    {
        return new self("The file [{$path}] could not be read.");
    }

    public static function empty(string $path): self
    {
        return new self("The file [{$path}] has no header row.");
    }

    /** @param  array<int, string>  $missing */
    public static function missingColumns(array $missing): self
    {
        return new self('The file is missing required column(s): '.implode(', ', $missing).'.');
    }
}
