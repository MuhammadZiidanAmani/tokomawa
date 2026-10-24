<?php

namespace Database\Factories;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Register;
use App\Domain\Sales\Enums\CashMovementType;
use App\Domain\Sales\Models\CashMovement;
use App\Domain\Sales\Models\CashShift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CashMovement>
 */
class CashMovementFactory extends Factory
{
    protected $model = CashMovement::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'register_id' => Register::factory(),
            'cash_shift_id' => CashShift::factory(),
            'movement_type' => CashMovementType::OpeningFloat,
            'amount_delta' => '100000.00',
            'source_key' => fn (): string => 'cash:'.Str::ulid(),
            'description' => 'Ledger kas pengujian',
            'occurred_at' => now(),
            'created_by' => User::factory(),
        ];
    }
}
