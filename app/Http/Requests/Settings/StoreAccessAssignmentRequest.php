<?php

namespace App\Http\Requests\Settings;

use App\Domain\Access\Models\Role;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAccessAssignmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('access.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'role_ids' => ['required', 'array', 'min:1'],
            'role_ids.*' => ['required', 'integer', 'distinct', Rule::exists('roles', 'id')->where('is_active', true)],
            'branch_ids' => ['nullable', 'array'],
            'branch_ids.*' => ['required', 'integer', 'distinct', Rule::exists('branches', 'id')->where('is_active', true)],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $actor = $this->user();
            $branchIds = array_map('intval', $this->input('branch_ids', []));
            $roleIds = array_map('intval', $this->input('role_ids', []));

            if ($actor !== null && ! $actor->hasRole('super_admin')) {
                $allowedBranchIds = $actor->branches()->pluck('branches.id')->map(fn ($id): int => (int) $id)->all();
                if (array_diff($branchIds, $allowedBranchIds) !== []) {
                    $validator->errors()->add('branch_ids', 'Cabang yang dipilih berada di luar akses Anda.');
                }

                $assignsSuperAdmin = Role::query()
                    ->whereKey($roleIds)
                    ->where('code', 'super_admin')
                    ->exists();

                if ($assignsSuperAdmin) {
                    $validator->errors()->add('role_ids', 'Hanya Super Admin yang dapat memberikan role Super Admin.');
                }
            }

            $onlySuperAdmin = Role::query()->whereKey($roleIds)->where('code', 'super_admin')->count() === count($roleIds);
            if ($branchIds === [] && ! $onlySuperAdmin) {
                $validator->errors()->add('branch_ids', 'Minimal satu cabang wajib dipilih untuk role operasional.');
            }

            if (User::query()->whereKey($this->integer('user_id'))->doesntExist()) {
                $validator->errors()->add('user_id', 'Pengguna tidak ditemukan.');
            }
        }];
    }

    public function messages(): array
    {
        return [
            'user_id.required' => 'Pengguna wajib dipilih.',
            'role_ids.required' => 'Minimal satu role wajib dipilih.',
            'role_ids.min' => 'Minimal satu role wajib dipilih.',
            'reason.required' => 'Alasan perubahan akses wajib diisi.',
            'reason.min' => 'Alasan perubahan akses minimal 5 karakter.',
        ];
    }
}
