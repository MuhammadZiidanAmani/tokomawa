<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Subcategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subcategory>
 */
class SubcategoryFactory extends Factory
{
    protected $model = Subcategory::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'category_id' => Category::factory(),
            'code' => fake()->unique()->bothify('SUB-##??'),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
            'created_by' => null,
        ];
    }
}
