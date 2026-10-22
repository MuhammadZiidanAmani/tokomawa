<?php

namespace Tests\Feature\Http\Controllers\Master;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductUnit;
use App\Domain\Catalog\Models\Unit;
use App\Domain\Organization\Models\Branch;
use App\Domain\Pricing\Models\PriceList;
use App\Domain\Pricing\Models\ProductPrice;
use App\Domain\Pricing\Models\TaxRule;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class PricingControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_marketing_creates_precise_effective_price_and_change_log(): void
    {
        $this->seed();
        $marketing = $this->userWithRole('marketing');
        $unit = Unit::factory()->create();
        $product = Product::factory()->for($unit, 'baseUnit')->create();

        $this->actingAs($marketing)->post(route('master.price-lists.store'), [
            'code' => 'RETAIL', 'name' => 'Harga Retail', 'description' => 'Harga konsumen akhir.',
            'priority' => 100, 'is_active' => '1', 'reason' => 'Menambah strata retail.',
        ])->assertSessionHasNoErrors();

        $priceList = PriceList::query()->where('code', 'RETAIL')->firstOrFail();
        $this->actingAs($marketing)->post(route('master.prices.store'), $this->pricePayload($product, $priceList, $unit))
            ->assertSessionHasNoErrors()->assertSessionHas('status');

        $price = ProductPrice::query()->firstOrFail();
        $this->assertSame('12345.67', $price->amount);
        $this->assertSame('1.250', $price->minimum_quantity);
        $this->assertDatabaseHas('price_change_logs', [
            'product_price_id' => $price->id, 'change_type' => 'created', 'new_amount' => 12345.67,
        ]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'product_price.created', 'entity_id' => $price->public_id]);

        $this->actingAs($marketing)->put(route('master.price-lists.update', $priceList), [
            'code' => 'RETAIL', 'name' => 'Harga Retail Utama', 'priority' => 90,
            'is_active' => '1', 'reason' => 'Memperbarui strata harga.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('Harga Retail Utama', $priceList->fresh()->name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'price_list.updated', 'entity_id' => $priceList->public_id]);
    }

    public function test_overlapping_price_period_is_rejected_but_adjacent_period_is_allowed(): void
    {
        $this->seed();
        $marketing = $this->userWithRole('marketing');
        $unit = Unit::factory()->create();
        $product = Product::factory()->for($unit, 'baseUnit')->create();
        $priceList = PriceList::factory()->create();

        $this->actingAs($marketing)->post(route('master.prices.store'), $this->pricePayload($product, $priceList, $unit, [
            'effective_from' => '2026-01-01 00:00:00', 'effective_to' => '2026-02-01 00:00:00',
        ]))->assertSessionHasNoErrors();
        $this->actingAs($marketing)->post(route('master.prices.store'), $this->pricePayload($product, $priceList, $unit, [
            'amount' => '13000.00', 'effective_from' => '2026-01-15 00:00:00', 'effective_to' => '2026-03-01 00:00:00',
        ]))->assertSessionHasErrors(['effective_from' => 'Periode harga bertabrakan dengan harga aktif pada strata, satuan, dan minimum kuantitas yang sama.']);
        $this->actingAs($marketing)->post(route('master.prices.store'), $this->pricePayload($product, $priceList, $unit, [
            'amount' => '14000.00', 'effective_from' => '2026-02-01 00:00:00', 'effective_to' => '2026-03-01 00:00:00',
        ]))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('product_prices', 2);
    }

    public function test_price_unit_must_be_configured_for_product(): void
    {
        $this->seed();
        $marketing = $this->userWithRole('marketing');
        $baseUnit = Unit::factory()->create();
        $foreignUnit = Unit::factory()->create();
        $product = Product::factory()->for($baseUnit, 'baseUnit')->create();
        $priceList = PriceList::factory()->create();

        $this->actingAs($marketing)->post(route('master.prices.store'), $this->pricePayload($product, $priceList, $foreignUnit))
            ->assertSessionHasErrors(['unit_id' => 'Satuan harga harus terdaftar sebagai satuan barang.']);

        ProductUnit::factory()->for($product)->for($foreignUnit)->create(['conversion_factor' => '12.000000']);
        $this->actingAs($marketing)->post(route('master.prices.store'), $this->pricePayload($product, $priceList, $foreignUnit))
            ->assertSessionHasNoErrors();
    }

    public function test_finance_configures_versioned_tax_without_hardcoded_rate_and_overlap_is_rejected(): void
    {
        $this->seed();
        $finance = $this->userWithRole('finance');

        $this->actingAs($finance)->post(route('master.tax-rules.store'), $this->taxPayload())
            ->assertSessionHasNoErrors()->assertSessionHas('status');

        $taxRule = TaxRule::query()->firstOrFail();
        $this->assertSame('11.1234', $taxRule->rate);
        $this->assertSame('half_up', $taxRule->rounding_mode->value);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tax_rule.created', 'entity_id' => $taxRule->public_id]);

        $this->actingAs($finance)->put(route('master.tax-rules.update', $taxRule), $this->taxPayload([
            'name' => 'PPN umum diperbarui', 'rate' => '11.2500', 'reason' => 'Memperbarui konfigurasi pajak.',
        ]))->assertSessionHasNoErrors();
        $this->assertSame('11.2500', $taxRule->fresh()->rate);
        $this->assertDatabaseHas('audit_logs', ['action' => 'tax_rule.updated', 'entity_id' => $taxRule->public_id]);

        $this->actingAs($finance)->post(route('master.tax-rules.store'), $this->taxPayload([
            'rate' => '12.0000', 'effective_from' => '2026-06-01 00:00:00',
        ]))->assertSessionHasErrors(['effective_from' => 'Periode aturan pajak bertabrakan dengan versi aktif berkode sama.']);
        $this->assertDatabaseCount('tax_rules', 1);
    }

    public function test_role_boundaries_separate_price_and_tax_management(): void
    {
        $this->seed();
        $manager = $this->userWithRole('store_manager');
        $finance = $this->userWithRole('finance');
        $marketing = $this->userWithRole('marketing');

        $this->actingAs($manager)->post(route('master.tax-rules.store'), $this->taxPayload())->assertForbidden();
        $this->actingAs($finance)->post(route('master.price-lists.store'), [
            'code' => 'FIN', 'name' => 'Tidak boleh', 'priority' => 100,
            'is_active' => '1', 'reason' => 'Uji RBAC.',
        ])->assertForbidden();
        $this->actingAs($marketing)->get(route('master.pricing.index'))->assertOk()->assertDontSee('Konfigurasi Pajak');
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function pricePayload(Product $product, PriceList $priceList, Unit $unit, array $overrides = []): array
    {
        return [
            'product_id' => $product->id, 'price_list_id' => $priceList->id, 'unit_id' => $unit->id,
            'amount' => '12345.67', 'minimum_quantity' => '1.250',
            'effective_from' => '2026-01-01 00:00:00', 'effective_to' => '2026-12-31 23:59:59',
            'priority' => 100, 'is_active' => '1', 'reason' => 'Menambah harga efektif.',
            ...$overrides,
        ];
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function taxPayload(array $overrides = []): array
    {
        return [
            'code' => 'PPN-UMUM', 'name' => 'PPN umum yang dapat dikonfigurasi', 'rate' => '11.1234',
            'calculation_mode' => 'exclusive', 'rounding_mode' => 'half_up', 'rounding_scale' => 2,
            'effective_from' => '2026-01-01 00:00:00', 'effective_to' => null,
            'is_active' => '1', 'reason' => 'Mengatur pajak tanpa hardcode.',
            ...$overrides,
        ];
    }

    private function userWithRole(string $role): User
    {
        $branch = Branch::factory()->create();

        return User::factory()->withRole($role)->forBranch($branch)->create();
    }
}
