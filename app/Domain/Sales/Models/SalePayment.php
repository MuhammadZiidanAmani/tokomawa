<?php

namespace App\Domain\Sales\Models;

use App\Domain\Organization\Models\Branch;
use App\Domain\Sales\Enums\PaymentMethod;
use App\Models\User;
use Database\Factories\SalePaymentFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['sale_id', 'branch_id', 'cash_shift_id', 'method', 'status', 'amount', 'tendered_amount', 'change_amount', 'source_key', 'paid_at', 'created_by'])]
class SalePayment extends Model
{
    /** @use HasFactory<SalePaymentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $payment): void {
            $payment->public_id ??= (string) Str::ulid();
        });
        static::updating(fn (): never => throw new DomainException('Pembayaran penjualan tidak dapat diubah.'));
        static::deleting(fn (): never => throw new DomainException('Pembayaran penjualan tidak dapat dihapus.'));
    }

    protected static function newFactory(): SalePaymentFactory
    {
        return SalePaymentFactory::new();
    }

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashShift::class, 'cash_shift_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'tendered_amount' => 'decimal:2',
            'change_amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
    }
}
