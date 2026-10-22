<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Access\Models\ApprovalRequest;
use App\Domain\Inventory\Enums\StockAdjustmentStatus;
use App\Domain\Inventory\Enums\StockAdjustmentType;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Warehouse;
use App\Models\User;
use Database\Factories\StockAdjustmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'document_number', 'branch_id', 'type', 'status', 'source_warehouse_id', 'destination_warehouse_id',
    'reason', 'occurred_at', 'approval_request_id', 'submission_key', 'created_by', 'submitted_by',
    'approved_by', 'posted_by', 'submitted_at', 'approved_at', 'posted_at',
])]
class StockAdjustment extends Model
{
    /** @use HasFactory<StockAdjustmentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (StockAdjustment $adjustment): void {
            $adjustment->public_id ??= (string) Str::ulid();
        });
    }

    protected static function newFactory(): StockAdjustmentFactory
    {
        return StockAdjustmentFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function sourceWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'source_warehouse_id');
    }

    public function destinationWarehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'destination_warehouse_id');
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class)->orderBy('line_number');
    }

    protected function casts(): array
    {
        return [
            'type' => StockAdjustmentType::class,
            'status' => StockAdjustmentStatus::class,
            'occurred_at' => 'datetime', 'submitted_at' => 'datetime', 'approved_at' => 'datetime', 'posted_at' => 'datetime',
        ];
    }
}
