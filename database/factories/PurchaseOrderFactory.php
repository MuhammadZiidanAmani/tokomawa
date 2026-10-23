<?php

namespace Database\Factories;

use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Organization\Models\Branch;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Suppliers\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PurchaseOrder>
 */
class PurchaseOrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'supplier_id' => Supplier::factory(),
            'document_number' => 'PO/'.fake()->date('Y/m').'/'.fake()->unique()->numerify('######'),
            'status' => PurchaseOrderStatus::Draft,
            'order_date' => now()->toDateString(),
            'expected_at' => now()->addDays(7)->toDateString(),
            'currency' => 'IDR',
            'supplier_code_snapshot' => 'SUP-TEST',
            'supplier_name_snapshot' => fake()->company(),
            'supplier_address_snapshot' => fake()->address(),
            'subtotal' => '0.00',
            'grand_total' => '0.00',
            'creation_key' => (string) Str::uuid(),
            'created_by' => User::factory(),
        ];
    }
}
