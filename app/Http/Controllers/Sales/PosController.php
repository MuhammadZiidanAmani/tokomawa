<?php

namespace App\Http\Controllers\Sales;

use App\Domain\Organization\Support\CurrentBranchContext;
use App\Domain\Sales\Enums\CashShiftStatus;
use App\Domain\Sales\Models\CashShift;
use App\Domain\Sales\Models\Sale;
use App\Domain\Sales\Services\SalePostingService;
use App\Domain\Sales\Services\SalesPricingService;
use App\Domain\Shared\Exceptions\IdempotencyConflictException;
use App\Domain\Shared\Exceptions\IdempotencyInProgressException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\CheckoutSaleRequest;
use App\Http\Requests\Sales\ProductLookupRequest;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PosController extends Controller
{
    public function index(CurrentBranchContext $context): View|\Illuminate\Http\RedirectResponse
    {
        Gate::authorize('create', Sale::class);
        $branch = $context->get();
        abort_if($branch === null, 404);
        $shift = $this->activeShift($branch->getKey(), request()->user()->getKey(), request()->user()->hasPermission('cash_shifts.use_any'));
        if ($shift === null) {
            return redirect()->route('cash-shifts.create')->withErrors(['cash_shift' => 'Buka shift kasir sebelum memulai transaksi.']);
        }
        Gate::authorize('view', $shift);

        return view('sales.pos.index', ['shift' => $shift->load(['register', 'warehouse', 'cashier'])]);
    }

    public function lookup(
        ProductLookupRequest $request,
        CurrentBranchContext $context,
        SalesPricingService $pricing,
    ): JsonResponse {
        $branch = $context->get();
        abort_if($branch === null, 404);
        $shift = $this->activeShift($branch->getKey(), $request->user()->getKey(), $request->user()->hasPermission('cash_shifts.use_any'));
        abort_if($shift === null, 409, 'Shift kasir tidak aktif.');

        try {
            $items = $pricing->lookup($shift->warehouse, (string) $request->validated('q'), CarbonImmutable::now('UTC'));
        } catch (DomainException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'errors' => ['q' => [$exception->getMessage()]]], 422);
        }

        return response()->json(['data' => $items]);
    }

    public function checkout(
        CheckoutSaleRequest $request,
        CurrentBranchContext $context,
        SalePostingService $service,
    ): JsonResponse {
        $branch = $context->get();
        abort_if($branch === null, 404);

        try {
            $sale = $service->checkout($request->user(), $branch, $request->validated());
        } catch (DomainException|IdempotencyConflictException|IdempotencyInProgressException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'errors' => ['checkout' => [$exception->getMessage()]],
            ], 422);
        }

        return response()->json([
            'message' => 'Penjualan berhasil diposting.',
            'sale_id' => $sale->public_id,
            'document_number' => $sale->document_number,
            'grand_total' => $sale->grand_total,
            'change_due' => $sale->change_due,
            'url' => route('sales.show', $sale),
        ], 201);
    }

    public function status(Request $request, CurrentBranchContext $context): JsonResponse
    {
        Gate::authorize('create', Sale::class);
        $validated = $request->validate(['idempotency_key' => ['required', 'uuid']]);
        $branch = $context->get();
        abort_if($branch === null, 404);
        $sale = Sale::query()
            ->whereBelongsTo($branch)
            ->where('posting_key', $validated['idempotency_key'])
            ->where(function ($query) use ($request): void {
                $query->where('cashier_id', $request->user()->getKey());
                if ($request->user()->hasPermission('cash_shifts.use_any')) {
                    $query->orWhereNotNull('cashier_id');
                }
            })
            ->firstOrFail();

        return response()->json([
            'status' => $sale->status->value,
            'sale_id' => $sale->public_id,
            'url' => route('sales.show', $sale),
        ]);
    }

    private function activeShift(int $branchId, int $cashierId, bool $mayUseAny): ?CashShift
    {
        return CashShift::query()
            ->with('warehouse')
            ->where('branch_id', $branchId)
            ->where('status', CashShiftStatus::Open)
            ->when(! $mayUseAny, fn ($query) => $query->where('cashier_id', $cashierId))
            ->orderByDesc('opened_at')
            ->first();
    }
}
