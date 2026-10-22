<?php

namespace App\Http\Requests\Inventory;

use Illuminate\Foundation\Http\FormRequest;

class ApproveOpeningBalanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('inventory.opening.approve') ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'decision_notes' => ['required', 'string', 'min:10', 'max:1000'],
            'idempotency_key' => ['required', 'uuid'],
        ];
    }
}
