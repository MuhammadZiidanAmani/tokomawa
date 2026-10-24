<?php

namespace Tests\Feature;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductBarcode;
use App\Domain\Catalog\Models\Unit;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Register;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Pricing\Models\PriceList;
use App\Domain\Pricing\Models\ProductPrice;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Services\CashShiftService;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PosControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_redirects_and_cashier_without_shift_is_sent_to_open_shift(): void
    {
        $this->get(route('pos.index'))->assertRedirect(route('login'));
        $this->seed();
        $branch = Branch::factory()->create();
        Register::factory()->for($branch)->create();
        Warehouse::factory()->for($branch)->create();
        $cashier = User::factory()->withRole('cashier')->forBranch($branch)->create();

        $this->actingAs($cashier)->get(route('pos.index'))
            ->assertRedirect(route('cash-shifts.create'))
            ->assertSessionHasErrors('cash_shift');
        $this->actingAs($cashier)->get(route('cash-shifts.create'))
            ->assertOk()->assertSee('Buka Shift Kasir')->assertSee($branch->name);
    }

    public function test_cashier_can_lookup_barcode_and_checkout_through_json_http(): void
    {
        [$branch, $register, $warehouse, $cashier, $product, $unit] = $this->context();
        ProductBarcode::factory()->create(['product_id' => $product->id, 'unit_id' => $unit->id, 'barcode' => '8990000000001', 'is_active' => true]);
        $shift = app(CashShiftService::class)->open($cashier, $branch, $register->id, $warehouse->id, '100000.00', (string) Str::uuid());

        $this->actingAs($cashier)->get(route('pos.index'))
            ->assertOk()->assertSee('Total Belanja')->assertSee($register->name)->assertSee('F9');
        $this->actingAs($cashier)->getJson(route('pos.lookup', ['q' => '8990000000001']))
            ->assertOk()->assertJsonPath('data.0.product_id', $product->id)->assertJsonPath('data.0.price', '15000.00');
        $response = $this->actingAs($cashier)->postJson(route('pos.checkout'), [
            'cash_shift_id' => $shift->id, 'cash_tendered' => '20000.00', 'idempotency_key' => (string) Str::uuid(),
            'items' => [['product_id' => $product->id, 'unit_id' => $unit->id, 'inventory_batch_id' => null, 'quantity' => '1.000']],
        ]);
        $sale = Sale::query()->sole();

        $response->assertCreated()->assertJsonPath('sale_id', $sale->public_id)->assertJsonPath('change_due', '5000.00');
        $this->actingAs($cashier)->get(route('sales.show', $sale))
            ->assertOk()->assertSee($sale->document_number)->assertSee($product->name)->assertSee('Transaksi Baru');
    }

    public function test_invalid_payload_rbac_and_cross_branch_sale_are_rejected(): void
    {
        [$branch, $register, $warehouse, $cashier] = $this->context();
        $shift = app(CashShiftService::class)->open($cashier, $branch, $register->id, $warehouse->id, '0', (string) Str::uuid());
        $this->actingAs($cashier)->postJson(route('pos.checkout'), [
            'cash_shift_id' => $shift->id, 'cash_tendered' => '-1', 'idempotency_key' => 'invalid', 'items' => [],
        ])->assertUnprocessable()->assertJsonValidationErrors(['cash_tendered', 'idempotency_key', 'items']);

        $foreignBranch = Branch::factory()->create();
        $foreignUser = User::factory()->withRole('cashier')->forBranch($foreignBranch)->create();
        $foreignSale = Sale::factory()->create([
            'branch_id' => $foreignBranch->id,
            'warehouse_id' => Warehouse::factory()->for($foreignBranch),
            'register_id' => Register::factory()->for($foreignBranch),
            'cashier_id' => $foreignUser->id,
            'posted_by' => $foreignUser->id,
        ]);
        $this->actingAs($cashier)->get(route('sales.show', $foreignSale))->assertNotFound();

        $purchasing = User::factory()->withRole('purchasing')->forBranch($branch)->create();
        $this->actingAs($purchasing)->get(route('pos.index'))->assertForbidden();
    }

    /** @return array{Branch, Register, Warehouse, User, Product, Unit} */
    private function context(): array
    {
        $this->seed();
        $branch = Branch::factory()->create();
        $register = Register::factory()->for($branch)->create(['is_active' => true]);
        $warehouse = Warehouse::factory()->for($branch)->create(['is_active' => true]);
        $cashier = User::factory()->withRole('cashier')->forBranch($branch)->create(['is_active' => true]);
        $unit = Unit::factory()->create(['is_active' => true]);
        $product = Product::factory()->for($unit, 'baseUnit')->create(['is_active' => true]);
        $priceList = PriceList::factory()->create(['is_active' => true]);
        ProductPrice::factory()->create([
            'product_id' => $product->id, 'unit_id' => $unit->id, 'price_list_id' => $priceList->id,
            'amount' => '15000.00', 'effective_from' => now()->subDay(), 'effective_to' => null, 'is_active' => true,
        ]);
        InventoryBalance::factory()->create([
            'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id, 'product_id' => $product->id,
            'qty_on_hand' => '5.000', 'avg_unit_cost' => '9000.000000', 'inventory_value' => '45000.000000',
        ]);

        return [$branch, $register, $warehouse, $cashier, $product, $unit];
    }
}
