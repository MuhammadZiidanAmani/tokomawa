<?php

namespace App\Http\Requests\Master;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertSupplierProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('suppliers.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'product_id' => ['required', 'integer', Rule::exists('products', 'id')->where('is_active', true)],
            'purchase_unit_id' => ['nullable', 'integer', Rule::exists('units', 'id')->where('is_active', true)],
            'supplier_sku' => ['nullable', 'string', 'max:80'],
            'last_purchase_price' => ['nullable', 'decimal:0,2', 'gte:0'],
            'contract_price' => ['nullable', 'decimal:0,2', 'gte:0'],
            'minimum_order_qty' => ['required', 'decimal:0,3', 'gt:0'],
            'lead_time_days' => ['nullable', 'integer', 'between:0,3650'],
            'priority' => ['required', 'integer', 'between:1,255'],
            'is_primary' => ['required', 'boolean'],
            'is_active' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_primary' => $this->boolean('is_primary'), 'is_active' => $this->boolean('is_active', true)]);
    }
}
