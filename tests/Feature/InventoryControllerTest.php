<?php

namespace Tests\Feature;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Unit;
use App\Domain\Inventory\Models\StockAdjustment;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Warehouse;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventoryControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_and_user_without_permission_is_forbidden(): void
    {
        $this->get(route('inventory.index'))->assertRedirect(route('login'));
        $this->seed();
        $branch = Branch::factory()->create();
        $cashier = User::factory()->withRole('cashier')->forBranch($branch)->create();

        $this->actingAs($cashier)->get(route('inventory.index'))->assertForbidden();
    }

    public function test_inventory_pages_render_empty_states_for_authorized_branch_user(): void
    {
        $this->seed();
        $branch = Branch::factory()->create();
        $user = User::factory()->withRole('inventory')->forBranch($branch)->create();
        $warehouse = Warehouse::factory()->for($branch)->create();

        $this->actingAs($user)->get(route('inventory.index'))->assertOk()->assertSee('Dashboard Persediaan')->assertSee('Belum ada mutasi stok');
        $this->actingAs($user)->get(route('inventory.balances'))->assertOk()->assertSee('Belum ada saldo');
        $this->actingAs($user)->get(route('inventory.movements'))->assertOk()->assertSee('Belum ada mutasi stok');
        $this->actingAs($user)->get(route('inventory.opening.index'))->assertOk()->assertSee('Input saldo awal');
        $this->actingAs($user)->get(route('inventory.warehouses.show', $warehouse))->assertOk()->assertSee('belum memiliki saldo stok');
        $this->actingAs($user)->get(route('inventory.reconciliation'))->assertOk()->assertSee('Belum ada data persediaan');
    }

    public function test_opening_balance_request_validates_payload_and_persists_pending_document(): void
    {
        $this->seed();
        $branch = Branch::factory()->create();
        $warehouse = Warehouse::factory()->for($branch)->create();
        $user = User::factory()->withRole('inventory')->forBranch($branch)->create();
        $unit = Unit::factory()->create();
        $product = Product::factory()->for($unit, 'baseUnit')->create();

        $this->actingAs($user)->from(route('inventory.opening.index'))->post(route('inventory.opening.store'), [
            'warehouse_id' => $warehouse->id,
            'occurred_at' => now()->addMinute()->format('Y-m-d H:i:s'),
            'reason' => 'Pendek',
            'submission_key' => 'bukan-uuid',
            'items' => [],
        ])->assertRedirect(route('inventory.opening.index'))->assertInvalid(['occurred_at', 'reason', 'submission_key', 'items']);
        $this->assertDatabaseCount('stock_adjustments', 0);

        $response = $this->actingAs($user)->post(route('inventory.opening.store'), [
            'warehouse_id' => $warehouse->id,
            'occurred_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            'reason' => 'Saldo awal berdasarkan berita acara fisik',
            'submission_key' => (string) Str::uuid(),
            'items' => [[
                'product_id' => $product->id,
                'unit_id' => $unit->id,
                'quantity' => '5.000',
                'unit_cost' => '1234.500000',
                'stock_status' => 'sellable',
            ]],
        ]);

        $response->assertRedirect(route('inventory.opening.index'))->assertSessionHasNoErrors()->assertSessionHas('status');
        $this->assertDatabaseHas('stock_adjustments', ['branch_id' => $branch->id, 'status' => 'pending_approval']);
        $this->assertDatabaseHas('stock_adjustment_items', ['product_id' => $product->id, 'base_quantity' => '5.000']);
        $this->assertDatabaseHas('approval_requests', ['permission_code' => 'inventory.opening.approve', 'status' => 'pending']);
        $this->assertDatabaseCount('inventory_movements', 0);
    }

    public function test_cross_branch_warehouse_and_adjustment_return_not_found(): void
    {
        $this->seed();
        $ownBranch = Branch::factory()->create();
        $foreignBranch = Branch::factory()->create();
        $user = User::factory()->withRole('store_manager')->forBranch($ownBranch)->create();
        $foreignWarehouse = Warehouse::factory()->for($foreignBranch)->create();
        $foreignCreator = User::factory()->withRole('inventory')->forBranch($foreignBranch)->create();
        $unit = Unit::factory()->create();
        $product = Product::factory()->for($unit, 'baseUnit')->create();
        $foreignAdjustment = StockAdjustment::factory()->create([
            'branch_id' => $foreignBranch->id,
            'destination_warehouse_id' => $foreignWarehouse->id,
            'created_by' => $foreignCreator->id,
        ]);

        $this->actingAs($user)->get(route('inventory.warehouses.show', $foreignWarehouse))->assertNotFound();
        $this->actingAs($user)->post(route('inventory.opening.store'), [
            'warehouse_id' => $foreignWarehouse->id,
            'occurred_at' => now()->subMinute()->format('Y-m-d H:i:s'),
            'reason' => 'Percobaan akses gudang cabang lain',
            'submission_key' => (string) Str::uuid(),
            'items' => [['product_id' => $product->id, 'unit_id' => $unit->id, 'quantity' => '1.000', 'unit_cost' => '1.000000', 'stock_status' => 'sellable']],
        ])->assertNotFound();
        $this->actingAs($user)->post(route('inventory.opening.approve', $foreignAdjustment), [
            'decision_notes' => 'Mencoba menyetujui cabang lain',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertNotFound();
    }

    public function test_inventory_role_cannot_approve_opening_balance(): void
    {
        $this->seed();
        $branch = Branch::factory()->create();
        $user = User::factory()->withRole('inventory')->forBranch($branch)->create();
        $warehouse = Warehouse::factory()->for($branch)->create();
        $adjustment = StockAdjustment::factory()->create([
            'branch_id' => $branch->id,
            'destination_warehouse_id' => $warehouse->id,
            'created_by' => $user->id,
        ]);

        $this->actingAs($user)->post(route('inventory.opening.approve', $adjustment), [
            'decision_notes' => 'Tidak memiliki izin persetujuan',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertForbidden();
    }
}
