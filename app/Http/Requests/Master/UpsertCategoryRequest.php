<?php

namespace App\Http\Requests\Master;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpsertCategoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('catalog.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'department_id' => ['required', 'integer', Rule::exists('departments', 'id')->where('is_active', true)],
            'code' => [
                'required', 'string', 'max:40', 'regex:/^[A-Z0-9-]+$/',
                Rule::unique('categories')->where('department_id', $this->integer('department_id'))->ignore($this->route('category')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => Str::upper(trim((string) $this->input('code'))), 'name' => trim((string) $this->input('name')), 'is_active' => $this->boolean('is_active', true)]);
    }

    public function messages(): array
    {
        return ['department_id.exists' => 'Departemen aktif tidak ditemukan.', 'code.unique' => 'Kode kategori sudah digunakan pada departemen tersebut.'];
    }
}
