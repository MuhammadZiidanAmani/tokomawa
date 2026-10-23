<?php

namespace App\Domain\AccountsPayable\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['purchase_invoice_id', 'line_number', 'product_id', 'unit_id', 'product_sku_snapshot', 'product_name_snapshot', 'unit_code_snapshot', 'unit_name_snapshot', 'unit_symbol_snapshot', 'conversion_factor', 'quantity', 'base_quantity', 'receipt_unit_price_snapshot', 'invoice_unit_price', 'price_variance', 'subtotal', 'discount_amount', 'tax_rule_id', 'tax_code_snapshot', 'tax_rate_snapshot', 'tax_mode_snapshot', 'tax_rounding_snapshot', 'tax_amount', 'line_total'])]
class PurchaseInvoiceItem extends Model
{
    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            $item->public_id ??= (string) Str::ulid();
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'purchase_invoice_id');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(GoodsReceiptInvoiceAllocation::class);
    }

    protected function casts(): array
    {
        return ['quantity' => 'decimal:3', 'base_quantity' => 'decimal:3', 'conversion_factor' => 'decimal:6', 'receipt_unit_price_snapshot' => 'decimal:2', 'invoice_unit_price' => 'decimal:2', 'price_variance' => 'decimal:2', 'subtotal' => 'decimal:2', 'discount_amount' => 'decimal:2', 'tax_rate_snapshot' => 'decimal:4', 'tax_amount' => 'decimal:2', 'line_total' => 'decimal:2'];
    }
}
