<?php

namespace Tests\Feature\Http\Controllers\Master;

use App\Domain\Access\Models\AuditLog;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Department;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductBarcode;
use App\Domain\Catalog\Models\ProductUnit;
use App\Domain\Catalog\Models\Unit;
use App\Domain\Organization\Models\Branch;
use App\Domain\Suppliers\Models\Supplier;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class ProductControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_manager_creates_product_with_precise_units_barcodes_supplier_and_audit(): void
    {
        $this->seed();
        $branch = Branch::factory()->create();
        $manager = User::factory()->withRole('store_manager')->forBranch($branch)->create();
        $unit = Unit::factory()->create(['code' => 'PCS']);
        $supplier = Supplier::factory()->create();

        $response = $this->actingAs($manager)->post(route('master.products.store'), $this->validPayload($unit, [
            'default_supplier_id' => $supplier->id,
            'primary_barcode' => '8990000000001',
            'alternate_barcodes_text' => "8990000000002\n8990000000003",
        ]));

        $product = Product::query()->where('sku', 'SKU-0001')->first();
        $this->assertNotNull($product);
        $response->assertRedirect(route('master.products.show', $product))->assertSessionHas('status');
        $this->assertSame('0.125', $product->min_stock);
        $this->assertSame($supplier->id, $product->default_supplier_id);
        $this->assertSame(3, ProductBarcode::query()->whereBelongsTo($product)->where('is_active', true)->count());
        $this->assertDatabaseHas('product_units', [
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'conversion_factor' => 1,
            'is_purchase_unit' => true,
            'is_sale_unit' => true,
        ]);
        $this->assertDatabaseHas('supplier_products', ['product_id' => $product->id, 'supplier_id' => $supplier->id, 'is_primary' => true]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'product.created', 'entity_id' => $product->public_id]);
    }

    public function test_duplicate_sku_and_barcode_are_rejected_with_indonesian_errors_without_side_effects(): void
    {
        $this->seed();
        $branch = Branch::factory()->create();
        $manager = User::factory()->withRole('store_manager')->forBranch($branch)->create();
        $unit = Unit::factory()->create();
        $existing = Product::factory()->for($unit, 'baseUnit')->create(['sku' => 'SKU-0001']);
        ProductBarcode::factory()->for($existing)->for($unit)->create(['barcode' => '8990000000001']);

        $response = $this->actingAs($manager)->from(route('master.products.create'))->post(
            route('master.products.store'),
            $this->validPayload($unit, ['primary_barcode' => '8990000000001']),
        );

        $response->assertRedirect(route('master.products.create'))
            ->assertSessionHasErrors(['sku' => 'SKU sudah digunakan barang lain.', 'primary_barcode' => 'Barcode utama sudah digunakan barang lain.']);
        $this->assertSame(1, Product::query()->count());
        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_category_must_belong_to_selected_department(): void
    {
        $this->seed();
        $branch = Branch::factory()->create();
        $manager = User::factory()->withRole('store_manager')->forBranch($branch)->create();
        $unit = Unit::factory()->create();
        $selectedDepartment = Department::factory()->create();
        $foreignCategory = Category::factory()->create();

        $response = $this->actingAs($manager)->post(route('master.products.store'), $this->validPayload($unit, [
            'department_id' => $selectedDepartment->id,
            'category_id' => $foreignCategory->id,
        ]));

        $response->assertSessionHasErrors(['category_id' => 'Kategori harus berada pada departemen yang dipilih.']);
        $this->assertDatabaseCount('products', 0);
    }

    public function test_same_unit_with_different_conversion_factors_is_rejected(): void
    {
        $this->seed();
        $branch = Branch::factory()->create();
        $manager = User::factory()->withRole('store_manager')->forBranch($branch)->create();
        $unit = Unit::factory()->create();

        $response = $this->actingAs($manager)->post(route('master.products.store'), $this->validPayload($unit, [
            'sale_conversion_factor' => '2.000000',
        ]));

        $response->assertSessionHasErrors('sale_conversion_factor');
        $this->assertDatabaseCount('products', 0);
    }

    public function test_cashier_cannot_create_product_and_master_has_no_stock_edit_action(): void
    {
        $this->seed();
        $branch = Branch::factory()->create();
        $cashier = User::factory()->withRole('cashier')->forBranch($branch)->create();
        $unit = Unit::factory()->create();
        $product = Product::factory()->for($unit, 'baseUnit')->create();

        $this->actingAs($cashier)->post(route('master.products.store'), $this->validPayload($unit))->assertForbidden();
        $this->actingAs($cashier)->get(route('master.products.show', $product))
            ->assertOk()
            ->assertSee('Tidak ada saldo stok yang dapat diedit.')
            ->assertDontSee('name="on_hand"', false);
    }

    public function test_manager_updates_product_without_creating_duplicate_children(): void
    {
        $this->seed();
        $branch = Branch::factory()->create();
        $manager = User::factory()->withRole('store_manager')->forBranch($branch)->create();
        $unit = Unit::factory()->create();
        $product = Product::factory()->for($unit, 'baseUnit')->create(['sku' => 'SKU-LAMA']);
        ProductUnit::factory()->for($product)->for($unit)->create();
        ProductBarcode::factory()->for($product)->for($unit)->create(['barcode' => '8990000000010']);

        $this->actingAs($manager)->put(route('master.products.update', $product), $this->validPayload($unit, [
            'sku' => 'SKU-BARU',
            'name' => 'Nama Barang Diperbarui',
            'primary_barcode' => '8990000000011',
            'min_stock' => '2.750',
            'reason' => 'Memperbarui master barang.',
        ]))->assertRedirect(route('master.products.show', $product))->assertSessionHasNoErrors();

        $product->refresh();
        $this->assertSame('SKU-BARU', $product->sku);
        $this->assertSame('2.750', $product->min_stock);
        $this->assertSame(1, $product->barcodes()->where('is_active', true)->count());
        $this->assertSame(1, $product->units()->where('is_active', true)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'product.updated', 'entity_id' => $product->public_id]);
    }

    public function test_database_unique_and_foreign_key_constraints_protect_catalog_integrity(): void
    {
        $unit = Unit::factory()->create();
        Product::factory()->for($unit, 'baseUnit')->create(['sku' => 'DB-UNIQUE']);

        try {
            Product::factory()->for($unit, 'baseUnit')->create(['sku' => 'DB-UNIQUE']);
            $this->fail('Unique SKU constraint tidak menolak duplikasi.');
        } catch (QueryException) {
            $this->assertSame(1, Product::query()->where('sku', 'DB-UNIQUE')->count());
        }

        $this->expectException(QueryException::class);
        $unit->delete();
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function validPayload(Unit $unit, array $overrides = []): array
    {
        return [
            'sku' => 'SKU-0001',
            'name' => 'Beras Premium',
            'classification' => 'stock',
            'department_id' => null,
            'category_id' => null,
            'subcategory_id' => null,
            'brand_id' => null,
            'base_unit_id' => $unit->id,
            'purchase_unit_id' => $unit->id,
            'purchase_conversion_factor' => '1.000000',
            'sale_unit_id' => $unit->id,
            'sale_conversion_factor' => '1.000000',
            'default_supplier_id' => null,
            'tax_rule_id' => null,
            'primary_barcode' => null,
            'alternate_barcodes_text' => '',
            'description' => 'Barang kebutuhan toko.',
            'track_batch' => '0',
            'track_expiry' => '0',
            'allow_fractional_qty' => '0',
            'min_stock' => '0.125',
            'is_active' => '1',
            'reason' => 'Membuat master barang pengujian.',
            ...$overrides,
        ];
    }
}
