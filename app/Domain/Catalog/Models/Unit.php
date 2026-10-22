<?php

namespace App\Domain\Catalog\Models;

use App\Models\User;
use Database\Factories\UnitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['code', 'name', 'symbol', 'allow_fractional', 'decimal_places', 'is_active', 'created_by'])]
class Unit extends Model
{
    /** @use HasFactory<UnitFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Unit $unit): void {
            $unit->public_id ??= (string) Str::ulid();
        });
    }

    protected static function newFactory(): UnitFactory
    {
        return UnitFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function baseProducts(): HasMany
    {
        return $this->hasMany(Product::class, 'base_unit_id');
    }

    public function productUnits(): HasMany
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'allow_fractional' => 'boolean',
            'decimal_places' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
