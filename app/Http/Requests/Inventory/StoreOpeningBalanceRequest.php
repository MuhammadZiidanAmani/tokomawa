<?php

namespace App\Http\Requests\Inventory;

use App\Domain\Inventory\Enums\StockStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOpeningBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.opening.create') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'occurred_at' => ['required', 'date', 'before_or_equal:now'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'submission_key' => ['required', 'uuid'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => ['required', 'integer', 'distinct', 'exists:products,id'],
            'items.*.unit_id' => ['required', 'integer', 'exists:units,id'],
            'items.*.quantity' => ['required', 'decimal:0,3', 'gt:0', 'max:999999999999999.999'],
            'items.*.unit_cost' => ['required', 'decimal:0,6', 'gte:0', 'max:999999999999.999999'],
            'items.*.stock_status' => ['required', Rule::enum(StockStatus::class)],
            'items.*.lot_number' => ['nullable', 'string', 'max:100'],
            'items.*.manufactured_at' => ['nullable', 'date'],
            'items.*.expires_at' => ['nullable', 'date', 'after_or_equal:items.*.manufactured_at'],
            'items.*.notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'warehouse_id' => 'gudang tujuan',
            'occurred_at' => 'tanggal saldo awal',
            'reason' => 'alasan',
            'items.*.product_id' => 'barang',
            'items.*.unit_id' => 'satuan',
            'items.*.quantity' => 'kuantitas',
            'items.*.unit_cost' => 'HPP satuan',
        ];
    }
}
