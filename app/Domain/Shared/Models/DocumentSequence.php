<?php

namespace App\Domain\Shared\Models;

use App\Domain\Organization\Models\Branch;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['branch_id', 'document_type', 'year', 'month', 'last_number'])]
class DocumentSequence extends Model
{
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
