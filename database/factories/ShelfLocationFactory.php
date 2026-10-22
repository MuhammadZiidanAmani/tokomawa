<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\ShelfLocation;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShelfLocation>
 */
class ShelfLocationFactory extends Factory
{
    protected $model = ShelfLocation::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $branch = Branch::factory();

        return [
            'branch_id' => $branch,
            'warehouse_id' => Warehouse::factory()->for($branch),
            'code' => fake()->unique()->bothify('RAK-##??'),
            'name' => 'Rak '.fake()->word(),
            'zone' => fake()->optional()->randomLetter(),
            'aisle' => fake()->optional()->numerify('##'),
            'level' => fake()->optional()->numerify('#'),
            'description' => null,
            'is_active' => true,
            'created_by' => null,
        ];
    }
}
