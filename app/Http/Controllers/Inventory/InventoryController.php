<?php

namespace App\Http\Controllers\Inventory;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\InventoryBalance;
use App\Domain\Inventory\Models\InventoryMovement;
use App\Domain\Inventory\Models\StockAdjustment;
use App\Domain\Inventory\Services\InventoryReconciliationService;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Organization\Support\CurrentBranchContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class InventoryController extends Controller
{
    public function index(CurrentBranchContext $context): View
    {
        Gate::authorize('inventory.view');
        $branch = $context->get();
        abort_if($branch === null, 404);

        return view('inventory.index', [
            'totalProducts' => InventoryBalance::query()->whereBelongsTo($branch)->distinct('product_id')->count('product_id'),
            'totalQuantity' => InventoryBalance::query()->whereBelongsTo($branch)->sum('qty_on_hand'),
            'totalValue' => InventoryBalance::query()->whereBelongsTo($branch)->sum('inventory_value'),
            'pendingCount' => StockAdjustment::query()->whereBelongsTo($branch)->where('status', 'pending_approval')->count(),
            'recentMovements' => InventoryMovement::query()->with(['product', 'warehouse'])
                ->whereBelongsTo($branch)->latest('posted_at')->limit(8)->get(),
        ]);
    }

    public function balances(Request $request, CurrentBranchContext $context): View
    {
        Gate::authorize('inventory.view');
        $branch = $context->get();
        abort_if($branch === null, 404);

        $balances = InventoryBalance::query()->with(['product.baseUnit', 'warehouse', 'batch'])
            ->whereBelongsTo($branch)
            ->when($request->string('search')->toString(), function ($query, string $search): void {
                $query->whereHas('product', fn ($product) => $product
                    ->where('sku', 'like', "%{$search}%")
                    ->orWhere('name', 'like', "%{$search}%"));
            })
            ->when($request->integer('warehouse_id'), fn ($query, int $warehouseId) => $query->where('warehouse_id', $warehouseId))
            ->orderBy('product_id')->paginate(20)->withQueryString();

        return view('inventory.balances', [
            'balances' => $balances,
            'warehouses' => Warehouse::query()->whereBelongsTo($branch)->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function movements(Request $request, CurrentBranchContext $context): View
    {
        Gate::authorize('inventory.view');
        $branch = $context->get();
        abort_if($branch === null, 404);

        $movements = InventoryMovement::query()->with(['product.baseUnit', 'warehouse', 'batch', 'creator'])
            ->whereBelongsTo($branch)
            ->when($request->string('search')->toString(), function ($query, string $search): void {
                $query->where(function ($nested) use ($search): void {
                    $nested->where('source_id', 'like', "%{$search}%")
                        ->orWhereHas('product', fn ($product) => $product->where('sku', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
                });
            })
            ->latest('posted_at')->paginate(20)->withQueryString();

        return view('inventory.movements', compact('movements'));
    }

    public function warehouse(Warehouse $warehouse, CurrentBranchContext $context): View
    {
        Gate::authorize('inventory.view');
        $branch = $context->get();
        abort_if($branch === null || $warehouse->branch_id !== $branch->getKey(), 404);

        $balances = InventoryBalance::query()->with(['product.baseUnit', 'batch'])
            ->whereBelongsTo($branch)->whereBelongsTo($warehouse)->orderBy('product_id')->paginate(20);

        return view('inventory.warehouse', compact('warehouse', 'balances'));
    }

    public function reconciliation(CurrentBranchContext $context, InventoryReconciliationService $service): View
    {
        Gate::authorize('inventory.reconcile');
        $branch = $context->get();
        abort_if($branch === null, 404);

        return view('inventory.reconciliation', ['results' => $service->reconcile($branch)]);
    }
}
