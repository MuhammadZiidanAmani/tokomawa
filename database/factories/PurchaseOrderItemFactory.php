<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Unit;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseOrderItem>
 */
class PurchaseOrderItemFactory extends Factory
{
    protected $model = PurchaseOrderItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'purchase_order_id' => PurchaseOrder::factory(),
            'line_number' => 1,
            'product_id' => Product::factory(),
            'unit_id' => Unit::factory(),
            'product_sku_snapshot' => 'SKU-TEST',
            'product_name_snapshot' => fake()->words(3, true),
            'unit_code_snapshot' => 'PCS',
            'unit_name_snapshot' => 'Pieces',
            'unit_symbol_snapshot' => 'PCS',
            'conversion_factor' => '1.000000',
            'quantity' => '1.000',
            'base_quantity' => '1.000',
            'received_base_quantity' => '0.000',
            'unit_price' => '1000.00',
            'subtotal' => '1000.00',
        ];
    }
}
