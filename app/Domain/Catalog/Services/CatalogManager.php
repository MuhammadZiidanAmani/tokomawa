<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Access\DTOs\AuditContext;
use App\Domain\Access\Services\AuditLogger;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Department;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductBarcode;
use App\Domain\Catalog\Models\ProductLocation;
use App\Domain\Catalog\Models\ProductUnit;
use App\Domain\Catalog\Models\ShelfLocation;
use App\Domain\Catalog\Models\Subcategory;
use App\Domain\Catalog\Models\Unit;
use App\Domain\Organization\Models\Branch;
use App\Domain\Suppliers\Models\SupplierProduct;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CatalogManager
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /** @param array<string, mixed> $data */
    public function saveDepartment(?Department $department, array $data, AuditContext $context): Department
    {
        return $this->saveReference($department ?? new Department, $data, $context, 'department');
    }

    /** @param array<string, mixed> $data */
    public function saveCategory(?Category $category, array $data, AuditContext $context): Category
    {
        return $this->saveReference($category ?? new Category, $data, $context, 'category');
    }

    /** @param array<string, mixed> $data */
    public function saveSubcategory(?Subcategory $subcategory, array $data, AuditContext $context): Subcategory
    {
        return $this->saveReference($subcategory ?? new Subcategory, $data, $context, 'subcategory');
    }

    /** @param array<string, mixed> $data */
    public function saveBrand(?Brand $brand, array $data, AuditContext $context): Brand
    {
        return $this->saveReference($brand ?? new Brand, $data, $context, 'brand');
    }

    /** @param array<string, mixed> $data */
    public function saveUnit(?Unit $unit, array $data, AuditContext $context): Unit
    {
        return $this->saveReference($unit ?? new Unit, $data, $context, 'unit');
    }

    /** @param array<string, mixed> $data */
    public function saveProduct(?Product $product, array $data, AuditContext $context): Product
    {
        return DB::transaction(function () use ($product, $data, $context): Product {
            $product = $product === null
                ? new Product
                : Product::query()->lockForUpdate()->findOrFail($product->getKey());
            $wasRecentlyCreated = ! $product->exists;
            $before = $product->exists ? $this->productSnapshot($product) : null;

            $product->fill(Arr::only($data, [
                'sku', 'name', 'classification', 'department_id', 'category_id', 'subcategory_id',
                'brand_id', 'base_unit_id', 'default_supplier_id', 'tax_rule_id', 'description',
                'track_batch', 'track_expiry', 'allow_fractional_qty', 'min_stock', 'is_active',
            ]));
            if (! $product->exists) {
                $product->created_by = $context->actor?->getKey();
            }
            $product->save();

            $this->syncProductBarcodes($product, $data, $context);
            $this->syncProductUnits($product, $data, $context);
            $this->syncDefaultSupplier($product, $context);
            $product->load(['barcodes', 'units', 'supplierProducts']);

            $this->auditLogger->record(
                $context,
                $wasRecentlyCreated ? 'product.created' : 'product.updated',
                'product',
                $product->public_id,
                before: $before,
                after: $this->productSnapshot($product),
            );

            return $product;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function saveShelfLocation(
        ?ShelfLocation $location,
        Branch $branch,
        array $data,
        AuditContext $context,
    ): ShelfLocation {
        if (! $branch->warehouses()->whereKey($data['warehouse_id'])->exists()) {
            throw ValidationException::withMessages([
                'warehouse_id' => 'Gudang harus berada pada cabang aktif.',
            ]);
        }

        return DB::transaction(function () use ($location, $branch, $data, $context): ShelfLocation {
            $location = $location === null
                ? new ShelfLocation
                : ShelfLocation::query()->lockForUpdate()->findOrFail($location->getKey());
            abort_unless(! $location->exists || $location->branch_id === $branch->getKey(), 404);
            $created = ! $location->exists;
            $before = $location->exists ? $location->only($this->locationFields()) : null;

            $location->fill(Arr::only($data, $this->locationFields()));
            $location->branch()->associate($branch);
            if (! $location->exists) {
                $location->created_by = $context->actor?->getKey();
            }
            $location->save();

            $this->auditLogger->record(
                $context,
                $created ? 'shelf_location.created' : 'shelf_location.updated',
                'shelf_location',
                $location->public_id,
                before: $before,
                after: $location->only($this->locationFields()),
            );

            return $location;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    public function assignProductLocation(ShelfLocation $location, array $data, AuditContext $context): ProductLocation
    {
        abort_unless($context->branch?->is($location->branch), 404);

        return DB::transaction(function () use ($location, $data, $context): ProductLocation {
            if ((bool) $data['is_primary']) {
                ProductLocation::query()
                    ->where('product_id', $data['product_id'])
                    ->whereHas('shelfLocation', fn ($query) => $query->where('branch_id', $location->branch_id))
                    ->update(['is_primary' => false]);
            }

            $assignment = ProductLocation::query()->updateOrCreate(
                ['product_id' => $data['product_id'], 'shelf_location_id' => $location->getKey()],
                [
                    'is_primary' => (bool) $data['is_primary'],
                    'is_active' => (bool) $data['is_active'],
                    'created_by' => $context->actor?->getKey(),
                ],
            );

            $this->auditLogger->record(
                $context,
                'product_location.saved',
                'product_location',
                $assignment->public_id,
                after: $assignment->only(['product_id', 'shelf_location_id', 'is_primary', 'is_active']),
            );

            return $assignment;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    private function saveReference(Model $model, array $data, AuditContext $context, string $entityType): mixed
    {
        return DB::transaction(function () use ($model, $data, $context, $entityType): Model {
            if ($model->exists) {
                $model = $model->newQuery()->lockForUpdate()->findOrFail($model->getKey());
            }
            $created = ! $model->exists;
            $before = $model->exists ? $model->attributesToArray() : null;
            $model->fill($data);
            if (! $model->exists) {
                $model->setAttribute('created_by', $context->actor?->getKey());
            }
            $model->save();

            $this->auditLogger->record(
                $context,
                $entityType.'.'.($created ? 'created' : 'updated'),
                $entityType,
                $model->getAttribute('public_id'),
                before: $before,
                after: $model->attributesToArray(),
            );

            return $model;
        }, 3);
    }

    /** @param array<string, mixed> $data */
    private function syncProductBarcodes(Product $product, array $data, AuditContext $context): void
    {
        $barcodes = collect([$data['primary_barcode'] ?? null])
            ->merge($data['alternate_barcodes'] ?? [])
            ->filter()
            ->unique()
            ->values();

        $product->barcodes()->update(['is_active' => false, 'is_primary' => false]);

        foreach ($barcodes as $index => $barcodeValue) {
            $barcode = ProductBarcode::query()->firstOrNew(['barcode' => $barcodeValue]);
            if ($barcode->exists && $barcode->product_id !== $product->getKey()) {
                throw ValidationException::withMessages(['primary_barcode' => 'Barcode sudah digunakan barang lain.']);
            }
            $barcode->fill([
                'product_id' => $product->getKey(),
                'unit_id' => $product->base_unit_id,
                'barcode' => $barcodeValue,
                'is_primary' => $index === 0 && filled($data['primary_barcode'] ?? null),
                'is_active' => true,
                'created_by' => $barcode->created_by ?? $context->actor?->getKey(),
            ])->save();
        }
    }

    /** @param array<string, mixed> $data */
    private function syncProductUnits(Product $product, array $data, AuditContext $context): void
    {
        $units = [
            $product->base_unit_id => [
                'conversion_factor' => '1.000000',
                'is_purchase_unit' => (int) ($data['purchase_unit_id'] ?? 0) === $product->base_unit_id,
                'is_sale_unit' => (int) ($data['sale_unit_id'] ?? 0) === $product->base_unit_id,
            ],
        ];

        foreach (['purchase', 'sale'] as $usage) {
            $unitId = (int) ($data[$usage.'_unit_id'] ?? 0);
            if ($unitId === 0) {
                continue;
            }
            $units[$unitId] ??= [
                'conversion_factor' => (string) $data[$usage.'_conversion_factor'],
                'is_purchase_unit' => false,
                'is_sale_unit' => false,
            ];
            $units[$unitId]['is_'.$usage.'_unit'] = true;
        }

        $product->units()->update([
            'is_purchase_unit' => false,
            'is_sale_unit' => false,
            'is_active' => false,
        ]);

        foreach ($units as $unitId => $attributes) {
            ProductUnit::query()->updateOrCreate(
                ['product_id' => $product->getKey(), 'unit_id' => $unitId],
                [...$attributes, 'is_active' => true, 'created_by' => $context->actor?->getKey()],
            );
        }
    }

    private function syncDefaultSupplier(Product $product, AuditContext $context): void
    {
        $product->supplierProducts()->update(['is_primary' => false]);
        if ($product->default_supplier_id === null) {
            return;
        }

        SupplierProduct::query()->updateOrCreate(
            ['supplier_id' => $product->default_supplier_id, 'product_id' => $product->getKey()],
            [
                'purchase_unit_id' => $product->base_unit_id,
                'minimum_order_qty' => '1.000',
                'priority' => 1,
                'is_primary' => true,
                'is_active' => true,
                'created_by' => $context->actor?->getKey(),
            ],
        );
    }

    /** @return list<string> */
    private function locationFields(): array
    {
        return ['warehouse_id', 'code', 'name', 'zone', 'aisle', 'level', 'description', 'is_active'];
    }

    /** @return array<string, mixed> */
    private function productSnapshot(Product $product): array
    {
        return [
            ...$product->only([
                'public_id', 'sku', 'name', 'classification', 'department_id', 'category_id',
                'subcategory_id', 'brand_id', 'base_unit_id', 'default_supplier_id', 'tax_rule_id',
                'description', 'track_batch', 'track_expiry', 'allow_fractional_qty', 'min_stock', 'is_active',
            ]),
            'barcodes' => $product->barcodes()->where('is_active', true)->orderBy('barcode')->pluck('barcode')->all(),
            'units' => $product->units()->where('is_active', true)->orderBy('unit_id')->get()
                ->map->only(['unit_id', 'conversion_factor', 'is_purchase_unit', 'is_sale_unit'])->all(),
        ];
    }
}
