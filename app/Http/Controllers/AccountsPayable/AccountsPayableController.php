<?php

namespace App\Http\Controllers\AccountsPayable;

use App\Domain\AccountsPayable\Enums\PurchaseInvoiceStatus;
use App\Domain\AccountsPayable\Models\PurchaseInvoice;
use App\Domain\AccountsPayable\Services\ApReconciliationService;
use App\Domain\Organization\Support\CurrentBranchContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountsPayableController extends Controller
{
    public function index(Request $request, CurrentBranchContext $context, ApReconciliationService $reconciliation): View
    {
        abort_unless($request->user()?->hasPermission('accounts_payable.view'), 403);
        $branch = $context->get();
        abort_if($branch === null, 404);
        $invoices = PurchaseInvoice::query()->whereBelongsTo($branch)->where('status', PurchaseInvoiceStatus::Posted)->where('outstanding_amount', '>', 0)->with('supplier')->orderBy('due_date')->paginate(20)->withQueryString();

        return view('accounts-payable.index', ['invoices' => $invoices, 'reconciliation' => $reconciliation->reconcile($branch), 'cashTotal' => PurchaseInvoice::query()->whereBelongsTo($branch)->where('status', 'posted')->where('purchase_type', 'cash')->sum('grand_total'), 'creditTotal' => PurchaseInvoice::query()->whereBelongsTo($branch)->where('status', 'posted')->where('purchase_type', 'credit')->sum('grand_total')]);
    }
}
