<?php

namespace App\Domain\Shared\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'id', 'scope', 'operation', 'key', 'request_hash', 'status', 'response_code',
    'response_payload', 'created_by', 'expires_at',
])]
class IdempotencyKey extends Model
{
    public const STATUS_PROCESSING = 'processing';

    public const STATUS_COMPLETED = 'completed';

    public $incrementing = false;

    protected $keyType = 'string';

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'response_payload' => 'array',
            'expires_at' => 'datetime',
        ];
    }
}
