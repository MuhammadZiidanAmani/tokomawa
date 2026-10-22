<?php

namespace App\Domain\Access\Models;

use App\Domain\Organization\Models\Branch;
use App\Models\User;
use Database\Factories\ApprovalLimitFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['branch_id', 'role_id', 'permission_id', 'minimum_amount', 'maximum_amount', 'is_active', 'created_by'])]
class ApprovalLimit extends Model
{
    /** @use HasFactory<ApprovalLimitFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (ApprovalLimit $limit): void {
            $limit->public_id ??= (string) Str::ulid();
        });
    }

    protected static function newFactory(): ApprovalLimitFactory
    {
        return ApprovalLimitFactory::new();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function permission(): BelongsTo
    {
        return $this->belongsTo(Permission::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'minimum_amount' => 'decimal:2',
            'maximum_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
