<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Organization\Models\Branch;
use App\Models\User;
use Database\Factories\InventoryBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['branch_id', 'product_id', 'lot_number', 'manufactured_at', 'expires_at', 'is_active', 'created_by'])]
class InventoryBatch extends Model
{
    /** @use HasFactory<InventoryBatchFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (InventoryBatch $batch): void {
            $batch->public_id ??= (string) Str::ulid();
        });
    }

    protected static function newFactory(): InventoryBatchFactory
    {
        return InventoryBatchFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return ['manufactured_at' => 'date', 'expires_at' => 'date', 'is_active' => 'boolean'];
    }
}
