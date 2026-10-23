<?php

namespace App\Http\Requests\Purchasing;

use App\Domain\Purchasing\Models\PurchaseOrder;
use Illuminate\Foundation\Http\FormRequest;

class SendPurchaseOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $purchaseOrder = $this->route('purchase_order');

        return $purchaseOrder instanceof PurchaseOrder
            && ($this->user()?->can('send', $purchaseOrder) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
