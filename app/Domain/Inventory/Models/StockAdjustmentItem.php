<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Unit;
use Database\Factories\StockAdjustmentItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'stock_adjustment_id', 'line_number', 'product_id', 'unit_id', 'inventory_batch_id',
    'quantity', 'base_quantity', 'unit_cost', 'stock_status', 'notes',
])]
class StockAdjustmentItem extends Model
{
    /** @use HasFactory<StockAdjustmentItemFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (StockAdjustmentItem $item): void {
            $item->public_id ??= (string) Str::ulid();
        });
    }

    protected static function newFactory(): StockAdjustmentItemFactory
    {
        return StockAdjustmentItemFactory::new();
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'stock_adjustment_id');
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
        return ['quantity' => 'decimal:3', 'base_quantity' => 'decimal:3', 'unit_cost' => 'decimal:6'];
    }
}
