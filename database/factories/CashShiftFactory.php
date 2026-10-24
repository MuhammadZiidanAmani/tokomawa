<?php

namespace Database\Factories;

use App\Domain\Sales\Models\CashShift;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Register;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Sales\Enums\CashShiftStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CashShift>
 */
class CashShiftFactory extends Factory
{
    protected $model = CashShift::class;

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
            'warehouse_id' => Warehouse::factory(),
            'cashier_id' => User::factory(),
            'status' => CashShiftStatus::Open,
            'opening_float' => '100000.00',
            'opened_at' => now(),
            'active_register_key' => fn (): string => 'register:'.Str::ulid(),
            'active_cashier_key' => fn (): string => 'cashier:'.Str::ulid(),
            'opening_key' => (string) Str::uuid(),
            'opened_by' => User::factory(),
        ];
    }
}
