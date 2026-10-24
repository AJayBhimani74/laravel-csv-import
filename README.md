# laravel-csv-import

A small package for importing CSV files in Laravel. You describe the columns with normal validation rules and say what to do with a valid row. The package reads the file one line at a time, validates each row, saves the good ones, and gives you a report of the bad ones with their row numbers. One bad row never stops the rest.

It is the idea I used inside my [tradequote](https://github.com/AJayBhimani74/tradequote) demo, pulled out into something reusable.

## Install

It is not on Packagist. Install it from GitHub:

```bash
composer config repositories.csv-import vcs https://github.com/AJayBhimani74/laravel-csv-import
composer require ajaybhimani74/laravel-csv-import:dev-main
```

Needs PHP 8.2+ and Laravel 12. Laravel 11 is not supported: all its releases are flagged by Composer's security advisories now. The service provider is found automatically.

## Use

```bash
php artisan make:csv-importer ProductImporter    # creates app/Imports/ProductImporter.php
```

```php
namespace App\Imports;

use AjayBhimani\CsvImport\CsvImporter;
use App\Models\Product;

class ProductImporter extends CsvImporter
{
    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:64'],
            'name' => ['required', 'string'],
            'price' => ['required', 'numeric', 'min:0'],
            'stock' => ['nullable', 'integer', 'min:0'],
        ];
    }

    public function handle(array $row): void
    {
        Product::updateOrCreate(['sku' => $row['sku']], $row);
    }
}
```

```php
$result = ProductImporter::make()->import($path);

$result->total;      // rows read (blank lines are not counted)
$result->imported;   // rows saved
$result->failed;     // rows that failed
$result->errors;     // [['row' => 3, 'messages' => ['The name field is required.']], ...]
$result->toArray();  // handy for a JSON response or a database column
```

Row numbers count the header as row 1, so row 3 is the second data record.

## What it takes care of

| | |
|---|---|
| Header names | Trimmed and lower-cased, so ` Price ` matches `price`. |
| Other column names | `aliases()` maps names used in the file to yours: `['Product Code' => 'sku']`. |
| Missing required columns | If a column whose rule contains `required` is not in the file, an `InvalidCsv` exception is thrown before any row is read. |
| Delimiter | Comma, semicolon, tab or pipe, detected from the header line. |
| Excel files | A UTF-8 byte order mark at the start is removed. |
| Quoted values | Commas and line breaks inside quotes stay in the value. |
| Blank lines | Skipped. |
| Short or long rows | Short rows are padded with `null` (so `required` fails). Extra values with no header name are dropped. |
| Cleaning | Override `prepare(array $row)` to trim or convert values before validation. |
| Errors while saving | If `handle()` throws, that row is reported as failed and the import goes on. |
| Big error lists | Only the first 100 failed rows are kept in `errors` (set `$maxErrors`). The `failed` count is always exact. |
| Trying it first | `->dryRun()` validates every row and saves nothing. |

## Queues

The importer is plain PHP, so run it wherever you like. For large files, call it from a queued job and store `$result->toArray()` where the user can read it. See the tradequote project for an example.

## Tests

```bash
composer install
vendor/bin/phpunit
```

17 tests. CI runs them on PHP 8.2, 8.3 and 8.4 with Laravel 12.

For the design reasons, read [docs/HOW-IT-WORKS.md](docs/HOW-IT-WORKS.md).
