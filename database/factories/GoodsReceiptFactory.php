<?php

namespace Database\Factories;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Purchasing\Enums\GoodsReceiptStatus;
use App\Domain\Purchasing\Models\GoodsReceipt;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Suppliers\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GoodsReceipt>
 */
class GoodsReceiptFactory extends Factory
{
    protected $model = GoodsReceipt::class;

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
            'purchase_order_id' => PurchaseOrder::factory(),
            'supplier_id' => Supplier::factory(),
            'document_number' => 'GR/'.fake()->date('Y/m').'/'.fake()->unique()->numerify('######'),
            'status' => GoodsReceiptStatus::Draft,
            'supplier_delivery_note' => fake()->unique()->bothify('SJ-####'),
            'supplier_delivery_note_normalized' => fn (array $attributes): string => Str::upper($attributes['supplier_delivery_note']),
            'received_at' => now(),
            'supplier_code_snapshot' => 'SUP-TEST',
            'supplier_name_snapshot' => fake()->company(),
            'total_base_quantity' => '0.000',
            'total_value' => '0.00',
            'creation_key' => (string) Str::uuid(),
            'created_by' => User::factory(),
        ];
    }
}
