<?php

namespace App\Domain\Sales\Services;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductBarcode;
use App\Domain\Catalog\Models\ProductUnit;
use App\Domain\Catalog\Models\Unit;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\InventoryBatch;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Pricing\Models\ProductPrice;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SalesPricingService
{
    /**
     * @param  list<array<string, mixed>>  $inputItems
     * @return array{items:list<array<string,mixed>>,subtotal:string,discount_total:string,tax_total:string,grand_total:string,price_list_id:int,price_list_code:string,price_list_name:string}
     */
    public function quoteCart(Branch $branch, Warehouse $warehouse, array $inputItems, CarbonImmutable $pricedAt): array
    {
        if ($warehouse->branch_id !== $branch->getKey() || ! $warehouse->is_active) {
            throw new DomainException('Gudang penjualan tidak aktif atau tidak sesuai cabang.');
        }
        if ($inputItems === []) {
            throw new DomainException('Keranjang penjualan masih kosong.');
        }

        $normalized = collect($inputItems)
            ->groupBy(fn (array $item): string => implode(':', [
                (int) $item['product_id'],
                (int) $item['unit_id'],
                (int) ($item['inventory_batch_id'] ?? 0),
            ]))
            ->map(function (Collection $rows): array {
                $first = $rows->first();
                $quantity = $rows->reduce(
                    fn (string $carry, array $row): string => bcadd($carry, (string) $row['quantity'], 3),
                    '0.000',
                );

                return [...$first, 'quantity' => $quantity];
            })
            ->sortBy(fn (array $item): string => sprintf('%020d-%020d-%020d', $item['product_id'], $item['inventory_batch_id'] ?? 0, $item['unit_id']))
            ->values();

        $items = [];
        $subtotal = '0.00';
        $selectedPriceListId = null;
        $selectedPriceListCode = null;
        $selectedPriceListName = null;
        foreach ($normalized as $input) {
            $item = $this->quoteItem($warehouse, $input, $pricedAt);
            if ($selectedPriceListId !== null && $selectedPriceListId !== $item['price_list_id']) {
                throw new DomainException('Seluruh item POS harus menggunakan strata harga aktif yang sama.');
            }
            $selectedPriceListId = $item['price_list_id'];
            $selectedPriceListCode = $item['price_list_code_snapshot'];
            $selectedPriceListName = $item['price_list_name_snapshot'];
            $items[] = $item;
            $subtotal = bcadd($subtotal, $item['subtotal'], 2);
        }

        return [
            'items' => $items,
            'subtotal' => $subtotal,
            'discount_total' => '0.00',
            'tax_total' => '0.00',
            'grand_total' => $subtotal,
            'price_list_id' => (int) $selectedPriceListId,
            'price_list_code' => (string) $selectedPriceListCode,
            'price_list_name' => (string) $selectedPriceListName,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function lookup(Warehouse $warehouse, string $term, CarbonImmutable $pricedAt): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $barcode = ProductBarcode::query()
            ->with(['product.baseUnit', 'unit'])
            ->where('barcode', $term)
            ->where('is_active', true)
            ->first();
        if ($barcode !== null) {
            if (! $barcode->product->is_active) {
                throw new DomainException('Barang dari barcode ini sudah tidak aktif.');
            }

            return [$this->lookupRow($warehouse, $barcode->product, $barcode->unit_id ?? $barcode->product->base_unit_id, $pricedAt, $barcode->barcode)];
        }

        return Product::query()
            ->with('baseUnit')
            ->where('is_active', true)
            ->where(function (Builder $query) use ($term): void {
                $query->where('sku', $term)
                    ->orWhere('sku', 'like', $term.'%')
                    ->orWhere('name', 'like', '%'.$term.'%');
            })
            ->orderByRaw('CASE WHEN sku = ? THEN 0 ELSE 1 END', [$term])
            ->orderBy('name')
            ->orderBy('id')
            ->limit(20)
            ->get()
            ->map(fn (Product $product): array => $this->lookupRow($warehouse, $product, $product->base_unit_id, $pricedAt))
            ->all();
    }

    /** @param array<string,mixed> $input @return array<string,mixed> */
    private function quoteItem(Warehouse $warehouse, array $input, CarbonImmutable $pricedAt): array
    {
        $product = Product::query()->with(['baseUnit', 'defaultSupplier'])->findOrFail((int) $input['product_id']);
        if (! $product->is_active) {
            throw new DomainException("Barang {$product->name} sudah tidak aktif.");
        }
        if ($product->tax_rule_id !== null) {
            throw new DomainException("Checkout {$product->name} ditahan: kebijakan pajak dan pembulatan DEC-006 belum disahkan.");
        }

        [$unit, $conversionFactor] = $this->resolveUnit($product, (int) $input['unit_id']);
        $quantity = bcadd((string) $input['quantity'], '0', 3);
        if (bccomp($quantity, '0', 3) <= 0) {
            throw new DomainException("Kuantitas {$product->name} harus lebih dari nol.");
        }
        if (! $product->allow_fractional_qty && bccomp($quantity, bcadd($quantity, '0', 0), 3) !== 0) {
            throw new DomainException("Barang {$product->name} tidak mengizinkan kuantitas pecahan.");
        }

        $baseQuantity = bcmul($quantity, $conversionFactor, 3);
        $batch = $this->resolveBatch($warehouse, $product, $input['inventory_batch_id'] ?? null, $pricedAt);
        $price = ProductPrice::query()
            ->with('priceList')
            ->where('product_id', $product->getKey())
            ->where('unit_id', $unit->getKey())
            ->where('minimum_quantity', '<=', $quantity)
            ->where('is_active', true)
            ->where('effective_from', '<=', $pricedAt)
            ->where(fn (Builder $query) => $query->whereNull('effective_to')->orWhere('effective_to', '>', $pricedAt))
            ->whereHas('priceList', fn (Builder $query) => $query->where('is_active', true))
            ->orderByDesc('minimum_quantity')
            ->orderByDesc('priority')
            ->orderByDesc('effective_from')
            ->orderBy('id')
            ->first();
        if ($price === null) {
            throw new DomainException("Harga jual aktif untuk {$product->name} / {$unit->symbol} belum tersedia.");
        }
        if (bccomp($price->amount, '0', 2) < 0) {
            throw new DomainException('Harga jual tidak boleh negatif.');
        }

        $subtotal = bcmul($quantity, $price->amount, 2);

        return [
            'product' => $product,
            'unit' => $unit,
            'batch' => $batch,
            'product_id' => $product->getKey(),
            'unit_id' => $unit->getKey(),
            'inventory_batch_id' => $batch?->getKey(),
            'supplier_attribution_id' => $product->default_supplier_id,
            'product_sku_snapshot' => $product->sku,
            'product_name_snapshot' => $product->name,
            'unit_code_snapshot' => $unit->code,
            'unit_name_snapshot' => $unit->name,
            'unit_symbol_snapshot' => $unit->symbol,
            'batch_lot_snapshot' => $batch?->lot_number,
            'batch_expiry_snapshot' => $batch?->expires_at?->toDateString(),
            'conversion_factor' => $conversionFactor,
            'quantity' => $quantity,
            'base_quantity' => $baseQuantity,
            'unit_price_snapshot' => $price->amount,
            'price_list_id' => $price->price_list_id,
            'price_list_code_snapshot' => $price->priceList->code,
            'price_list_name_snapshot' => $price->priceList->name,
            'subtotal' => $subtotal,
            'discount_amount' => '0.00',
            'tax_rule_id' => null,
            'tax_code_snapshot' => null,
            'tax_rate_snapshot' => null,
            'tax_amount' => '0.00',
            'line_total' => $subtotal,
        ];
    }

    /** @return array{0:Unit,1:string} */
    private function resolveUnit(Product $product, int $unitId): array
    {
        if ($unitId === $product->base_unit_id) {
            return [$product->baseUnit, '1.000000'];
        }

        $productUnit = ProductUnit::query()
            ->with('unit')
            ->where('product_id', $product->getKey())
            ->where('unit_id', $unitId)
            ->where('is_sale_unit', true)
            ->where('is_active', true)
            ->first();
        if ($productUnit === null) {
            throw new DomainException("Satuan jual {$product->name} tidak valid atau tidak aktif.");
        }

        return [$productUnit->unit, $productUnit->conversion_factor];
    }

    private function resolveBatch(Warehouse $warehouse, Product $product, mixed $batchId, CarbonImmutable $pricedAt): ?InventoryBatch
    {
        if (! $product->track_batch) {
            if ($batchId !== null && $batchId !== '') {
                throw new DomainException("Barang {$product->name} tidak menggunakan batch.");
            }

            return null;
        }
        if ($batchId === null || $batchId === '') {
            throw new DomainException("Pilih batch secara eksplisit untuk {$product->name}; alokasi FEFO/FIFO belum disahkan.");
        }

        $batch = InventoryBatch::query()
            ->whereKey((int) $batchId)
            ->where('branch_id', $warehouse->branch_id)
            ->where('product_id', $product->getKey())
            ->where('is_active', true)
            ->first();
        if ($batch === null) {
            throw new DomainException("Batch {$product->name} tidak valid atau tidak aktif.");
        }
        if ($batch->expires_at !== null && $batch->expires_at->lt($pricedAt->startOfDay())) {
            throw new DomainException("Batch {$product->name} sudah kedaluwarsa dan tidak boleh dijual.");
        }

        return $batch;
    }

    /** @return array<string,mixed> */
    private function lookupRow(Warehouse $warehouse, Product $product, int $unitId, CarbonImmutable $pricedAt, ?string $barcode = null): array
    {
        [$unit, $conversionFactor] = $this->resolveUnit($product, $unitId);
        $price = ProductPrice::query()
            ->with('priceList')
            ->where('product_id', $product->getKey())
            ->where('unit_id', $unit->getKey())
            ->where('minimum_quantity', '<=', '1.000')
            ->where('is_active', true)
            ->where('effective_from', '<=', $pricedAt)
            ->where(fn (Builder $query) => $query->whereNull('effective_to')->orWhere('effective_to', '>', $pricedAt))
            ->whereHas('priceList', fn (Builder $query) => $query->where('is_active', true))
            ->orderByDesc('priority')
            ->orderByDesc('effective_from')
            ->first();
        $available = InventoryBalance::query()
            ->where('warehouse_id', $warehouse->getKey())
            ->where('product_id', $product->getKey())
            ->where('stock_status', 'sellable')
            ->selectRaw('COALESCE(SUM(qty_on_hand - qty_reserved), 0) as available')
            ->value('available') ?? '0.000';
        $batches = $product->track_batch
            ? InventoryBalance::query()
                ->join('inventory_batches', 'inventory_batches.id', '=', 'inventory_balances.inventory_batch_id')
                ->where('inventory_balances.warehouse_id', $warehouse->getKey())
                ->where('inventory_balances.product_id', $product->getKey())
                ->where('inventory_balances.stock_status', 'sellable')
                ->where('inventory_batches.is_active', true)
                ->whereRaw('(inventory_balances.qty_on_hand - inventory_balances.qty_reserved) > 0')
                ->orderBy('inventory_batches.expires_at')
                ->orderBy('inventory_batches.lot_number')
                ->get([
                    'inventory_batches.id',
                    'inventory_batches.lot_number',
                    'inventory_batches.expires_at',
                    'inventory_balances.qty_on_hand',
                    'inventory_balances.qty_reserved',
                ])
                ->map(fn ($balance): array => [
                    'id' => $balance->id,
                    'lot_number' => $balance->lot_number,
                    'expires_at' => $balance->expires_at,
                    'available_base_quantity' => bcsub($balance->qty_on_hand, $balance->qty_reserved, 3),
                ])
                ->values()
                ->all()
            : [];

        return [
            'product_id' => $product->getKey(),
            'sku' => $product->sku,
            'name' => $product->name,
            'barcode' => $barcode,
            'unit_id' => $unit->getKey(),
            'unit_symbol' => $unit->symbol,
            'conversion_factor' => $conversionFactor,
            'price' => $price?->amount,
            'price_list' => $price?->priceList?->name,
            'available_base_quantity' => bcadd((string) $available, '0', 3),
            'requires_batch' => $product->track_batch,
            'batches' => $batches,
            'tax_policy_blocked' => $product->tax_rule_id !== null,
        ];
    }
}
