<?php

namespace App\Http\Requests\Purchasing;

use App\Domain\AccountsPayable\Models\PurchaseInvoice;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PostPurchaseInvoiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $invoice = $this->route('purchase_invoice');

        return $invoice instanceof PurchaseInvoice && ($this->user()?->can('post', $invoice) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return ['idempotency_key' => ['required', 'uuid']];
    }
}
