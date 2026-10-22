<?php

namespace Tests\Feature\Domain\Catalog;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class M03SchemaTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** @var list<string> */
    private const TABLES = [
        'departments', 'categories', 'subcategories', 'brands', 'units', 'tax_rules', 'price_lists',
        'suppliers', 'products', 'product_barcodes', 'product_units', 'supplier_products',
        'shelf_locations', 'product_locations', 'product_prices', 'price_change_logs',
    ];

    public function test_m03_suite_is_running_exclusively_on_retail_testing_with_innodb_tables(): void
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

    public function test_business_identifiers_and_effective_periods_have_database_unique_constraints(): void
    {
        $database = DB::connection()->getDatabaseName();
        $expectedIndexes = [
            'departments_code_unique', 'categories_department_id_code_unique',
            'subcategories_category_id_code_unique', 'brands_code_unique', 'units_code_unique',
            'tax_rules_code_effective_from_unique', 'price_lists_code_unique', 'suppliers_code_unique',
            'products_sku_unique', 'product_barcodes_barcode_unique', 'product_units_product_id_unit_id_unique',
            'supplier_products_supplier_id_product_id_unique', 'supplier_products_supplier_id_supplier_sku_unique',
            'shelf_locations_warehouse_id_code_unique', 'product_locations_product_id_shelf_location_id_unique',
            'product_prices_effective_unique',
        ];

        $indexes = DB::table('information_schema.statistics')
            ->where('table_schema', $database)
            ->where('non_unique', 0)
            ->whereIn('index_name', $expectedIndexes)
            ->distinct()
            ->pluck('index_name')
            ->all();

        $this->assertEqualsCanonicalizing($expectedIndexes, $indexes);
    }

    public function test_money_quantity_and_tax_columns_use_fixed_decimal_precision(): void
    {
        $database = DB::connection()->getDatabaseName();
        $expected = [
            'products.min_stock' => [18, 3],
            'product_units.conversion_factor' => [18, 6],
            'supplier_products.last_purchase_price' => [18, 2],
            'supplier_products.minimum_order_qty' => [18, 3],
            'product_prices.amount' => [18, 2],
            'product_prices.minimum_quantity' => [18, 3],
            'tax_rules.rate' => [7, 4],
        ];

        $columns = DB::table('information_schema.columns')
            ->where('table_schema', $database)
            ->whereIn('table_name', self::TABLES)
            ->where('data_type', 'decimal')
            ->get()
            ->keyBy(fn (object $column): string => $column->TABLE_NAME.'.'.$column->COLUMN_NAME);

        foreach ($expected as $column => [$precision, $scale]) {
            $definition = $columns->get($column);
            $this->assertNotNull($definition, "Kolom decimal {$column} tidak ditemukan.");
            $this->assertSame($precision, (int) $definition->NUMERIC_PRECISION, "Presisi {$column} tidak sesuai.");
            $this->assertSame($scale, (int) $definition->NUMERIC_SCALE, "Skala {$column} tidak sesuai.");
        }
    }

    public function test_critical_master_foreign_keys_restrict_destructive_deletes(): void
    {
        $database = DB::connection()->getDatabaseName();
        $constraints = DB::table('information_schema.referential_constraints')
            ->where('constraint_schema', $database)
            ->whereIn('constraint_name', [
                'products_base_unit_id_foreign',
                'product_barcodes_product_id_foreign',
                'product_units_product_id_foreign',
                'supplier_products_supplier_id_foreign',
                'shelf_locations_branch_id_foreign',
                'product_locations_shelf_location_id_foreign',
                'product_prices_product_id_foreign',
                'price_change_logs_product_price_id_foreign',
            ])
            ->pluck('delete_rule', 'constraint_name');

        $this->assertCount(8, $constraints);
        foreach ($constraints as $name => $deleteRule) {
            $this->assertSame('RESTRICT', $deleteRule, "Foreign key {$name} tidak boleh cascade delete.");
        }
    }
}
