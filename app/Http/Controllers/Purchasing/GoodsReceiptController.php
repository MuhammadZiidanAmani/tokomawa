<?php

namespace App\Http\Controllers\Purchasing;

use App\Domain\Access\Models\AuditLog;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Organization\Support\CurrentBranchContext;
use App\Domain\Purchasing\Enums\GoodsReceiptStatus;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Models\GoodsReceipt;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Models\PurchaseOrderItem;
use App\Domain\Purchasing\Services\GoodsReceiptService;
use App\Domain\Shared\Exceptions\IdempotencyConflictException;
use App\Domain\Shared\Exceptions\IdempotencyInProgressException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\CancelGoodsReceiptRequest;
use App\Http\Requests\Purchasing\PostGoodsReceiptRequest;
use App\Http\Requests\Purchasing\UpsertGoodsReceiptRequest;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class GoodsReceiptController extends Controller
{
    public function index(Request $request, CurrentBranchContext $context): View
    {
        Gate::authorize('viewAny', GoodsReceipt::class);
        $branch = $context->get();
        abort_if($branch === null, 404);

        $statusValues = collect(GoodsReceiptStatus::cases())->pluck('value');
        $receipts = GoodsReceipt::query()
            ->with(['purchaseOrder', 'warehouse', 'creator'])
            ->withCount('items')
            ->whereBelongsTo($branch)
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = trim((string) $request->query('search'));
                $query->where(function ($nested) use ($search): void {
                    $nested->where('document_number', 'like', "%{$search}%")
                        ->orWhere('supplier_delivery_note', 'like', "%{$search}%")
                        ->orWhere('supplier_name_snapshot', 'like', "%{$search}%")
                        ->orWhereHas('purchaseOrder', fn ($po) => $po->where('document_number', 'like', "%{$search}%"));
                });
            })
            ->when($statusValues->contains($request->query('status')), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('warehouse_id'), fn ($query) => $query->where('warehouse_id', $request->integer('warehouse_id')))
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('purchasing.goods-receipts.index', [
            'receipts' => $receipts,
            'statuses' => GoodsReceiptStatus::cases(),
            'warehouses' => Warehouse::query()->whereBelongsTo($branch)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(CurrentBranchContext $context): View
    {
        Gate::authorize('create', GoodsReceipt::class);
        $branch = $context->get();
        abort_if($branch === null, 404);

        return view('purchasing.goods-receipts.form', $this->formData($branch->getKey()));
    }

    public function store(
        UpsertGoodsReceiptRequest $request,
        CurrentBranchContext $context,
        GoodsReceiptService $service,
    ): RedirectResponse {
        $branch = $context->get();
        abort_if($branch === null, 404);

        try {
            $receipt = $service->createDraft($request->user(), $branch, $request->validated());
        } catch (DomainException|IdempotencyConflictException|IdempotencyInProgressException $exception) {
            return back()->withErrors(['goods_receipt' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('purchasing.goods-receipts.show', $receipt)
            ->with('status', "Draft {$receipt->document_number} berhasil dibuat tanpa mengubah stok.");
    }

    public function show(GoodsReceipt $goodsReceipt, CurrentBranchContext $context): View
    {
        abort_if($context->get()?->getKey() !== $goodsReceipt->branch_id, 404);
        Gate::authorize('view', $goodsReceipt);
        $goodsReceipt->load([
            'purchaseOrder', 'warehouse', 'supplier', 'items.product', 'items.unit', 'items.batch', 'creator', 'poster',
        ]);

        return view('purchasing.goods-receipts.show', [
            'goodsReceipt' => $goodsReceipt,
            'movements' => InventoryMovement::query()
                ->with(['product', 'warehouse', 'batch'])
                ->where('source_type', 'goods_receipt')
                ->where('source_id', $goodsReceipt->public_id)
                ->orderBy('id')
                ->get(),
            'auditLogs' => AuditLog::query()
                ->with('actor')
                ->where('branch_id', $goodsReceipt->branch_id)
                ->where('entity_type', 'goods_receipt')
                ->where('entity_id', $goodsReceipt->public_id)
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }

    public function edit(GoodsReceipt $goodsReceipt, CurrentBranchContext $context): View
    {
        $branch = $context->get();
        abort_if($branch === null || $branch->getKey() !== $goodsReceipt->branch_id, 404);
        Gate::authorize('update', $goodsReceipt);
        $goodsReceipt->load('items');

        return view('purchasing.goods-receipts.form', [
            ...$this->formData($branch->getKey()),
            'goodsReceipt' => $goodsReceipt,
        ]);
    }

    public function update(
        UpsertGoodsReceiptRequest $request,
        GoodsReceipt $goodsReceipt,
        CurrentBranchContext $context,
        GoodsReceiptService $service,
    ): RedirectResponse {
        $branch = $context->get();
        abort_if($branch === null || $branch->getKey() !== $goodsReceipt->branch_id, 404);

        try {
            $updated = $service->updateDraft($goodsReceipt, $request->user(), $branch, $request->validated());
        } catch (DomainException|IdempotencyConflictException|IdempotencyInProgressException $exception) {
            return back()->withErrors(['goods_receipt' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('purchasing.goods-receipts.show', $updated)
            ->with('status', "Draft {$updated->document_number} berhasil diperbarui.");
    }

    public function post(
        PostGoodsReceiptRequest $request,
        GoodsReceipt $goodsReceipt,
        CurrentBranchContext $context,
        GoodsReceiptService $service,
    ): RedirectResponse {
        $branch = $context->get();
        abort_if($branch === null || $branch->getKey() !== $goodsReceipt->branch_id, 404);

        try {
            $posted = $service->post($goodsReceipt, $request->user(), $branch, $request->validated('idempotency_key'));
        } catch (DomainException|IdempotencyConflictException|IdempotencyInProgressException $exception) {
            return back()->withErrors(['workflow' => $exception->getMessage()]);
        }

        return redirect()->route('purchasing.goods-receipts.show', $posted)
            ->with('status', 'Penerimaan berhasil diposting. Stok dan outstanding PO telah diperbarui atomik.');
    }

    public function cancel(
        CancelGoodsReceiptRequest $request,
        GoodsReceipt $goodsReceipt,
        CurrentBranchContext $context,
        GoodsReceiptService $service,
    ): RedirectResponse {
        $branch = $context->get();
        abort_if($branch === null || $branch->getKey() !== $goodsReceipt->branch_id, 404);
        $data = $request->validated();

        try {
            $cancelled = $service->cancel($goodsReceipt, $request->user(), $branch, $data['reason'], $data['idempotency_key']);
        } catch (DomainException|IdempotencyConflictException|IdempotencyInProgressException $exception) {
            return back()->withErrors(['workflow' => $exception->getMessage()]);
        }

        return redirect()->route('purchasing.goods-receipts.show', $cancelled)
            ->with('status', 'Draft penerimaan dibatalkan tanpa mengubah stok.');
    }

    /** @return array<string, mixed> */
    private function formData(int $branchId): array
    {
        $purchaseOrders = PurchaseOrder::query()
            ->with([
                'items' => fn ($query) => $query
                    ->with('product:id,track_batch,track_expiry')
                    ->whereColumn('received_base_quantity', '<', 'base_quantity')
                    ->orderBy('line_number'),
            ])
            ->where('branch_id', $branchId)
            ->whereIn('status', [
                PurchaseOrderStatus::Approved,
                PurchaseOrderStatus::Sent,
                PurchaseOrderStatus::PartiallyReceived,
            ])
            ->whereHas('items', fn ($query) => $query->whereColumn('received_base_quantity', '<', 'base_quantity'))
            ->orderByDesc('order_date')
            ->limit(200)
            ->get()
            ->map(fn (PurchaseOrder $purchaseOrder): array => [
                'id' => $purchaseOrder->getKey(),
                'number' => $purchaseOrder->document_number,
                'supplier' => $purchaseOrder->supplier_name_snapshot,
                'status' => $purchaseOrder->status->label(),
                'items' => $purchaseOrder->items->map(fn (PurchaseOrderItem $item): array => [
                    'id' => $item->getKey(),
                    'sku' => $item->product_sku_snapshot,
                    'name' => $item->product_name_snapshot,
                    'unit' => $item->unit_symbol_snapshot,
                    'ordered' => $item->quantity,
                    'received' => bcdiv($item->received_base_quantity, $item->conversion_factor, 3),
                    'outstanding' => bcdiv($item->outstandingBaseQuantity(), $item->conversion_factor, 3),
                    'track_batch' => $item->product->track_batch,
                    'track_expiry' => $item->product->track_expiry,
                ])->values(),
            ]);

        return [
            'goodsReceipt' => null,
            'purchaseOrders' => $purchaseOrders,
            'warehouses' => Warehouse::query()
                ->where('branch_id', $branchId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'code', 'name']),
            'formKey' => (string) Str::uuid(),
        ];
    }
}
