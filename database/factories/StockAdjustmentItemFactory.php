<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\StockAdjustment;
use App\Domain\Inventory\Models\StockAdjustmentItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StockAdjustmentItem> */
class StockAdjustmentItemFactory extends Factory
{
    protected $model = StockAdjustmentItem::class;

    public function definition(): array
    {
        $product = Product::factory();

        return [
            'stock_adjustment_id' => StockAdjustment::factory(),
            'line_number' => 1,
            'product_id' => $product,
            'unit_id' => fn (array $attributes) => Product::query()->findOrFail($attributes['product_id'])->base_unit_id,
            'inventory_batch_id' => null,
            'quantity' => '1.000',
            'base_quantity' => '1.000',
            'unit_cost' => '1000.000000',
            'stock_status' => 'sellable',
            'notes' => null,
        ];
    }
}
