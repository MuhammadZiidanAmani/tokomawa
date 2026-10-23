<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class M14SchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_m14_schema_is_innodb_with_fixed_decimals_and_unique_sources(): void
    {
        $database = DB::connection()->getDatabaseName();
        $this->assertSame('retail_testing', $database);
        $tables = ['purchase_invoices', 'purchase_invoice_items', 'goods_receipt_invoice_allocations', 'purchase_invoice_cash_payments', 'ap_entries'];
        $engines = DB::table('information_schema.tables')->where('table_schema', $database)->whereIn('table_name', $tables)->pluck('engine', 'table_name');
        $this->assertCount(5, $engines);
        foreach ($tables as $table) {
            $this->assertSame('InnoDB', $engines[$table]);
        }
        $this->assertSame(1, DB::table('information_schema.statistics')->where('table_schema', $database)->where('index_name', 'purchase_invoices_supplier_number_unique')->where('non_unique', 0)->distinct()->count('index_name'));
        $this->assertSame(1, DB::table('information_schema.statistics')->where('table_schema', $database)->where('index_name', 'ap_entries_source_key_unique')->where('non_unique', 0)->distinct()->count('index_name'));
        $amount = DB::table('information_schema.columns')->where('table_schema', $database)->where('table_name', 'ap_entries')->where('column_name', 'amount_delta')->first();
        $this->assertSame(24, (int) $amount->NUMERIC_PRECISION);
        $this->assertSame(2, (int) $amount->NUMERIC_SCALE);
    }
}
