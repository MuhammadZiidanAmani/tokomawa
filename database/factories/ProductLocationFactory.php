<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductLocation;
use App\Domain\Catalog\Models\ShelfLocation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductLocation>
 */
class ProductLocationFactory extends Factory
{
    protected $model = ProductLocation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'shelf_location_id' => ShelfLocation::factory(),
            'is_primary' => true,
            'is_active' => true,
            'created_by' => null,
        ];
    }
}
