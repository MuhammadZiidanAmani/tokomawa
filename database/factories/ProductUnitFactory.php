<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductUnit;
use App\Domain\Catalog\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductUnit>
 */
class ProductUnitFactory extends Factory
{
    protected $model = ProductUnit::class;

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
            'conversion_factor' => '1.000000',
            'is_purchase_unit' => true,
            'is_sale_unit' => true,
            'is_active' => true,
            'created_by' => null,
        ];
    }
}
