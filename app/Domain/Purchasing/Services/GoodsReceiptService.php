<?php

namespace App\Domain\Purchasing\Services;

use App\Domain\Access\DTOs\AuditContext;
use App\Domain\Access\Services\AuditLogger;
use App\Domain\Inventory\Models\InventoryBatch;
use App\Domain\Inventory\Services\InventoryPostingService;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Purchasing\Enums\GoodsReceiptStatus;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Models\GoodsReceipt;
use App\Domain\Purchasing\Models\GoodsReceiptItem;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\PurchaseOrderItem;
use App\Domain\Shared\Services\DocumentNumberService;
use App\Domain\Shared\Services\IdempotencyService;
use App\Domain\Shared\Services\OutboxRecorder;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GoodsReceiptService
{
    public function __construct(
        private readonly PurchaseOrderCalculator $calculator,
        private readonly DocumentNumberService $documentNumbers,
        private readonly IdempotencyService $idempotency,
        private readonly InventoryPostingService $inventoryPosting,
        private readonly AuditLogger $auditLogger,
        private readonly OutboxRecorder $outbox,
    ) {}

    /** @param array<string, mixed> $data */
    public function createDraft(User $actor, Branch $branch, array $data): GoodsReceipt
    {
        $this->authorize($actor, $branch, 'goods_receipts.create');

        $result = $this->idempotency->execute(
            'branch:'.$branch->getKey(),
            'goods_receipt.create',
            $data['idempotency_key'],
            $data,
            function () use ($actor, $branch, $data): array {
                $purchaseOrder = $this->lockReceivablePurchaseOrder((int) $data['purchase_order_id'], $branch);
                $warehouse = $this->lockWarehouse((int) $data['warehouse_id'], $branch);
                $receivedAt = CarbonImmutable::parse($data['received_at'], $branch->timezone)->utc();
                $deliveryNote = trim((string) $data['supplier_delivery_note']);
                $normalizedDeliveryNote = $this->normalizeDeliveryNote($deliveryNote);
                $this->ensureDeliveryNoteIsUnique($purchaseOrder->supplier_id, $normalizedDeliveryNote);
                [$items, $totalBaseQuantity, $totalValue] = $this->buildItems($purchaseOrder, $data['items'], $receivedAt);

                $receipt = GoodsReceipt::query()->create([
                    'branch_id' => $branch->getKey(),
                    'warehouse_id' => $warehouse->getKey(),
                    'purchase_order_id' => $purchaseOrder->getKey(),
                    'supplier_id' => $purchaseOrder->supplier_id,
                    'document_number' => $this->documentNumbers->next($branch, 'goods_receipt', 'GR', $receivedAt),
                    'status' => GoodsReceiptStatus::Draft,
                    'supplier_delivery_note' => $deliveryNote,
                    'supplier_delivery_note_normalized' => $normalizedDeliveryNote,
                    'received_at' => $receivedAt,
                    'supplier_code_snapshot' => $purchaseOrder->supplier_code_snapshot,
                    'supplier_name_snapshot' => $purchaseOrder->supplier_name_snapshot,
                    'total_base_quantity' => $totalBaseQuantity,
                    'total_value' => $totalValue,
                    'notes' => $data['notes'] ?? null,
                    'creation_key' => $data['idempotency_key'],
                    'created_by' => $actor->getKey(),
                ]);
                $receipt->items()->createMany($items);
                $this->auditLogger->record(
                    new AuditContext($actor, $branch, 'Membuat draft penerimaan barang.'),
                    'goods_receipt.created',
                    'goods_receipt',
                    $receipt->public_id,
                    after: $this->auditSnapshot($receipt),
                );

                return ['goods_receipt_id' => $receipt->public_id];
            },
            $actor,
            201,
        );

        return GoodsReceipt::query()->where('public_id', $result->payload['goods_receipt_id'])->firstOrFail();
    }

    /** @param array<string, mixed> $data */
    public function updateDraft(GoodsReceipt $receipt, User $actor, Branch $branch, array $data): GoodsReceipt
    {
        $this->authorize($actor, $branch, 'goods_receipts.update');
        $this->ensureBranch($receipt, $branch);

        $result = $this->idempotency->execute(
            'goods_receipt:'.$receipt->public_id,
            'goods_receipt.update',
            $data['idempotency_key'],
            $data,
            function () use ($receipt, $actor, $branch, $data): array {
                $locked = GoodsReceipt::query()->lockForUpdate()->findOrFail($receipt->getKey());
                if ($locked->status !== GoodsReceiptStatus::Draft) {
                    throw new DomainException('Hanya penerimaan berstatus draft yang dapat diubah.');
                }
                if ((int) $data['purchase_order_id'] !== $locked->purchase_order_id) {
                    throw new DomainException('Purchase Order pada draft penerimaan tidak dapat diganti.');
                }

                $purchaseOrder = $this->lockReceivablePurchaseOrder($locked->purchase_order_id, $branch);
                $warehouse = $this->lockWarehouse((int) $data['warehouse_id'], $branch);
                $receivedAt = CarbonImmutable::parse($data['received_at'], $branch->timezone)->utc();
                $deliveryNote = trim((string) $data['supplier_delivery_note']);
                $normalizedDeliveryNote = $this->normalizeDeliveryNote($deliveryNote);
                $this->ensureDeliveryNoteIsUnique($purchaseOrder->supplier_id, $normalizedDeliveryNote, $locked);
                [$items, $totalBaseQuantity, $totalValue] = $this->buildItems($purchaseOrder, $data['items'], $receivedAt);
                $before = $this->auditSnapshot($locked);

                $locked->update([
                    'warehouse_id' => $warehouse->getKey(),
                    'supplier_delivery_note' => $deliveryNote,
                    'supplier_delivery_note_normalized' => $normalizedDeliveryNote,
                    'received_at' => $receivedAt,
                    'total_base_quantity' => $totalBaseQuantity,
                    'total_value' => $totalValue,
                    'notes' => $data['notes'] ?? null,
                    'version' => $locked->version + 1,
                ]);
                $locked->items()->delete();
                $locked->items()->createMany($items);
                $this->auditLogger->record(
                    new AuditContext($actor, $branch, 'Memperbarui draft penerimaan barang.'),
                    'goods_receipt.updated',
                    'goods_receipt',
                    $locked->public_id,
                    before: $before,
                    after: $this->auditSnapshot($locked),
                );

                return ['goods_receipt_id' => $locked->public_id];
            },
            $actor,
        );

        return GoodsReceipt::query()->where('public_id', $result->payload['goods_receipt_id'])->firstOrFail();
    }

    public function post(GoodsReceipt $receipt, User $actor, Branch $branch, string $idempotencyKey): GoodsReceipt
    {
        $this->authorize($actor, $branch, 'goods_receipts.post');
        $this->ensureBranch($receipt, $branch);

        $result = $this->idempotency->execute(
            'goods_receipt:'.$receipt->public_id,
            'goods_receipt.post',
            $idempotencyKey,
            ['goods_receipt_id' => $receipt->public_id],
            function () use ($receipt, $actor, $branch, $idempotencyKey): array {
                $locked = GoodsReceipt::query()->lockForUpdate()->findOrFail($receipt->getKey());
                if ($locked->status !== GoodsReceiptStatus::Draft) {
                    throw new DomainException('Penerimaan barang sudah diposting atau dibatalkan.');
                }

                $warehouse = $this->lockWarehouse($locked->warehouse_id, $branch);
                $purchaseOrder = PurchaseOrder::query()->lockForUpdate()->findOrFail($locked->purchase_order_id);
                $this->ensurePurchaseOrderCanReceive($purchaseOrder, $branch);
                $receiptItems = GoodsReceiptItem::query()
                    ->with('product')
                    ->where('goods_receipt_id', $locked->getKey())
                    ->orderBy('product_id')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
                if ($receiptItems->isEmpty()) {
                    throw new DomainException('Penerimaan barang harus memiliki minimal satu item.');
                }

                $purchaseOrderItems = PurchaseOrderItem::query()
                    ->whereIn('id', $receiptItems->pluck('purchase_order_item_id'))
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get()
                    ->keyBy('id');
                $this->validateOutstanding($purchaseOrder, $receiptItems, $purchaseOrderItems);

                foreach ($receiptItems as $receiptItem) {
                    /** @var PurchaseOrderItem $purchaseOrderItem */
                    $purchaseOrderItem = $purchaseOrderItems->get($receiptItem->purchase_order_item_id);
                    $batch = $this->resolveBatch($locked, $receiptItem, $actor);
                    if ($batch !== null) {
                        $receiptItem->update(['inventory_batch_id' => $batch->getKey()]);
                    }

                    $this->inventoryPosting->receive(
                        $branch,
                        $warehouse,
                        $receiptItem->product,
                        $batch,
                        $receiptItem->base_quantity,
                        $receiptItem->base_unit_cost_snapshot,
                        $locked->public_id,
                        $receiptItem->public_id,
                        $locked->received_at,
                        $actor,
                        $idempotencyKey,
                    );
                    $purchaseOrderItem->update([
                        'received_base_quantity' => bcadd($purchaseOrderItem->received_base_quantity, $receiptItem->base_quantity, 3),
                    ]);
                }

                $hasOutstanding = PurchaseOrderItem::query()
                    ->where('purchase_order_id', $purchaseOrder->getKey())
                    ->whereColumn('received_base_quantity', '<', 'base_quantity')
                    ->exists();
                $purchaseOrder->update([
                    'status' => $hasOutstanding ? PurchaseOrderStatus::PartiallyReceived : PurchaseOrderStatus::Completed,
                    'version' => $purchaseOrder->version + 1,
                ]);
                $locked->update([
                    'status' => GoodsReceiptStatus::Posted,
                    'posting_key' => $idempotencyKey,
                    'posted_by' => $actor->getKey(),
                    'posted_at' => now(),
                    'version' => $locked->version + 1,
                ]);
                $this->auditLogger->record(
                    new AuditContext($actor, $branch, 'Memposting penerimaan barang dan menambah stok.'),
                    'goods_receipt.posted',
                    'goods_receipt',
                    $locked->public_id,
                    before: ['status' => GoodsReceiptStatus::Draft->value],
                    after: [
                        'status' => GoodsReceiptStatus::Posted->value,
                        'document_number' => $locked->document_number,
                        'purchase_order_status' => $purchaseOrder->status->value,
                    ],
                );
                $this->outbox->record($branch, 'goods_receipt.posted', 'goods_receipt', $locked->public_id, [
                    'document_number' => $locked->document_number,
                    'purchase_order_id' => $purchaseOrder->public_id,
                    'warehouse_id' => $warehouse->public_id,
                ]);

                return ['goods_receipt_id' => $locked->public_id];
            },
            $actor,
        );

        return GoodsReceipt::query()->where('public_id', $result->payload['goods_receipt_id'])->firstOrFail();
    }

    public function cancel(GoodsReceipt $receipt, User $actor, Branch $branch, string $reason, string $idempotencyKey): GoodsReceipt
    {
        $this->authorize($actor, $branch, 'goods_receipts.cancel');
        $this->ensureBranch($receipt, $branch);

        $result = $this->idempotency->execute(
            'goods_receipt:'.$receipt->public_id,
            'goods_receipt.cancel',
            $idempotencyKey,
            ['reason' => $reason],
            function () use ($receipt, $actor, $branch, $reason): array {
                $locked = GoodsReceipt::query()->lockForUpdate()->findOrFail($receipt->getKey());
                if ($locked->status !== GoodsReceiptStatus::Draft) {
                    throw new DomainException('Hanya draft penerimaan yang dapat dibatalkan.');
                }

                $locked->update([
                    'status' => GoodsReceiptStatus::Cancelled,
                    'cancelled_by' => $actor->getKey(),
                    'cancelled_at' => now(),
                    'cancellation_reason' => $reason,
                    'version' => $locked->version + 1,
                ]);
                $this->auditLogger->record(
                    new AuditContext($actor, $branch, $reason),
                    'goods_receipt.cancelled',
                    'goods_receipt',
                    $locked->public_id,
                    before: ['status' => GoodsReceiptStatus::Draft->value],
                    after: ['status' => GoodsReceiptStatus::Cancelled->value],
                );
                $this->outbox->record($branch, 'goods_receipt.cancelled', 'goods_receipt', $locked->public_id, [
                    'document_number' => $locked->document_number,
                ]);

                return ['goods_receipt_id' => $locked->public_id];
            },
            $actor,
        );

        return GoodsReceipt::query()->where('public_id', $result->payload['goods_receipt_id'])->firstOrFail();
    }

    /**
     * @param  list<array<string, mixed>>  $inputItems
     * @return array{0:list<array<string, mixed>>,1:string,2:string}
     */
    private function buildItems(PurchaseOrder $purchaseOrder, array $inputItems, CarbonImmutable $receivedAt): array
    {
        $itemIds = collect($inputItems)->pluck('purchase_order_item_id')->map(fn ($id): int => (int) $id);
        if ($itemIds->unique()->count() !== $itemIds->count()) {
            throw new DomainException('Item Purchase Order tidak boleh diterima lebih dari satu baris pada dokumen yang sama.');
        }

        $purchaseOrderItems = PurchaseOrderItem::query()
            ->with('product')
            ->where('purchase_order_id', $purchaseOrder->getKey())
            ->whereIn('id', $itemIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');
        if ($purchaseOrderItems->count() !== $itemIds->count()) {
            throw new DomainException('Salah satu item tidak berasal dari Purchase Order yang dipilih.');
        }

        $items = [];
        $totalBaseQuantity = '0.000';
        $totalValue = '0.00';
        foreach ($inputItems as $index => $input) {
            /** @var PurchaseOrderItem $purchaseOrderItem */
            $purchaseOrderItem = $purchaseOrderItems->get((int) $input['purchase_order_item_id']);
            $quantity = $this->calculator->quantity((string) $input['quantity']);
            $baseQuantity = $this->calculator->baseQuantity($quantity, $purchaseOrderItem->conversion_factor);
            if (bccomp($baseQuantity, $purchaseOrderItem->outstandingBaseQuantity(), 3) > 0) {
                throw new DomainException("Kuantitas {$purchaseOrderItem->product_name_snapshot} melebihi outstanding PO.");
            }

            $lotNumber = isset($input['lot_number']) && trim((string) $input['lot_number']) !== ''
                ? Str::upper(trim((string) $input['lot_number']))
                : null;
            $manufacturedAt = $input['manufactured_at'] ?? null;
            $expiresAt = $input['expires_at'] ?? null;
            $this->validateBatchInput(
                $purchaseOrderItem->product->track_batch,
                $purchaseOrderItem->product->track_expiry,
                $lotNumber,
                $manufacturedAt,
                $expiresAt,
                $receivedAt,
                $purchaseOrderItem->product_name_snapshot,
            );
            $baseUnitCost = bcdiv($purchaseOrderItem->unit_price, $purchaseOrderItem->conversion_factor, 6);
            $lineTotal = $this->calculator->subtotal($quantity, $purchaseOrderItem->unit_price);
            $items[] = [
                'line_number' => $index + 1,
                'purchase_order_item_id' => $purchaseOrderItem->getKey(),
                'product_id' => $purchaseOrderItem->product_id,
                'unit_id' => $purchaseOrderItem->unit_id,
                'product_sku_snapshot' => $purchaseOrderItem->product_sku_snapshot,
                'product_name_snapshot' => $purchaseOrderItem->product_name_snapshot,
                'unit_code_snapshot' => $purchaseOrderItem->unit_code_snapshot,
                'unit_name_snapshot' => $purchaseOrderItem->unit_name_snapshot,
                'unit_symbol_snapshot' => $purchaseOrderItem->unit_symbol_snapshot,
                'conversion_factor' => $purchaseOrderItem->conversion_factor,
                'quantity' => $quantity,
                'base_quantity' => $baseQuantity,
                'unit_price_snapshot' => $purchaseOrderItem->unit_price,
                'base_unit_cost_snapshot' => $baseUnitCost,
                'line_total' => $lineTotal,
                'track_batch_snapshot' => $purchaseOrderItem->product->track_batch,
                'track_expiry_snapshot' => $purchaseOrderItem->product->track_expiry,
                'lot_number_snapshot' => $lotNumber,
                'manufactured_at_snapshot' => $manufacturedAt,
                'expires_at_snapshot' => $expiresAt,
                'stock_status' => 'sellable',
                'notes' => $input['notes'] ?? null,
            ];
            $totalBaseQuantity = bcadd($totalBaseQuantity, $baseQuantity, 3);
            $totalValue = bcadd($totalValue, $lineTotal, 2);
        }

        return [$items, $totalBaseQuantity, $totalValue];
    }

    private function validateBatchInput(
        bool $trackBatch,
        bool $trackExpiry,
        ?string $lotNumber,
        ?string $manufacturedAt,
        ?string $expiresAt,
        CarbonImmutable $receivedAt,
        string $productName,
    ): void {
        if (! $trackBatch && ($lotNumber !== null || $manufacturedAt !== null || $expiresAt !== null)) {
            throw new DomainException("Barang {$productName} tidak dikonfigurasi untuk pelacakan batch.");
        }
        if ($trackBatch && $lotNumber === null) {
            throw new DomainException("Nomor batch wajib diisi untuk {$productName}.");
        }
        if ($trackExpiry && $expiresAt === null) {
            throw new DomainException("Tanggal kedaluwarsa wajib diisi untuk {$productName}.");
        }
        if ($manufacturedAt !== null && $expiresAt !== null && $expiresAt < $manufacturedAt) {
            throw new DomainException("Tanggal kedaluwarsa {$productName} harus setelah tanggal produksi.");
        }
        if ($expiresAt !== null && $expiresAt < $receivedAt->toDateString()) {
            throw new DomainException("Batch {$productName} sudah kedaluwarsa pada tanggal penerimaan.");
        }
    }

    /** @param Collection<int, GoodsReceiptItem> $receiptItems @param Collection<int, PurchaseOrderItem> $purchaseOrderItems */
    private function validateOutstanding(PurchaseOrder $purchaseOrder, Collection $receiptItems, Collection $purchaseOrderItems): void
    {
        if ($purchaseOrderItems->count() !== $receiptItems->count()) {
            throw new DomainException('Item penerimaan tidak cocok dengan Purchase Order.');
        }

        foreach ($receiptItems as $receiptItem) {
            /** @var PurchaseOrderItem|null $purchaseOrderItem */
            $purchaseOrderItem = $purchaseOrderItems->get($receiptItem->purchase_order_item_id);
            if ($purchaseOrderItem === null || $purchaseOrderItem->purchase_order_id !== $purchaseOrder->getKey()) {
                throw new DomainException('Item penerimaan tidak berasal dari Purchase Order yang dipilih.');
            }
            if (bccomp($receiptItem->base_quantity, $purchaseOrderItem->outstandingBaseQuantity(), 3) > 0) {
                throw new DomainException("Kuantitas {$receiptItem->product_name_snapshot} melebihi outstanding PO.");
            }
        }
    }

    private function resolveBatch(GoodsReceipt $receipt, GoodsReceiptItem $item, User $actor): ?InventoryBatch
    {
        if (! $item->track_batch_snapshot) {
            return null;
        }

        $scope = [
            'branch_id' => $receipt->branch_id,
            'product_id' => $item->product_id,
            'lot_number' => $item->lot_number_snapshot,
        ];
        DB::table('inventory_batches')->insertOrIgnore([
            ...$scope,
            'public_id' => (string) Str::ulid(),
            'manufactured_at' => $item->manufactured_at_snapshot?->toDateString(),
            'expires_at' => $item->expires_at_snapshot?->toDateString(),
            'is_active' => true,
            'created_by' => $actor->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $batch = InventoryBatch::query()->where($scope)->lockForUpdate()->firstOrFail();
        if (
            $batch->manufactured_at?->toDateString() !== $item->manufactured_at_snapshot?->toDateString()
            || $batch->expires_at?->toDateString() !== $item->expires_at_snapshot?->toDateString()
        ) {
            throw new DomainException("Identitas batch {$item->lot_number_snapshot} tidak konsisten dengan penerimaan sebelumnya.");
        }

        return $batch;
    }

    private function lockReceivablePurchaseOrder(int $purchaseOrderId, Branch $branch): PurchaseOrder
    {
        $purchaseOrder = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrderId);
        $this->ensurePurchaseOrderCanReceive($purchaseOrder, $branch);

        return $purchaseOrder;
    }

    private function ensurePurchaseOrderCanReceive(PurchaseOrder $purchaseOrder, Branch $branch): void
    {
        if ($purchaseOrder->branch_id !== $branch->getKey()) {
            throw new AuthorizationException;
        }
        if (! in_array($purchaseOrder->status, [
            PurchaseOrderStatus::Approved,
            PurchaseOrderStatus::Sent,
            PurchaseOrderStatus::PartiallyReceived,
        ], true)) {
            throw new DomainException('Purchase Order belum dapat diterima atau sudah selesai.');
        }
    }

    private function lockWarehouse(int $warehouseId, Branch $branch): Warehouse
    {
        $warehouse = Warehouse::query()->lockForUpdate()->findOrFail($warehouseId);
        if ($warehouse->branch_id !== $branch->getKey()) {
            throw new AuthorizationException;
        }
        if (! $warehouse->is_active) {
            throw new DomainException('Gudang penerimaan sudah tidak aktif.');
        }

        return $warehouse;
    }

    private function normalizeDeliveryNote(string $deliveryNote): string
    {
        return Str::upper((string) preg_replace('/\s+/', '', $deliveryNote));
    }

    private function ensureDeliveryNoteIsUnique(int $supplierId, string $normalized, ?GoodsReceipt $except = null): void
    {
        $exists = GoodsReceipt::query()
            ->where('supplier_id', $supplierId)
            ->where('supplier_delivery_note_normalized', $normalized)
            ->when($except !== null, fn ($query) => $query->whereKeyNot($except->getKey()))
            ->exists();
        if ($exists) {
            throw new DomainException('Nomor surat jalan supplier sudah pernah digunakan.');
        }
    }

    private function authorize(User $actor, Branch $branch, string $permission): void
    {
        if (! $actor->hasPermission($permission) || ! $actor->canAccessBranch($branch)) {
            throw new AuthorizationException;
        }
    }

    private function ensureBranch(GoodsReceipt $receipt, Branch $branch): void
    {
        if ($receipt->branch_id !== $branch->getKey()) {
            throw new AuthorizationException;
        }
    }

    /** @return array<string, mixed> */
    private function auditSnapshot(GoodsReceipt $receipt): array
    {
        return $receipt->only([
            'public_id', 'document_number', 'purchase_order_id', 'warehouse_id', 'supplier_id', 'status',
            'supplier_delivery_note', 'received_at', 'total_base_quantity', 'total_value', 'version',
        ]);
    }
}
