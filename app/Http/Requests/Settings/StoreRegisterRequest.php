<?php

namespace App\Http\Requests\Settings;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Register;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreRegisterRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $branch = $this->route('branch');

        return $branch instanceof Branch
            && ($this->user()?->can('create', [Register::class, $branch]) ?? false);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $branch = $this->route('branch');

        return [
            'code' => [
                'required', 'string', 'max:20', 'regex:/^[A-Z0-9-]+$/',
                Rule::unique('registers', 'code')->where('branch_id', $branch?->getKey()),
            ],
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => Str::upper(trim((string) $this->input('code'))),
            'name' => trim((string) $this->input('name')),
        ]);
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode register wajib diisi.',
            'code.regex' => 'Kode register hanya boleh berisi huruf kapital, angka, dan tanda hubung.',
            'code.unique' => 'Kode register sudah digunakan pada cabang ini.',
            'name.required' => 'Nama register wajib diisi.',
        ];
    }
}
