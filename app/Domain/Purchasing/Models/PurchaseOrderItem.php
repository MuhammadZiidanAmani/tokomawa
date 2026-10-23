<?php

namespace App\Domain\Purchasing\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Unit;
use App\Domain\Suppliers\Models\SupplierProduct;
use Database\Factories\PurchaseOrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'purchase_order_id', 'line_number', 'product_id', 'unit_id', 'supplier_product_id',
    'product_sku_snapshot', 'product_name_snapshot', 'supplier_sku_snapshot', 'unit_code_snapshot',
    'unit_name_snapshot', 'unit_symbol_snapshot', 'conversion_factor', 'quantity', 'base_quantity',
    'received_base_quantity', 'unit_price', 'subtotal',
])]
class PurchaseOrderItem extends Model
{
    /** @use HasFactory<PurchaseOrderItemFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (PurchaseOrderItem $item): void {
            $item->public_id ??= (string) Str::ulid();
        });
    }

    protected static function newFactory(): PurchaseOrderItemFactory
    {
        return PurchaseOrderItemFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function supplierProduct(): BelongsTo
    {
        return $this->belongsTo(SupplierProduct::class);
    }

    public function goodsReceiptItems(): HasMany
    {
        return $this->hasMany(GoodsReceiptItem::class);
    }

    public function outstandingBaseQuantity(): string
    {
        return bcsub($this->base_quantity, $this->received_base_quantity, 3);
    }

    protected function casts(): array
    {
        return [
            'conversion_factor' => 'decimal:6',
            'quantity' => 'decimal:3',
            'base_quantity' => 'decimal:3',
            'received_base_quantity' => 'decimal:3',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
        ];
    }
}
