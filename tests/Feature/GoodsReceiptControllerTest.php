<?php

namespace Tests\Feature;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Unit;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Models\GoodsReceipt;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\PurchaseOrderItem;
use App\Domain\Suppliers\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GoodsReceiptControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_and_cashier_is_forbidden(): void
    {
        $this->get(route('purchasing.goods-receipts.index'))->assertRedirect(route('login'));
        $this->seed();
        $branch = Branch::factory()->create();
        $cashier = User::factory()->withRole('cashier')->forBranch($branch)->create();

        $this->actingAs($cashier)->get(route('purchasing.goods-receipts.index'))->assertForbidden();
    }

    public function test_inventory_user_can_create_draft_through_http(): void
    {
        [$branch, $warehouse, $user, $purchaseOrder, $item] = $this->context();
        $this->actingAs($user)->get(route('purchasing.goods-receipts.create'))
            ->assertOk()->assertSee($branch->name)->assertSee($purchaseOrder->document_number)->assertSee('Simpan Draft');

        $response = $this->actingAs($user)->post(route('purchasing.goods-receipts.store'), $this->payload($purchaseOrder, $warehouse, $item));
        $receipt = GoodsReceipt::query()->sole();

        $response->assertRedirect(route('purchasing.goods-receipts.show', $receipt));
        $this->assertDatabaseHas('goods_receipts', ['status' => 'draft', 'supplier_delivery_note_normalized' => 'SJ-HTTP-01']);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('inventory_balances', 0);
    }

    public function test_invalid_quantity_is_rejected_and_branch_isolation_returns_not_found(): void
    {
        [$branch, $warehouse, $user, $purchaseOrder, $item] = $this->context();
        $invalid = $this->payload($purchaseOrder, $warehouse, $item);
        $invalid['items'][0]['quantity'] = '0';
        $this->actingAs($user)->post(route('purchasing.goods-receipts.store'), $invalid)
            ->assertSessionHasErrors(['items.0.quantity']);

        $foreignBranch = Branch::factory()->create();
        $foreignWarehouse = Warehouse::factory()->for($foreignBranch)->create();
        $foreignUser = User::factory()->withRole('inventory')->forBranch($foreignBranch)->create();
        $foreignReceipt = GoodsReceipt::factory()->create([
            'branch_id' => $foreignBranch->id,
            'warehouse_id' => $foreignWarehouse->id,
            'purchase_order_id' => PurchaseOrder::factory()->create([
                'branch_id' => $foreignBranch->id,
                'created_by' => $foreignUser->id,
            ])->id,
            'created_by' => $foreignUser->id,
        ]);

        $this->assertNotSame($branch->id, $foreignReceipt->branch_id);
        $this->actingAs($user)->get(route('purchasing.goods-receipts.show', $foreignReceipt))->assertNotFound();
        $this->actingAs($user)->get(route('purchasing.goods-receipts.edit', $foreignReceipt))->assertNotFound();
    }

    public function test_purchasing_role_can_draft_but_cannot_post_receipt(): void
    {
        [$branch, $warehouse, , $purchaseOrder, $item] = $this->context();
        $purchasing = User::factory()->withRole('purchasing')->forBranch($branch)->create();
        $payload = $this->payload($purchaseOrder, $warehouse, $item);
        $this->actingAs($purchasing)->post(route('purchasing.goods-receipts.store'), $payload)->assertRedirect();
        $receipt = GoodsReceipt::query()->sole();

        $this->actingAs($purchasing)->post(route('purchasing.goods-receipts.post', $receipt), [
            'idempotency_key' => (string) Str::uuid(),
        ])->assertForbidden();
        $this->assertSame('draft', $receipt->fresh()->status->value);
    }

    /** @return array{Branch, Warehouse, User, PurchaseOrder, PurchaseOrderItem} */
    private function context(): array
    {
        $this->seed();
        $branch = Branch::factory()->create();
        $warehouse = Warehouse::factory()->for($branch)->create(['is_active' => true]);
        $user = User::factory()->withRole('inventory')->forBranch($branch)->create();
        $supplier = Supplier::factory()->create(['is_active' => true]);
        $unit = Unit::factory()->create(['is_active' => true]);
        $product = Product::factory()->for($unit, 'baseUnit')->create();
        $purchaseOrder = PurchaseOrder::factory()->create([
            'branch_id' => $branch->id, 'supplier_id' => $supplier->id,
            'status' => PurchaseOrderStatus::Approved, 'created_by' => $user->id,
            'supplier_code_snapshot' => $supplier->code, 'supplier_name_snapshot' => $supplier->name,
        ]);
        $item = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id, 'product_id' => $product->id, 'unit_id' => $unit->id,
            'product_sku_snapshot' => $product->sku, 'product_name_snapshot' => $product->name,
            'quantity' => '5.000', 'base_quantity' => '5.000', 'unit_price' => '1000.00', 'subtotal' => '5000.00',
        ]);

        return [$branch, $warehouse, $user, $purchaseOrder, $item];
    }

    /** @return array<string, mixed> */
    private function payload(PurchaseOrder $purchaseOrder, Warehouse $warehouse, PurchaseOrderItem $item): array
    {
        return [
            'purchase_order_id' => $purchaseOrder->id, 'warehouse_id' => $warehouse->id,
            'supplier_delivery_note' => 'SJ-HTTP-01', 'received_at' => '2026-10-23T10:00',
            'notes' => 'Penerimaan HTTP', 'idempotency_key' => (string) Str::uuid(),
            'items' => [[
                'purchase_order_item_id' => $item->id, 'quantity' => '2.000',
                'lot_number' => null, 'manufactured_at' => null, 'expires_at' => null, 'notes' => null,
            ]],
        ];

    }
}
