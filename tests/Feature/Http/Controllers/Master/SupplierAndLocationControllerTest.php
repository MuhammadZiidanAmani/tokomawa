<?php

namespace Tests\Feature\Http\Controllers\Master;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ShelfLocation;
use App\Domain\Catalog\Models\Unit;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Suppliers\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class SupplierAndLocationControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_purchasing_creates_supplier_and_precise_primary_product_relation(): void
    {
        $this->seed();
        $branch = Branch::factory()->create();
        $purchasing = User::factory()->withRole('purchasing')->forBranch($branch)->create();
        $unit = Unit::factory()->create();
        $product = Product::factory()->for($unit, 'baseUnit')->create();

        $this->actingAs($purchasing)->post(route('master.suppliers.store'), [
            'code' => 'sup-001', 'name' => 'PT Pangan Aman', 'address' => 'Jalan Pasar 1',
            'phone' => '021555000', 'email' => 'supplier@example.test',
            'tax_identifier' => '01.234.567.8-999.000', 'contact_name' => 'Budi',
            'payment_terms_days' => 30, 'bank_name' => 'Bank Contoh',
            'bank_account_name' => 'PT Pangan Aman', 'bank_account_number' => '123456789',
            'is_active' => '1', 'reason' => 'Menambah supplier.',
        ])->assertSessionHasNoErrors();

        $supplier = Supplier::query()->where('code', 'SUP-001')->firstOrFail();
        $this->actingAs($purchasing)->post(route('master.suppliers.products.store', $supplier), [
            'product_id' => $product->id, 'purchase_unit_id' => $unit->id,
            'supplier_sku' => 'VENDOR-001', 'last_purchase_price' => '12345.67',
            'contract_price' => '12000.25', 'minimum_order_qty' => '2.500',
            'lead_time_days' => 4, 'priority' => 1, 'is_primary' => '1',
            'is_active' => '1', 'reason' => 'Menetapkan supplier utama.',
        ])->assertSessionHasNoErrors()->assertSessionHas('status');

        $relation = $supplier->supplierProducts()->whereBelongsTo($product)->firstOrFail();
        $this->assertSame('12345.67', $relation->last_purchase_price);
        $this->assertSame('12000.25', $relation->contract_price);
        $this->assertSame('2.500', $relation->minimum_order_qty);
        $this->assertTrue($relation->is_primary);
        $this->assertSame($supplier->id, $product->fresh()->default_supplier_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'supplier.created', 'entity_id' => $supplier->public_id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'supplier_product.saved', 'entity_id' => $relation->public_id]);

        $this->actingAs($purchasing)->put(route('master.suppliers.update', $supplier), [
            'code' => 'SUP-001', 'name' => 'PT Pangan Aman Diperbarui', 'email' => 'baru@example.test',
            'payment_terms_days' => 14, 'is_active' => '1', 'reason' => 'Memperbarui supplier.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('PT Pangan Aman Diperbarui', $supplier->fresh()->name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'supplier.updated', 'entity_id' => $supplier->public_id]);
    }

    public function test_location_management_is_scoped_to_the_active_branch(): void
    {
        $this->seed();
        $ownBranch = Branch::factory()->create();
        $foreignBranch = Branch::factory()->create();
        $manager = User::factory()->withRole('store_manager')->forBranch($ownBranch)->create();
        $ownWarehouse = Warehouse::factory()->for($ownBranch)->create();
        $foreignWarehouse = Warehouse::factory()->for($foreignBranch)->create();
        $unit = Unit::factory()->create();
        $product = Product::factory()->for($unit, 'baseUnit')->create();

        $this->actingAs($manager)->post(route('master.locations.store'), [
            'warehouse_id' => $ownWarehouse->id, 'code' => 'A-01', 'name' => 'Rak A 01',
            'zone' => 'A', 'aisle' => '01', 'level' => '1', 'is_active' => '1',
            'reason' => 'Menambah rak cabang aktif.',
        ])->assertSessionHasNoErrors();

        $location = ShelfLocation::query()->where('code', 'A-01')->firstOrFail();
        $this->assertSame($ownBranch->id, $location->branch_id);
        $this->actingAs($manager)->post(route('master.locations.products.store', $location), [
            'product_id' => $product->id, 'is_primary' => '1', 'is_active' => '1',
            'reason' => 'Menetapkan rak utama.',
        ])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('product_locations', [
            'product_id' => $product->id, 'shelf_location_id' => $location->id, 'is_primary' => true,
        ]);

        $this->actingAs($manager)->post(route('master.locations.store'), [
            'warehouse_id' => $foreignWarehouse->id, 'code' => 'X-01', 'name' => 'Rak Asing',
            'is_active' => '1', 'reason' => 'Uji isolasi cabang.',
        ])->assertSessionHasErrors('warehouse_id');

        $foreignLocation = ShelfLocation::factory()->for($foreignBranch)->create(['warehouse_id' => $foreignWarehouse->id]);
        $this->actingAs($manager)->put(route('master.locations.update', $foreignLocation), [
            'warehouse_id' => $foreignWarehouse->id, 'code' => $foreignLocation->code,
            'name' => 'Tidak boleh berubah', 'is_active' => '1', 'reason' => 'Uji isolasi.',
        ])->assertNotFound();
        $this->assertNotSame('Tidak boleh berubah', $foreignLocation->fresh()->name);

        $this->actingAs($manager)->put(route('master.locations.update', $location), [
            'warehouse_id' => $ownWarehouse->id, 'code' => 'A-01', 'name' => 'Rak A 01 Diperbarui',
            'is_active' => '0', 'reason' => 'Menonaktifkan rak.',
        ])->assertSessionHasNoErrors();
        $this->assertFalse($location->fresh()->is_active);
    }

    public function test_inventory_can_manage_locations_but_cannot_manage_suppliers(): void
    {
        $this->seed();
        $branch = Branch::factory()->create();
        $inventory = User::factory()->withRole('inventory')->forBranch($branch)->create();
        $warehouse = Warehouse::factory()->for($branch)->create();

        $this->actingAs($inventory)->post(route('master.locations.store'), [
            'warehouse_id' => $warehouse->id, 'code' => 'INV-01', 'name' => 'Rak Inventory',
            'is_active' => '1', 'reason' => 'Uji izin inventory.',
        ])->assertSessionHasNoErrors();

        $this->actingAs($inventory)->post(route('master.suppliers.store'), [
            'code' => 'DENIED', 'name' => 'Tidak boleh', 'is_active' => '1', 'reason' => 'Uji RBAC.',
        ])->assertForbidden();
    }
}
