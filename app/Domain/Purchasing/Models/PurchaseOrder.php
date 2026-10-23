<?php

namespace App\Domain\Purchasing\Models;

use App\Domain\Access\Models\ApprovalRequest;
use App\Domain\Organization\Models\Branch;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Suppliers\Models\Supplier;
use App\Models\User;
use Database\Factories\PurchaseOrderFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'branch_id', 'supplier_id', 'document_number', 'status', 'order_date', 'expected_at', 'currency',
    'supplier_code_snapshot', 'supplier_name_snapshot', 'supplier_address_snapshot', 'subtotal', 'grand_total',
    'notes', 'approval_request_id', 'creation_key', 'version', 'created_by', 'submitted_by', 'approved_by',
    'rejected_by', 'sent_by', 'cancelled_by', 'submitted_at', 'approved_at', 'rejected_at', 'sent_at',
    'cancelled_at', 'decision_notes', 'cancellation_reason',
])]
class PurchaseOrder extends Model
{
    /** @use HasFactory<PurchaseOrderFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (PurchaseOrder $purchaseOrder): void {
            $purchaseOrder->public_id ??= (string) Str::ulid();
        });
    }

    protected static function newFactory(): PurchaseOrderFactory
    {
        return PurchaseOrderFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function approvalRequest(): BelongsTo
    {
        return $this->belongsTo(ApprovalRequest::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class)->orderBy('line_number');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'order_date' => 'date',
            'expected_at' => 'date',
            'subtotal' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'version' => 'integer',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'sent_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }
}
