<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Unit;
use App\Domain\Purchasing\Models\GoodsReceipt;
use App\Domain\Purchasing\Models\GoodsReceiptItem;
use App\Domain\Purchasing\Models\PurchaseOrderItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoodsReceiptItem>
 */
class GoodsReceiptItemFactory extends Factory
{
    protected $model = GoodsReceiptItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'goods_receipt_id' => GoodsReceipt::factory(),
            'purchase_order_item_id' => PurchaseOrderItem::factory(),
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
            'unit_price_snapshot' => '1000.00',
            'base_unit_cost_snapshot' => '1000.000000',
            'line_total' => '1000.00',
            'track_batch_snapshot' => false,
            'track_expiry_snapshot' => false,
            'stock_status' => 'sellable',
        ];
    }
}
