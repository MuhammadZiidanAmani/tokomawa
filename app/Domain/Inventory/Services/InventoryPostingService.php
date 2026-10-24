<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Access\DTOs\AuditContext;
use App\Domain\Access\Models\ApprovalRequest;
use App\Domain\Access\Services\AuditLogger;
use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Enums\StockAdjustmentStatus;
use App\Domain\Inventory\Enums\StockAdjustmentType;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\InventoryBatch;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Inventory\Models\StockAdjustment;
use App\Domain\Inventory\Models\StockAdjustmentItem;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Shared\DTOs\IdempotencyResult;
use App\Domain\Shared\Services\IdempotencyService;
use App\Domain\Shared\Services\OutboxRecorder;
use App\Models\User;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use LogicException;

class InventoryPostingService
{
    public function __construct(
        private readonly IdempotencyService $idempotency,
        private readonly AuditLogger $auditLogger,
        private readonly OutboxRecorder $outbox,
    ) {}

    public function post(StockAdjustment $adjustment, User $actor, string $idempotencyKey): IdempotencyResult
    {
        $branch = $adjustment->branch()->firstOrFail();

        if (! $actor->canAccessBranch($branch)) {
            throw new AuthorizationException;
        }

        return $this->idempotency->execute(
            'branch:'.$branch->getKey(),
            'inventory.adjustment.post',
            $idempotencyKey,
            ['adjustment_id' => $adjustment->public_id],
            function () use ($adjustment, $actor, $branch, $idempotencyKey): array {
                $locked = StockAdjustment::query()
                    ->with(['items.product', 'items.batch', 'approvalRequest'])
                    ->lockForUpdate()
                    ->findOrFail($adjustment->getKey());

                if ($locked->status !== StockAdjustmentStatus::Approved) {
                    throw new DomainException('Dokumen stok belum disetujui atau sudah diposting.');
                }

                if ($locked->approvalRequest?->status !== ApprovalRequest::STATUS_APPROVED) {
                    throw new DomainException('Persetujuan dokumen stok belum sah.');
                }

                $requiredPermission = $locked->type === StockAdjustmentType::OpeningBalance
                    ? 'inventory.opening.approve'
                    : 'inventory.transfer.approve';
                if (! $actor->hasPermission($requiredPermission)) {
                    throw new AuthorizationException;
                }

                $items = $locked->items->sortBy(fn (StockAdjustmentItem $item): string => sprintf('%020d-%020d', $item->product_id, $item->id));
                foreach ($items as $item) {
                    $this->postItem($locked, $item, $actor, $idempotencyKey);
                }

                $locked->update([
                    'status' => StockAdjustmentStatus::Posted,
                    'posted_by' => $actor->getKey(),
                    'posted_at' => now(),
                ]);

                $this->auditLogger->record(
                    new AuditContext($actor, $branch, $locked->reason),
                    'inventory.adjustment.posted',
                    'stock_adjustment',
                    $locked->public_id,
                    before: ['status' => StockAdjustmentStatus::Approved->value],
                    after: ['status' => StockAdjustmentStatus::Posted->value, 'document_number' => $locked->document_number],
                );
                $this->outbox->record(
                    $branch,
                    'inventory.adjustment.posted',
                    'stock_adjustment',
                    $locked->public_id,
                    ['document_number' => $locked->document_number, 'type' => $locked->type->value],
                );

                return ['adjustment_id' => $locked->public_id, 'document_number' => $locked->document_number];
            },
            $actor,
        );
    }

    public function receive(
        Branch $branch,
        Warehouse $warehouse,
        Product $product,
        ?InventoryBatch $batch,
        string $baseQuantity,
        string $baseUnitCost,
        string $sourceId,
        string $sourceLineId,
        CarbonInterface $occurredAt,
        User $actor,
        string $idempotencyKey,
    ): InventoryMovement {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Posting penerimaan inventory wajib berada di dalam transaksi database.');
        }

        if ($warehouse->branch_id !== $branch->getKey()) {
            throw new DomainException('Gudang penerimaan tidak berada pada cabang yang sama.');
        }

