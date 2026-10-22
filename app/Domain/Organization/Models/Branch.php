<?php

namespace App\Domain\Organization\Models;

use App\Domain\Access\Models\ApprovalLimit;
use App\Models\User;
use Database\Factories\BranchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['code', 'name', 'timezone', 'is_active', 'created_by'])]
class Branch extends Model
{
    /** @use HasFactory<BranchFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Branch $branch): void {
            $branch->public_id ??= (string) Str::ulid();
        });
    }

    protected static function newFactory(): BranchFactory
    {
        return BranchFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function warehouses(): HasMany
    {
        return $this->hasMany(Warehouse::class);
    }

    public function registers(): HasMany
    {
        return $this->hasMany(Register::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_branches')
            ->withPivot('is_default')
            ->withTimestamps();
    }

    public function approvalLimits(): HasMany
    {
        return $this->hasMany(ApprovalLimit::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
