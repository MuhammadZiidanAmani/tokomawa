<?php

namespace App\Domain\Suppliers\Models;

use App\Domain\Catalog\Models\Product;
use App\Models\User;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'code', 'name', 'address', 'phone', 'email', 'tax_identifier', 'contact_name',
    'payment_terms_days', 'bank_name', 'bank_account_name', 'bank_account_number', 'is_active', 'created_by',
])]
class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Supplier $supplier): void {
            $supplier->public_id ??= (string) Str::ulid();
        });
    }

    protected static function newFactory(): SupplierFactory
    {
        return SupplierFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function supplierProducts(): HasMany
    {
        return $this->hasMany(SupplierProduct::class);
    }

    public function defaultProducts(): HasMany
    {
        return $this->hasMany(Product::class, 'default_supplier_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return ['payment_terms_days' => 'integer', 'is_active' => 'boolean'];
    }
}
