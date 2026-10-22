<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'department_id' => Department::factory(),
            'code' => fake()->unique()->bothify('CAT-##??'),
            'name' => fake()->unique()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'is_active' => true,
            'created_by' => null,
        ];
    }
}
