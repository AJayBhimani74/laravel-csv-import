# How laravel-csv-import works

Read this before an interview. It explains the package in plain words.

## The idea

Importing a CSV sounds trivial and is full of small traps: Excel files with hidden characters, semicolons instead of commas, blank lines, one bad row in 5,000, memory use. The package turns that into a tiny contract: **describe the rules, say what to do with a good row**, and it handles the rest.

## The pieces

| File | Job |
|---|---|
| `src/CsvImporter.php` | The abstract class you extend. All the reading logic is here. |
| `src/ImportResult.php` | A read-only result: counts and the first errors. |
| `src/Exceptions/InvalidCsv.php` | For problems with the whole file (unreadable, empty, missing columns). |
| `src/Console/MakeImporterCommand.php` + `stubs/importer.stub` | `php artisan make:csv-importer`. |
| `src/CsvImportServiceProvider.php` | Registers the command. Laravel finds it through `composer.json` (`extra.laravel.providers`), so you don't register anything. |

## How an import runs

1. Open the file. If that fails, throw `InvalidCsv`.
2. Read the first line. Remove a BOM if there is one (Excel adds one when saving "CSV UTF-8"; without this the first column would be called `\xEF\xBB\xBFsku` and never match).
3. Pick the delimiter by counting `, ; tab |` in the header line and choosing the most frequent.
4. Normalise header names (trim, lower-case), then apply `aliases()`.
5. Work out the required columns from the rules (the ones with `required`). If any are missing from the header, throw before reading a single row. This is a problem with the whole file, not a row.
6. Read one record at a time with `fgetcsv`. For each record:
   - skip it if it is blank,
   - turn it into `column => value` (padding or cutting to the header length),
   - call `prepare()`, then run Laravel's `Validator` with your rules,
   - if valid, call your `handle()`,
   - count it as imported or failed, and keep the first 100 errors.
7. Return an `ImportResult`.

## Design choices

- **One row at a time.** `fgetcsv` never loads the whole file. A 1 GB file uses the same memory as a 1 KB one.
- **Laravel's own validator.** People already know the rules, the messages, and how to write custom ones. Nothing new to learn.
- **Failing rows don't stop the import**, whether they fail validation or `handle()` throws. A user with 5,000 rows wants a list of the 12 bad ones, not a stack trace on row 1.
- **Header problems are different from row problems**, so they throw instead of being reported 5,000 times.
- **`handle()` is yours.** The package doesn't know about databases. Use `create`, `updateOrCreate`, a service class, or an API call.
- **`$maxErrors` caps the stored errors but not the count.** A broken file could otherwise produce a huge error array, but `failed` still says exactly how many rows failed.
- **Dry run** reuses the same code path and only skips `handle()`, so the preview is guaranteed to match the real import.
- **Row numbers include the header as row 1**, which matches what the user sees in a spreadsheet when the file has no blank lines or multi-line cells. A cell with a line break inside quotes counts as one record, so the number can differ from the line number in a text editor. This is documented in the README.

## Testing a package

The tests use **Orchestra Testbench**, which boots a small Laravel app inside the test run so the validator, facades and the artisan command work without a real project. CI runs the suite against Laravel 11 and 12 on PHP 8.2 and 8.3. I also installed the package into a fresh Laravel app from the local folder and imported a semicolon-separated file with capitalised headers, to check auto-discovery and the generator command for real.

## Questions you may get

**Why a package and not a trait?** An abstract class gives a fixed shape (`rules`, `handle`) and an obvious place for the shared logic. A trait would mix the reading logic into every importer.

**Why isn't it queued by default?** Whether to queue is a decision for the app: a 50-row import is faster inline. Making the importer plain PHP lets you call it from a controller, a command or a job.

**How would you do very large files?** Run it in a queued job, and commit in batches inside `handle()` or collect rows and insert in chunks. The importer already streams the file.

**What are the limits?** Rows are saved one at a time (simple, slower than bulk insert), the delimiter guess can be wrong for odd files, and character encodings other than UTF-8 are not converted.

**What would you add next?** Chunked handling (`handleChunk(array $rows)` for bulk inserts), an encoding option, and an option to stop after N errors.

**How did you publish it?** It's on GitHub. Composer can install from a VCS repository. Packagist would be one submit button away; I haven't done it.

## Try it yourself

Create an importer with the artisan command in any Laravel app, point it at a CSV with a few bad rows, and print `$result->toArray()`. Then try `->dryRun()`, change the delimiter to `;`, and remove a required column to see the exception. Read `tests/ImporterTest.php`; each test name is one behaviour.
