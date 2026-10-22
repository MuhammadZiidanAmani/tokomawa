<?php

namespace App\Domain\Pricing\Models;

use App\Models\User;
use Database\Factories\PriceListFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['code', 'name', 'description', 'priority', 'is_active', 'created_by'])]
class PriceList extends Model
{
    /** @use HasFactory<PriceListFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (PriceList $priceList): void {
            $priceList->public_id ??= (string) Str::ulid();
        });
    }

    protected static function newFactory(): PriceListFactory
    {
        return PriceListFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function prices(): HasMany
    {
        return $this->hasMany(ProductPrice::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return ['priority' => 'integer', 'is_active' => 'boolean'];
    }
}
