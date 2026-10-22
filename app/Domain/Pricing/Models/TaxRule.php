<?php

namespace App\Domain\Pricing\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Pricing\Enums\TaxRoundingMode;
use App\Models\User;
use Database\Factories\TaxRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'code', 'name', 'rate', 'calculation_mode', 'rounding_mode', 'rounding_scale',
    'effective_from', 'effective_to', 'is_active', 'created_by',
])]
class TaxRule extends Model
{
    /** @use HasFactory<TaxRuleFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (TaxRule $taxRule): void {
            $taxRule->public_id ??= (string) Str::ulid();
        });
    }

    protected static function newFactory(): TaxRuleFactory
    {
        return TaxRuleFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'rate' => 'decimal:4',
            'rounding_mode' => TaxRoundingMode::class,
            'rounding_scale' => 'integer',
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
