<?php

namespace App\Domain\Catalog\Models;

use App\Models\User;
use Database\Factories\ProductBarcodeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['product_id', 'unit_id', 'barcode', 'is_primary', 'is_active', 'created_by'])]
class ProductBarcode extends Model
{
    /** @use HasFactory<ProductBarcodeFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (ProductBarcode $barcode): void {
            $barcode->public_id ??= (string) Str::ulid();
        });
    }

    protected static function newFactory(): ProductBarcodeFactory
    {
        return ProductBarcodeFactory::new();
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
        return ['is_primary' => 'boolean', 'is_active' => 'boolean'];
    }
}
