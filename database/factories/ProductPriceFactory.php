<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Unit;
use App\Domain\Pricing\Models\PriceList;
use App\Domain\Pricing\Models\ProductPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProductPrice>
 */
class ProductPriceFactory extends Factory
{
    protected $model = ProductPrice::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'price_list_id' => PriceList::factory(),
            'unit_id' => Unit::factory(),
            'amount' => '12500.00',
            'currency' => 'IDR',
            'minimum_quantity' => '1.000',
            'effective_from' => now()->startOfDay(),
            'effective_to' => null,
            'priority' => 100,
            'is_active' => true,
            'created_by' => null,
        ];
    }
}
