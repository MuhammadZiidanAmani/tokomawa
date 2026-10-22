<?php

namespace Database\Factories;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Register;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Register>
 */
class RegisterFactory extends Factory
{
    protected $model = Register::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'code' => fake()->unique()->bothify('REG-###??'),
            'name' => 'Kasir '.fake()->numberBetween(1, 20),
            'is_active' => true,
            'created_by' => null,
        ];
    }
}
