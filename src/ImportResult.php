<?php

namespace AjayBhimani\CsvImport;

class ImportResult
{
    /**
     * @param  array<int, array{row: int, messages: array<int, string>}>  $errors  the first rows that failed
     */
    public function __construct(
        public readonly int $total,
        public readonly int $imported,
        public readonly int $failed,
        public readonly array $errors,
        public readonly bool $dryRun,
    ) {
    }

    public function hasErrors(): bool
    {
        return $this->failed > 0;
    }

    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'imported' => $this->imported,
            'failed' => $this->failed,
            'errors' => $this->errors,
            'dry_run' => $this->dryRun,
        ];
    }
}
