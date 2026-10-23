<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class M13SchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @var list<string> */
    private const TABLES = ['goods_receipts', 'goods_receipt_items'];

    public function test_m13_tables_use_innodb_on_isolated_testing_database(): void
    {
        $database = DB::connection()->getDatabaseName();
        $this->assertSame('retail_testing', $database);
        $tables = DB::table('information_schema.tables')->where('table_schema', $database)
            ->whereIn('table_name', self::TABLES)->pluck('engine', 'table_name');

        $this->assertCount(2, $tables);
        $this->assertSame('InnoDB', $tables->get('goods_receipts'));
        $this->assertSame('InnoDB', $tables->get('goods_receipt_items'));
    }

    public function test_unique_constraints_indexes_and_decimal_precision_exist(): void
    {
        $database = DB::connection()->getDatabaseName();
        $unique = [
            'goods_receipts_public_id_unique', 'goods_receipts_creation_key_unique',
            'goods_receipts_posting_key_unique', 'goods_receipts_branch_document_unique',
            'goods_receipts_supplier_delivery_note_unique', 'goods_receipt_items_public_id_unique',
            'goods_receipt_items_line_unique', 'goods_receipt_items_po_line_unique',
        ];
        $actual = DB::table('information_schema.statistics')->where('table_schema', $database)
            ->where('non_unique', 0)->whereIn('index_name', $unique)->distinct()->pluck('index_name')->all();
        $this->assertEqualsCanonicalizing($unique, $actual);

        $expected = [
            'goods_receipts.total_base_quantity' => [24, 3], 'goods_receipts.total_value' => [24, 2],
            'goods_receipt_items.conversion_factor' => [18, 6], 'goods_receipt_items.quantity' => [18, 3],
            'goods_receipt_items.base_quantity' => [18, 3], 'goods_receipt_items.unit_price_snapshot' => [18, 2],
            'goods_receipt_items.base_unit_cost_snapshot' => [18, 6], 'goods_receipt_items.line_total' => [24, 2],
        ];
        $columns = DB::table('information_schema.columns')->where('table_schema', $database)
            ->whereIn('table_name', self::TABLES)->where('data_type', 'decimal')->get()
            ->keyBy(fn (object $column): string => $column->TABLE_NAME.'.'.$column->COLUMN_NAME);
        foreach ($expected as $column => [$precision, $scale]) {
            $definition = $columns->get($column);
            $this->assertNotNull($definition, "Kolom {$column} tidak ditemukan.");
            $this->assertSame($precision, (int) $definition->NUMERIC_PRECISION);
            $this->assertSame($scale, (int) $definition->NUMERIC_SCALE);
            $this->assertStringContainsString('unsigned', strtolower($definition->COLUMN_TYPE));
        }
    }

    public function test_transaction_references_use_restrict_delete_rules(): void
    {
        $database = DB::connection()->getDatabaseName();
        $expected = [
            'goods_receipts_branch_id_foreign', 'goods_receipts_warehouse_id_foreign',
            'goods_receipts_purchase_order_id_foreign', 'goods_receipts_supplier_id_foreign',
            'goods_receipt_items_goods_receipt_id_foreign', 'goods_receipt_items_purchase_order_item_id_foreign',
            'goods_receipt_items_product_id_foreign', 'goods_receipt_items_unit_id_foreign',
            'goods_receipt_items_inventory_batch_id_foreign',
        ];
        $constraints = DB::table('information_schema.referential_constraints')->where('constraint_schema', $database)
            ->whereIn('constraint_name', $expected)->pluck('delete_rule', 'constraint_name');

        $this->assertCount(count($expected), $constraints);
        foreach ($expected as $constraint) {
            $this->assertSame('RESTRICT', $constraints->get($constraint));
        }

    }
}
