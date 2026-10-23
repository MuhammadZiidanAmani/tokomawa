<?php

namespace App\Http\Requests\Purchasing;

use App\Domain\Purchasing\Models\GoodsReceipt;
use Illuminate\Foundation\Http\FormRequest;

class PostGoodsReceiptRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $receipt = $this->route('goods_receipt');

        return $receipt instanceof GoodsReceipt && ($this->user()?->can('post', $receipt) ?? false);
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
