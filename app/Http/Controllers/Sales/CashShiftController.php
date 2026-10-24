<?php

namespace App\Http\Controllers\Sales;

use App\Domain\Organization\Models\Register;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Organization\Support\CurrentBranchContext;
use App\Domain\Sales\Enums\CashShiftStatus;
use App\Domain\Sales\Models\CashShift;
use App\Domain\Sales\Services\CashShiftService;
use App\Domain\Shared\Exceptions\IdempotencyConflictException;
use App\Domain\Shared\Exceptions\IdempotencyInProgressException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\OpenCashShiftRequest;
use DomainException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CashShiftController extends Controller
{
    public function create(CurrentBranchContext $context): View|RedirectResponse
    {
        Gate::authorize('create', CashShift::class);
        $branch = $context->get();
        abort_if($branch === null, 404);

        $activeShift = CashShift::query()
            ->whereBelongsTo($branch)
            ->where('cashier_id', request()->user()->getKey())
            ->where('status', CashShiftStatus::Open)
            ->first();
        if ($activeShift !== null) {
            return redirect()->route('pos.index');
        }

        return view('sales.shifts.open', [
            'registers' => Register::query()->whereBelongsTo($branch)->where('is_active', true)->orderBy('name')->get(),
            'warehouses' => Warehouse::query()->whereBelongsTo($branch)->where('is_active', true)->orderBy('name')->get(),
            'formKey' => (string) Str::uuid(),
        ]);
    }

    public function store(
        OpenCashShiftRequest $request,
        CurrentBranchContext $context,
        CashShiftService $service,
    ): RedirectResponse {
        $branch = $context->get();
        abort_if($branch === null, 404);
        $data = $request->validated();

        try {
            $service->open(
                $request->user(),
                $branch,
                (int) $data['register_id'],
                (int) $data['warehouse_id'],
                (string) $data['opening_float'],
                (string) $data['idempotency_key'],
            );
        } catch (DomainException|IdempotencyConflictException|IdempotencyInProgressException $exception) {
            return back()->withErrors(['cash_shift' => $exception->getMessage()])->withInput();
        }

        return redirect()->route('pos.index')->with('status', 'Shift kasir berhasil dibuka.');
    }
}
