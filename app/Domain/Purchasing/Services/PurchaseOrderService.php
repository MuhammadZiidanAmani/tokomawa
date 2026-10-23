<?php

namespace App\Domain\Purchasing\Services;

use App\Domain\Access\DTOs\AuditContext;
use App\Domain\Access\Services\AuditLogger;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductUnit;
use App\Domain\Catalog\Models\Unit;
use App\Domain\Organization\Models\Branch;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Shared\Services\DocumentNumberService;
use App\Domain\Shared\Services\IdempotencyService;
use App\Domain\Suppliers\Models\Supplier;
use App\Domain\Suppliers\Models\SupplierProduct;
use App\Models\User;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;

class PurchaseOrderService
{
    public function __construct(
        private readonly PurchaseOrderCalculator $calculator,
        private readonly DocumentNumberService $documentNumbers,
        private readonly IdempotencyService $idempotency,
        private readonly AuditLogger $auditLogger,
    ) {}

    /** @param array<string, mixed> $data */
    public function createDraft(User $actor, Branch $branch, array $data): PurchaseOrder
    {
        if (! $actor->hasPermission('purchase_orders.create') || ! $actor->canAccessBranch($branch)) {
            throw new AuthorizationException;
        }

        $result = $this->idempotency->execute(
            'branch:'.$branch->getKey(),
            'purchase_order.create',
            $data['idempotency_key'],
            $data,
            function () use ($actor, $branch, $data): array {
                $supplier = Supplier::query()->lockForUpdate()->findOrFail($data['supplier_id']);
                $this->ensureSupplierIsActive($supplier);
                [$items, $total] = $this->buildItems($supplier, $data['items']);
                $effectiveAt = CarbonImmutable::parse($data['order_date'], $branch->timezone)->startOfDay()->utc();
                $purchaseOrder = PurchaseOrder::query()->create([
                    'branch_id' => $branch->getKey(),
                    'supplier_id' => $supplier->getKey(),
                    'document_number' => $this->documentNumbers->next($branch, 'purchase_order', 'PO', $effectiveAt),
                    'status' => PurchaseOrderStatus::Draft,
                    'order_date' => $data['order_date'],
                    'expected_at' => $data['expected_at'] ?? null,
                    'currency' => 'IDR',
                    ...$this->supplierSnapshot($supplier),
                    'subtotal' => $total,
                    'grand_total' => $total,
                    'notes' => $data['notes'] ?? null,
                    'creation_key' => $data['idempotency_key'],
                    'created_by' => $actor->getKey(),
                ]);
                $this->replaceItems($purchaseOrder, $items);
                $this->auditLogger->record(
                    new AuditContext($actor, $branch, 'Membuat draft Purchase Order.'),
                    'purchase_order.created',
                    'purchase_order',
                    $purchaseOrder->public_id,
                    after: $this->auditSnapshot($purchaseOrder),
                );

                return ['purchase_order_id' => $purchaseOrder->public_id];
            },
            $actor,
            201,
        );

        return PurchaseOrder::query()->where('public_id', $result->payload['purchase_order_id'])->firstOrFail();
    }

    /** @param array<string, mixed> $data */
    public function updateDraft(PurchaseOrder $purchaseOrder, User $actor, Branch $branch, array $data): PurchaseOrder
    {
        if (! $actor->hasPermission('purchase_orders.update') || ! $actor->canAccessBranch($branch)) {
            throw new AuthorizationException;
        }

        $result = $this->idempotency->execute(
            'purchase_order:'.$purchaseOrder->public_id,
            'purchase_order.update',
            $data['idempotency_key'],
            $data,
            function () use ($purchaseOrder, $actor, $branch, $data): array {
                $locked = PurchaseOrder::query()->lockForUpdate()->findOrFail($purchaseOrder->getKey());
                if ($locked->branch_id !== $branch->getKey()) {
                    throw new AuthorizationException;
                }
                if ($locked->status !== PurchaseOrderStatus::Draft) {
                    throw new DomainException('Hanya Purchase Order berstatus draft yang dapat diubah.');
                }

                $before = $this->auditSnapshot($locked);
                $supplier = Supplier::query()->lockForUpdate()->findOrFail($data['supplier_id']);
                $this->ensureSupplierIsActive($supplier);
                [$items, $total] = $this->buildItems($supplier, $data['items']);
                $locked->update([
                    'supplier_id' => $supplier->getKey(),
                    'order_date' => $data['order_date'],
                    'expected_at' => $data['expected_at'] ?? null,
                    ...$this->supplierSnapshot($supplier),
                    'subtotal' => $total,
                    'grand_total' => $total,
                    'notes' => $data['notes'] ?? null,
                    'version' => $locked->version + 1,
                ]);
                $this->replaceItems($locked, $items);
                $this->auditLogger->record(
                    new AuditContext($actor, $branch, 'Memperbarui draft Purchase Order.'),
                    'purchase_order.updated',
                    'purchase_order',
                    $locked->public_id,
                    before: $before,
                    after: $this->auditSnapshot($locked->fresh()),
                );

                return ['purchase_order_id' => $locked->public_id];
            },
            $actor,
        );

        return PurchaseOrder::query()->where('public_id', $result->payload['purchase_order_id'])->firstOrFail();
    }

