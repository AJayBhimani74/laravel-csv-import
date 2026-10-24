<?php

namespace AjayBhimani\CsvImport;

use AjayBhimani\CsvImport\Exceptions\InvalidCsv;
use Illuminate\Support\Facades\Validator;
use Throwable;

abstract class CsvImporter
{
    /** How many failed rows are kept in the result. The failed count is always exact. */
    protected int $maxErrors = 100;

    private bool $dryRun = false;

    /** Validation rules by column name. */
    abstract public function rules(): array;

    /** Save one row that passed validation. Throw to mark the row as failed. */
    abstract public function handle(array $row): void;

    /** Other names a column may have in the file: ['Product Code' => 'sku']. */
    public function aliases(): array
    {
        return [];
    }

    /** Clean a row before it is validated, for example trim values. */
    public function prepare(array $row): array
    {
        return $row;
    }

    public static function make(): static
    {
        return new static;
    }

    /** Validate everything but do not call handle(). */
    public function dryRun(bool $dryRun = true): static
    {
        $this->dryRun = $dryRun;

        return $this;
    }

    public function import(string $path): ImportResult
    {
        $file = @fopen($path, 'r');

        if ($file === false) {
            throw InvalidCsv::unreadable($path);
        }

        try {
            return $this->read($file, $path);
        } finally {
            fclose($file);
        }
    }

    private function read($file, string $path): ImportResult
    {
        $firstLine = fgets($file);

        if ($firstLine === false || trim($firstLine) === '') {
            throw InvalidCsv::empty($path);
        }

        $firstLine = $this->withoutBom($firstLine);
        $delimiter = $this->delimiter($firstLine);
        $header = $this->header(str_getcsv($firstLine, $delimiter));

        $missing = array_values(array_diff($this->requiredColumns(), $header));
        if ($missing) {
            throw InvalidCsv::missingColumns($missing);
        }

        $total = $imported = $failed = 0;
        $errors = [];
        $record = 1;

        while (($cells = fgetcsv($file, null, $delimiter)) !== false) {
            $record++;

            if ($cells === [null]) {
                continue;
            }

            $total++;
            $row = $this->prepare($this->combine($header, $cells));
            $messages = $this->check($row);

            if ($messages === null) {
                $messages = $this->save($row);
            }

            if ($messages === null) {
                $imported++;

                continue;
            }

            $failed++;
            if (count($errors) < $this->maxErrors) {
                $errors[] = ['row' => $record, 'messages' => $messages];
            }
        }

        return new ImportResult($total, $imported, $failed, $errors, $this->dryRun);
    }

    /** @return array<int, string>|null messages, or null when the row is valid */
    private function check(array $row): ?array
    {
        $validator = Validator::make($row, $this->rules());

        return $validator->fails() ? $validator->errors()->all() : null;
    }

    /** @return array<int, string>|null */
    private function save(array $row): ?array
    {
        if ($this->dryRun) {
            return null;
        }

        try {
            $this->handle($row);
        } catch (Throwable $e) {
            return ['Could not save the row: '.$e->getMessage()];
        }

        return null;
    }

    private function withoutBom(string $line): string
    {
        return str_starts_with($line, "\xEF\xBB\xBF") ? substr($line, 3) : $line;
    }

    private function delimiter(string $headerLine): string
    {
        $best = ',';
        $most = 0;

        foreach ([',', ';', "\t", '|'] as $candidate) {
            $count = substr_count($headerLine, $candidate);

            if ($count > $most) {
                [$best, $most] = [$candidate, $count];
            }
        }

        return $best;
    }

    /** @return array<int, string> */
    private function header(array $cells): array
    {
        $aliases = [];
        foreach ($this->aliases() as $from => $to) {
            $aliases[$this->normalise($from)] = $to;
        }

        return array_map(function ($cell) use ($aliases) {
            $name = $this->normalise((string) $cell);

            return $aliases[$name] ?? $name;
        }, $cells);
    }

    private function normalise(string $name): string
    {
        return strtolower(trim($name));
    }

    /** @return array<int, string> */
    private function requiredColumns(): array
    {
        $required = [];

        foreach ($this->rules() as $column => $rules) {
            $rules = is_string($rules) ? explode('|', $rules) : $rules;

            if (in_array('required', $rules, true)) {
                $required[] = $column;
            }
        }

        return $required;
    }

    private function combine(array $header, array $cells): array
    {
        $cells = array_pad(array_slice($cells, 0, count($header)), count($header), null);

        return array_combine($header, $cells);
    }
}
