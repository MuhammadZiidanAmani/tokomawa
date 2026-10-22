<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\InventoryBatch;
use App\Domain\Organization\Models\Branch;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventoryBatch> */
class InventoryBatchFactory extends Factory
{
    protected $model = InventoryBatch::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'product_id' => Product::factory(),
            'lot_number' => fake()->unique()->bothify('LOT-####'),
            'manufactured_at' => null,
            'expires_at' => null,
            'is_active' => true,
            'created_by' => null,
        ];
    }
}
