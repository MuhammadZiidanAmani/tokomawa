<?php

namespace App\Http\Requests\Settings;

use App\Domain\Organization\Models\Branch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApprovalLimitRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $branch = $this->route('branch');

        return $branch instanceof Branch
            && ($this->user()?->can('approval_limits.manage') ?? false)
            && ($this->user()?->canAccessBranch($branch) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')->where('is_active', true)],
            'permission_id' => ['required', 'integer', Rule::exists('permissions', 'id')],
            'minimum_amount' => ['required', 'numeric', 'decimal:0,2', 'min:0'],
            'maximum_amount' => ['nullable', 'numeric', 'decimal:0,2', 'gte:minimum_amount'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'role_id.required' => 'Role wajib dipilih.',
            'permission_id.required' => 'Jenis persetujuan wajib dipilih.',
            'minimum_amount.required' => 'Batas minimum wajib diisi.',
            'maximum_amount.gte' => 'Batas maksimum harus sama dengan atau lebih besar dari batas minimum.',
            'reason.required' => 'Alasan konfigurasi wajib diisi.',
            'reason.min' => 'Alasan konfigurasi minimal 5 karakter.',
        ];
    }
}
