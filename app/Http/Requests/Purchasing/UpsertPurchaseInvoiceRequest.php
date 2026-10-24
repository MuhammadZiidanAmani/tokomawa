<?php

namespace App\Http\Requests\Purchasing;

use App\Domain\AccountsPayable\Models\PurchaseInvoice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertPurchaseInvoiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $invoice = $this->route('purchase_invoice');

        return $invoice instanceof PurchaseInvoice ? ($this->user()?->can('update', $invoice) ?? false) : ($this->user()?->can('create', PurchaseInvoice::class) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('is_active', true)], 'supplier_invoice_number' => ['required', 'string', 'max:100'], 'purchase_type' => ['required', Rule::in(['cash', 'credit'])], 'invoice_date' => ['required', 'date'], 'due_date' => ['nullable', 'date', 'after_or_equal:invoice_date', 'required_if:purchase_type,credit'], 'notes' => ['nullable', 'string', 'max:2000'], 'idempotency_key' => ['required', 'uuid'], 'items' => ['required', 'array', 'min:1', 'max:100'], 'items.*.goods_receipt_item_id' => ['required', 'integer', 'distinct', Rule::exists('goods_receipt_items', 'id')], 'items.*.base_quantity' => ['required', 'decimal:0,3', 'gt:0'], 'items.*.invoice_unit_price' => ['required', 'decimal:0,2', 'gte:0'], 'items.*.discount_amount' => ['nullable', 'decimal:0,2', 'gte:0'], 'items.*.tax_rule_id' => ['nullable', 'integer', Rule::exists('tax_rules', 'id')->where('is_active', true)], 'items.*.tax_amount' => ['nullable', 'decimal:0,2', 'gte:0']];
    }
}
