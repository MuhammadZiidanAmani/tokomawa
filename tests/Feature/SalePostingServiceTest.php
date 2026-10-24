<?php

namespace Tests\Feature;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Unit;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Register;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Pricing\Models\PriceList;
use App\Domain\Pricing\Models\ProductPrice;
use App\Domain\Sales\Models\CashMovement;
use App\Domain\Sales\Models\CashShift;
use App\Domain\Sales\Models\SaleItem;
use App\Domain\Sales\Models\SalePayment;
use App\Domain\Sales\Services\CashShiftService;
use App\Domain\Sales\Services\SalePostingService;
use App\Domain\Sales\Services\SalesReconciliationService;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SalePostingServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_cash_checkout_posts_stock_payment_cash_cogs_audit_and_reconciliation_atomically(): void
    {
        [$branch, , , $cashier, $shift, $product, $unit, $balance] = $this->context(stock: '10.000', cost: '7500.125000', price: '12500.50');
        $sale = app(SalePostingService::class)->checkout($cashier, $branch, $this->payload($shift, $product, $unit, '2.000', '30000.00'));

        $this->assertSame('posted', $sale->status->value);
        $this->assertSame('25001.00', $sale->grand_total);
        $this->assertSame('4999.00', $sale->change_due);
        $this->assertSame('8.000', $balance->fresh()->qty_on_hand);
        $this->assertSame('60001.000000', $balance->fresh()->inventory_value);
        $this->assertSame('7500.125000', SaleItem::query()->sole()->cogs_unit_snapshot);
        $this->assertSame('15000.250000', SaleItem::query()->sole()->cogs_total_snapshot);
        $this->assertSame('25001.00', SalePayment::query()->sole()->amount);
        $this->assertSame('25001.00', CashMovement::query()->where('movement_type', 'sale')->sole()->amount_delta);
        $this->assertSame('out', InventoryMovement::query()->sole()->direction);
        $this->assertDatabaseHas('audit_logs', ['action' => 'sale.posted', 'entity_id' => $sale->public_id]);
        $this->assertDatabaseHas('outbox_events', ['event_type' => 'sale.posted', 'aggregate_id' => $sale->public_id]);
        $saleReconciliation = app(SalesReconciliationService::class)->reconcileSale($sale);
        $this->assertTrue($saleReconciliation['is_reconciled']);
        $this->assertSame('0.00', $saleReconciliation['item_difference']);
        $shiftReconciliation = app(SalesReconciliationService::class)->reconcileShift($shift);
        $this->assertTrue($shiftReconciliation['is_reconciled']);
        $this->assertSame('0.00', $shiftReconciliation['sale_difference']);
    }

    public function test_checkout_replay_is_idempotent_and_posted_sale_is_immutable(): void
    {
        [$branch, , , $cashier, $shift, $product, $unit] = $this->context();
        $payload = $this->payload($shift, $product, $unit, '1.000', '20000.00');
        $service = app(SalePostingService::class);
        $sale = $service->checkout($cashier, $branch, $payload);
        $replay = $service->checkout($cashier, $branch, $payload);

        $this->assertTrue($sale->is($replay));
        $this->assertDatabaseCount('sales', 1);
        $this->assertDatabaseCount('sale_items', 1);
        $this->assertDatabaseCount('sale_payments', 1);
        $this->assertSame(2, CashMovement::query()->count());
        $this->assertDatabaseCount('inventory_movements', 1);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Penjualan terposting tidak dapat diubah');
        $sale->fresh()->update(['grand_total' => '1.00']);
    }

    public function test_insufficient_cash_and_stock_roll_back_every_effect(): void
    {
        [$branch, , , $cashier, $shift, $product, $unit, $balance] = $this->context(stock: '1.000');
        $service = app(SalePostingService::class);

        foreach ([['1.000', '100.00', 'Uang diterima kurang'], ['2.000', '50000.00', 'Stok tersedia']] as [$quantity, $cash, $message]) {
            try {
                $service->checkout($cashier, $branch, $this->payload($shift, $product, $unit, $quantity, $cash));
                $this->fail('Checkout invalid seharusnya ditolak.');
            } catch (DomainException $exception) {
                $this->assertStringContainsString($message, $exception->getMessage());
            }
        }

        $this->assertSame('1.000', $balance->fresh()->qty_on_hand);
        $this->assertDatabaseCount('sales', 0);
        $this->assertDatabaseCount('sale_items', 0);
        $this->assertDatabaseCount('sale_payments', 0);
        $this->assertSame(1, CashMovement::query()->count());
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseMissing('idempotency_keys', ['operation' => 'sale.checkout']);
    }

    public function test_server_price_is_authoritative_and_taxed_product_is_blocked(): void
    {
        [$branch, , , $cashier, $shift, $product, $unit] = $this->context(price: '12345.67');
        $payload = $this->payload($shift, $product, $unit, '1.000', '20000.00');
        $payload['items'][0]['unit_price'] = '1.00';
        $sale = app(SalePostingService::class)->checkout($cashier, $branch, $payload);
        $this->assertSame('12345.67', $sale->grand_total);

        [$taxBranch, , , $taxCashier, $taxShift, $taxProduct, $taxUnit] = $this->context(taxed: true);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('DEC-006 belum disahkan');
        app(SalePostingService::class)->checkout($taxCashier, $taxBranch, $this->payload($taxShift, $taxProduct, $taxUnit, '1.000', '20000.00'));
    }

    /** @return array{Branch, Register, Warehouse, User, CashShift, Product, Unit, InventoryBalance} */
    private function context(string $stock = '5.000', string $cost = '7000.000000', string $price = '10000.00', bool $taxed = false): array
    {
        $this->seed();
        $branch = Branch::factory()->create();
        $register = Register::factory()->for($branch)->create(['is_active' => true]);
        $warehouse = Warehouse::factory()->for($branch)->create(['is_active' => true]);
        $cashier = User::factory()->withRole('cashier')->forBranch($branch)->create(['is_active' => true]);
        $unit = Unit::factory()->create(['is_active' => true]);
        $product = Product::factory()->for($unit, 'baseUnit')->create(['is_active' => true]);
        if ($taxed) {
            $product->forceFill(['tax_rule_id' => \App\Domain\Pricing\Models\TaxRule::factory()->create()->id])->save();
        }
        $priceList = PriceList::factory()->create(['is_active' => true]);
        ProductPrice::factory()->create([
            'product_id' => $product->id, 'unit_id' => $unit->id, 'price_list_id' => $priceList->id,
            'amount' => $price, 'minimum_quantity' => '1.000', 'effective_from' => now()->subDay(),
            'effective_to' => null, 'is_active' => true,
        ]);
        $balance = InventoryBalance::factory()->create([
            'branch_id' => $branch->id, 'warehouse_id' => $warehouse->id, 'product_id' => $product->id,
            'qty_on_hand' => $stock, 'qty_reserved' => '0.000', 'avg_unit_cost' => $cost,
            'inventory_value' => bcmul($stock, $cost, 6),
        ]);
        $shift = app(CashShiftService::class)->open($cashier, $branch, $register->id, $warehouse->id, '100000.00', (string) Str::uuid());

        return [$branch, $register, $warehouse, $cashier, $shift, $product, $unit, $balance];
    }

    /** @return array<string, mixed> */
    private function payload(CashShift $shift, Product $product, Unit $unit, string $quantity, string $tendered): array
    {
        return [
            'cash_shift_id' => $shift->id, 'cash_tendered' => $tendered, 'idempotency_key' => (string) Str::uuid(),
            'items' => [['product_id' => $product->id, 'unit_id' => $unit->id, 'inventory_batch_id' => null, 'quantity' => $quantity]],
        ];
    }
}
