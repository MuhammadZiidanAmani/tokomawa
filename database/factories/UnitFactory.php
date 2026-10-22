<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('U-##??'),
            'name' => fake()->unique()->word(),
            'symbol' => fake()->unique()->lexify('???'),
            'allow_fractional' => false,
            'decimal_places' => 0,
            'is_active' => true,
            'created_by' => null,
        ];
    }
}
