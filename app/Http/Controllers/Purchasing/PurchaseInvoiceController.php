<?php

namespace App\Http\Controllers\Purchasing;

use App\Domain\Access\Models\AuditLog;
use App\Domain\AccountsPayable\Enums\PurchaseType;
use App\Domain\AccountsPayable\Models\PurchaseInvoice;
use App\Domain\AccountsPayable\Services\PurchaseInvoiceService;
use App\Domain\Organization\Support\CurrentBranchContext;
use App\Domain\Purchasing\Enums\GoodsReceiptStatus;
use App\Domain\Purchasing\Models\GoodsReceiptItem;
use App\Domain\Shared\Exceptions\IdempotencyConflictException;
use App\Domain\Shared\Exceptions\IdempotencyInProgressException;
use App\Domain\Suppliers\Models\Supplier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\PostPurchaseInvoiceRequest;
use App\Http\Requests\Purchasing\UpsertPurchaseInvoiceRequest;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PurchaseInvoiceController extends Controller
{
    public function index(Request $request, CurrentBranchContext $context): View
    {
        Gate::authorize('viewAny', PurchaseInvoice::class);
        $branch = $context->get();
        abort_if($branch === null, 404);
        $invoices = PurchaseInvoice::query()->with('supplier')->whereBelongsTo($branch)->when($request->filled('search'), fn ($q) => $q->where(fn ($n) => $n->where('document_number', 'like', '%'.$request->query('search').'%')->orWhere('supplier_invoice_number', 'like', '%'.$request->query('search').'%')->orWhere('supplier_name_snapshot', 'like', '%'.$request->query('search').'%')))->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))->orderByDesc('invoice_date')->paginate(20)->withQueryString();

        return view('purchasing.purchase-invoices.index', compact('invoices'));
    }

    public function create(CurrentBranchContext $context): View
    {
        Gate::authorize('create', PurchaseInvoice::class);
        $branch = $context->get();
        abort_if($branch === null, 404);

        return view('purchasing.purchase-invoices.form', $this->formData($branch->id));
    }

    public function store(UpsertPurchaseInvoiceRequest $request, CurrentBranchContext $context, PurchaseInvoiceService $service): RedirectResponse
    {
        $branch = $context->get();
        abort_if($branch === null, 404);
        try {
            $invoice = $service->createDraft($request->user(), $branch, $request->validated());
        } catch (DomainException|IdempotencyConflictException|IdempotencyInProgressException $e) {
            return back()->withErrors(['purchase_invoice' => $e->getMessage()])->withInput();
        }

        return redirect()->route('purchasing.purchase-invoices.show', $invoice)->with('status', 'Draft faktur berhasil dibuat tanpa mengubah stok.');
    }

    public function show(PurchaseInvoice $purchaseInvoice, CurrentBranchContext $context): View
    {
        abort_if($context->get()?->id !== $purchaseInvoice->branch_id, 404);
        Gate::authorize('view', $purchaseInvoice);
        $purchaseInvoice->load(['items.allocations.receipt', 'supplier', 'cashPayment', 'apEntries']);

        return view('purchasing.purchase-invoices.show', ['invoice' => $purchaseInvoice, 'auditLogs' => AuditLog::query()->where('entity_type', 'purchase_invoice')->where('entity_id', $purchaseInvoice->public_id)->latest('created_at')->get()]);
    }

    public function edit(PurchaseInvoice $purchaseInvoice, CurrentBranchContext $context): View
    {
        abort_if($context->get()?->id !== $purchaseInvoice->branch_id, 404);
        Gate::authorize('update', $purchaseInvoice);

        return view('purchasing.purchase-invoices.form', [...$this->formData($purchaseInvoice->branch_id), 'invoice' => $purchaseInvoice->load('allocations')]);
    }

    public function update(UpsertPurchaseInvoiceRequest $request, PurchaseInvoice $purchaseInvoice, CurrentBranchContext $context, PurchaseInvoiceService $service): RedirectResponse
    {
        $branch = $context->get();
        abort_if($branch === null || $branch->id !== $purchaseInvoice->branch_id, 404);
        try {
            $invoice = $service->updateDraft($purchaseInvoice, $request->user(), $branch, $request->validated());
        } catch (DomainException|IdempotencyConflictException|IdempotencyInProgressException $e) {
            return back()->withErrors(['purchase_invoice' => $e->getMessage()])->withInput();
        }

        return redirect()->route('purchasing.purchase-invoices.show', $invoice)->with('status', 'Draft faktur diperbarui.');
    }

    public function post(PostPurchaseInvoiceRequest $request, PurchaseInvoice $purchaseInvoice, CurrentBranchContext $context, PurchaseInvoiceService $service): RedirectResponse
    {
        $branch = $context->get();
        abort_if($branch === null || $branch->id !== $purchaseInvoice->branch_id, 404);
        try {
            $invoice = $service->post($purchaseInvoice, $request->user(), $branch, $request->validated('idempotency_key'));
        } catch (DomainException|IdempotencyConflictException|IdempotencyInProgressException $e) {
            return back()->withErrors(['workflow' => $e->getMessage()]);
        }

        return redirect()->route('purchasing.purchase-invoices.show', $invoice)->with('status', 'Faktur berhasil diposting tanpa mutasi stok.');
    }

    /** @return array<string,mixed> */
    private function formData(int $branchId): array
    {
        return ['invoice' => null, 'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(), 'receiptItems' => GoodsReceiptItem::query()->with('goodsReceipt')->whereHas('goodsReceipt', fn ($q) => $q->where('branch_id', $branchId)->where('status', GoodsReceiptStatus::Posted->value))->orderBy('goods_receipt_id')->get(), 'types' => PurchaseType::cases(), 'formKey' => (string) Str::uuid()];
    }
}
