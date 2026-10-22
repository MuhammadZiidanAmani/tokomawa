<?php

namespace App\Domain\Suppliers\Services;

use App\Domain\Access\DTOs\AuditContext;
use App\Domain\Access\Services\AuditLogger;
use App\Domain\Catalog\Models\Product;
use App\Domain\Suppliers\Models\Supplier;
use App\Domain\Suppliers\Models\SupplierProduct;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SupplierManager
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $data */
    public function saveSupplier(?Supplier $supplier, array $data, AuditContext $context): Supplier
    {
        return DB::transaction(function () use ($supplier, $data, $context): Supplier {
            $supplier = $supplier === null
                ? new Supplier
                : Supplier::query()->lockForUpdate()->findOrFail($supplier->getKey());
            $created = ! $supplier->exists;
            $before = $supplier->exists ? $supplier->attributesToArray() : null;
            $supplier->fill(Arr::except($data, ['reason']));
            if (! $supplier->exists) {
                $supplier->created_by = $context->actor?->getKey();
            }
            $supplier->save();

            $this->auditLogger->record(
                $context,
                $created ? 'supplier.created' : 'supplier.updated',
                'supplier',
                $supplier->public_id,
                before: $before,
                after: $supplier->attributesToArray(),
            );

            return $supplier;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function saveSupplierProduct(Supplier $supplier, array $data, AuditContext $context): SupplierProduct
    {
        return DB::transaction(function () use ($supplier, $data, $context): SupplierProduct {
            $product = Product::query()->lockForUpdate()->findOrFail($data['product_id']);

            if ((bool) $data['is_primary']) {
                $product->supplierProducts()->update(['is_primary' => false]);
                $product->update(['default_supplier_id' => $supplier->getKey()]);
            }

            $supplierProduct = SupplierProduct::query()->updateOrCreate(
                ['supplier_id' => $supplier->getKey(), 'product_id' => $product->getKey()],
                [
                    ...Arr::only($data, [
                        'purchase_unit_id', 'supplier_sku', 'last_purchase_price', 'contract_price',
                        'minimum_order_qty', 'lead_time_days', 'priority', 'is_primary', 'is_active',
                    ]),
                    'created_by' => $context->actor?->getKey(),
                ],
            );

            if (! $supplierProduct->is_active && $product->default_supplier_id === $supplier->getKey()) {
                $product->update(['default_supplier_id' => null]);
            }

            $this->auditLogger->record(
                $context,
                'supplier_product.saved',
                'supplier_product',
                $supplierProduct->public_id,
                after: $supplierProduct->attributesToArray(),
            );

            return $supplierProduct;
        }, 3);
    }
}
