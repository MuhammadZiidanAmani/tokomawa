<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductBarcode;
use App\Domain\Catalog\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductBarcode>
 */
class ProductBarcodeFactory extends Factory
{
    protected $model = ProductBarcode::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'unit_id' => Unit::factory(),
            'barcode' => fake()->unique()->ean13(),
            'is_primary' => true,
            'is_active' => true,
            'created_by' => null,
        ];
    }
}
