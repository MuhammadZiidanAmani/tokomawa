<?php

namespace App\Domain\Purchasing\Services;

use App\Domain\Access\DTOs\AuditContext;
use App\Domain\Access\Services\ApprovalService;
use App\Domain\Access\Services\AuditLogger;
use App\Domain\Organization\Models\Branch;
use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Shared\Services\IdempotencyService;
use App\Domain\Shared\Services\OutboxRecorder;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;

class PurchaseOrderWorkflowService
{
    public function __construct(
        private readonly IdempotencyService $idempotency,
        private readonly ApprovalService $approvals,
        private readonly AuditLogger $auditLogger,
        private readonly OutboxRecorder $outbox,
    ) {}

    public function submit(
        PurchaseOrder $purchaseOrder,
        User $actor,
        Branch $branch,
        string $reason,
        string $idempotencyKey,
    ): PurchaseOrder {
        $this->authorize($actor, $branch, 'purchase_orders.submit');
        $this->ensureBranch($purchaseOrder, $branch);

        return $this->execute($purchaseOrder, $actor, 'submit', $idempotencyKey, [
            'reason' => $reason,
        ], function (PurchaseOrder $locked) use ($actor, $branch, $reason, $idempotencyKey): void {
            if ($locked->status !== PurchaseOrderStatus::Draft) {
                throw new DomainException('Hanya Purchase Order draft yang dapat diajukan.');
            }

            if (! $locked->items()->exists()) {
                throw new DomainException('Purchase Order harus memiliki minimal satu item.');
            }

            $approval = $this->approvals->request(
                $actor,
                $branch,
                'purchase_orders.approve',
                'purchase_order',
                $locked->public_id,
                $locked->grand_total,
                $reason,
                'purchase-order:'.$idempotencyKey,
            );

            $this->transition($locked, $actor, $branch, PurchaseOrderStatus::PendingApproval, $reason, [
                'approval_request_id' => $approval->getKey(),
                'submitted_by' => $actor->getKey(),
                'submitted_at' => now(),
            ]);
        });
    }

    public function approve(
        PurchaseOrder $purchaseOrder,
        User $actor,
        Branch $branch,
        string $decisionNotes,
        string $idempotencyKey,
    ): PurchaseOrder {
        $this->authorize($actor, $branch, 'purchase_orders.approve');
        $this->ensureBranch($purchaseOrder, $branch);

        return $this->execute($purchaseOrder, $actor, 'approve', $idempotencyKey, [
            'decision_notes' => $decisionNotes,
        ], function (PurchaseOrder $locked) use ($actor, $branch, $decisionNotes): void {
            if ($locked->status !== PurchaseOrderStatus::PendingApproval || $locked->approvalRequest === null) {
                throw new DomainException('Purchase Order tidak sedang menunggu persetujuan.');
            }
            if ($locked->created_by === $actor->getKey()) {
                throw new DomainException('Pembuat Purchase Order tidak boleh menyetujui dokumennya sendiri.');
            }

            $this->approvals->approve($locked->approvalRequest, $actor, $decisionNotes);
            $this->transition($locked, $actor, $branch, PurchaseOrderStatus::Approved, $decisionNotes, [
                'approved_by' => $actor->getKey(),
                'approved_at' => now(),
                'decision_notes' => $decisionNotes,
            ]);
        });
    }

    public function reject(
        PurchaseOrder $purchaseOrder,
        User $actor,
        Branch $branch,
        string $decisionNotes,
        string $idempotencyKey,
    ): PurchaseOrder {
        $this->authorize($actor, $branch, 'purchase_orders.approve');
        $this->ensureBranch($purchaseOrder, $branch);

        return $this->execute($purchaseOrder, $actor, 'reject', $idempotencyKey, [
            'decision_notes' => $decisionNotes,
        ], function (PurchaseOrder $locked) use ($actor, $branch, $decisionNotes): void {
            if ($locked->status !== PurchaseOrderStatus::PendingApproval || $locked->approvalRequest === null) {
                throw new DomainException('Purchase Order tidak sedang menunggu persetujuan.');
            }
            if ($locked->created_by === $actor->getKey()) {
                throw new DomainException('Pembuat Purchase Order tidak boleh menolak dokumennya sendiri.');
            }

            $this->approvals->reject($locked->approvalRequest, $actor, $decisionNotes);
            $this->transition($locked, $actor, $branch, PurchaseOrderStatus::Rejected, $decisionNotes, [
                'rejected_by' => $actor->getKey(),
                'rejected_at' => now(),
                'decision_notes' => $decisionNotes,
            ]);
        });
    }

