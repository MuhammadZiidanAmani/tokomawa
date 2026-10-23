<?php

namespace App\Http\Controllers\Purchasing;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Support\CurrentBranchContext;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Purchasing\Services\PurchaseOrderWorkflowService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Purchasing\CancelPurchaseOrderRequest;
use App\Http\Requests\Purchasing\DecidePurchaseOrderRequest;
use App\Http\Requests\Purchasing\SendPurchaseOrderRequest;
use App\Http\Requests\Purchasing\SubmitPurchaseOrderRequest;
use DomainException;
use Illuminate\Http\RedirectResponse;

class PurchaseOrderWorkflowController extends Controller
{
    public function submit(SubmitPurchaseOrderRequest $request, PurchaseOrder $purchaseOrder, CurrentBranchContext $context, PurchaseOrderWorkflowService $service): RedirectResponse
    {
        $data = $request->validated();
        $branch = $this->branch($context, $purchaseOrder);

        return $this->perform(
            fn () => $service->submit($purchaseOrder, $request->user(), $branch, $data['reason'], $data['idempotency_key']),
            'Purchase Order diajukan untuk persetujuan.',
        );
    }

    public function approve(DecidePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder, CurrentBranchContext $context, PurchaseOrderWorkflowService $service): RedirectResponse
    {
        $data = $request->validated();
        $branch = $this->branch($context, $purchaseOrder);

        return $this->perform(
            fn () => $service->approve($purchaseOrder, $request->user(), $branch, $data['decision_notes'], $data['idempotency_key']),
            'Purchase Order berhasil disetujui.',
        );
    }

    public function reject(DecidePurchaseOrderRequest $request, PurchaseOrder $purchaseOrder, CurrentBranchContext $context, PurchaseOrderWorkflowService $service): RedirectResponse
    {
        $data = $request->validated();
        $branch = $this->branch($context, $purchaseOrder);

        return $this->perform(
            fn () => $service->reject($purchaseOrder, $request->user(), $branch, $data['decision_notes'], $data['idempotency_key']),
            'Purchase Order ditolak.',
        );
    }

    public function send(SendPurchaseOrderRequest $request, PurchaseOrder $purchaseOrder, CurrentBranchContext $context, PurchaseOrderWorkflowService $service): RedirectResponse
    {
        $data = $request->validated();
        $branch = $this->branch($context, $purchaseOrder);

        return $this->perform(
            fn () => $service->send($purchaseOrder, $request->user(), $branch, $data['idempotency_key']),
            'Purchase Order ditandai telah dikirim.',
        );
    }

    public function cancel(CancelPurchaseOrderRequest $request, PurchaseOrder $purchaseOrder, CurrentBranchContext $context, PurchaseOrderWorkflowService $service): RedirectResponse
    {
        $data = $request->validated();
        $branch = $this->branch($context, $purchaseOrder);

        return $this->perform(
            fn () => $service->cancel($purchaseOrder, $request->user(), $branch, $data['reason'], $data['idempotency_key']),
            'Purchase Order dibatalkan.',
        );
    }

    private function branch(CurrentBranchContext $context, PurchaseOrder $purchaseOrder): Branch
    {
        $branch = $context->get();
        abort_if($branch === null || $branch->getKey() !== $purchaseOrder->branch_id, 404);

        return $branch;
    }

    private function perform(callable $callback, string $message): RedirectResponse
    {
        try {
            $purchaseOrder = $callback();
        } catch (DomainException $exception) {
            return back()->withErrors(['workflow' => $exception->getMessage()]);
        }

        return redirect()->route('purchasing.purchase-orders.show', $purchaseOrder)->with('status', $message);
    }
}
