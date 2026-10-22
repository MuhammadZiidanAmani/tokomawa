<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Unit;
use App\Domain\Suppliers\Models\Supplier;
use App\Domain\Suppliers\Models\SupplierProduct;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierProduct>
 */
class SupplierProductFactory extends Factory
{
    protected $model = SupplierProduct::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => Supplier::factory(),
            'product_id' => Product::factory(),
            'purchase_unit_id' => Unit::factory(),
            'supplier_sku' => fake()->unique()->bothify('SP-#####'),
            'last_purchase_price' => '10000.00',
            'contract_price' => null,
            'minimum_order_qty' => '1.000',
            'lead_time_days' => 3,
            'priority' => 1,
            'is_primary' => false,
            'is_active' => true,
            'created_by' => null,
        ];
    }
}
