<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Access\DTOs\AuditContext;
use App\Domain\Access\Models\ApprovalRequest;
use App\Domain\Access\Services\AuditLogger;
use App\Domain\Inventory\Enums\StockAdjustmentStatus;
use App\Domain\Inventory\Enums\StockAdjustmentType;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Inventory\Models\StockAdjustment;
use App\Domain\Inventory\Models\StockAdjustmentItem;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Shared\DTOs\IdempotencyResult;
use App\Domain\Shared\Services\IdempotencyService;
use App\Domain\Shared\Services\OutboxRecorder;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

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
        $batchScope = $item->batch?->public_id ?? 'NO-BATCH';
        $scope = [
            'warehouse_id' => $warehouseId,
            'product_id' => $item->product_id,
            'batch_scope' => $batchScope,
            'stock_status' => $item->stock_status,
        ];

        DB::table('inventory_balances')->insertOrIgnore([
            ...$scope,
            'branch_id' => $adjustment->branch_id,
            'inventory_batch_id' => $item->inventory_batch_id,
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
        $newAverage = InventoryDecimal::weightedAverage($balance->qty_on_hand, $balance->avg_unit_cost, $quantity, $unitCost);
        $newValue = InventoryDecimal::value($newQuantity, $newAverage);
        $valueDelta = InventoryDecimal::value($quantity, $unitCost);

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
        $newAverage = bccomp($newQuantity, '0', 3) === 0 ? '0.000000' : $unitCost;
        $newValue = InventoryDecimal::value($newQuantity, $newAverage);
        $valueDelta = '-'.InventoryDecimal::value($quantity, $unitCost);

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
