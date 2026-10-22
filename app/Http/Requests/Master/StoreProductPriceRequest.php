<?php

namespace App\Http\Requests\Master;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProductPriceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('pricing.manage') ?? false;
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
            'price_list_id' => ['required', 'integer', Rule::exists('price_lists', 'id')->where('is_active', true)],
            'unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where('is_active', true)],
            'amount' => ['required', 'decimal:0,2', 'gte:0'],
            'minimum_quantity' => ['required', 'decimal:0,3', 'gt:0'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
            'priority' => ['required', 'integer', 'between:1,65535'],
            'is_active' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['is_active' => $this->boolean('is_active', true)]);
    }

    public function messages(): array
    {
        return ['amount.gte' => 'Harga tidak boleh negatif.', 'effective_to.after' => 'Tanggal akhir harus setelah tanggal mulai.'];
    }
}
