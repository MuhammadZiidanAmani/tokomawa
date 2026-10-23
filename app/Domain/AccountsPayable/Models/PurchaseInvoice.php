<?php

namespace App\Domain\AccountsPayable\Models;

use App\Domain\AccountsPayable\Enums\PurchaseInvoiceStatus;
use App\Domain\AccountsPayable\Enums\PurchaseType;
use App\Domain\Organization\Models\Branch;
use App\Domain\Suppliers\Models\Supplier;
use App\Models\User;
use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable(['branch_id', 'supplier_id', 'document_number', 'supplier_invoice_number', 'supplier_invoice_number_normalized', 'status', 'purchase_type', 'invoice_date', 'due_date', 'currency', 'supplier_code_snapshot', 'supplier_name_snapshot', 'supplier_address_snapshot', 'subtotal', 'discount_total', 'tax_total', 'grand_total', 'outstanding_amount', 'notes', 'creation_key', 'posting_key', 'version', 'created_by', 'posted_by', 'posted_at'])]
class PurchaseInvoice extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $invoice): void {
            $invoice->public_id ??= (string) Str::ulid();
        });
        static::updating(function (self $invoice): void {
            if ($invoice->getRawOriginal('status') === PurchaseInvoiceStatus::Posted->value) {
                throw new DomainException('Faktur terposting tidak dapat diubah.');
            }
        });
        static::deleting(fn (): never => throw new DomainException('Faktur pembelian tidak dapat dihapus.'));
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceItem::class)->orderBy('line_number');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(GoodsReceiptInvoiceAllocation::class);
    }

    public function apEntries(): HasMany
    {
        return $this->hasMany(ApEntry::class);
    }

    public function cashPayment(): HasOne
    {
        return $this->hasOne(PurchaseInvoiceCashPayment::class);
    }

    protected function casts(): array
    {
        return ['status' => PurchaseInvoiceStatus::class, 'purchase_type' => PurchaseType::class, 'invoice_date' => 'date', 'due_date' => 'date', 'subtotal' => 'decimal:2', 'discount_total' => 'decimal:2', 'tax_total' => 'decimal:2', 'grand_total' => 'decimal:2', 'outstanding_amount' => 'decimal:2', 'posted_at' => 'datetime'];
    }
}
