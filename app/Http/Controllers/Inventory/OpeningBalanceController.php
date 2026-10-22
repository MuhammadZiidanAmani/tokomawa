<?php

namespace App\Http\Controllers\Inventory;

use App\Domain\Catalog\Models\Product;
use App\Domain\Inventory\Models\StockAdjustment;
use App\Domain\Inventory\Services\OpeningBalanceApprovalService;
use App\Domain\Inventory\Services\StockAdjustmentSubmissionService;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Organization\Support\CurrentBranchContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\ApproveOpeningBalanceRequest;
use App\Http\Requests\Inventory\StoreOpeningBalanceRequest;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class OpeningBalanceController extends Controller
{
    public function index(CurrentBranchContext $context): View
    {
        Gate::authorize('inventory.view');
        $branch = $context->get();
        abort_if($branch === null, 404);

        return view('inventory.opening-balances', [
            'warehouses' => Warehouse::query()->whereBelongsTo($branch)->where('is_active', true)->orderBy('name')->get(),
            'products' => Product::query()->with(['baseUnit', 'units.unit'])->where('is_active', true)->orderBy('name')->limit(200)->get(),
            'adjustments' => StockAdjustment::query()->with(['destinationWarehouse', 'creator', 'approvalRequest'])
                ->whereBelongsTo($branch)->where('type', 'opening_balance')->latest()->paginate(20),
        ]);
    }

    public function store(
        StoreOpeningBalanceRequest $request,
        CurrentBranchContext $context,
        StockAdjustmentSubmissionService $service,
    ): RedirectResponse {
        $branch = $context->get();
        abort_if($branch === null, 404);
        $data = $request->validated();
        $warehouse = Warehouse::query()->findOrFail($data['warehouse_id']);

        $adjustment = $service->submitOpeningBalance(
            $request->user(),
            $branch,
            $warehouse,
            $data['items'],
            $data['reason'],
            CarbonImmutable::parse($data['occurred_at'], $branch->timezone)->utc(),
            $data['submission_key'],
        );

        return redirect()->route('inventory.opening.index')
            ->with('status', "Saldo awal {$adjustment->document_number} diajukan untuk persetujuan.");
    }

    public function approve(
        ApproveOpeningBalanceRequest $request,
        StockAdjustment $adjustment,
        OpeningBalanceApprovalService $service,
    ): RedirectResponse {
        Gate::authorize('approve', $adjustment);
        $data = $request->validated();
        $result = $service->approveAndPost($adjustment, $request->user(), $data['decision_notes'], $data['idempotency_key']);

        return redirect()->route('inventory.opening.index')
            ->with('status', "Saldo awal {$result->payload['document_number']} disetujui dan diposting.");
    }
}