        if ($batch !== null && ($batch->branch_id !== $branch->getKey() || $batch->product_id !== $product->getKey())) {
            throw new DomainException('Batch penerimaan tidak sesuai dengan cabang atau barang.');
        }

        $quantity = InventoryDecimal::quantity($baseQuantity);
        if (bccomp($quantity, '0', 3) <= 0) {
            throw new DomainException('Kuantitas penerimaan harus lebih dari nol.');
        }

        $unitCost = InventoryDecimal::cost($baseUnitCost);
        $balance = $this->lockBalanceScope(
            $branch->getKey(),
            $warehouse->getKey(),
            $product->getKey(),
            $batch,
            'sellable',
        );
        $newQuantity = bcadd($balance->qty_on_hand, $quantity, 3);
        $valueDelta = InventoryDecimal::value($quantity, $unitCost);
        $newValue = bcadd($balance->inventory_value, $valueDelta, 6);
        $newAverage = InventoryDecimal::averageFromValue($newValue, $newQuantity);

        $balance->update([
            'qty_on_hand' => $newQuantity,
            'avg_unit_cost' => $newAverage,
            'inventory_value' => $newValue,
            'version' => $balance->version + 1,
        ]);

        return InventoryMovement::query()->create([
            'branch_id' => $branch->getKey(),
            'warehouse_id' => $warehouse->getKey(),
            'product_id' => $product->getKey(),
            'inventory_batch_id' => $batch?->getKey(),
            'batch_scope' => $balance->batch_scope,
            'stock_status' => 'sellable',
            'movement_type' => 'goods_receipt',
            'direction' => 'in',
            'qty_delta' => $quantity,
            'qty_after' => $newQuantity,
            'unit_cost_snapshot' => $unitCost,
            'value_delta' => $valueDelta,
            'source_type' => 'goods_receipt',
            'source_id' => $sourceId,
            'source_line_id' => $sourceLineId,
            'source_key' => "goods-receipt:{$sourceLineId}:in:{$warehouse->getKey()}",
            'idempotency_key' => $idempotencyKey,
            'occurred_at' => $occurredAt,
            'posted_at' => now(),
            'created_by' => $actor->getKey(),
            'created_at' => now(),
        ]);
    }

    public function issueSale(
        Branch $branch,
        Warehouse $warehouse,
        Product $product,
        ?InventoryBatch $batch,
        string $baseQuantity,
        string $sourceId,
        string $sourceLineId,
        CarbonInterface $occurredAt,
        User $actor,
        string $idempotencyKey,
    ): InventoryMovement {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Posting penjualan inventory wajib berada di dalam transaksi database.');
        }
        if ($warehouse->branch_id !== $branch->getKey()) {
            throw new DomainException('Gudang penjualan tidak berada pada cabang yang sama.');
        }
        if ($batch !== null && ($batch->branch_id !== $branch->getKey() || $batch->product_id !== $product->getKey())) {
            throw new DomainException('Batch penjualan tidak sesuai dengan cabang atau barang.');
        }

        $quantity = InventoryDecimal::quantity($baseQuantity);
        if (bccomp($quantity, '0', 3) <= 0) {
            throw new DomainException('Kuantitas penjualan harus lebih dari nol.');
        }

        $balance = $this->lockBalanceScope(
            $branch->getKey(),
            $warehouse->getKey(),
            $product->getKey(),
            $batch,
            'sellable',
        );
        $available = bcsub($balance->qty_on_hand, $balance->qty_reserved, 3);
        if (bccomp($available, $quantity, 3) < 0) {
            throw new DomainException("Stok tersedia {$available}; permintaan {$quantity}.");
        }

        $unitCost = InventoryDecimal::cost($balance->avg_unit_cost);
        $newQuantity = bcsub($balance->qty_on_hand, $quantity, 3);
        $valueMagnitude = InventoryDecimal::value($quantity, $unitCost);
        $valueDelta = '-'.$valueMagnitude;
        $newValue = bccomp($newQuantity, '0', 3) === 0
            ? '0.000000'
            : bcadd($balance->inventory_value, $valueDelta, 6);
        $newAverage = InventoryDecimal::averageFromValue($newValue, $newQuantity);
        $balance->update([
            'qty_on_hand' => $newQuantity,
            'avg_unit_cost' => $newAverage,
            'inventory_value' => $newValue,
            'version' => $balance->version + 1,
        ]);

        return InventoryMovement::query()->create([
            'branch_id' => $branch->getKey(),
            'warehouse_id' => $warehouse->getKey(),
            'product_id' => $product->getKey(),
            'inventory_batch_id' => $batch?->getKey(),
            'batch_scope' => $balance->batch_scope,
            'stock_status' => 'sellable',
            'movement_type' => 'sale',
            'direction' => 'out',
            'qty_delta' => '-'.$quantity,
            'qty_after' => $newQuantity,
            'unit_cost_snapshot' => $unitCost,
            'value_delta' => $valueDelta,
            'source_type' => 'sale',
            'source_id' => $sourceId,
            'source_line_id' => $sourceLineId,
            'source_key' => "sale:{$sourceLineId}:out:{$warehouse->getKey()}",
            'idempotency_key' => $idempotencyKey,
            'occurred_at' => $occurredAt,
            'posted_at' => now(),
            'created_by' => $actor->getKey(),
            'created_at' => now(),
        ]);
    }

    private function postItem(
        StockAdjustment $adjustment,
        StockAdjustmentItem $item,
        User $actor,
        string $idempotencyKey,
    ): void {
        $warehouseIds = array_values(array_filter([
            $adjustment->source_warehouse_id,
            $adjustment->destination_warehouse_id,
        ]));
        sort($warehouseIds);

        /** @var array<int, InventoryBalance> $balances */
        $balances = [];
        foreach ($warehouseIds as $warehouseId) {
            $warehouse = Warehouse::query()->findOrFail($warehouseId);
            if ($warehouse->branch_id !== $adjustment->branch_id) {
                throw new DomainException('Gudang dokumen tidak berada pada cabang yang sama.');
            }
            $balances[$warehouseId] = $this->lockBalance($adjustment, $item, $warehouseId);
        }

        match ($adjustment->type) {
            StockAdjustmentType::OpeningBalance, StockAdjustmentType::Increase => $this->increase(
                $adjustment,
                $item,
                $balances[$adjustment->destination_warehouse_id],
                InventoryDecimal::cost((string) $item->unit_cost),
                $actor,
                $idempotencyKey,
            ),
            StockAdjustmentType::Decrease => $this->decrease(
                $adjustment,
                $item,
                $balances[$adjustment->source_warehouse_id],
                $actor,
                $idempotencyKey,
            ),
            StockAdjustmentType::WarehouseTransfer => $this->transfer(
                $adjustment,
                $item,
                $balances[$adjustment->source_warehouse_id],
                $balances[$adjustment->destination_warehouse_id],
                $actor,
                $idempotencyKey,
            ),
        };
    }

    private function lockBalance(StockAdjustment $adjustment, StockAdjustmentItem $item, int $warehouseId): InventoryBalance
    {
        return $this->lockBalanceScope(
            $adjustment->branch_id,
            $warehouseId,
            $item->product_id,
            $item->batch,
            $item->stock_status,
        );
    }

    private function lockBalanceScope(
        int $branchId,
        int $warehouseId,
        int $productId,
        ?InventoryBatch $batch,
        string $stockStatus,
    ): InventoryBalance {
        $batchScope = $batch?->public_id ?? 'NO-BATCH';
        $scope = [
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'batch_scope' => $batchScope,
            'stock_status' => $stockStatus,
        ];

        DB::table('inventory_balances')->insertOrIgnore([
            ...$scope,
            'branch_id' => $branchId,
            'inventory_batch_id' => $batch?->getKey(),
            'qty_on_hand' => '0.000',
            'qty_reserved' => '0.000',
            'avg_unit_cost' => '0.000000',
            'inventory_value' => '0.000000',
            'version' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return InventoryBalance::query()->where($scope)->lockForUpdate()->firstOrFail();
    }

    private function increase(
        StockAdjustment $adjustment,
        StockAdjustmentItem $item,
        InventoryBalance $balance,
        string $unitCost,
        User $actor,
        string $idempotencyKey,
    ): void {
        $quantity = InventoryDecimal::quantity($item->base_quantity);
        $newQuantity = bcadd($balance->qty_on_hand, $quantity, 3);
        $valueDelta = InventoryDecimal::value($quantity, $unitCost);
        $newValue = bcadd($balance->inventory_value, $valueDelta, 6);
        $newAverage = InventoryDecimal::averageFromValue($newValue, $newQuantity);

        $balance->update([
            'qty_on_hand' => $newQuantity,
            'avg_unit_cost' => $newAverage,
            'inventory_value' => $newValue,
            'version' => $balance->version + 1,
        ]);
        $this->recordMovement($adjustment, $item, $balance, 'in', $quantity, $newQuantity, $unitCost, $valueDelta, $actor, $idempotencyKey);
    }

    private function decrease(
        StockAdjustment $adjustment,
        StockAdjustmentItem $item,
        InventoryBalance $balance,
        User $actor,
        string $idempotencyKey,
    ): string {
        $quantity = InventoryDecimal::quantity($item->base_quantity);
        $available = bcsub($balance->qty_on_hand, $balance->qty_reserved, 3);
        if (bccomp($available, $quantity, 3) < 0) {
            throw new DomainException("Stok tersedia {$available}; permintaan {$quantity}.");
        }

        $unitCost = InventoryDecimal::cost($balance->avg_unit_cost);
        $newQuantity = bcsub($balance->qty_on_hand, $quantity, 3);
        $valueDelta = '-'.InventoryDecimal::value($quantity, $unitCost);
        $newValue = bccomp($newQuantity, '0', 3) === 0
            ? '0.000000'
            : bcadd($balance->inventory_value, $valueDelta, 6);
        $newAverage = InventoryDecimal::averageFromValue($newValue, $newQuantity);

        $balance->update([
            'qty_on_hand' => $newQuantity,
            'avg_unit_cost' => $newAverage,
            'inventory_value' => $newValue,
            'version' => $balance->version + 1,
        ]);
        $this->recordMovement($adjustment, $item, $balance, 'out', '-'.$quantity, $newQuantity, $unitCost, $valueDelta, $actor, $idempotencyKey);

        return $unitCost;
    }

    private function transfer(
        StockAdjustment $adjustment,
        StockAdjustmentItem $item,
        InventoryBalance $source,
        InventoryBalance $destination,
        User $actor,
        string $idempotencyKey,
    ): void {
        if ($source->warehouse_id === $destination->warehouse_id) {
            throw new DomainException('Gudang asal dan tujuan harus berbeda.');
        }

        $unitCost = $this->decrease($adjustment, $item, $source, $actor, $idempotencyKey);
        $this->increase($adjustment, $item, $destination, $unitCost, $actor, $idempotencyKey);
    }

    private function recordMovement(
        StockAdjustment $adjustment,
        StockAdjustmentItem $item,
        InventoryBalance $balance,
        string $direction,
        string $quantityDelta,
        string $quantityAfter,
        string $unitCost,
        string $valueDelta,
        User $actor,
        string $idempotencyKey,
    ): void {
        InventoryMovement::query()->create([
            'branch_id' => $adjustment->branch_id,
            'warehouse_id' => $balance->warehouse_id,
            'product_id' => $item->product_id,
            'inventory_batch_id' => $item->inventory_batch_id,
            'batch_scope' => $balance->batch_scope,
            'stock_status' => $item->stock_status,
            'movement_type' => $adjustment->type->value,
            'direction' => $direction,
            'qty_delta' => $quantityDelta,
            'qty_after' => $quantityAfter,
            'unit_cost_snapshot' => $unitCost,
            'value_delta' => $valueDelta,
            'source_type' => 'stock_adjustment',
            'source_id' => $adjustment->public_id,
            'source_line_id' => $item->public_id,
            'source_key' => "stock-adjustment:{$item->public_id}:{$direction}:{$balance->warehouse_id}",
            'idempotency_key' => $idempotencyKey,
            'occurred_at' => $adjustment->occurred_at,
            'posted_at' => now(),
            'created_by' => $actor->getKey(),
            'created_at' => now(),
        ]);
    }
}
