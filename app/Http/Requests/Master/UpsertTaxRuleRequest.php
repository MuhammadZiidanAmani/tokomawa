<?php

namespace App\Http\Requests\Master;

use App\Domain\Pricing\Enums\TaxRoundingMode;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpsertTaxRuleRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('tax.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:64', 'regex:/^[A-Z0-9-]+$/'],
            'name' => ['required', 'string', 'max:255'],
            'rate' => ['required', 'decimal:0,4', 'between:0,100'],
            'calculation_mode' => ['required', Rule::in(['inclusive', 'exclusive', 'exempt'])],
            'rounding_mode' => ['required', Rule::enum(TaxRoundingMode::class)],
            'rounding_scale' => ['required', 'integer', 'between:0,4'],
            'effective_from' => ['required', 'date'],
            'effective_to' => ['nullable', 'date', 'after:effective_from'],
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
        return ['rate.between' => 'Tarif pajak harus berada di antara 0 dan 100 persen.', 'effective_to.after' => 'Tanggal akhir harus setelah tanggal mulai.'];
    }
}
