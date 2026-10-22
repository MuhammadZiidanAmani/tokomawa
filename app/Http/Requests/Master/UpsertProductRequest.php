<?php

namespace App\Http\Requests\Master;

use App\Domain\Catalog\Enums\ProductClassification;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Subcategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Unique;
use Illuminate\Validation\Validator;

class UpsertProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $product = $this->route('product');

        return $this->user()?->can($product instanceof Product ? 'update' : 'create', $product ?? Product::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:64', 'regex:/^[A-Z0-9._-]+$/', Rule::unique('products')->ignore($this->route('product'))],
            'name' => ['required', 'string', 'max:255'],
            'classification' => ['required', Rule::enum(ProductClassification::class)],
            'department_id' => ['nullable', 'integer', Rule::exists('departments', 'id')->where('is_active', true)],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('is_active', true)],
            'subcategory_id' => ['nullable', 'integer', Rule::exists('subcategories', 'id')->where('is_active', true)],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')->where('is_active', true)],
            'base_unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where('is_active', true)],
            'purchase_unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where('is_active', true)],
            'purchase_conversion_factor' => ['required', 'decimal:0,6', 'gt:0'],
            'sale_unit_id' => ['required', 'integer', Rule::exists('units', 'id')->where('is_active', true)],
            'sale_conversion_factor' => ['required', 'decimal:0,6', 'gt:0'],
            'default_supplier_id' => ['nullable', 'integer', Rule::exists('suppliers', 'id')->where('is_active', true)],
            'tax_rule_id' => ['nullable', 'integer', Rule::exists('tax_rules', 'id')->where('is_active', true)],
            'primary_barcode' => ['nullable', 'string', 'max:64', $this->barcodeUniqueRule()],
            'alternate_barcodes' => ['array', 'max:20'],
            'alternate_barcodes.*' => ['string', 'max:64', 'distinct', $this->barcodeUniqueRule()],
            'description' => ['nullable', 'string', 'max:3000'],
            'track_batch' => ['required', 'boolean'],
            'track_expiry' => ['required', 'boolean'],
            'allow_fractional_qty' => ['required', 'boolean'],
            'min_stock' => ['required', 'decimal:0,3', 'gte:0'],
            'is_active' => ['required', 'boolean'],
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['department_id', 'category_id', 'subcategory_id'])) {
                return;
            }

            $departmentId = $this->integer('department_id') ?: null;
            $categoryId = $this->integer('category_id') ?: null;
            $subcategoryId = $this->integer('subcategory_id') ?: null;

            if ($categoryId !== null && ($departmentId === null || ! Category::query()->whereKey($categoryId)->where('department_id', $departmentId)->exists())) {
                $validator->errors()->add('category_id', 'Kategori harus berada pada departemen yang dipilih.');
            }

            if ($subcategoryId !== null && ($categoryId === null || ! Subcategory::query()->whereKey($subcategoryId)->where('category_id', $categoryId)->exists())) {
                $validator->errors()->add('subcategory_id', 'Subkategori harus berada pada kategori yang dipilih.');
            }

            if ($this->integer('purchase_unit_id') === $this->integer('base_unit_id') && ! $this->isDecimalOne('purchase_conversion_factor')) {
                $validator->errors()->add('purchase_conversion_factor', 'Faktor satuan dasar wajib bernilai 1.');
            }

            if ($this->integer('sale_unit_id') === $this->integer('base_unit_id') && ! $this->isDecimalOne('sale_conversion_factor')) {
                $validator->errors()->add('sale_conversion_factor', 'Faktor satuan dasar wajib bernilai 1.');
            }

            if (
                $this->integer('purchase_unit_id') === $this->integer('sale_unit_id')
                && $this->normalizeDecimal('purchase_conversion_factor') !== $this->normalizeDecimal('sale_conversion_factor')
            ) {
                $validator->errors()->add('sale_conversion_factor', 'Satuan yang sama harus memakai faktor konversi yang sama.');
            }

            if (in_array($this->input('primary_barcode'), $this->input('alternate_barcodes', []), true)) {
                $validator->errors()->add('alternate_barcodes', 'Barcode utama tidak boleh diulang sebagai barcode alternatif.');
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        $alternateBarcodes = collect(preg_split('/[\r\n,]+/', (string) $this->input('alternate_barcodes_text')))
            ->map(fn (string $barcode): string => trim($barcode))
            ->filter()
            ->values()
            ->all();

        $this->merge([
            'sku' => Str::upper(trim((string) $this->input('sku'))),
            'name' => trim((string) $this->input('name')),
            'primary_barcode' => trim((string) $this->input('primary_barcode')) ?: null,
            'alternate_barcodes' => $alternateBarcodes,
            'track_batch' => $this->boolean('track_batch'),
            'track_expiry' => $this->boolean('track_expiry'),
            'allow_fractional_qty' => $this->boolean('allow_fractional_qty'),
            'is_active' => $this->boolean('is_active', true),
        ]);
    }

    public function messages(): array
    {
        return [
            'sku.unique' => 'SKU sudah digunakan barang lain.',
            'primary_barcode.unique' => 'Barcode utama sudah digunakan barang lain.',
            'alternate_barcodes.*.unique' => 'Salah satu barcode alternatif sudah digunakan barang lain.',
            'purchase_conversion_factor.gt' => 'Faktor konversi pembelian harus lebih besar dari nol.',
            'sale_conversion_factor.gt' => 'Faktor konversi penjualan harus lebih besar dari nol.',
        ];
    }

    private function barcodeUniqueRule(): Unique
    {
        $productId = $this->route('product') instanceof Product ? $this->route('product')->getKey() : 0;

        return Rule::unique('product_barcodes', 'barcode')->where(
            fn ($query) => $query->where('product_id', '!=', $productId),
        );
    }

    private function isDecimalOne(string $field): bool
    {
        return preg_match('/^0*1(?:\.0{1,6})?$/', $this->string($field)->toString()) === 1;
    }

    private function normalizeDecimal(string $field): string
    {
        $value = ltrim($this->string($field)->toString(), '0');
        [$integer, $fraction] = array_pad(explode('.', $value, 2), 2, '');

        return ($integer === '' ? '0' : $integer).'.'.rtrim($fraction, '0');
    }
}
