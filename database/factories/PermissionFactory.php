<?php

namespace Database\Factories;

use App\Domain\Access\Models\Permission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Permission>
 */
class PermissionFactory extends Factory
{
    protected $model = Permission::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('module.????'),
            'name' => fake()->sentence(3),
            'module' => 'test',
        ];
    }
}
