<?php

namespace App\Domain\Suppliers\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Unit;
use App\Models\User;
use Database\Factories\SupplierProductFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'supplier_id', 'product_id', 'purchase_unit_id', 'supplier_sku', 'last_purchase_price',
    'contract_price', 'minimum_order_qty', 'lead_time_days', 'priority', 'is_primary', 'is_active', 'created_by',
])]
class SupplierProduct extends Model
{
    /** @use HasFactory<SupplierProductFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (SupplierProduct $supplierProduct): void {
            $supplierProduct->public_id ??= (string) Str::ulid();
        });
    }

    protected static function newFactory(): SupplierProductFactory
    {
        return SupplierProductFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function purchaseUnit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'purchase_unit_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'last_purchase_price' => 'decimal:2',
            'contract_price' => 'decimal:2',
            'minimum_order_qty' => 'decimal:3',
            'lead_time_days' => 'integer',
            'priority' => 'integer',
            'is_primary' => 'boolean',
            'is_active' => 'boolean',
        ];
    }
}
