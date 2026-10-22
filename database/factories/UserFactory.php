<?php

namespace Database\Factories;

use App\Domain\Access\Models\Role;
use App\Domain\Organization\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'is_active' => true,
            'last_login_at' => null,
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the user cannot sign in.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    public function withRole(string $roleCode): static
    {
        return $this->afterCreating(function (User $user) use ($roleCode): void {
            $role = Role::query()->where('code', $roleCode)->firstOrFail();
            $user->roles()->syncWithoutDetaching($role);
        });
    }

    public function forBranch(Branch $branch, bool $isDefault = true): static
    {
        return $this->afterCreating(function (User $user) use ($branch, $isDefault): void {
            $user->branches()->syncWithoutDetaching([
                $branch->getKey() => ['is_default' => $isDefault],
            ]);
        });
    }
}
