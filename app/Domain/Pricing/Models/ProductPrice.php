<?php

namespace App\Domain\Pricing\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Unit;
use App\Models\User;
use Database\Factories\ProductPriceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'product_id', 'price_list_id', 'unit_id', 'amount', 'currency', 'minimum_quantity',
    'effective_from', 'effective_to', 'priority', 'is_active', 'created_by',
])]
class ProductPrice extends Model
{
    /** @use HasFactory<ProductPriceFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (ProductPrice $productPrice): void {
            $productPrice->public_id ??= (string) Str::ulid();
        });
    }

    protected static function newFactory(): ProductPriceFactory
    {
        return ProductPriceFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function changeLogs(): HasMany
    {
        return $this->hasMany(PriceChangeLog::class);
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'minimum_quantity' => 'decimal:3',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
            'priority' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
