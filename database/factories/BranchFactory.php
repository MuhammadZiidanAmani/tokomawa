<?php

namespace Database\Factories;

use App\Domain\Organization\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('CBG-###??'),
            'name' => fake()->company(),
            'timezone' => 'Asia/Jakarta',
            'is_active' => true,
            'created_by' => null,
        ];
    }
}
