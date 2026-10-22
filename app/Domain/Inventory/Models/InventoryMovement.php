<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Warehouse;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'branch_id', 'warehouse_id', 'product_id', 'inventory_batch_id', 'batch_scope', 'stock_status',
    'movement_type', 'direction', 'qty_delta', 'qty_after', 'unit_cost_snapshot', 'value_delta',
    'source_type', 'source_id', 'source_line_id', 'source_key', 'idempotency_key', 'occurred_at',
    'posted_at', 'created_by', 'reversal_of_id',
])]
class InventoryMovement extends Model
{
    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::creating(function (InventoryMovement $movement): void {
            $movement->id ??= (string) Str::ulid();
        });
        static::updating(fn (): never => throw new DomainException('Mutasi stok yang sudah tercatat tidak dapat diubah.'));
        static::deleting(fn (): never => throw new DomainException('Mutasi stok tidak dapat dihapus. Gunakan transaksi koreksi.'));
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(InventoryBatch::class, 'inventory_batch_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'qty_delta' => 'decimal:3', 'qty_after' => 'decimal:3',
            'unit_cost_snapshot' => 'decimal:6', 'value_delta' => 'decimal:6',
            'occurred_at' => 'datetime', 'posted_at' => 'datetime', 'created_at' => 'datetime',
        ];
    }
}
