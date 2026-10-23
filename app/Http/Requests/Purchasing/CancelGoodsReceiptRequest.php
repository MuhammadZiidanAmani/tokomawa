<?php

namespace App\Http\Requests\Purchasing;

use App\Domain\Purchasing\Models\GoodsReceipt;
use Illuminate\Foundation\Http\FormRequest;

class CancelGoodsReceiptRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $receipt = $this->route('goods_receipt');

        return $receipt instanceof GoodsReceipt && ($this->user()?->can('cancel', $receipt) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['reason' => 'alasan pembatalan'];
    }
}
