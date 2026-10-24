<?php

namespace App\Domain\Sales\Models;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Register;
use App\Domain\Sales\Enums\CashMovementType;
use App\Models\User;
use Database\Factories\CashMovementFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['branch_id', 'register_id', 'cash_shift_id', 'sale_id', 'sale_payment_id', 'movement_type', 'amount_delta', 'source_key', 'description', 'occurred_at', 'created_by'])]
class CashMovement extends Model
{
    /** @use HasFactory<CashMovementFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::creating(function (self $movement): void {
            $movement->id ??= (string) Str::ulid();
        });
        static::updating(fn (): never => throw new DomainException('Ledger kas tidak dapat diubah.'));
        static::deleting(fn (): never => throw new DomainException('Ledger kas tidak dapat dihapus.'));
    }

    protected static function newFactory(): CashMovementFactory
    {
        return CashMovementFactory::new();
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashShift::class, 'cash_shift_id');
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(SalePayment::class, 'sale_payment_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'movement_type' => CashMovementType::class,
            'amount_delta' => 'decimal:2',
            'occurred_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
