<?php

namespace App\Domain\Access\Models;

use App\Domain\Organization\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable([
    'branch_id', 'permission_code', 'entity_type', 'entity_id', 'amount', 'status', 'reason',
    'requested_by', 'approved_by', 'decision_notes', 'idempotency_key', 'requested_at', 'decided_at',
])]
class ApprovalRequest extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    protected static function booted(): void
    {
        static::creating(function (ApprovalRequest $approvalRequest): void {
            $approvalRequest->public_id ??= (string) Str::ulid();
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }
}
