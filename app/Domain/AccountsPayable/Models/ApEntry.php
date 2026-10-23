<?php

namespace App\Domain\AccountsPayable\Models;

use DomainException;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable(['branch_id', 'supplier_id', 'purchase_invoice_id', 'cash_payment_id', 'entry_type', 'amount_delta', 'due_date', 'source_key', 'occurred_at', 'description', 'created_by'])]
class ApEntry extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected static function booted(): void
    {
        static::creating(function (self $entry): void {
            $entry->id ??= (string) Str::ulid();
        });
        static::updating(fn (): never => throw new DomainException('Ledger AP tidak dapat diubah.'));
        static::deleting(fn (): never => throw new DomainException('Ledger AP tidak dapat dihapus.'));
    }

    protected function casts(): array
    {
        return ['amount_delta' => 'decimal:2', 'due_date' => 'date', 'occurred_at' => 'datetime'];
    }
}
