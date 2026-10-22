<?php

namespace App\Domain\Pricing\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Unit;
use App\Models\User;
use Database\Factories\PriceChangeLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'product_price_id', 'product_id', 'price_list_id', 'unit_id', 'change_type', 'previous_amount',
    'new_amount', 'previous_effective_from', 'previous_effective_to', 'new_effective_from',
    'new_effective_to', 'reason', 'changed_by', 'changed_at',
])]
class PriceChangeLog extends Model
{
    /** @use HasFactory<PriceChangeLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public const CREATED_AT = null;

    protected static function newFactory(): PriceChangeLogFactory
    {
        return PriceChangeLogFactory::new();
    }

    public function productPrice(): BelongsTo
    {
        return $this->belongsTo(ProductPrice::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }

    protected function casts(): array
    {
        return [
            'previous_amount' => 'decimal:2',
            'new_amount' => 'decimal:2',
            'previous_effective_from' => 'datetime',
            'previous_effective_to' => 'datetime',
            'new_effective_from' => 'datetime',
            'new_effective_to' => 'datetime',
            'changed_at' => 'datetime',
        ];
    }
}
