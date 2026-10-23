<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Access\DTOs\AuditContext;
use App\Domain\Access\Services\ApprovalService;
use App\Domain\Access\Services\AuditLogger;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductUnit;
use App\Domain\Inventory\Enums\StockAdjustmentStatus;
use App\Domain\Inventory\Enums\StockAdjustmentType;
use App\Domain\Inventory\Enums\StockStatus;
use App\Domain\Inventory\Models\InventoryBatch;
use App\Domain\Inventory\Models\StockAdjustment;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Shared\Services\DocumentNumberService;
use App\Domain\Shared\Services\IdempotencyService;
use App\Models\User;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;

class StockAdjustmentSubmissionService
{
    public function __construct(
        private readonly IdempotencyService $idempotency,
        private readonly DocumentNumberService $documentNumbers,
        private readonly ApprovalService $approvals,
        private readonly AuditLogger $auditLogger,
    ) {}

    /**
     * @param  list<array{product_id:int, unit_id:int, quantity:string, unit_cost:string, stock_status?:string, lot_number?:string|null, manufactured_at?:string|null, expires_at?:string|null, notes?:string|null}>  $items
     */
    public function submitOpeningBalance(
        User $requester,
        Branch $branch,
        Warehouse $warehouse,
        array $items,
        string $reason,
        CarbonInterface $occurredAt,
        string $submissionKey,
    ): StockAdjustment {
        if (! $requester->hasPermission('inventory.opening.create') || ! $requester->canAccessBranch($branch)) {
            throw new AuthorizationException;
        }

        if ($warehouse->branch_id !== $branch->getKey() || ! $warehouse->is_active) {
            throw new DomainException('Gudang tujuan tidak tersedia pada cabang aktif.');
        }

        $payload = [
            'branch' => $branch->public_id,
            'warehouse' => $warehouse->public_id,
            'items' => $items,
            'reason' => $reason,
            'occurred_at' => $occurredAt->toIso8601String(),
        ];

        $result = $this->idempotency->execute(
            'branch:'.$branch->getKey(),
            'inventory.opening.submit',
            $submissionKey,
            $payload,
            function () use ($requester, $branch, $warehouse, $items, $reason, $occurredAt, $submissionKey): array {
                $documentNumber = $this->documentNumbers->next($branch, 'opening_balance', 'OPN', $occurredAt);
                $adjustment = StockAdjustment::query()->create([
                    'document_number' => $documentNumber,
                    'branch_id' => $branch->getKey(),
                    'type' => StockAdjustmentType::OpeningBalance,
                    'status' => StockAdjustmentStatus::PendingApproval,
                    'destination_warehouse_id' => $warehouse->getKey(),
                    'reason' => $reason,
                    'occurred_at' => $occurredAt,
                    'submission_key' => $submissionKey,
                    'created_by' => $requester->getKey(),
                    'submitted_by' => $requester->getKey(),
                    'submitted_at' => now(),
                ]);

                $approvalAmount = '0.000000';
                foreach ($items as $index => $itemData) {
                    $product = Product::query()->with('units')->findOrFail($itemData['product_id']);
                    $batch = $this->resolveBatch($branch, $product, $itemData, $requester);
                    $baseQuantity = $this->toBaseQuantity($product, $itemData['unit_id'], $itemData['quantity']);
                    $unitCost = InventoryDecimal::cost($itemData['unit_cost']);
                    $approvalAmount = bcadd($approvalAmount, InventoryDecimal::value($baseQuantity, $unitCost), 6);

                    $adjustment->items()->create([
                        'line_number' => $index + 1,
                        'product_id' => $product->getKey(),
                        'unit_id' => $itemData['unit_id'],
                        'inventory_batch_id' => $batch?->getKey(),
                        'quantity' => InventoryDecimal::quantity($itemData['quantity']),
                        'base_quantity' => $baseQuantity,
                        'unit_cost' => $unitCost,
                        'stock_status' => $itemData['stock_status'] ?? StockStatus::Sellable->value,
                        'notes' => $itemData['notes'] ?? null,
                    ]);
                }

                $approval = $this->approvals->request(
                    $requester,
                    $branch,
                    'inventory.opening.approve',
                    'stock_adjustment',
                    $adjustment->public_id,
                    bcround($approvalAmount, 2),
                    $reason,
                    'opening:'.$submissionKey,
                );
                $adjustment->update(['approval_request_id' => $approval->getKey()]);

                $this->auditLogger->record(
                    new AuditContext($requester, $branch, $reason),
                    'inventory.opening.submitted',
                    'stock_adjustment',
                    $adjustment->public_id,
                    after: ['document_number' => $documentNumber, 'status' => StockAdjustmentStatus::PendingApproval->value],
                );

                return ['adjustment_id' => $adjustment->public_id];
            },
            $requester,
            201,
        );

        return StockAdjustment::query()->where('public_id', $result->payload['adjustment_id'])->firstOrFail();
    }

    /** @param array<string, mixed> $itemData */
    private function resolveBatch(Branch $branch, Product $product, array $itemData, User $requester): ?InventoryBatch
    {
        if (! $product->track_batch) {
            return null;
        }

        $lotNumber = trim((string) ($itemData['lot_number'] ?? ''));
        if ($lotNumber === '') {
            throw new DomainException("Nomor batch wajib untuk {$product->name}.");
        }

        if ($product->track_expiry && empty($itemData['expires_at'])) {
            throw new DomainException("Tanggal kedaluwarsa wajib untuk {$product->name}.");
        }

        return InventoryBatch::query()->firstOrCreate(
            ['branch_id' => $branch->getKey(), 'product_id' => $product->getKey(), 'lot_number' => $lotNumber],
            [
                'manufactured_at' => $itemData['manufactured_at'] ?? null,
                'expires_at' => $itemData['expires_at'] ?? null,
                'is_active' => true,
                'created_by' => $requester->getKey(),
            ],
        );
    }

    private function toBaseQuantity(Product $product, int $unitId, string $quantity): string
    {
        $factor = $unitId === $product->base_unit_id
            ? '1.000000'
            : ProductUnit::query()
                ->whereBelongsTo($product)
                ->where('unit_id', $unitId)
                ->where('is_active', true)
                ->value('conversion_factor');

        if ($factor === null) {
            throw new DomainException("Satuan tidak valid untuk {$product->name}.");
        }

        $baseQuantity = bcmul($quantity, (string) $factor, 3);
        if (bccomp($baseQuantity, '0', 3) <= 0) {
            throw new DomainException('Kuantitas harus lebih dari nol.');
        }

        return $baseQuantity;
    }
}
