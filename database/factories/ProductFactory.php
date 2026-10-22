<?php

namespace Database\Factories;

use App\Domain\Catalog\Enums\ProductClassification;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sku' => fake()->unique()->bothify('SKU-#####'),
            'name' => fake()->words(3, true),
            'classification' => ProductClassification::Stock,
            'department_id' => null,
            'category_id' => null,
            'subcategory_id' => null,
            'brand_id' => null,
            'base_unit_id' => Unit::factory(),
            'default_supplier_id' => null,
            'tax_rule_id' => null,
            'description' => fake()->optional()->sentence(),
            'track_batch' => false,
            'track_expiry' => false,
            'allow_fractional_qty' => false,
            'min_stock' => '0.000',
            'is_active' => true,
            'created_by' => null,
        ];
    }
}
