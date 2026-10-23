<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class M11SchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @var list<string> */
    private const TABLES = [
        'inventory_batches', 'inventory_movements', 'inventory_balances',
        'stock_adjustments', 'stock_adjustment_items',
    ];

    public function test_m11_tables_use_innodb_on_isolated_testing_database(): void
    {
        $database = DB::connection()->getDatabaseName();
        $this->assertSame('retail_testing', $database);

        $tables = DB::table('information_schema.tables')
            ->where('table_schema', $database)
            ->whereIn('table_name', self::TABLES)
            ->pluck('engine', 'table_name');

        $this->assertCount(count(self::TABLES), $tables);
        foreach (self::TABLES as $table) {
            $this->assertSame('InnoDB', $tables->get($table), "Tabel {$table} wajib menggunakan InnoDB.");
        }
    }

    public function test_ledger_source_and_balance_scope_have_unique_constraints(): void
    {
        $database = DB::connection()->getDatabaseName();
        $expected = [
            'inventory_batches_branch_id_product_id_lot_number_unique',
            'stock_adjustments_document_number_unique',
            'stock_adjustments_submission_key_unique',
            'stock_adjustment_items_stock_adjustment_id_line_number_unique',
            'inventory_balances_scope_unique',
            'inventory_movements_source_key_unique',
        ];

        $indexes = DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('non_unique', 0)
            ->whereIn('index_name', $expected)
            ->distinct()
            ->pluck('index_name')
            ->all();

        $this->assertEqualsCanonicalizing($expected, $indexes);
    }

    public function test_inventory_quantities_and_costs_use_fixed_decimal_precision(): void
    {
        $database = DB::connection()->getDatabaseName();
        $expected = [
            'stock_adjustment_items.quantity' => [18, 3],
            'stock_adjustment_items.base_quantity' => [18, 3],
            'stock_adjustment_items.unit_cost' => [18, 6],
            'inventory_balances.qty_on_hand' => [18, 3],
            'inventory_balances.avg_unit_cost' => [18, 6],
            'inventory_balances.inventory_value' => [24, 6],
            'inventory_movements.qty_delta' => [18, 3],
            'inventory_movements.unit_cost_snapshot' => [18, 6],
            'inventory_movements.value_delta' => [24, 6],
        ];

        $columns = DB::table('information_schema.columns')
            ->where('table_schema', $database)
            ->whereIn('table_name', self::TABLES)
            ->where('data_type', 'decimal')
            ->get()
            ->keyBy(fn (object $column): string => $column->TABLE_NAME.'.'.$column->COLUMN_NAME);

        foreach ($expected as $column => [$precision, $scale]) {
            $definition = $columns->get($column);
            $this->assertNotNull($definition, "Kolom {$column} tidak ditemukan.");
            $this->assertSame($precision, (int) $definition->NUMERIC_PRECISION);
            $this->assertSame($scale, (int) $definition->NUMERIC_SCALE);
        }

        foreach ([
            'stock_adjustment_items.quantity',
            'stock_adjustment_items.base_quantity',
            'stock_adjustment_items.unit_cost',
            'inventory_balances.qty_on_hand',
            'inventory_balances.qty_reserved',
            'inventory_balances.avg_unit_cost',
            'inventory_balances.inventory_value',
            'inventory_movements.qty_after',
            'inventory_movements.unit_cost_snapshot',
        ] as $unsignedColumn) {
            $this->assertStringContainsString('unsigned', strtolower($columns->get($unsignedColumn)->COLUMN_TYPE));
        }
    }

    public function test_ledger_foreign_keys_restrict_destructive_deletes(): void
    {
        $database = DB::connection()->getDatabaseName();
        $expected = [
            'inventory_movements_warehouse_id_foreign',
            'inventory_movements_product_id_foreign',
            'inventory_movements_inventory_batch_id_foreign',
            'stock_adjustment_items_stock_adjustment_id_foreign',
            'inventory_balances_product_id_foreign',
        ];
        $constraints = DB::table('information_schema.referential_constraints')
            ->where('constraint_schema', $database)
            ->whereIn('constraint_name', $expected)
            ->pluck('delete_rule', 'constraint_name');

        $this->assertCount(count($expected), $constraints);
        foreach ($expected as $name) {
            $this->assertSame('RESTRICT', $constraints->get($name));
        }
    }
}
