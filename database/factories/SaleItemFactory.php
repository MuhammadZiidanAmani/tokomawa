<?php

namespace Database\Factories;

use App\Domain\Sales\Models\SaleItem;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Unit;
use App\Domain\Inventory\Models\InventoryMovement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SaleItem>
 */
class SaleItemFactory extends Factory
{
    protected $model = SaleItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'sale_id' => Sale::factory(),
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
            'unit_price_snapshot' => '10000.00',
            'price_list_code_snapshot' => 'RETAIL',
            'price_list_name_snapshot' => 'Harga Retail',
            'subtotal' => '10000.00',
            'discount_amount' => '0.00',
            'tax_amount' => '0.00',
            'line_total' => '10000.00',
            'cogs_unit_snapshot' => '7000.000000',
            'cogs_total_snapshot' => '7000.000000',
            'inventory_movement_id' => fn () => InventoryMovement::query()->inRandomOrder()->value('id'),
        ];
    }
}
