<?php

namespace AjayBhimani\CsvImport\Tests;

use AjayBhimani\CsvImport\Exceptions\InvalidCsv;
use AjayBhimani\CsvImport\Tests\Fixtures\ProductImporter;

class ImporterTest extends TestCase
{
    public function test_valid_rows_are_saved_and_counted(): void
    {
        $importer = new ProductImporter;

        $result = $importer->import($this->csv("sku,name,price,stock\nA1,Bolt,1.5,10\nA2,Nut,0.5,\n"));

        $this->assertSame(2, $result->total);
        $this->assertSame(2, $result->imported);
        $this->assertSame(0, $result->failed);
        $this->assertFalse($result->hasErrors());
        $this->assertSame('Bolt', $importer->saved[0]['name']);
    }

    public function test_bad_rows_are_reported_with_their_row_number_and_good_rows_still_saved(): void
    {
        $importer = new ProductImporter;

        $result = $importer->import($this->csv("sku,name,price\nA1,Bolt,1\nA2,,5\nA3,Nut,abc\nA4,Washer,2\n"));

        $this->assertSame(['A1', 'A4'], array_map(fn ($r) => $r['sku'], $importer->saved));
        $this->assertSame(4, $result->total);
        $this->assertSame(2, $result->imported);
        $this->assertSame(2, $result->failed);
        $this->assertSame([3, 4], array_column($result->errors, 'row'));
        $this->assertStringContainsString('name', $result->errors[0]['messages'][0]);
        $this->assertStringContainsString('price', $result->errors[1]['messages'][0]);
    }

    public function test_blank_lines_are_skipped_and_not_counted(): void
    {
        $result = (new ProductImporter)->import($this->csv("sku,name,price\n\nA1,Bolt,1\n\n\nA2,Nut,2\n"));

        $this->assertSame(2, $result->total);
        $this->assertSame(2, $result->imported);
    }

    public function test_a_utf8_byte_order_mark_does_not_break_the_first_column(): void
    {
        $result = (new ProductImporter)->import($this->csv("\xEF\xBB\xBFsku,name,price\nA1,Bolt,1\n"));

        $this->assertSame(1, $result->imported);
    }

    public function test_semicolon_and_tab_delimiters_are_detected(): void
    {
        $semicolon = (new ProductImporter)->import($this->csv("sku;name;price\nA1;Bolt;1\n"));
        $tab = (new ProductImporter)->import($this->csv("sku\tname\tprice\nA1\tBolt\t1\n"));

        $this->assertSame(1, $semicolon->imported);
        $this->assertSame(1, $tab->imported);
    }

    public function test_headers_are_case_and_space_insensitive_and_aliases_work(): void
    {
        $importer = new ProductImporter;

        $result = $importer->import($this->csv("  Product Code , TITLE ,Price\nA1,Bolt,1\n"));

        $this->assertSame(1, $result->imported);
        $this->assertSame(['sku', 'name', 'price'], array_keys($importer->saved[0]));
    }

    public function test_missing_required_columns_stop_the_import_before_any_row(): void
    {
        $importer = new ProductImporter;

        try {
            $importer->import($this->csv("sku,title\nA1,Bolt\n"));
            $this->fail('expected an exception');
        } catch (InvalidCsv $e) {
            $this->assertSame('The file is missing required column(s): price.', $e->getMessage());
        }

        $this->assertSame([], $importer->saved);
    }

    public function test_unreadable_and_empty_files_throw(): void
    {
        $this->expectException(InvalidCsv::class);
        (new ProductImporter)->import('/definitely/not/here.csv');
    }

    public function test_an_empty_file_throws(): void
    {
        $this->expectExceptionMessage('has no header row');
        (new ProductImporter)->import($this->csv(''));
    }

    public function test_values_are_cleaned_by_prepare_before_validation(): void
    {
        $importer = new ProductImporter;

        $result = $importer->import($this->csv("sku,name,price\n  A1  ,  Bolt  ,  2.5  \n"));

        $this->assertSame(1, $result->imported);
        $this->assertSame('A1', $importer->saved[0]['sku']);
    }

    public function test_dry_run_validates_but_saves_nothing(): void
    {
        $importer = new ProductImporter;

        $result = $importer->dryRun()->import($this->csv("sku,name,price\nA1,Bolt,1\nA2,,1\n"));

        $this->assertSame([], $importer->saved);
        $this->assertTrue($result->dryRun);
        $this->assertSame(1, $result->imported);
        $this->assertSame(1, $result->failed);
    }

    public function test_a_row_that_throws_while_saving_is_failed_and_the_import_goes_on(): void
    {
        $importer = new ProductImporter;
        $importer->failOn = 'A2';

        $result = $importer->import($this->csv("sku,name,price\nA1,Bolt,1\nA2,Nut,1\nA3,Washer,1\n"));

        $this->assertSame(2, $result->imported);
        $this->assertSame(1, $result->failed);
        $this->assertSame('Could not save the row: database said no', $result->errors[0]['messages'][0]);
        $this->assertSame(3, $result->errors[0]['row']);
    }

    public function test_only_the_first_errors_are_kept_but_the_failed_count_is_exact(): void
    {
        $rows = str_repeat("X,,1\n", 10);

        $result = (new ProductImporter)->import($this->csv("sku,name,price\n".$rows));

        $this->assertSame(10, $result->failed);
        $this->assertCount(3, $result->errors);
    }

    public function test_quoted_fields_with_commas_and_new_lines_are_one_value(): void
    {
        $importer = new ProductImporter;

        $result = $importer->import($this->csv("sku,name,price\nA1,\"Bolt, zinc\nplated\",1\nA2,Nut,2\n"));

        $this->assertSame(2, $result->imported);
        $this->assertSame("Bolt, zinc\nplated", $importer->saved[0]['name']);
    }

    public function test_extra_columns_are_kept_and_short_rows_are_padded(): void
    {
        $importer = new ProductImporter;

        $result = $importer->import($this->csv("sku,name,price,colour\nA1,Bolt,1,red,extra\nA2,Nut\n"));

        $this->assertSame(1, $result->imported);
        $this->assertSame(1, $result->failed);
        $this->assertSame('red', $importer->saved[0]['colour']);
        $this->assertSame(3, $result->errors[0]['row']);
    }

    public function test_result_can_be_turned_into_an_array(): void
    {
        $result = (new ProductImporter)->import($this->csv("sku,name,price\nA1,Bolt,1\n"));

        $this->assertSame(
            ['total' => 1, 'imported' => 1, 'failed' => 0, 'errors' => [], 'dry_run' => false],
            $result->toArray()
        );
    }
}
