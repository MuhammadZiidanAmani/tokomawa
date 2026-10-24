<?php

namespace App\Http\Requests\Sales;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class OpenCashShiftRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->is_active
            && ($this->user()?->hasPermission('cash_shifts.open') ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'register_id' => ['required', 'integer', 'exists:registers,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'opening_float' => ['required', 'decimal:0,2', 'gte:0'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'register_id.required' => 'Register kasir wajib dipilih.',
            'warehouse_id.required' => 'Gudang pengambilan stok wajib dipilih.',
            'opening_float.required' => 'Modal awal kas wajib diisi.',
            'opening_float.decimal' => 'Modal awal kas harus berupa nominal dengan maksimal dua angka desimal.',
            'opening_float.gte' => 'Modal awal kas tidak boleh negatif.',
            'idempotency_key.uuid' => 'Kunci permintaan buka shift tidak valid. Muat ulang halaman lalu coba kembali.',
        ];
    }
}
