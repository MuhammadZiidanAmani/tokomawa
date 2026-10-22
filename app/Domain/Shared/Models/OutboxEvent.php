<?php

namespace App\Domain\Shared\Models;

use App\Domain\Organization\Models\Branch;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'id', 'branch_id', 'event_type', 'aggregate_type', 'aggregate_id', 'payload',
    'occurred_at', 'published_at', 'attempts',
])]
class OutboxEvent extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'occurred_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }
}
