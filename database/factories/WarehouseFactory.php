<?php

namespace Database\Factories;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'code' => fake()->unique()->bothify('GDG-###??'),
            'name' => 'Gudang '.fake()->city(),
            'is_active' => true,
            'created_by' => null,
        ];
    }
}
