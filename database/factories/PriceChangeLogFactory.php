<?php

namespace Database\Factories;

use App\Domain\Pricing\Models\PriceChangeLog;
use App\Domain\Pricing\Models\ProductPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceChangeLog>
 */
class PriceChangeLogFactory extends Factory
{
    protected $model = PriceChangeLog::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = ProductPrice::factory();

        return [
            'product_price_id' => $price,
            'product_id' => fn (array $attributes) => ProductPrice::query()->findOrFail($attributes['product_price_id'])->product_id,
            'price_list_id' => fn (array $attributes) => ProductPrice::query()->findOrFail($attributes['product_price_id'])->price_list_id,
            'unit_id' => fn (array $attributes) => ProductPrice::query()->findOrFail($attributes['product_price_id'])->unit_id,
            'change_type' => 'created',
            'previous_amount' => null,
            'new_amount' => '12500.00',
            'previous_effective_from' => null,
            'previous_effective_to' => null,
            'new_effective_from' => now()->startOfDay(),
            'new_effective_to' => null,
            'reason' => 'Data pengujian',
            'changed_by' => null,
            'changed_at' => now(),
        ];
    }
}
