<?php

namespace App\Http\Requests\Master;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpsertShelfLocationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('locations.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'code' => [
                'required', 'string', 'max:40', 'regex:/^[A-Z0-9-]+$/',
                Rule::unique('shelf_locations')->where('warehouse_id', $this->integer('warehouse_id'))->ignore($this->route('location')),
            ],
            'name' => ['required', 'string', 'max:255'],
            'zone' => ['nullable', 'string', 'max:40'],
            'aisle' => ['nullable', 'string', 'max:40'],
            'level' => ['nullable', 'string', 'max:40'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['code' => Str::upper(trim((string) $this->input('code'))), 'name' => trim((string) $this->input('name')), 'is_active' => $this->boolean('is_active', true)]);
    }
}
