<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Brand;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    protected $model = Brand::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('BRD-##??'),
            'name' => fake()->unique()->company(),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
            'created_by' => null,
        ];
    }
}
