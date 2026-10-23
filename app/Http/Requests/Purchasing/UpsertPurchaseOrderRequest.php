<?php

namespace App\Http\Requests\Purchasing;

use App\Domain\Purchasing\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertPurchaseOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $purchaseOrder = $this->route('purchase_order');

        return $purchaseOrder instanceof PurchaseOrder
            ? ($this->user()?->can('update', $purchaseOrder) ?? false)
            : ($this->user()?->can('create', PurchaseOrder::class) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', Rule::exists('suppliers', 'id')->where('is_active', true)],
            'order_date' => ['required', 'date'],
            'expected_at' => ['nullable', 'date', 'after_or_equal:order_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'uuid'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'distinct', Rule::exists('products', 'id')->where('is_active', true)],
            'items.*.unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where('is_active', true)],
            'items.*.quantity' => ['required', 'decimal:0,3', 'gt:0', 'max:999999999999999.999'],
            'items.*.unit_price' => ['required', 'decimal:0,2', 'gte:0', 'max:9999999999999999.99'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'supplier_id' => 'supplier',
            'order_date' => 'tanggal PO',
            'expected_at' => 'estimasi kedatangan',
            'notes' => 'catatan',
            'items' => 'item PO',
            'items.*.product_id' => 'barang',
            'items.*.unit_id' => 'satuan pembelian',
            'items.*.quantity' => 'kuantitas',
            'items.*.unit_price' => 'harga beli',
        ];
    }
}
