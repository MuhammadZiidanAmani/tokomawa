<?php

namespace Tests\Feature\Http\Controllers\Purchasing;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Unit;
use App\Domain\Organization\Models\Branch;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Services\PurchaseOrderService;
use App\Domain\Purchasing\Services\PurchaseOrderWorkflowService;
use App\Domain\Suppliers\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurchaseOrderControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_and_user_without_permission_is_forbidden(): void
    {
        $this->get(route('purchasing.purchase-orders.index'))->assertRedirect(route('login'));
        $this->seed();
        $branch = Branch::factory()->create();
        $cashier = User::factory()->withRole('cashier')->forBranch($branch)->create();

        $this->actingAs($cashier)->get(route('purchasing.purchase-orders.index'))->assertForbidden();
    }

    public function test_purchasing_user_can_open_index_and_create_form(): void
    {
        [$branch, $user] = $this->context();

        $this->actingAs($user)->get(route('purchasing.purchase-orders.index'))
            ->assertOk()
            ->assertSee('Purchase Order')
            ->assertSee('Buat Purchase Order');
        $this->actingAs($user)->get(route('purchasing.purchase-orders.create'))
            ->assertOk()
            ->assertSee($branch->name)
            ->assertSee('Simpan Draft');
    }

    public function test_valid_http_request_creates_draft_and_invalid_values_are_rejected(): void
    {
        [, $user, $supplier, $product, $unit] = $this->context();
        $payload = $this->payload($supplier, $product, $unit);

        $response = $this->actingAs($user)->post(route('purchasing.purchase-orders.store'), $payload);
        $purchaseOrder = PurchaseOrder::query()->sole();
        $response->assertRedirect(route('purchasing.purchase-orders.show', $purchaseOrder));
        $this->assertDatabaseHas('purchase_orders', ['status' => 'draft', 'grand_total' => '3000.00']);

        $invalid = $payload;
        $invalid['idempotency_key'] = (string) Str::uuid();
        $invalid['items'][0]['quantity'] = '0';
        $invalid['items'][0]['unit_price'] = '-1';
        $this->actingAs($user)->from(route('purchasing.purchase-orders.create'))
            ->post(route('purchasing.purchase-orders.store'), $invalid)
            ->assertRedirect(route('purchasing.purchase-orders.create'))
            ->assertSessionHasErrors(['items.0.quantity', 'items.0.unit_price']);
        $this->assertDatabaseCount('purchase_orders', 1);
    }

    public function test_branch_isolation_returns_not_found_for_foreign_purchase_order(): void
    {
        [$ownBranch, $user] = $this->context();
        $foreignBranch = Branch::factory()->create();
        $foreignCreator = User::factory()->withRole('purchasing')->forBranch($foreignBranch)->create();
        $purchaseOrder = PurchaseOrder::factory()->create([
            'branch_id' => $foreignBranch->id,
            'created_by' => $foreignCreator->id,
        ]);

        $this->assertNotSame($ownBranch->id, $purchaseOrder->branch_id);
        $this->actingAs($user)->get(route('purchasing.purchase-orders.show', $purchaseOrder))->assertNotFound();
        $this->actingAs($user)->get(route('purchasing.purchase-orders.edit', $purchaseOrder))->assertNotFound();
    }

    public function test_approved_purchase_order_cannot_be_edited_through_http(): void
    {
        [$branch, $user] = $this->context();
        $purchaseOrder = PurchaseOrder::factory()->create([
            'branch_id' => $branch->id,
            'created_by' => $user->id,
            'status' => PurchaseOrderStatus::Approved,
        ]);

        $this->actingAs($user)->get(route('purchasing.purchase-orders.edit', $purchaseOrder))->assertForbidden();
    }

    public function test_index_filters_and_never_leaks_other_branch_documents(): void
    {
        [$branch, $user, $supplier] = $this->context();
        $own = PurchaseOrder::factory()->for($supplier)->create([
            'branch_id' => $branch->id,
            'created_by' => $user->id,
            'document_number' => 'PO/2026/10/000777',
            'supplier_name_snapshot' => 'Supplier Milik Cabang',
        ]);
        $foreignBranch = Branch::factory()->create();
        PurchaseOrder::factory()->create([
            'branch_id' => $foreignBranch->id,
            'document_number' => 'PO/2026/10/000888',
            'supplier_name_snapshot' => 'Supplier Rahasia',
        ]);

        $this->actingAs($user)->get(route('purchasing.purchase-orders.index', ['search' => '000777']))
            ->assertOk()
            ->assertSee($own->document_number)
            ->assertDontSee('000888')
            ->assertDontSee('Supplier Rahasia');
    }

    public function test_purchasing_role_cannot_approve_pending_order(): void
    {
        [$branch, $user, $supplier, $product, $unit] = $this->context();
        $purchaseOrder = app(PurchaseOrderService::class)->createDraft($user, $branch, $this->payload($supplier, $product, $unit));
        $submitted = app(PurchaseOrderWorkflowService::class)->submit(
            $purchaseOrder,
            $user,
            $branch,
            'Diajukan untuk pengujian otorisasi HTTP',
            (string) Str::uuid(),
        );

        $this->actingAs($user)->post(route('purchasing.purchase-orders.approve', $submitted), [
            'decision_notes' => 'Mencoba menyetujui tanpa hak yang sesuai',
            'idempotency_key' => (string) Str::uuid(),
        ])->assertForbidden();
        $this->assertSame(PurchaseOrderStatus::PendingApproval, $submitted->fresh()->status);
    }

    /** @return array{Branch, User, Supplier, Product, Unit} */
    private function context(): array
    {
        $this->seed();
        $branch = Branch::factory()->create();
        $user = User::factory()->withRole('purchasing')->forBranch($branch)->create();
        $supplier = Supplier::factory()->create(['is_active' => true]);
        $unit = Unit::factory()->create(['code' => fake()->unique()->bothify('U##'), 'is_active' => true]);
        $product = Product::factory()->for($unit, 'baseUnit')->create(['is_active' => true]);

        return [$branch, $user, $supplier, $product, $unit];
    }

    /** @return array<string, mixed> */
    private function payload(Supplier $supplier, Product $product, Unit $unit): array
    {
        return [
            'supplier_id' => $supplier->id,
            'order_date' => '2026-10-23',
            'expected_at' => '2026-10-30',
            'notes' => 'Pesanan HTTP pengujian',
            'idempotency_key' => (string) Str::uuid(),
            'items' => [[
                'product_id' => $product->id,
                'unit_id' => $unit->id,
                'quantity' => '2.000',
                'unit_price' => '1500.00',
            ]],
        ];
    }
}
