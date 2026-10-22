<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<InventoryBalance> */
class InventoryBalanceFactory extends Factory
{
    protected $model = InventoryBalance::class;

    public function definition(): array
    {
        $branch = Branch::factory();

        return [
            'branch_id' => $branch,
            'warehouse_id' => Warehouse::factory()->for($branch),
            'product_id' => Product::factory(),
            'inventory_batch_id' => null,
            'batch_scope' => 'NO-BATCH',
            'stock_status' => 'sellable',
            'qty_on_hand' => '0.000',
            'qty_reserved' => '0.000',
            'avg_unit_cost' => '0.000000',
            'inventory_value' => '0.000000',
            'version' => 0,
        ];
    }
}
