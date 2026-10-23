<?php

namespace App\Http\Controllers\Purchasing;

use App\Domain\Access\Models\AuditLog;
use App\Domain\Catalog\Models\Product;
use App\Domain\Organization\Support\CurrentBranchContext;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Services\PurchaseOrderService;
use App\Domain\Suppliers\Models\Supplier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\UpsertPurchaseOrderRequest;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class PurchaseOrderController extends Controller
{
    public function index(Request $request, CurrentBranchContext $context): View
    {
        Gate::authorize('viewAny', PurchaseOrder::class);
        $branch = $context->get();
        abort_if($branch === null, 404);

        $statusValues = collect(PurchaseOrderStatus::cases())->pluck('value');
        $purchaseOrders = PurchaseOrder::query()
            ->with(['supplier', 'creator'])
            ->withCount('items')
            ->whereBelongsTo($branch)
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim((string) $request->query('search'));
                $query->where(function ($nested) use ($search): void {
                    $nested->where('document_number', 'like', "%{$search}%")
                        ->orWhere('supplier_name_snapshot', 'like', "%{$search}%");
                });
            })
            ->when($request->filled('supplier_id'), fn ($query) => $query->where('supplier_id', $request->integer('supplier_id')))
            ->when($statusValues->contains($request->query('status')), fn ($query) => $query->where('status', $request->query('status')))
            ->orderByDesc('order_date')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('purchasing.purchase-orders.index', [
            'purchaseOrders' => $purchaseOrders,
            'suppliers' => Supplier::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => PurchaseOrderStatus::cases(),
        ]);
    }

    public function create(CurrentBranchContext $context): View
    {
        Gate::authorize('create', PurchaseOrder::class);
        abort_if($context->get() === null, 404);

        return view('purchasing.purchase-orders.form', $this->formData());
    }

    public function store(UpsertPurchaseOrderRequest $request, CurrentBranchContext $context, PurchaseOrderService $service): RedirectResponse
    {
        $branch = $context->get();
        abort_if($branch === null, 404);

        try {
            $purchaseOrder = $service->createDraft($request->user(), $branch, $request->validated());
        } catch (DomainException $exception) {
            return back()->withErrors(['purchase_order' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('purchasing.purchase-orders.show', $purchaseOrder)
            ->with('status', "Draft {$purchaseOrder->document_number} berhasil dibuat.");
    }

    public function show(PurchaseOrder $purchaseOrder, CurrentBranchContext $context): View
    {
        abort_if($context->get()?->getKey() !== $purchaseOrder->branch_id, 404);
        Gate::authorize('view', $purchaseOrder);
        $purchaseOrder->load([
            'supplier', 'items.product', 'items.unit', 'creator', 'submitter', 'approver',
            'approvalRequest.requester', 'approvalRequest.approver',
        ]);

        return view('purchasing.purchase-orders.show', [
            'purchaseOrder' => $purchaseOrder,
            'auditLogs' => AuditLog::query()
                ->with('actor')
                ->where('branch_id', $purchaseOrder->branch_id)
                ->where('entity_type', 'purchase_order')
                ->where('entity_id', $purchaseOrder->public_id)
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function edit(PurchaseOrder $purchaseOrder, CurrentBranchContext $context): View
    {
        abort_if($context->get()?->getKey() !== $purchaseOrder->branch_id, 404);
        Gate::authorize('update', $purchaseOrder);
        $purchaseOrder->load('items');

        return view('purchasing.purchase-orders.form', [
            ...$this->formData(),
            'purchaseOrder' => $purchaseOrder,
        ]);
    }

    public function update(UpsertPurchaseOrderRequest $request, PurchaseOrder $purchaseOrder, CurrentBranchContext $context, PurchaseOrderService $service): RedirectResponse
    {
        $branch = $context->get();
        abort_if($branch === null || $branch->getKey() !== $purchaseOrder->branch_id, 404);

        try {
            $updated = $service->updateDraft($purchaseOrder, $request->user(), $branch, $request->validated());
        } catch (DomainException $exception) {
            return back()->withErrors(['purchase_order' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('purchasing.purchase-orders.show', $updated)
            ->with('status', "Draft {$updated->document_number} berhasil diperbarui.");
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        return [
            'purchaseOrder' => null,
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(['id', 'code', 'name']),
            'products' => $this->productOptions(),
            'formKey' => (string) Str::uuid(),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function productOptions(): Collection
    {
        return Product::query()
            ->with([
                'baseUnit:id,code,name,symbol,is_active',
                'units' => fn ($query) => $query->where('is_active', true)->where('is_purchase_unit', true),
                'units.unit:id,code,name,symbol,is_active',
                'supplierProducts' => fn ($query) => $query->where('is_active', true),
            ])
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'sku', 'name', 'base_unit_id'])
            ->map(function (Product $product): array {
                $units = collect([[
                    'id' => $product->baseUnit->getKey(),
                    'label' => $product->baseUnit->name.' ('.$product->baseUnit->symbol.')',
                    'factor' => '1.000000',
                ]])->merge($product->units->filter(fn ($item): bool => $item->unit->is_active)->map(fn ($item): array => [
                    'id' => $item->unit_id,
                    'label' => $item->unit->name.' ('.$item->unit->symbol.')',
                    'factor' => $item->conversion_factor,
                ]))->unique('id')->values();

                return [
                    'id' => $product->getKey(),
                    'sku' => $product->sku,
                    'name' => $product->name,
                    'units' => $units,
                    'supplier_options' => $product->supplierProducts->mapWithKeys(fn ($mapping): array => [
                        $mapping->supplier_id => [
                            'unit_id' => $mapping->purchase_unit_id,
                            'price' => $mapping->contract_price ?? $mapping->last_purchase_price,
                        ],
                    ]),
                ];
            });
    }
}
