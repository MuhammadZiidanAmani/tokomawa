<?php

namespace Tests\Feature;

use App\Domain\Access\Models\ApprovalLimit;
use App\Domain\Access\Models\ApprovalRequest;
use App\Domain\Access\Models\Permission;
use App\Domain\Access\Models\Role;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductUnit;
use App\Domain\Catalog\Models\Unit;
use App\Domain\Inventory\Enums\StockAdjustmentStatus;
use App\Domain\Inventory\Enums\StockAdjustmentType;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\InventoryBatch;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Inventory\Models\StockAdjustment;
use App\Domain\Inventory\Services\InventoryPostingService;
use App\Domain\Inventory\Services\InventoryReconciliationService;
use App\Domain\Inventory\Services\OpeningBalanceApprovalService;
use App\Domain\Inventory\Services\StockAdjustmentSubmissionService;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Warehouse;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class InventoryPostingTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_approved_opening_balance_posts_exactly_once_and_replays_same_key(): void
    {
        [$branch, $warehouse, $requester, $approver, $product, $unit] = $this->openingContext();
        $submission = app(StockAdjustmentSubmissionService::class)->submitOpeningBalance(
            $requester,
            $branch,
            $warehouse,
            [$this->openingItem($product, $unit, '10.000', '1250.000000')],
            'Saldo awal berdasarkan berita acara fisik',
            now(),
            (string) Str::uuid(),
        );
        $postingKey = (string) Str::uuid();

        $first = app(OpeningBalanceApprovalService::class)->approveAndPost($submission, $approver, 'Jumlah dan dokumen sumber sudah diperiksa', $postingKey);
        $second = app(OpeningBalanceApprovalService::class)->approveAndPost($submission->fresh(), $approver, 'Retry aman dari permintaan yang sama', $postingKey);

        $this->assertFalse($first->replayed);
        $this->assertTrue($second->replayed);
        $this->assertSame($first->payload, $second->payload);
        $this->assertDatabaseCount('inventory_movements', 1);
        $this->assertDatabaseHas('inventory_balances', [
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'qty_on_hand' => '10.000',
            'avg_unit_cost' => '1250.000000',
            'inventory_value' => '12500.000000',
        ]);
        $this->assertDatabaseHas('stock_adjustments', ['id' => $submission->id, 'status' => 'posted', 'posted_by' => $approver->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'inventory.adjustment.posted', 'entity_id' => $submission->public_id]);
        $this->assertDatabaseHas('outbox_events', ['event_type' => 'inventory.adjustment.posted', 'aggregate_id' => $submission->public_id]);
    }

    public function test_moving_weighted_average_and_unit_conversion_use_decimal_precision(): void
    {
        [$branch, $warehouse, $requester, $approver, $product, $baseUnit] = $this->openingContext();
        $box = Unit::factory()->create(['code' => 'BOX']);
        ProductUnit::factory()->for($product)->for($box)->create(['conversion_factor' => '12.000000']);
        $first = $this->submitAndApprove($branch, $warehouse, $requester, $approver, $product, $baseUnit, '12.000', '1000.000000');
        $second = app(StockAdjustmentSubmissionService::class)->submitOpeningBalance(
            $requester,
            $branch,
            $warehouse,
            [$this->openingItem($product, $box, '1.000', '2000.000000')],
            'Tambahan saldo awal dalam satuan box',
            now(),
            (string) Str::uuid(),
        );
        app(OpeningBalanceApprovalService::class)->approveAndPost($second, $approver, 'Konversi satuan dan HPP telah diperiksa', (string) Str::uuid());

        $balance = InventoryBalance::query()->where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->firstOrFail();
        $this->assertSame(StockAdjustmentStatus::Posted, $first->fresh()->status);
        $this->assertSame('24.000', $balance->qty_on_hand);
        $this->assertSame('1500.000000', $balance->avg_unit_cost);
        $this->assertSame('36000.000000', $balance->inventory_value);
    }

    public function test_transfer_moves_stock_between_warehouses_without_changing_total_value(): void
    {
        [$branch, $source, , $approver, $product, $unit] = $this->openingContext();
        $destination = Warehouse::factory()->for($branch)->create();
        InventoryBalance::factory()->create([
            'branch_id' => $branch->id,
            'warehouse_id' => $source->id,
            'product_id' => $product->id,
            'qty_on_hand' => '8.000',
            'avg_unit_cost' => '2500.000000',
            'inventory_value' => '20000.000000',
        ]);
        $adjustment = $this->approvedAdjustment($branch, $approver, StockAdjustmentType::WarehouseTransfer, $source, $destination, $product, $unit, '3.000');

        app(InventoryPostingService::class)->post($adjustment, $approver, (string) Str::uuid());

        $this->assertDatabaseHas('inventory_balances', ['warehouse_id' => $source->id, 'qty_on_hand' => '5.000', 'inventory_value' => '12500.000000']);
        $this->assertDatabaseHas('inventory_balances', ['warehouse_id' => $destination->id, 'qty_on_hand' => '3.000', 'inventory_value' => '7500.000000']);
        $this->assertSame(2, InventoryMovement::query()->where('source_id', $adjustment->public_id)->count());
        $this->assertSame(0, bccomp('20000.000000', (string) InventoryBalance::query()->where('product_id', $product->id)->sum('inventory_value'), 6));
    }

    public function test_negative_stock_is_rejected_and_all_partial_changes_are_rolled_back(): void
    {
        [$branch, $warehouse, , $approver, $firstProduct, $unit] = $this->openingContext();
        $secondProduct = Product::factory()->for($unit, 'baseUnit')->create();
        InventoryBalance::factory()->create([
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $firstProduct->id,
            'qty_on_hand' => '5.000',
            'avg_unit_cost' => '1000.000000',
            'inventory_value' => '5000.000000',
        ]);
        $adjustment = $this->approvedAdjustment($branch, $approver, StockAdjustmentType::Decrease, $warehouse, null, $firstProduct, $unit, '2.000');
        $adjustment->items()->create([
            'line_number' => 2,
            'product_id' => $secondProduct->id,
            'unit_id' => $unit->id,
            'quantity' => '1.000',
            'base_quantity' => '1.000',
            'unit_cost' => null,
            'stock_status' => 'sellable',
        ]);

        try {
            app(InventoryPostingService::class)->post($adjustment, $approver, (string) Str::uuid());
            $this->fail('DomainException tidak dilempar untuk saldo negatif.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('Stok tersedia', $exception->getMessage());
        }

        $this->assertDatabaseHas('inventory_balances', ['product_id' => $firstProduct->id, 'qty_on_hand' => '5.000']);
        $this->assertDatabaseMissing('inventory_balances', ['product_id' => $secondProduct->id]);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertSame(StockAdjustmentStatus::Approved, $adjustment->fresh()->status);
    }

    public function test_posted_movement_cannot_be_updated_or_deleted(): void
    {
        [$branch, $warehouse, $requester, $approver, $product, $unit] = $this->openingContext();
        $this->submitAndApprove($branch, $warehouse, $requester, $approver, $product, $unit, '1.000', '1000.000000');
        $movement = InventoryMovement::query()->firstOrFail();

        try {
            $movement->update(['qty_delta' => '99.000']);
            $this->fail('Mutasi stok dapat diubah.');
        } catch (DomainException $exception) {
            $this->assertSame('Mutasi stok yang sudah tercatat tidak dapat diubah.', $exception->getMessage());
        }

        try {
            $movement->delete();
            $this->fail('Mutasi stok dapat dihapus.');
        } catch (DomainException $exception) {
            $this->assertSame('Mutasi stok tidak dapat dihapus. Gunakan transaksi koreksi.', $exception->getMessage());
        }

        $this->assertDatabaseHas('inventory_movements', ['id' => $movement->id, 'qty_delta' => '1.000']);
    }

    public function test_reconciliation_reports_zero_for_valid_data_and_detects_projection_drift(): void
    {
        [$branch, $warehouse, $requester, $approver, $product, $unit] = $this->openingContext();
        $this->submitAndApprove($branch, $warehouse, $requester, $approver, $product, $unit, '4.000', '3000.000000');
        $service = app(InventoryReconciliationService::class);

        $valid = $service->reconcile($branch)->sole();
        $this->assertTrue($valid['is_reconciled']);
        $this->assertSame('0.000', $valid['quantity_difference']);
        $this->assertSame('0.000000', $valid['value_difference']);

        InventoryBalance::query()->where('warehouse_id', $warehouse->id)->where('product_id', $product->id)->update(['qty_on_hand' => '5.000']);
        $drift = $service->reconcile($branch)->sole();
        $this->assertFalse($drift['is_reconciled']);
        $this->assertSame('1.000', $drift['quantity_difference']);
    }

    public function test_creator_cannot_approve_own_opening_balance(): void
    {
        [$branch, $warehouse, $requester, , $product, $unit] = $this->openingContext();
        $adjustment = app(StockAdjustmentSubmissionService::class)->submitOpeningBalance(
            $requester,
            $branch,
            $warehouse,
            [$this->openingItem($product, $unit, '1.000', '1000.000000')],
            'Saldo awal membutuhkan pemisahan tugas',
            now(),
            (string) Str::uuid(),
        );

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Pembuat saldo awal tidak boleh menyetujui dokumennya sendiri.');
        app(OpeningBalanceApprovalService::class)->approveAndPost($adjustment, $requester, 'Mencoba menyetujui dokumen sendiri', (string) Str::uuid());
    }

    public function test_batch_and_expiry_tracking_are_required_and_persisted(): void
    {
        [$branch, $warehouse, $requester, , $product, $unit] = $this->openingContext();
        $product->update(['track_batch' => true, 'track_expiry' => true]);

        try {
            app(StockAdjustmentSubmissionService::class)->submitOpeningBalance(
                $requester,
                $branch,
                $warehouse,
                [$this->openingItem($product, $unit, '1.000', '1000.000000')],
                'Saldo awal produk dengan kendali batch',
                now(),
                (string) Str::uuid(),
            );
            $this->fail('Produk batch dapat diajukan tanpa nomor lot.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('Nomor batch wajib', $exception->getMessage());
        }
        $this->assertDatabaseCount('stock_adjustments', 0);

        $item = $this->openingItem($product, $unit, '1.000', '1000.000000');
        $item['lot_number'] = 'LOT-2026-001';
        $item['manufactured_at'] = '2026-10-01';
        $item['expires_at'] = '2027-10-01';
        app(StockAdjustmentSubmissionService::class)->submitOpeningBalance(
            $requester,
            $branch,
            $warehouse,
            [$item],
            'Saldo awal produk dengan batch lengkap',
            now(),
            (string) Str::uuid(),
        );

        $batch = InventoryBatch::query()->sole();
        $this->assertSame('LOT-2026-001', $batch->lot_number);
        $this->assertSame('2027-10-01', $batch->expires_at->toDateString());
    }

    /** @return array{Branch, Warehouse, User, User, Product, Unit} */
    private function openingContext(): array
    {
        $this->seed();
        $branch = Branch::factory()->create();
        $warehouse = Warehouse::factory()->for($branch)->create();
        $requester = User::factory()->withRole('inventory')->forBranch($branch)->create();
        $approver = User::factory()->withRole('store_manager')->forBranch($branch)->create();
        $unit = Unit::factory()->create(['code' => fake()->unique()->bothify('U##')]);
        $product = Product::factory()->for($unit, 'baseUnit')->create();
        ApprovalLimit::factory()->create([
            'branch_id' => $branch->id,
            'role_id' => Role::query()->where('code', 'store_manager')->value('id'),
            'permission_id' => Permission::query()->where('code', 'inventory.opening.approve')->value('id'),
            'maximum_amount' => '9999999999999999.99',
        ]);

        return [$branch, $warehouse, $requester, $approver, $product, $unit];
    }

    /** @return array<string, mixed> */
    private function openingItem(Product $product, Unit $unit, string $quantity, string $unitCost): array
    {
        return ['product_id' => $product->id, 'unit_id' => $unit->id, 'quantity' => $quantity, 'unit_cost' => $unitCost, 'stock_status' => 'sellable'];
    }

    private function submitAndApprove(Branch $branch, Warehouse $warehouse, User $requester, User $approver, Product $product, Unit $unit, string $quantity, string $unitCost): StockAdjustment
    {
        $adjustment = app(StockAdjustmentSubmissionService::class)->submitOpeningBalance(
            $requester,
            $branch,
            $warehouse,
            [$this->openingItem($product, $unit, $quantity, $unitCost)],
            'Saldo awal berdasarkan penghitungan fisik',
            now(),
            (string) Str::uuid(),
        );
        app(OpeningBalanceApprovalService::class)->approveAndPost($adjustment, $approver, 'Dokumen dan jumlah telah diperiksa', (string) Str::uuid());

        return $adjustment;
    }

    private function approvedAdjustment(Branch $branch, User $actor, StockAdjustmentType $type, ?Warehouse $source, ?Warehouse $destination, Product $product, Unit $unit, string $quantity): StockAdjustment
    {
        $adjustment = StockAdjustment::factory()->create([
            'branch_id' => $branch->id,
            'type' => $type,
            'status' => StockAdjustmentStatus::Approved,
            'source_warehouse_id' => $source?->id,
            'destination_warehouse_id' => $destination?->id,
            'created_by' => $actor->id,
            'approved_by' => $actor->id,
            'approved_at' => now(),
        ]);
        $approval = ApprovalRequest::query()->create([
            'branch_id' => $branch->id,
            'permission_code' => 'inventory.transfer.approve',
            'entity_type' => 'stock_adjustment',
            'entity_id' => $adjustment->public_id,
            'amount' => '0.00',
            'status' => ApprovalRequest::STATUS_APPROVED,
            'reason' => 'Persetujuan pengujian mutasi stok',
            'requested_by' => $actor->id,
            'approved_by' => $actor->id,
            'decision_notes' => 'Disetujui untuk pengujian',
            'requested_at' => now(),
            'decided_at' => now(),
        ]);
        $adjustment->update(['approval_request_id' => $approval->id]);
        $adjustment->items()->create([
            'line_number' => 1,
            'product_id' => $product->id,
            'unit_id' => $unit->id,
            'quantity' => $quantity,
            'base_quantity' => $quantity,
            'unit_cost' => null,
            'stock_status' => 'sellable',
        ]);

        return $adjustment->fresh();
    }
}
