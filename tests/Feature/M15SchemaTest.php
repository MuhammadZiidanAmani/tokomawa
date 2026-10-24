<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class M15SchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_m15_schema_is_innodb_with_decimal_money_and_unique_posting_sources(): void
    {
        $database = DB::connection()->getDatabaseName();
        $this->assertSame('retail_testing', $database);
        $tables = ['cash_shifts', 'sales', 'sale_items', 'sale_payments', 'cash_movements'];
        $engines = DB::table('information_schema.tables')
            ->where('table_schema', $database)->whereIn('table_name', $tables)->pluck('engine', 'table_name');
        $this->assertCount(5, $engines);
        foreach ($tables as $table) {
            $this->assertSame('InnoDB', $engines[$table]);
        }

        foreach (['cash_shifts_active_register_key_unique', 'cash_shifts_active_cashier_key_unique', 'sales_posting_key_unique', 'sale_items_inventory_movement_unique', 'sale_payments_source_key_unique', 'cash_movements_source_key_unique'] as $index) {
            $this->assertSame(1, DB::table('information_schema.statistics')
                ->where('table_schema', $database)->where('index_name', $index)->where('non_unique', 0)
                ->distinct()->count('index_name'), $index);
        }
        $amount = DB::table('information_schema.columns')
            ->where('table_schema', $database)->where('table_name', 'sales')->where('column_name', 'grand_total')->first();
        $quantity = DB::table('information_schema.columns')
            ->where('table_schema', $database)->where('table_name', 'sale_items')->where('column_name', 'quantity')->first();
        $cost = DB::table('information_schema.columns')
            ->where('table_schema', $database)->where('table_name', 'sale_items')->where('column_name', 'cogs_unit_snapshot')->first();
        $this->assertSame(24, (int) $amount->NUMERIC_PRECISION);
        $this->assertSame(2, (int) $amount->NUMERIC_SCALE);
        $this->assertSame(18, (int) $quantity->NUMERIC_PRECISION);
        $this->assertSame(3, (int) $quantity->NUMERIC_SCALE);
        $this->assertSame(6, (int) $cost->NUMERIC_SCALE);
    }
}
