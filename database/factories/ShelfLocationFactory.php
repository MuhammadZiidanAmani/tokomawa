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

    public function configure(): static
    {
        return $this->afterMaking(function (ShelfLocation $location): void {
            $branch = $location->branch_id === null
                ? Branch::factory()->create()
                : Branch::query()->findOrFail($location->branch_id);

            $location->branch_id = $branch->getKey();
            $location->warehouse_id ??= Warehouse::factory()->for($branch)->create()->getKey();
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => null,
            'warehouse_id' => null,
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
