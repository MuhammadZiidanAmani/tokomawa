<?php

namespace App\Domain\Catalog\Models;

use App\Models\User;
use Database\Factories\ProductLocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['product_id', 'shelf_location_id', 'is_primary', 'is_active', 'created_by'])]
class ProductLocation extends Model
{
    /** @use HasFactory<ProductLocationFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (ProductLocation $productLocation): void {
            $productLocation->public_id ??= (string) Str::ulid();
        });
    }

    protected static function newFactory(): ProductLocationFactory
    {
        return ProductLocationFactory::new();
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function shelfLocation(): BelongsTo
    {
        return $this->belongsTo(ShelfLocation::class);
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