    public function send(
        PurchaseOrder $purchaseOrder,
        User $actor,
        Branch $branch,
        string $idempotencyKey,
    ): PurchaseOrder {
        $this->authorize($actor, $branch, 'purchase_orders.send');
        $this->ensureBranch($purchaseOrder, $branch);

        return $this->execute($purchaseOrder, $actor, 'send', $idempotencyKey, [], function (PurchaseOrder $locked) use ($actor, $branch): void {
            if ($locked->status !== PurchaseOrderStatus::Approved) {
                throw new DomainException('Hanya Purchase Order approved yang dapat ditandai telah dikirim.');
            }

            $this->transition($locked, $actor, $branch, PurchaseOrderStatus::Sent, 'Purchase Order dikirim kepada supplier.', [
                'sent_by' => $actor->getKey(),
                'sent_at' => now(),
            ]);
        });
    }

    public function cancel(
        PurchaseOrder $purchaseOrder,
        User $actor,
        Branch $branch,
        string $reason,
        string $idempotencyKey,
    ): PurchaseOrder {
        $this->authorize($actor, $branch, 'purchase_orders.cancel');
        $this->ensureBranch($purchaseOrder, $branch);

        return $this->execute($purchaseOrder, $actor, 'cancel', $idempotencyKey, [
            'reason' => $reason,
        ], function (PurchaseOrder $locked) use ($actor, $branch, $reason): void {
            if (! in_array($locked->status, [PurchaseOrderStatus::Draft, PurchaseOrderStatus::Rejected, PurchaseOrderStatus::Approved, PurchaseOrderStatus::Sent], true)) {
                throw new DomainException('Purchase Order pada status ini tidak dapat dibatalkan.');
            }

            $this->transition($locked, $actor, $branch, PurchaseOrderStatus::Cancelled, $reason, [
                'cancelled_by' => $actor->getKey(),
                'cancelled_at' => now(),
                'cancellation_reason' => $reason,
            ]);
        });
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  callable(PurchaseOrder): void  $callback
     */
    private function execute(
        PurchaseOrder $purchaseOrder,
        User $actor,
        string $operation,
        string $idempotencyKey,
        array $payload,
        callable $callback,
    ): PurchaseOrder {
        $result = $this->idempotency->execute(
            'purchase_order:'.$purchaseOrder->public_id,
            'purchase_order.'.$operation,
            $idempotencyKey,
            $payload,
            function () use ($purchaseOrder, $callback): array {
                $locked = PurchaseOrder::query()
                    ->with(['approvalRequest', 'branch'])
                    ->lockForUpdate()
                    ->findOrFail($purchaseOrder->getKey());
                $callback($locked);

                return ['purchase_order_id' => $locked->public_id];
            },
            $actor,
        );

        return PurchaseOrder::query()->where('public_id', $result->payload['purchase_order_id'])->firstOrFail();
    }

    /** @param array<string, mixed> $attributes */
    private function transition(
        PurchaseOrder $purchaseOrder,
        User $actor,
        Branch $branch,
        PurchaseOrderStatus $status,
        string $reason,
        array $attributes,
    ): void {
        $previousStatus = $purchaseOrder->status;
        $purchaseOrder->update([
            ...$attributes,
            'status' => $status,
            'version' => $purchaseOrder->version + 1,
        ]);

        $this->auditLogger->record(
            new AuditContext($actor, $branch, $reason),
            'purchase_order.'.$status->value,
            'purchase_order',
            $purchaseOrder->public_id,
            before: ['status' => $previousStatus->value],
            after: ['status' => $status->value, 'version' => $purchaseOrder->version],
        );
        $this->outbox->record($branch, 'purchase_order.'.$status->value, 'purchase_order', $purchaseOrder->public_id, [
            'document_number' => $purchaseOrder->document_number,
            'status' => $status->value,
        ]);
    }

    private function authorize(User $actor, Branch $branch, string $permission): void
    {
        if (! $actor->hasPermission($permission) || ! $actor->canAccessBranch($branch)) {
            throw new AuthorizationException;
        }
    }

    private function ensureBranch(PurchaseOrder $purchaseOrder, Branch $branch): void
    {
        if ($purchaseOrder->branch_id !== $branch->getKey()) {
            throw new AuthorizationException;
        }
    }
}
