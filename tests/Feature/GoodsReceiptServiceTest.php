<?php

namespace Tests\Feature;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Unit;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\InventoryBatch;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Purchasing\Enums\GoodsReceiptStatus;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\PurchaseOrderItem;
use App\Domain\Purchasing\Services\GoodsReceiptService;
use App\Domain\Suppliers\Models\Supplier;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class GoodsReceiptServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_partial_and_final_receipts_post_exactly_once_without_ap(): void
    {
        [$branch, $warehouse, $actor, $purchaseOrder, $item] = $this->context(quantity: '10.000', unitPrice: '1200.00');
        $service = app(GoodsReceiptService::class);
        $firstPayload = $this->payload($purchaseOrder, $warehouse, $item, '4.000', 'SJ-PART-01');
        $draft = $service->createDraft($actor, $branch, $firstPayload);
        $draftReplay = $service->createDraft($actor, $branch, $firstPayload);

        $this->assertTrue($draft->is($draftReplay));
        $this->assertDatabaseCount('inventory_movements', 0);
        $postKey = (string) Str::uuid();
        $service->post($draft, $actor, $branch, $postKey);
        $service->post($draft, $actor, $branch, $postKey);

        $this->assertSame(PurchaseOrderStatus::PartiallyReceived, $purchaseOrder->fresh()->status);
        $this->assertSame('4.000', $item->fresh()->received_base_quantity);
        $this->assertSame('4.000', InventoryBalance::query()->sole()->qty_on_hand);
        $this->assertSame('1200.000000', InventoryBalance::query()->sole()->avg_unit_cost);
        $this->assertSame(1, InventoryMovement::query()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'goods_receipt.posted', 'entity_id' => $draft->public_id]);

        $second = $service->createDraft($actor, $branch, $this->payload($purchaseOrder->fresh(), $warehouse, $item->fresh(), '6.000', 'SJ-PART-02'));
        $service->post($second, $actor, $branch, (string) Str::uuid());

        $this->assertSame(PurchaseOrderStatus::Completed, $purchaseOrder->fresh()->status);
        $this->assertSame('10.000', InventoryBalance::query()->sole()->qty_on_hand);
        $this->assertSame(2, InventoryMovement::query()->count());
        $this->assertDatabaseCount('purchase_invoices', 0);
        $this->assertDatabaseCount('ap_entries', 0);
        $this->assertFalse(Schema::hasTable('supplier_payments'));
    }

    public function test_conversion_mwa_batch_and_expiry_are_precise(): void
    {
        [$branch, $warehouse, $actor, $purchaseOrder, $item, $product] = $this->context(
            quantity: '2.000', conversionFactor: '12.000000', unitPrice: '12000.00', trackBatch: true, trackExpiry: true,
        );
        $existingBatch = InventoryBatch::factory()->create([
            'branch_id' => $branch->id, 'product_id' => $product->id, 'lot_number' => 'LOT-ABC',
            'manufactured_at' => '2026-10-01', 'expires_at' => '2027-10-01',
        ]);
        InventoryBalance::factory()->create([
            'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id, 'product_id' => $product->id,
            'inventory_batch_id' => $existingBatch->id, 'batch_scope' => $existingBatch->public_id,
            'qty_on_hand' => '12.000', 'avg_unit_cost' => '500.000000', 'inventory_value' => '6000.000000',
        ]);
        $payload = $this->payload($purchaseOrder, $warehouse, $item, '1.000', 'SJ-BATCH-01');
        $payload['items'][0] = [...$payload['items'][0], 'lot_number' => 'lot-abc', 'manufactured_at' => '2026-10-01', 'expires_at' => '2027-10-01'];
        $receipt = app(GoodsReceiptService::class)->createDraft($actor, $branch, $payload);
        app(GoodsReceiptService::class)->post($receipt, $actor, $branch, (string) Str::uuid());

        $balance = InventoryBalance::query()->sole();
        $batch = InventoryBatch::query()->sole();
        $this->assertSame('12.000', $receipt->items()->sole()->base_quantity);
        $this->assertSame('1000.000000', $receipt->items()->sole()->base_unit_cost_snapshot);
        $this->assertSame('24.000', $balance->qty_on_hand);
        $this->assertSame('18000.000000', $balance->inventory_value);
        $this->assertSame('750.000000', $balance->avg_unit_cost);
        $this->assertSame('LOT-ABC', $batch->lot_number);
        $this->assertSame($batch->id, InventoryMovement::query()->sole()->inventory_batch_id);
    }

    public function test_failure_on_later_batch_line_rolls_back_all_changes(): void
    {
        [$branch, $warehouse, $actor, $purchaseOrder, $firstItem] = $this->context();
        $unit = Unit::factory()->create(['is_active' => true]);
        $tracked = Product::factory()->for($unit, 'baseUnit')->create(['track_batch' => true, 'track_expiry' => true]);
        $secondItem = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id, 'line_number' => 2, 'product_id' => $tracked->id,
            'unit_id' => $unit->id, 'product_sku_snapshot' => $tracked->sku, 'product_name_snapshot' => $tracked->name,
            'quantity' => '2.000', 'base_quantity' => '2.000', 'unit_price' => '2000.00', 'subtotal' => '4000.00',
        ]);
        InventoryBatch::factory()->create([
            'branch_id' => $branch->id, 'product_id' => $tracked->id,
            'lot_number' => 'LOT-CONFLICT', 'expires_at' => '2028-01-01',
        ]);
        $payload = $this->payload($purchaseOrder, $warehouse, $firstItem, '1.000', 'SJ-ROLLBACK-01');
        $payload['items'][] = [
            'purchase_order_item_id' => $secondItem->id, 'quantity' => '1.000', 'lot_number' => 'LOT-CONFLICT',
            'manufactured_at' => null, 'expires_at' => '2027-01-01', 'notes' => null,
        ];
        $receipt = app(GoodsReceiptService::class)->createDraft($actor, $branch, $payload);

        try {
            app(GoodsReceiptService::class)->post($receipt, $actor, $branch, (string) Str::uuid());
            $this->fail('Posting konflik batch seharusnya gagal.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('tidak konsisten', $exception->getMessage());
        }

        $this->assertSame(GoodsReceiptStatus::Draft, $receipt->fresh()->status);
        $this->assertSame('0.000', $firstItem->fresh()->received_base_quantity);
        $this->assertSame('0.000', $secondItem->fresh()->received_base_quantity);
        $this->assertSame(PurchaseOrderStatus::Approved, $purchaseOrder->fresh()->status);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('inventory_balances', 0);
        $this->assertDatabaseMissing('idempotency_keys', ['operation' => 'goods_receipt.post']);
    }

    public function test_over_receipt_is_rejected_cancel_has_no_effect_and_posted_is_immutable(): void
    {
        [$branch, $warehouse, $actor, $purchaseOrder, $item] = $this->context(quantity: '2.000');
        $service = app(GoodsReceiptService::class);
        try {
            $service->createDraft($actor, $branch, $this->payload($purchaseOrder, $warehouse, $item, '3.000', 'SJ-OVER-01'));
            $this->fail('Over-receipt dapat dibuat.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('melebihi outstanding', $exception->getMessage());
        }

        $cancelled = $service->createDraft($actor, $branch, $this->payload($purchaseOrder, $warehouse, $item, '1.000', 'SJ-CANCEL-01'));
        $cancelKey = (string) Str::uuid();
        $service->cancel($cancelled, $actor, $branch, 'Kiriman supplier tidak sesuai dokumen.', $cancelKey);
        $service->cancel($cancelled, $actor, $branch, 'Kiriman supplier tidak sesuai dokumen.', $cancelKey);
        $this->assertDatabaseCount('inventory_movements', 0);

        $posted = $service->createDraft($actor, $branch, $this->payload($purchaseOrder, $warehouse, $item, '1.000', 'SJ-POST-01'));
        $service->post($posted, $actor, $branch, (string) Str::uuid());
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('sudah diposting tidak dapat diubah');
        $posted->fresh()->update(['notes' => 'Percobaan perubahan ilegal']);
    }

    /** @return array{Branch, Warehouse, User, PurchaseOrder, PurchaseOrderItem, Product, Unit, Supplier} */
    private function context(
        string $quantity = '5.000', string $conversionFactor = '1.000000', string $unitPrice = '1000.00',
        bool $trackBatch = false, bool $trackExpiry = false,
    ): array {
        $this->seed();
        $branch = Branch::factory()->create();
        $warehouse = Warehouse::factory()->for($branch)->create(['is_active' => true]);
        $actor = User::factory()->withRole('inventory')->forBranch($branch)->create();
        $supplier = Supplier::factory()->create(['is_active' => true]);
        $unit = Unit::factory()->create(['is_active' => true]);
        $product = Product::factory()->for($unit, 'baseUnit')->create([
            'is_active' => true, 'track_batch' => $trackBatch, 'track_expiry' => $trackExpiry,
        ]);
        $purchaseOrder = PurchaseOrder::factory()->create([
            'branch_id' => $branch->id, 'supplier_id' => $supplier->id, 'status' => PurchaseOrderStatus::Approved,
            'supplier_code_snapshot' => $supplier->code, 'supplier_name_snapshot' => $supplier->name, 'created_by' => $actor->id,
        ]);
        $item = PurchaseOrderItem::factory()->create([
            'purchase_order_id' => $purchaseOrder->id, 'product_id' => $product->id, 'unit_id' => $unit->id,
            'product_sku_snapshot' => $product->sku, 'product_name_snapshot' => $product->name,
            'conversion_factor' => $conversionFactor, 'quantity' => $quantity,
            'base_quantity' => bcmul($quantity, $conversionFactor, 3), 'received_base_quantity' => '0.000',
            'unit_price' => $unitPrice, 'subtotal' => bcround(bcmul($quantity, $unitPrice, 5), 2),
        ]);

        return [$branch, $warehouse, $actor, $purchaseOrder, $item, $product, $unit, $supplier];
    }

    /** @return array<string, mixed> */
    private function payload(PurchaseOrder $purchaseOrder, Warehouse $warehouse, PurchaseOrderItem $item, string $quantity, string $deliveryNote): array
    {
        return [
            'purchase_order_id' => $purchaseOrder->id, 'warehouse_id' => $warehouse->id,
            'supplier_delivery_note' => $deliveryNote, 'received_at' => '2026-10-23 10:00:00',
            'notes' => 'Penerimaan pengujian', 'idempotency_key' => (string) Str::uuid(),
            'items' => [[
                'purchase_order_item_id' => $item->id, 'quantity' => $quantity, 'lot_number' => null,
                'manufactured_at' => null, 'expires_at' => null, 'notes' => null,
            ]],
        ];

    }
}
