<?php

namespace App\Domain\AccountsPayable\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

#[Fillable(['purchase_invoice_id', 'branch_id', 'supplier_id', 'payment_number', 'method', 'amount', 'paid_at', 'reference', 'created_by'])]
class PurchaseInvoiceCashPayment extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->public_id ??= (string) Str::ulid();
        });
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'paid_at' => 'datetime'];
    }
}
