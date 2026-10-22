<?php

namespace Database\Factories;

use App\Domain\Pricing\Enums\TaxRoundingMode;
use App\Domain\Pricing\Models\TaxRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TaxRule>
 */
class TaxRuleFactory extends Factory
{
    protected $model = TaxRule::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('TAX-##??'),
            'name' => fake()->unique()->words(2, true),
            'rate' => '0.0000',
            'calculation_mode' => 'exclusive',
            'rounding_mode' => TaxRoundingMode::HalfUp,
            'rounding_scale' => 2,
            'effective_from' => now()->startOfDay(),
            'effective_to' => null,
            'is_active' => true,
            'created_by' => null,
        ];
    }
}
