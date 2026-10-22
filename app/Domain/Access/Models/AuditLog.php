<?php

namespace App\Domain\Access\Models;

use App\Domain\Organization\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable([
    'id', 'actor_id', 'role_snapshot', 'branch_id', 'action', 'entity_type', 'entity_id',
    'before_json', 'after_json', 'reason', 'ip_address', 'user_agent', 'created_at',
])]
class AuditLog extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Audit log tidak dapat diubah.'));
        static::deleting(fn (): never => throw new LogicException('Audit log tidak dapat dihapus.'));
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    protected function casts(): array
    {
        return [
            'role_snapshot' => 'array',
            'before_json' => 'array',
            'after_json' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
