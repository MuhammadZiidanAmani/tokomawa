<?php

namespace App\Http\Requests\Purchasing;

use App\Domain\Purchasing\Models\GoodsReceipt;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpsertGoodsReceiptRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $receipt = $this->route('goods_receipt');

        return $receipt instanceof GoodsReceipt
            ? ($this->user()?->can('update', $receipt) ?? false)
            : ($this->user()?->can('create', GoodsReceipt::class) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'purchase_order_id' => ['required', 'integer', Rule::exists('purchase_orders', 'id')],
            'warehouse_id' => ['required', 'integer', Rule::exists('warehouses', 'id')->where('is_active', true)],
            'supplier_delivery_note' => ['required', 'string', 'max:100'],
            'received_at' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'idempotency_key' => ['required', 'uuid'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.purchase_order_item_id' => ['required', 'integer', 'distinct', Rule::exists('purchase_order_items', 'id')],
            'items.*.quantity' => ['required', 'decimal:0,3', 'gt:0', 'max:999999999999999.999'],
            'items.*.lot_number' => ['nullable', 'string', 'max:100'],
            'items.*.manufactured_at' => ['nullable', 'date'],
            'items.*.expires_at' => ['nullable', 'date'],
            'items.*.notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'purchase_order_id' => 'Purchase Order',
            'warehouse_id' => 'gudang penerimaan',
            'supplier_delivery_note' => 'nomor surat jalan supplier',
            'received_at' => 'waktu penerimaan',
            'items' => 'item penerimaan',
            'items.*.purchase_order_item_id' => 'item Purchase Order',
            'items.*.quantity' => 'kuantitas diterima',
            'items.*.lot_number' => 'nomor batch',
            'items.*.manufactured_at' => 'tanggal produksi',
            'items.*.expires_at' => 'tanggal kedaluwarsa',
        ];
    }
}
