<?php

namespace App\Domain\Catalog\Models;

use App\Models\User;
use Database\Factories\ProductUnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'product_id', 'unit_id', 'conversion_factor', 'is_purchase_unit', 'is_sale_unit', 'is_active', 'created_by',
])]
class ProductUnit extends Model
{
    /** @use HasFactory<ProductUnitFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (ProductUnit $productUnit): void {
            $productUnit->public_id ??= (string) Str::ulid();
        });
    }

    protected static function newFactory(): ProductUnitFactory
    {
        return ProductUnitFactory::new();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'conversion_factor' => 'decimal:6',
            'is_purchase_unit' => 'boolean',
            'is_sale_unit' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
