<?php

namespace Database\Factories;

use App\Domain\Sales\Models\Sale;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Register;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Sales\Enums\SaleStatus;
use App\Domain\Sales\Models\CashShift;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'warehouse_id' => Warehouse::factory(),
            'register_id' => Register::factory(),
            'cash_shift_id' => CashShift::factory(),
            'cashier_id' => User::factory(),
            'document_number' => 'SALE/'.now()->format('Y/m').'/'.fake()->unique()->numerify('######'),
            'status' => SaleStatus::Posted,
            'sold_at' => now(),
            'currency' => 'IDR',
            'subtotal' => '10000.00',
            'discount_total' => '0.00',
            'tax_total' => '0.00',
            'grand_total' => '10000.00',
            'cash_tendered' => '10000.00',
            'change_due' => '0.00',
            'posting_key' => (string) Str::uuid(),
            'posted_by' => User::factory(),
            'posted_at' => now(),
        ];
    }
}
