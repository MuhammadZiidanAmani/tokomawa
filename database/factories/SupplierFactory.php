<?php

namespace Database\Factories;

use App\Domain\Suppliers\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('SUP-####'),
            'name' => fake()->company(),
            'address' => fake()->optional()->address(),
            'phone' => fake()->optional()->phoneNumber(),
            'email' => fake()->optional()->companyEmail(),
            'tax_identifier' => null,
            'contact_name' => fake()->optional()->name(),
            'payment_terms_days' => null,
            'bank_name' => null,
            'bank_account_name' => null,
            'bank_account_number' => null,
            'is_active' => true,
            'created_by' => null,
        ];
    }
}
