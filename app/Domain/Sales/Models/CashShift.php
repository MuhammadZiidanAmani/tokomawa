<?php

namespace App\Domain\Sales\Models;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Register;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Sales\Enums\CashShiftStatus;
use App\Models\User;
use Database\Factories\CashShiftFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['branch_id', 'register_id', 'warehouse_id', 'cashier_id', 'status', 'opening_float', 'opened_at', 'active_register_key', 'active_cashier_key', 'opening_key', 'opened_by', 'version'])]
class CashShift extends Model
{
    /** @use HasFactory<CashShiftFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $shift): void {
            $shift->public_id ??= (string) Str::ulid();
        });
        static::deleting(fn (): never => throw new DomainException('Shift kasir tidak dapat dihapus.'));
    }

    protected static function newFactory(): CashShiftFactory
    {
        return CashShiftFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function cashMovements(): HasMany
    {
        return $this->hasMany(CashMovement::class);
    }

    protected function casts(): array
    {
        return [
            'status' => CashShiftStatus::class,
            'opening_float' => 'decimal:2',
            'opened_at' => 'datetime',
            'version' => 'integer',
        ];
    }
}
