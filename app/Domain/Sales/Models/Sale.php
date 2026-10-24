<?php

namespace App\Domain\Sales\Models;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Register;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Pricing\Models\PriceList;
use App\Domain\Sales\Enums\SaleStatus;
use App\Models\User;
use Database\Factories\SaleFactory;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable(['branch_id', 'warehouse_id', 'register_id', 'cash_shift_id', 'cashier_id', 'price_list_id', 'document_number', 'status', 'sold_at', 'currency', 'subtotal', 'discount_total', 'tax_total', 'grand_total', 'cash_tendered', 'change_due', 'posting_key', 'version', 'posted_by', 'posted_at'])]
class Sale extends Model
{
    /** @use HasFactory<SaleFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $sale): void {
            $sale->public_id ??= (string) Str::ulid();
        });
        static::updating(function (self $sale): void {
            if ($sale->getRawOriginal('status') === SaleStatus::Posted->value) {
                throw new DomainException('Penjualan terposting tidak dapat diubah.');
            }
        });
        static::deleting(fn (): never => throw new DomainException('Penjualan tidak dapat dihapus. Gunakan reversal pada milestone yang sesuai.'));
    }

    protected static function newFactory(): SaleFactory
    {
        return SaleFactory::new();
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function register(): BelongsTo
    {
        return $this->belongsTo(Register::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(CashShift::class, 'cash_shift_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function priceList(): BelongsTo
    {
        return $this->belongsTo(PriceList::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class)->orderBy('line_number');
    }

    public function payment(): HasOne
    {
        return $this->hasOne(SalePayment::class);
    }

    public function cashMovement(): HasOne
    {
        return $this->hasOne(CashMovement::class);
    }

    protected function casts(): array
    {
        return [
            'status' => SaleStatus::class,
            'sold_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'cash_tendered' => 'decimal:2',
            'change_due' => 'decimal:2',
            'version' => 'integer',
            'posted_at' => 'datetime',
        ];
    }
}
