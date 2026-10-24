<?php

namespace AjayBhimani\CsvImport\Tests\Fixtures;

use AjayBhimani\CsvImport\CsvImporter;
use RuntimeException;

class ProductImporter extends CsvImporter
{
    /** rows that were saved */
    public array $saved = [];

    public ?string $failOn = null;

    protected int $maxErrors = 3;

    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:10'],
            'name' => ['required', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function aliases(): array
    {
        return ['Product Code' => 'sku', 'Title' => 'name'];
    }

    public function prepare(array $row): array
    {
        return array_map(fn ($value) => is_string($value) ? trim($value) : $value, $row);
    }

    public function handle(array $row): void
    {
        if ($row['sku'] === $this->failOn) {
            throw new RuntimeException('database said no');
        }

        $this->saved[] = $row;
    }
}
