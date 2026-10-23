<?php

namespace App\Http\Requests\Purchasing;

use App\Domain\Purchasing\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;

class DecidePurchaseOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $purchaseOrder = $this->route('purchase_order');

        return $purchaseOrder instanceof PurchaseOrder
            && ($this->user()?->can('approve', $purchaseOrder) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'decision_notes' => ['required', 'string', 'min:10', 'max:1000'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
