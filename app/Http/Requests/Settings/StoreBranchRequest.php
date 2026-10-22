<?php

namespace App\Http\Requests\Settings;

use App\Domain\Organization\Models\Branch;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreBranchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Branch::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:20', 'regex:/^[A-Z0-9-]+$/', 'unique:branches,code'],
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', 'string', 'timezone:all'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'code' => Str::upper(trim((string) $this->input('code'))),
            'name' => trim((string) $this->input('name')),
            'timezone' => trim((string) $this->input('timezone', 'Asia/Jakarta')),
        ]);
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode cabang wajib diisi.',
            'code.regex' => 'Kode cabang hanya boleh berisi huruf kapital, angka, dan tanda hubung.',
            'code.unique' => 'Kode cabang sudah digunakan.',
            'name.required' => 'Nama cabang wajib diisi.',
            'timezone.timezone' => 'Zona waktu cabang tidak valid.',
        ];
    }
}
