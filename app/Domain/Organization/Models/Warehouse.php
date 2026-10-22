<?php

namespace App\Domain\Organization\Models;

use App\Models\User;
use Database\Factories\WarehouseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['branch_id', 'code', 'name', 'is_active', 'created_by'])]
class Warehouse extends Model
{
    /** @use HasFactory<WarehouseFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Warehouse $warehouse): void {
            $warehouse->public_id ??= (string) Str::ulid();
        });
    }

    protected static function newFactory(): WarehouseFactory
    {
        return WarehouseFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
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
