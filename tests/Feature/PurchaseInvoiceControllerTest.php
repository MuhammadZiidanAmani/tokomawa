<?php

namespace Tests\Feature;

use App\Http\Requests\Purchasing\UpsertPurchaseInvoiceRequest;
use App\Domain\AccountsPayable\Models\PurchaseInvoice;
use App\Domain\Organization\Models\Branch;
use App\Domain\Suppliers\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tests\TestCase;

class PurchaseInvoiceControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_invoice_and_ap_routes_require_authentication(): void
    {
        $this->get('/pembelian/faktur-pembelian')->assertRedirect('/login');
        $this->get('/keuangan/hutang-supplier')->assertRedirect('/login');
    }

    public function test_credit_due_date_cannot_precede_invoice_date(): void
    {
        $validator = Validator::make(['supplier_id' => 999, 'supplier_invoice_number' => 'INV-X', 'purchase_type' => 'credit', 'invoice_date' => '2026-10-24', 'due_date' => '2026-10-23', 'idempotency_key' => (string) Str::uuid(), 'items' => []], (new UpsertPurchaseInvoiceRequest)->rules());
        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('due_date', $validator->errors()->toArray());
    }

    public function test_permission_and_cross_branch_invoice_are_denied(): void
    {
        $this->seed();
        $allowedBranch = Branch::factory()->create();
        $otherBranch = Branch::factory()->create();
        $finance = User::factory()->withRole('finance')->forBranch($allowedBranch)->create();
        $unprivileged = User::factory()->forBranch($allowedBranch)->create();
        $supplier = Supplier::factory()->create();
        $invoice = PurchaseInvoice::query()->create(['branch_id' => $otherBranch->id, 'supplier_id' => $supplier->id, 'document_number' => 'PI/2026/10/999999', 'supplier_invoice_number' => 'CROSS-1', 'supplier_invoice_number_normalized' => 'CROSS1', 'purchase_type' => 'credit', 'invoice_date' => '2026-10-24', 'due_date' => '2026-11-24', 'supplier_code_snapshot' => $supplier->code, 'supplier_name_snapshot' => $supplier->name, 'creation_key' => (string) Str::uuid(), 'created_by' => $finance->id]);

        $this->actingAs($unprivileged)->withSession(['current_branch_id' => $allowedBranch->id])->get('/pembelian/faktur-pembelian')->assertForbidden();
        $this->actingAs($finance)->withSession(['current_branch_id' => $allowedBranch->id])->get(route('purchasing.purchase-invoices.show', $invoice))->assertNotFound();
    }
}
