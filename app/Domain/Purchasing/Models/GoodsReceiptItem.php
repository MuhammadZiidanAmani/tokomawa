<?php

namespace App\Domain\Purchasing\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Unit;
use App\Domain\Inventory\Models\InventoryBatch;
use App\Domain\Purchasing\Enums\GoodsReceiptStatus;
use Database\Factories\GoodsReceiptItemFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'goods_receipt_id', 'purchase_order_item_id', 'line_number', 'product_id', 'unit_id',
    'inventory_batch_id', 'product_sku_snapshot', 'product_name_snapshot', 'unit_code_snapshot',
    'unit_name_snapshot', 'unit_symbol_snapshot', 'conversion_factor', 'quantity', 'base_quantity',
    'unit_price_snapshot', 'base_unit_cost_snapshot', 'line_total', 'track_batch_snapshot',
    'track_expiry_snapshot', 'lot_number_snapshot', 'manufactured_at_snapshot', 'expires_at_snapshot',
    'stock_status', 'notes',
])]
class GoodsReceiptItem extends Model
{
    /** @use HasFactory<GoodsReceiptItemFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (GoodsReceiptItem $item): void {
            $item->public_id ??= (string) Str::ulid();
        });
        static::updating(function (GoodsReceiptItem $item): void {
            if ($item->goodsReceipt()->where('status', GoodsReceiptStatus::Posted->value)->exists()) {
                throw new DomainException('Item penerimaan terposting tidak dapat diubah.');
            }
        });
        static::deleting(function (GoodsReceiptItem $item): void {
            if ($item->goodsReceipt()->where('status', GoodsReceiptStatus::Posted->value)->exists()) {
                throw new DomainException('Item penerimaan terposting tidak dapat dihapus.');
            }
        });
    }

    protected static function newFactory(): GoodsReceiptItemFactory
    {
        return GoodsReceiptItemFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function goodsReceipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class);
    }

    public function purchaseOrderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class);
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

    protected function casts(): array
    {
        return [
            'conversion_factor' => 'decimal:6',
            'quantity' => 'decimal:3',
            'base_quantity' => 'decimal:3',
            'unit_price_snapshot' => 'decimal:2',
            'base_unit_cost_snapshot' => 'decimal:6',
            'line_total' => 'decimal:2',
            'track_batch_snapshot' => 'boolean',
            'track_expiry_snapshot' => 'boolean',
            'manufactured_at_snapshot' => 'date',
            'expires_at_snapshot' => 'date',
        ];
    }
}
