<?php

namespace App\Domain\Access\Services;

use App\Domain\Access\DTOs\AuditContext;
use App\Domain\Access\Models\ApprovalRequest;
use App\Domain\Organization\Models\Branch;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class ApprovalService
{
    public function __construct(
        private readonly ApprovalLimitEvaluator $limitEvaluator,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function request(
        User $requester,
        Branch $branch,
        string $permissionCode,
        string $entityType,
        string|int $entityId,
        ?string $amount,
        string $reason,
        ?string $idempotencyKey = null,
    ): ApprovalRequest {
        if (! $requester->hasPermission('approvals.request') || ! $requester->canAccessBranch($branch)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use (
            $requester,
            $branch,
            $permissionCode,
            $entityType,
            $entityId,
            $amount,
            $reason,
            $idempotencyKey,
        ): ApprovalRequest {
            $approvalRequest = ApprovalRequest::query()->create([
                'branch_id' => $branch->getKey(),
                'permission_code' => $permissionCode,
                'entity_type' => $entityType,
                'entity_id' => (string) $entityId,
                'amount' => $amount,
                'status' => ApprovalRequest::STATUS_PENDING,
                'reason' => $reason,
                'requested_by' => $requester->getKey(),
                'idempotency_key' => $idempotencyKey,
                'requested_at' => now(),
            ]);

            $this->auditLogger->record(
                new AuditContext($requester, $branch, $reason),
                'approval.requested',
                'approval_request',
                $approvalRequest->public_id,
                after: $approvalRequest->only([
                    'public_id', 'permission_code', 'entity_type', 'entity_id', 'amount', 'status',
                ]),
            );

            return $approvalRequest;
        });
    }

    public function approve(
        ApprovalRequest $approvalRequest,
        User $approver,
        string $decisionNotes,
    ): ApprovalRequest {
        return DB::transaction(function () use ($approvalRequest, $approver, $decisionNotes): ApprovalRequest {
            $lockedRequest = ApprovalRequest::query()
                ->with('branch')
                ->lockForUpdate()
                ->findOrFail($approvalRequest->getKey());

            if ($lockedRequest->status !== ApprovalRequest::STATUS_PENDING) {
                throw new DomainException('Permintaan persetujuan sudah diputuskan.');
            }

            $amount = $lockedRequest->amount ?? '0.00';
            if (! $this->limitEvaluator->canApprove(
                $approver,
                $lockedRequest->branch,
                $lockedRequest->permission_code,
                $amount,
            )) {
                throw new AuthorizationException;
            }

            $lockedRequest->update([
                'status' => ApprovalRequest::STATUS_APPROVED,
                'approved_by' => $approver->getKey(),
                'decision_notes' => $decisionNotes,
                'decided_at' => now(),
            ]);

            $this->auditLogger->record(
                new AuditContext($approver, $lockedRequest->branch, $decisionNotes),
                'approval.approved',
                'approval_request',
                $lockedRequest->public_id,
                before: ['status' => ApprovalRequest::STATUS_PENDING],
                after: ['status' => ApprovalRequest::STATUS_APPROVED],
            );

            return $lockedRequest;
        });
    }

    public function reject(
        ApprovalRequest $approvalRequest,
        User $approver,
        string $decisionNotes,
    ): ApprovalRequest {
        return DB::transaction(function () use ($approvalRequest, $approver, $decisionNotes): ApprovalRequest {
            $lockedRequest = ApprovalRequest::query()
                ->with('branch')
                ->lockForUpdate()
                ->findOrFail($approvalRequest->getKey());

            if ($lockedRequest->status !== ApprovalRequest::STATUS_PENDING) {
                throw new DomainException('Permintaan persetujuan sudah diputuskan.');
            }

            $amount = $lockedRequest->amount ?? '0.00';
            if (! $this->limitEvaluator->canApprove(
                $approver,
                $lockedRequest->branch,
                $lockedRequest->permission_code,
                $amount,
            )) {
                throw new AuthorizationException;
            }

            $lockedRequest->update([
                'status' => ApprovalRequest::STATUS_REJECTED,
                'approved_by' => $approver->getKey(),
                'decision_notes' => $decisionNotes,
                'decided_at' => now(),
            ]);

            $this->auditLogger->record(
                new AuditContext($approver, $lockedRequest->branch, $decisionNotes),
                'approval.rejected',
                'approval_request',
                $lockedRequest->public_id,
                before: ['status' => ApprovalRequest::STATUS_PENDING],
                after: ['status' => ApprovalRequest::STATUS_REJECTED],
            );

            return $lockedRequest;
        });
    }
}
