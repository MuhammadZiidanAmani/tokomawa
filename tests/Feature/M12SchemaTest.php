<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class M12SchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @var list<string> */
    private const TABLES = ['purchase_orders', 'purchase_order_items'];

    public function test_m12_tables_use_innodb_on_isolated_testing_database(): void
    {
        $database = DB::connection()->getDatabaseName();
        $this->assertSame('retail_testing', $database);
        $tables = DB::table('information_schema.tables')
            ->where('table_schema', $database)
            ->whereIn('table_name', self::TABLES)
            ->pluck('engine', 'table_name');

        $this->assertCount(2, $tables);
        $this->assertSame('InnoDB', $tables->get('purchase_orders'));
        $this->assertSame('InnoDB', $tables->get('purchase_order_items'));
    }

    public function test_purchase_order_unique_constraints_and_query_indexes_exist(): void
    {
        $database = DB::connection()->getDatabaseName();
        $unique = [
            'purchase_orders_public_id_unique',
            'purchase_orders_approval_request_id_unique',
            'purchase_orders_creation_key_unique',
            'purchase_orders_branch_document_unique',
            'purchase_order_items_public_id_unique',
            'purchase_order_items_line_unique',
            'purchase_order_items_product_unique',
        ];
        $indexes = DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('non_unique', 0)
            ->whereIn('index_name', $unique)
            ->distinct()
            ->pluck('index_name')
            ->all();

        $this->assertEqualsCanonicalizing($unique, $indexes);
        $indexNames = DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('table_name', 'purchase_orders')
            ->whereIn('index_name', ['purchase_orders_branch_status_date_index', 'purchase_orders_supplier_status_date_index'])
            ->distinct()
            ->pluck('index_name')
            ->all();
        $this->assertEqualsCanonicalizing(['purchase_orders_branch_status_date_index', 'purchase_orders_supplier_status_date_index'], $indexNames);
    }

    public function test_purchase_order_money_and_quantities_use_fixed_decimal_precision(): void
    {
        $database = DB::connection()->getDatabaseName();
        $expected = [
            'purchase_orders.subtotal' => [24, 2],
            'purchase_orders.grand_total' => [24, 2],
            'purchase_order_items.conversion_factor' => [18, 6],
            'purchase_order_items.quantity' => [18, 3],
            'purchase_order_items.base_quantity' => [18, 3],
            'purchase_order_items.received_base_quantity' => [18, 3],
            'purchase_order_items.unit_price' => [18, 2],
            'purchase_order_items.subtotal' => [24, 2],
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
            $this->assertStringContainsString('unsigned', strtolower($definition->COLUMN_TYPE));
        }
    }

    public function test_purchase_order_foreign_keys_restrict_destructive_deletes(): void
    {
        $database = DB::connection()->getDatabaseName();
        $expected = [
            'purchase_orders_branch_id_foreign',
            'purchase_orders_supplier_id_foreign',
            'purchase_orders_approval_request_id_foreign',
            'purchase_order_items_purchase_order_id_foreign',
            'purchase_order_items_product_id_foreign',
            'purchase_order_items_unit_id_foreign',
            'purchase_order_items_supplier_product_id_foreign',
        ];
        $constraints = DB::table('information_schema.referential_constraints')
            ->where('constraint_schema', $database)
            ->whereIn('constraint_name', $expected)
            ->pluck('delete_rule', 'constraint_name');

        $this->assertCount(count($expected), $constraints);
        foreach ($expected as $constraint) {
            $this->assertSame('RESTRICT', $constraints->get($constraint));
        }
    }
}
