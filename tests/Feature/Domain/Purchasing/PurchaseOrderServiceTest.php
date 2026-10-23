<?php

namespace Tests\Feature\Domain\Purchasing;

use App\Domain\Access\Models\ApprovalLimit;
use App\Domain\Access\Models\Permission;
use App\Domain\Access\Models\Role;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductUnit;
use App\Domain\Catalog\Models\Unit;
use App\Domain\Organization\Models\Branch;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Services\PurchaseOrderService;
use App\Domain\Purchasing\Services\PurchaseOrderWorkflowService;
use App\Domain\Suppliers\Models\Supplier;
use App\Domain\Suppliers\Models\SupplierProduct;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurchaseOrderServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_draft_snapshots_supplier_product_unit_conversion_and_precise_totals_without_side_effects(): void
    {
        [$branch, $creator, $supplier, $product, $baseUnit] = $this->context();
        $box = Unit::factory()->create(['code' => 'BOX', 'name' => 'Box', 'symbol' => 'BOX']);
        ProductUnit::factory()->for($product)->for($box)->create([
            'conversion_factor' => '12.000000',
            'is_purchase_unit' => true,
        ]);
        SupplierProduct::factory()->for($supplier)->for($product)->create([
            'purchase_unit_id' => $box->id,
            'supplier_sku' => 'SUP-ITEM-01',
            'contract_price' => '12500.25',
        ]);

        $purchaseOrder = app(PurchaseOrderService::class)->createDraft($creator, $branch, [
            'supplier_id' => $supplier->id,
            'order_date' => '2026-10-23',
            'expected_at' => '2026-10-30',
            'notes' => 'Pesanan rutin mingguan',
            'idempotency_key' => (string) Str::uuid(),
            'items' => [[
                'product_id' => $product->id,
                'unit_id' => $box->id,
                'quantity' => '2.500',
                'unit_price' => '12500.25',
            ]],
        ]);

        $item = $purchaseOrder->items()->sole();
        $this->assertSame(PurchaseOrderStatus::Draft, $purchaseOrder->status);
        $this->assertSame('31250.63', $purchaseOrder->grand_total);
        $this->assertSame($supplier->name, $purchaseOrder->supplier_name_snapshot);
        $this->assertSame($product->sku, $item->product_sku_snapshot);
        $this->assertSame('SUP-ITEM-01', $item->supplier_sku_snapshot);
        $this->assertSame('12.000000', $item->conversion_factor);
        $this->assertSame('30.000', $item->base_quantity);
        $this->assertSame('0.000', $item->received_base_quantity);
        $this->assertStringStartsWith('PO/2026/10/', $purchaseOrder->document_number);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('inventory_balances', 0);
        $this->assertDatabaseCount('ap_entries', 0);
        $this->assertDatabaseCount('purchase_invoices', 0);
        $this->assertFalse(Schema::hasTable('supplier_payments'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'purchase_order.created', 'entity_id' => $purchaseOrder->public_id]);
    }

    public function test_create_and_update_are_idempotent_and_draft_items_can_be_replaced(): void
    {
        [$branch, $creator, $supplier, $product, $unit] = $this->context();
        $payload = $this->draftPayload($supplier, $product, $unit);

        $first = app(PurchaseOrderService::class)->createDraft($creator, $branch, $payload);
        $replay = app(PurchaseOrderService::class)->createDraft($creator, $branch, $payload);
        $update = $this->draftPayload($supplier, $product, $unit);
        $update['items'][0]['quantity'] = '3.000';
        $update['items'][0]['unit_price'] = '2000.00';
        $updated = app(PurchaseOrderService::class)->updateDraft($first, $creator, $branch, $update);
        $updateReplay = app(PurchaseOrderService::class)->updateDraft($first, $creator, $branch, $update);

        $this->assertTrue($first->is($replay));
        $this->assertTrue($updated->is($updateReplay));
        $this->assertDatabaseCount('purchase_orders', 1);
        $this->assertDatabaseCount('purchase_order_items', 1);
        $this->assertSame('6000.00', $updated->fresh()->grand_total);
        $this->assertSame(2, $updated->fresh()->version);
    }

    public function test_invalid_inactive_product_rolls_back_order_sequence_audit_and_idempotency_claim(): void
    {
        [$branch, $creator, $supplier, $product, $unit] = $this->context();
        $product->update(['is_active' => false]);

        try {
            app(PurchaseOrderService::class)->createDraft($creator, $branch, $this->draftPayload($supplier, $product, $unit));
            $this->fail('Produk nonaktif dapat dimasukkan ke Purchase Order.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('tidak aktif', $exception->getMessage());
        }

        $this->assertDatabaseCount('purchase_orders', 0);
        $this->assertDatabaseCount('purchase_order_items', 0);
        $this->assertDatabaseMissing('document_sequences', ['document_type' => 'purchase_order']);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'purchase_order.created']);
        $this->assertDatabaseMissing('idempotency_keys', ['operation' => 'purchase_order.create']);
    }

    public function test_submit_and_approve_are_idempotent_and_approved_order_cannot_be_edited(): void
    {
        [$branch, $creator, $supplier, $product, $unit] = $this->context();
        $approver = $this->approver($branch);
        $purchaseOrder = app(PurchaseOrderService::class)->createDraft($creator, $branch, $this->draftPayload($supplier, $product, $unit));
        $workflow = app(PurchaseOrderWorkflowService::class);
        $submitKey = (string) Str::uuid();

        $submitted = $workflow->submit($purchaseOrder, $creator, $branch, 'Diajukan sesuai kebutuhan pembelian toko', $submitKey);
        $submitReplay = $workflow->submit($purchaseOrder, $creator, $branch, 'Diajukan sesuai kebutuhan pembelian toko', $submitKey);
        $approveKey = (string) Str::uuid();
        $approved = $workflow->approve($submitted, $approver, $branch, 'Harga dan kebutuhan telah diverifikasi', $approveKey);
        $approveReplay = $workflow->approve($submitted, $approver, $branch, 'Harga dan kebutuhan telah diverifikasi', $approveKey);

        $this->assertSame(PurchaseOrderStatus::PendingApproval, $submitReplay->status);
        $this->assertSame(PurchaseOrderStatus::Approved, $approved->status);
        $this->assertSame(PurchaseOrderStatus::Approved, $approveReplay->status);
        $this->assertDatabaseCount('approval_requests', 1);
        $this->assertDatabaseHas('outbox_events', ['event_type' => 'purchase_order.approved', 'aggregate_id' => $purchaseOrder->public_id]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Hanya Purchase Order berstatus draft yang dapat diubah.');
        app(PurchaseOrderService::class)->updateDraft($approved, $creator, $branch, $this->draftPayload($supplier, $product, $unit));
    }

    public function test_creator_cannot_self_approve(): void
    {
        [$branch, , $supplier, $product, $unit] = $this->context();
        $creator = $this->approver($branch);
        $purchaseOrder = app(PurchaseOrderService::class)->createDraft($creator, $branch, $this->draftPayload($supplier, $product, $unit));
        $submitted = app(PurchaseOrderWorkflowService::class)->submit($purchaseOrder, $creator, $branch, 'Pengajuan pembelian untuk pemenuhan stok', (string) Str::uuid());

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Pembuat Purchase Order tidak boleh menyetujui dokumennya sendiri.');
        app(PurchaseOrderWorkflowService::class)->approve($submitted, $creator, $branch, 'Mencoba memutuskan pengajuan sendiri', (string) Str::uuid());
    }

    public function test_authorized_approver_can_reject_with_audit_history(): void
    {
        [$branch, $creator, $supplier, $product, $unit] = $this->context();
        $approver = $this->approver($branch);
        $purchaseOrder = app(PurchaseOrderService::class)->createDraft($creator, $branch, $this->draftPayload($supplier, $product, $unit));
        $submitted = app(PurchaseOrderWorkflowService::class)->submit($purchaseOrder, $creator, $branch, 'Pengajuan pembelian untuk kebutuhan operasional', (string) Str::uuid());

        $rejected = app(PurchaseOrderWorkflowService::class)->reject($submitted, $approver, $branch, 'Harga perlu dinegosiasikan kembali dengan supplier', (string) Str::uuid());

        $this->assertSame(PurchaseOrderStatus::Rejected, $rejected->status);
        $this->assertSame($approver->id, $rejected->rejected_by);
        $this->assertDatabaseHas('approval_requests', ['id' => $rejected->approval_request_id, 'status' => 'rejected']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'approval.rejected', 'entity_type' => 'approval_request']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'purchase_order.rejected', 'entity_id' => $rejected->public_id]);
    }

    public function test_approval_limit_is_required_and_failed_decision_rolls_back(): void
    {
        [$branch, $creator, $supplier, $product, $unit] = $this->context();
        $approverWithoutLimit = User::factory()->withRole('store_manager')->forBranch($branch)->create();
        $purchaseOrder = app(PurchaseOrderService::class)->createDraft($creator, $branch, $this->draftPayload($supplier, $product, $unit));
        $submitted = app(PurchaseOrderWorkflowService::class)->submit($purchaseOrder, $creator, $branch, 'Diajukan untuk pengujian batas persetujuan', (string) Str::uuid());

        try {
            app(PurchaseOrderWorkflowService::class)->approve($submitted, $approverWithoutLimit, $branch, 'Mencoba tanpa batas persetujuan terkonfigurasi', (string) Str::uuid());
            $this->fail('Purchase Order dapat disetujui tanpa approval limit.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        $this->assertSame(PurchaseOrderStatus::PendingApproval, $submitted->fresh()->status);
        $this->assertDatabaseHas('approval_requests', ['id' => $submitted->approval_request_id, 'status' => 'pending']);
        $this->assertDatabaseMissing('outbox_events', ['event_type' => 'purchase_order.approved', 'aggregate_id' => $submitted->public_id]);
    }

    public function test_approved_order_can_be_marked_sent_then_cancelled_without_inventory_effect(): void
    {
        [$branch, $creator, $supplier, $product, $unit] = $this->context();
        $approver = $this->approver($branch);
        $workflow = app(PurchaseOrderWorkflowService::class);
        $purchaseOrder = app(PurchaseOrderService::class)->createDraft($creator, $branch, $this->draftPayload($supplier, $product, $unit));
        $submitted = $workflow->submit($purchaseOrder, $creator, $branch, 'Diajukan untuk penerbitan Purchase Order', (string) Str::uuid());
        $approved = $workflow->approve($submitted, $approver, $branch, 'Dokumen telah lengkap dan dapat diterbitkan', (string) Str::uuid());
        $sent = $workflow->send($approved, $creator, $branch, (string) Str::uuid());
        $cancelled = $workflow->cancel($sent, $creator, $branch, 'Supplier tidak dapat memenuhi estimasi pengiriman', (string) Str::uuid());

        $this->assertSame(PurchaseOrderStatus::Sent, $sent->status);
        $this->assertSame(PurchaseOrderStatus::Cancelled, $cancelled->status);
        $this->assertSame('Supplier tidak dapat memenuhi estimasi pengiriman', $cancelled->cancellation_reason);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('inventory_balances', 0);
    }

    public function test_service_rejects_cross_branch_creation(): void
    {
        [, $creator, $supplier, $product, $unit] = $this->context();
        $foreignBranch = Branch::factory()->create();

        $this->expectException(AuthorizationException::class);
        app(PurchaseOrderService::class)->createDraft($creator, $foreignBranch, $this->draftPayload($supplier, $product, $unit));
    }

    /** @return array{Branch, User, Supplier, Product, Unit} */
    private function context(): array
    {
        $this->seed();
        $branch = Branch::factory()->create();
        $creator = User::factory()->withRole('purchasing')->forBranch($branch)->create();
        $supplier = Supplier::factory()->create(['is_active' => true]);
        $unit = Unit::factory()->create(['code' => fake()->unique()->bothify('U##'), 'is_active' => true]);
        $product = Product::factory()->for($unit, 'baseUnit')->create(['is_active' => true]);

        return [$branch, $creator, $supplier, $product, $unit];
    }

    private function approver(Branch $branch): User
    {
        $approver = User::factory()->withRole('store_manager')->forBranch($branch)->create();
        ApprovalLimit::factory()->create([
            'branch_id' => $branch->id,
            'role_id' => Role::query()->where('code', 'store_manager')->value('id'),
            'permission_id' => Permission::query()->where('code', 'purchase_orders.approve')->value('id'),
            'maximum_amount' => '9999999999999999.99',
        ]);

        return $approver;
    }

    /** @return array<string, mixed> */
    private function draftPayload(Supplier $supplier, Product $product, Unit $unit): array
    {
        return [
            'supplier_id' => $supplier->id,
            'order_date' => '2026-10-23',
            'expected_at' => '2026-10-30',
            'notes' => 'Draft pengujian Purchase Order',
            'idempotency_key' => (string) Str::uuid(),
            'items' => [[
                'product_id' => $product->id,
                'unit_id' => $unit->id,
                'quantity' => '2.000',
                'unit_price' => '1500.00',
            ]],
        ];
    }
}
