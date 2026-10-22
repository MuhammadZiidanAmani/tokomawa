<?php

namespace Database\Factories;

use App\Domain\Pricing\Models\PriceList;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PriceList>
 */
class PriceListFactory extends Factory
{
    protected $model = PriceList::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('PRICE-##??'),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'priority' => 100,
            'is_active' => true,
            'created_by' => null,
        ];
    }
}
