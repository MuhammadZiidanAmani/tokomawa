<?php

namespace Database\Factories;

use App\Domain\Access\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->jobTitle();

        return [
            'code' => Str::slug($name, '_'),
            'name' => $name,
            'is_system' => false,
            'is_active' => true,
        ];
    }
}
