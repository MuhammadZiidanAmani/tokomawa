<?php

namespace App\Http\Requests\Sales;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CheckoutSaleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_active
            && ($this->user()?->hasPermission('sales.checkout') ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cash_shift_id' => ['required', 'integer', 'exists:cash_shifts,id'],
            'cash_tendered' => ['required', 'decimal:0,2', 'gte:0'],
            'idempotency_key' => ['required', 'uuid'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.unit_id' => ['required', 'integer', 'exists:units,id'],
            'items.*.inventory_batch_id' => ['nullable', 'integer', 'exists:inventory_batches,id'],
            'items.*.quantity' => ['required', 'decimal:0,3', 'gt:0'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'cash_shift_id.required' => 'Shift kasir aktif tidak ditemukan.',
            'cash_tendered.required' => 'Uang diterima wajib diisi.',
            'cash_tendered.decimal' => 'Uang diterima harus berupa nominal dengan maksimal dua angka desimal.',
            'idempotency_key.uuid' => 'Kunci checkout tidak valid. Muat ulang halaman lalu coba kembali.',
            'items.required' => 'Keranjang belum memiliki barang.',
            'items.min' => 'Keranjang belum memiliki barang.',
            'items.max' => 'Satu transaksi maksimal memuat 100 baris barang.',
            'items.*.quantity.gt' => 'Kuantitas barang harus lebih dari nol.',
        ];
    }
}
