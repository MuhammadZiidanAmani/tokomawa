<?php

namespace App\Domain\Sales\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Unit;
use App\Domain\Inventory\Models\InventoryBatch;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Pricing\Models\TaxRule;
use App\Domain\Suppliers\Models\Supplier;
use Database\Factories\SaleItemFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['public_id', 'sale_id', 'line_number', 'product_id', 'unit_id', 'inventory_batch_id', 'supplier_attribution_id', 'product_sku_snapshot', 'product_name_snapshot', 'unit_code_snapshot', 'unit_name_snapshot', 'unit_symbol_snapshot', 'batch_lot_snapshot', 'batch_expiry_snapshot', 'conversion_factor', 'quantity', 'base_quantity', 'unit_price_snapshot', 'price_list_code_snapshot', 'price_list_name_snapshot', 'subtotal', 'discount_amount', 'tax_rule_id', 'tax_code_snapshot', 'tax_rate_snapshot', 'tax_amount', 'line_total', 'cogs_unit_snapshot', 'cogs_total_snapshot', 'inventory_movement_id'])]
class SaleItem extends Model
{
    /** @use HasFactory<SaleItemFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            $item->public_id ??= (string) Str::ulid();
        });
        static::updating(fn (): never => throw new DomainException('Item penjualan terposting tidak dapat diubah.'));
        static::deleting(fn (): never => throw new DomainException('Item penjualan tidak dapat dihapus.'));
    }

    protected static function newFactory(): SaleItemFactory
    {
        return SaleItemFactory::new();
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'inventory_batch_id');
    }

    public function supplierAttribution(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'supplier_attribution_id');
    }

    public function taxRule(): BelongsTo
    {
        return $this->belongsTo(TaxRule::class);
    }

    public function inventoryMovement(): BelongsTo
    {
        return $this->belongsTo(InventoryMovement::class);
    }

    protected function casts(): array
    {
        return [
            'batch_expiry_snapshot' => 'date',
            'conversion_factor' => 'decimal:6',
            'quantity' => 'decimal:3',
            'base_quantity' => 'decimal:3',
            'unit_price_snapshot' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'tax_rate_snapshot' => 'decimal:4',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
            'cogs_unit_snapshot' => 'decimal:6',
            'cogs_total_snapshot' => 'decimal:6',
        ];
    }
}
