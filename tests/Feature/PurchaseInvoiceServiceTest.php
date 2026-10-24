<?php

namespace Tests\Feature;

use App\Domain\AccountsPayable\Enums\PurchaseInvoiceStatus;
use App\Domain\AccountsPayable\Models\ApEntry;
use App\Domain\AccountsPayable\Services\ApReconciliationService;
use App\Domain\AccountsPayable\Services\PurchaseInvoiceService;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Unit;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Pricing\Models\TaxRule;
use App\Domain\Purchasing\Enums\GoodsReceiptStatus;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Models\GoodsReceipt;
use App\Domain\Purchasing\Models\GoodsReceiptItem;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\PurchaseOrderItem;
use App\Domain\Suppliers\Models\Supplier;
use App\Models\User;
use DomainException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurchaseInvoiceServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_credit_and_cash_posting_are_idempotent_without_stock_effect(): void
    {
        [$branch,$actor,$supplier,$item] = $this->context();
        $service = app(PurchaseInvoiceService::class);
        $credit = $service->createDraft($actor, $branch, $this->payload($supplier, $item, 'INV-001', 'credit'));
        $key = (string) Str::uuid();
        $service->post($credit, $actor, $branch, $key);
        $service->post($credit, $actor, $branch, $key);
        $this->assertSame(PurchaseInvoiceStatus::Posted, $credit->fresh()->status);
        $this->assertSame('10000.00', $credit->fresh()->outstanding_amount);
        $this->assertDatabaseCount('ap_entries', 1);
        $this->assertDatabaseCount('inventory_movements', 0);
        $this->assertDatabaseCount('inventory_balances', 0);
        [$branch2,$actor2,$supplier2,$item2] = $this->context();
        $cash = $service->createDraft($actor2, $branch2, $this->payload($supplier2, $item2, 'INV-CASH', 'cash'));
        $service->post($cash, $actor2, $branch2, (string) Str::uuid());
        $this->assertSame('0.00', $cash->fresh()->outstanding_amount);
        $this->assertDatabaseHas('purchase_invoice_cash_payments', ['purchase_invoice_id' => $cash->id, 'amount' => '10000.00']);
        $this->assertSame('0.00', bcadd((string) ApEntry::query()->where('purchase_invoice_id', $cash->id)->sum('amount_delta'), '0', 2));
        $this->assertSame('0.00', app(ApReconciliationService::class)->reconcile($branch2)['difference']);
    }

    public function test_second_invoice_cannot_allocate_received_quantity_twice(): void
    {
        [$branch,$actor,$supplier,$item] = $this->context();
        $service = app(PurchaseInvoiceService::class);
        $first = $service->createDraft($actor, $branch, $this->payload($supplier, $item, 'INV-FIRST', 'credit'));
        $service->post($first, $actor, $branch, (string) Str::uuid());
        $second = $service->createDraft($actor, $branch, $this->payload($supplier, $item, 'INV-SECOND', 'credit'));
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('melebihi penerimaan');
        $service->post($second, $actor, $branch, (string) Str::uuid());
    }

    public function test_supplier_number_is_normalized_and_tax_snapshot_blocks_posting_until_decision(): void
    {
        [$branch, $actor, $supplier, $item] = $this->context();
        $service = app(PurchaseInvoiceService::class);
        $first = $service->createDraft($actor, $branch, $this->payload($supplier, $item, 'INV-001', 'credit'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'purchase_invoice.created', 'entity_id' => $first->public_id]);
        try {
            $service->createDraft($actor, $branch, $this->payload($supplier, $item, 'inv 001', 'credit'));
            $this->fail('Nomor faktur ternormalisasi dapat diduplikasi.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('sudah pernah digunakan', $exception->getMessage());
        }

        [$branch2, $actor2, $supplier2, $item2] = $this->context();
        $taxRule = TaxRule::factory()->create(['code' => 'PPN-CFG', 'rate' => '11.0000', 'created_by' => $actor2->id]);
        $payload = $this->payload($supplier2, $item2, 'INV-TAX', 'credit');
        $payload['items'][0]['tax_rule_id'] = $taxRule->id;
        $taxed = $service->createDraft($actor2, $branch2, $payload);
        $taxRule->update(['rate' => '12.0000']);
        $this->assertSame('11.0000', $taxed->items()->sole()->tax_rate_snapshot);
        try {
            $service->post($taxed, $actor2, $branch2, (string) Str::uuid());
            $this->fail('Faktur berpajak terposting sebelum DEC-006 disahkan.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('DEC-006', $exception->getMessage());
        }
        $this->assertSame(PurchaseInvoiceStatus::Draft, $taxed->fresh()->status);
        $this->assertDatabaseMissing('ap_entries', ['purchase_invoice_id' => $taxed->id]);
    }

    /** @return array{Branch,User,Supplier,GoodsReceiptItem} */
    private function context(): array
    {
        $this->seed();
        $branch = Branch::factory()->create();
        $warehouse = Warehouse::factory()->for($branch)->create();
        $actor = User::factory()->withRole('super_admin')->forBranch($branch)->create();
        $supplier = Supplier::factory()->create();
        $unit = Unit::factory()->create();
        $product = Product::factory()->for($unit, 'baseUnit')->create();
        $po = PurchaseOrder::factory()->create(['branch_id' => $branch->id, 'supplier_id' => $supplier->id, 'status' => PurchaseOrderStatus::Completed, 'created_by' => $actor->id]);
        $poItem = PurchaseOrderItem::factory()->create(['purchase_order_id' => $po->id, 'product_id' => $product->id, 'unit_id' => $unit->id, 'base_quantity' => '10.000', 'received_base_quantity' => '10.000']);
        $receipt = GoodsReceipt::factory()->create(['branch_id' => $branch->id, 'warehouse_id' => $warehouse->id, 'purchase_order_id' => $po->id, 'supplier_id' => $supplier->id, 'status' => GoodsReceiptStatus::Posted, 'created_by' => $actor->id]);
        $item = GoodsReceiptItem::factory()->create(['goods_receipt_id' => $receipt->id, 'purchase_order_item_id' => $poItem->id, 'product_id' => $product->id, 'unit_id' => $unit->id, 'quantity' => '10.000', 'base_quantity' => '10.000', 'unit_price_snapshot' => '1000.00', 'line_total' => '10000.00']);

        return [$branch, $actor, $supplier, $item];
    }

    /** @return array<string,mixed> */
    private function payload(Supplier $supplier, GoodsReceiptItem $item, string $number, string $type): array
    {
        return ['supplier_id' => $supplier->id, 'supplier_invoice_number' => $number, 'purchase_type' => $type, 'invoice_date' => '2026-10-24', 'due_date' => $type === 'credit' ? '2026-11-24' : null, 'idempotency_key' => (string) Str::uuid(), 'items' => [['goods_receipt_item_id' => $item->id, 'base_quantity' => '10.000', 'invoice_unit_price' => '1000.00', 'discount_amount' => '0', 'tax_amount' => '0']]];
    }
}
