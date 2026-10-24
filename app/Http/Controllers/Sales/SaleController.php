<?php

namespace App\Http\Controllers\Sales;

use App\Domain\Access\Models\AuditLog;
use App\Domain\Organization\Support\CurrentBranchContext;
use App\Domain\Sales\Models\Sale;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function show(string $sale, CurrentBranchContext $context): View
    {
        $branch = $context->get();
        abort_if($branch === null, 404);
        $saleRecord = Sale::query()
            ->with(['items.product', 'items.unit', 'items.batch', 'payment', 'cashMovement', 'cashier', 'register', 'warehouse'])
            ->whereBelongsTo($branch)
            ->where('public_id', $sale)
            ->firstOrFail();
        Gate::authorize('view', $saleRecord);

        return view('sales.sales.show', [
            'sale' => $saleRecord,
            'auditLogs' => AuditLog::query()
                ->with('actor')
                ->where('branch_id', $branch->getKey())
                ->where('entity_type', 'sale')
                ->where('entity_id', $saleRecord->public_id)
                ->orderByDesc('created_at')
                ->get(),
        ]);
    }
}
