<?php

namespace Database\Factories;

use App\Domain\Sales\Models\SalePayment;
use App\Domain\Organization\Models\Branch;
use App\Domain\Sales\Enums\PaymentMethod;
use App\Domain\Sales\Models\CashShift;
use App\Domain\Sales\Models\Sale;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<SalePayment>
 */
class SalePaymentFactory extends Factory
{
    protected $model = SalePayment::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
            'branch_id' => Branch::factory(),
            'cash_shift_id' => CashShift::factory(),
            'method' => PaymentMethod::Cash,
            'status' => 'settled',
            'amount' => '10000.00',
            'tendered_amount' => '10000.00',
            'change_amount' => '0.00',
            'source_key' => fn (): string => 'sale:'.Str::ulid().':cash',
            'paid_at' => now(),
            'created_by' => User::factory(),
        ];
    }
}
