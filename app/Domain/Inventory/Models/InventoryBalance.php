<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Warehouse;
use Database\Factories\InventoryBalanceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'branch_id', 'warehouse_id', 'product_id', 'inventory_batch_id', 'batch_scope', 'stock_status',
    'qty_on_hand', 'qty_reserved', 'avg_unit_cost', 'inventory_value', 'version',
])]
class InventoryBalance extends Model
{
    /** @use HasFactory<InventoryBalanceFactory> */
    use HasFactory;

    protected static function newFactory(): InventoryBalanceFactory
    {
        return InventoryBalanceFactory::new();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'inventory_batch_id');
    }

    protected function casts(): array
    {
        return [
            'qty_on_hand' => 'decimal:3',
            'qty_reserved' => 'decimal:3',
            'avg_unit_cost' => 'decimal:6',
            'inventory_value' => 'decimal:6',
            'version' => 'integer',
        ];
    }
}
