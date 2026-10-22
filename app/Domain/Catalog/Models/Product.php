<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Catalog\Enums\ProductClassification;
use App\Domain\Pricing\Models\ProductPrice;
use App\Domain\Pricing\Models\TaxRule;
use App\Domain\Suppliers\Models\Supplier;
use App\Domain\Suppliers\Models\SupplierProduct;
use App\Models\User;
use Database\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'sku', 'name', 'classification', 'department_id', 'category_id', 'subcategory_id',
    'brand_id', 'base_unit_id', 'default_supplier_id', 'tax_rule_id', 'description',
    'track_batch', 'track_expiry', 'allow_fractional_qty', 'min_stock', 'is_active', 'created_by',
])]
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Product $product): void {
            $product->public_id ??= (string) Str::ulid();
        });
    }

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function subcategory(): BelongsTo
    {
        return $this->belongsTo(Subcategory::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    public function baseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'base_unit_id');
    }

    public function defaultSupplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class, 'default_supplier_id');
    }

    public function taxRule(): BelongsTo
    {
        return $this->belongsTo(TaxRule::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function barcodes(): HasMany
    {
        return $this->hasMany(ProductBarcode::class);
    }

    public function units(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(ProductLocation::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    protected function casts(): array
    {
        return [
            'classification' => ProductClassification::class,
            'track_batch' => 'boolean',
            'track_expiry' => 'boolean',
            'allow_fractional_qty' => 'boolean',
            'min_stock' => 'decimal:3',
            'is_active' => 'boolean',
        ];
    }
}