    /**
     * @param  list<array{product_id:int,unit_id:int,quantity:string,unit_price:string}>  $inputItems
     * @return array{0:list<array<string, mixed>>,1:string}
     */
    private function buildItems(Supplier $supplier, array $inputItems): array
    {
        $productIds = collect($inputItems)->pluck('product_id')->map(fn ($id): int => (int) $id)->sort()->values();
        $products = Product::query()
            ->with(['baseUnit', 'units.unit', 'supplierProducts'])
            ->whereKey($productIds)
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->keyBy('id');

        if ($products->count() !== $productIds->unique()->count()) {
            throw new DomainException('Salah satu barang tidak ditemukan.');
        }

        $items = [];
        $total = '0.00';
        foreach ($inputItems as $index => $input) {
            /** @var Product $product */
            $product = $products->get((int) $input['product_id']);
            if (! $product->is_active) {
                throw new DomainException("Barang {$product->name} sudah tidak aktif.");
            }

            [$unit, $factor] = $this->resolvePurchaseUnit($product, (int) $input['unit_id']);
            $supplierProduct = $product->supplierProducts
                ->first(fn (SupplierProduct $mapping): bool => $mapping->supplier_id === $supplier->getKey() && $mapping->is_active);
            if (config('retail.purchasing.require_supplier_product_mapping') && $supplierProduct === null) {
                throw new DomainException("Barang {$product->name} belum terhubung dengan supplier yang dipilih.");
            }

            $quantity = $this->calculator->quantity((string) $input['quantity']);
            $unitPrice = $this->calculator->money((string) $input['unit_price']);
            $subtotal = $this->calculator->subtotal($quantity, $unitPrice);
            $items[] = [
                'line_number' => $index + 1,
                'product_id' => $product->getKey(),
                'unit_id' => $unit->getKey(),
                'supplier_product_id' => $supplierProduct?->getKey(),
                'product_sku_snapshot' => $product->sku,
                'product_name_snapshot' => $product->name,
                'supplier_sku_snapshot' => $supplierProduct?->supplier_sku,
                'unit_code_snapshot' => $unit->code,
                'unit_name_snapshot' => $unit->name,
                'unit_symbol_snapshot' => $unit->symbol,
                'conversion_factor' => $factor,
                'quantity' => $quantity,
                'base_quantity' => $this->calculator->baseQuantity($quantity, $factor),
                'received_base_quantity' => '0.000',
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
            ];
            $total = bcadd($total, $subtotal, 2);
        }

        return [$items, $total];
    }

    /** @return array{0:Unit,1:string} */
    private function resolvePurchaseUnit(Product $product, int $unitId): array
    {
        if ($product->base_unit_id === $unitId && $product->baseUnit->is_active) {
            return [$product->baseUnit, '1.000000'];
        }

        /** @var ProductUnit|null $productUnit */
        $productUnit = $product->units->first(
            fn (ProductUnit $candidate): bool => $candidate->unit_id === $unitId
                && $candidate->is_active
                && $candidate->is_purchase_unit
                && $candidate->unit->is_active,
        );
        if ($productUnit === null) {
            throw new DomainException("Satuan pembelian tidak valid untuk {$product->name}.");
        }

        return [$productUnit->unit, $this->calculator->conversionFactor($productUnit->conversion_factor)];
    }

    /** @param list<array<string, mixed>> $items */
    private function replaceItems(PurchaseOrder $purchaseOrder, array $items): void
    {
        $purchaseOrder->items()->delete();
        $purchaseOrder->items()->createMany($items);
    }

    private function ensureSupplierIsActive(Supplier $supplier): void
    {
        if (! $supplier->is_active) {
            throw new DomainException('Supplier yang dipilih sudah tidak aktif.');
        }
    }

    /** @return array{supplier_code_snapshot:string,supplier_name_snapshot:string,supplier_address_snapshot:?string} */
    private function supplierSnapshot(Supplier $supplier): array
    {
        return [
            'supplier_code_snapshot' => $supplier->code,
            'supplier_name_snapshot' => $supplier->name,
            'supplier_address_snapshot' => $supplier->address,
        ];
    }

    /** @return array<string, mixed> */
    private function auditSnapshot(PurchaseOrder $purchaseOrder): array
    {
        return $purchaseOrder->only([
            'public_id', 'document_number', 'supplier_id', 'status', 'order_date', 'expected_at',
            'subtotal', 'grand_total', 'version',
        ]);
    }
}
