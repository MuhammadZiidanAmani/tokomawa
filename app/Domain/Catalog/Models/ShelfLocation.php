<?php

namespace App\Domain\Catalog\Models;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Warehouse;
use App\Models\User;
use Database\Factories\ShelfLocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'branch_id', 'warehouse_id', 'code', 'name', 'zone', 'aisle', 'level', 'description', 'is_active', 'created_by',
])]
class ShelfLocation extends Model
{
    /** @use HasFactory<ShelfLocationFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (ShelfLocation $location): void {
            $location->public_id ??= (string) Str::ulid();
        });
    }

    protected static function newFactory(): ShelfLocationFactory
    {
        return ShelfLocationFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function productLocations(): HasMany
    {
        return $this->hasMany(ProductLocation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
