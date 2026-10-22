<?php

namespace App\Domain\Inventory\Services;

use App\Domain\Access\Models\ApprovalRequest;
use App\Domain\Access\Services\ApprovalService;
use App\Domain\Inventory\Enums\StockAdjustmentStatus;
use App\Domain\Inventory\Enums\StockAdjustmentType;
use App\Domain\Inventory\Models\StockAdjustment;
use App\Domain\Shared\DTOs\IdempotencyResult;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;

class OpeningBalanceApprovalService
{
    public function __construct(
        private readonly ApprovalService $approvals,
        private readonly InventoryPostingService $posting,
    ) {}

    public function approveAndPost(
        StockAdjustment $adjustment,
        User $approver,
        string $decisionNotes,
        string $idempotencyKey,
    ): IdempotencyResult {
        return DB::transaction(function () use ($adjustment, $approver, $decisionNotes, $idempotencyKey): IdempotencyResult {
            $locked = StockAdjustment::query()->with('approvalRequest')->lockForUpdate()->findOrFail($adjustment->getKey());

            if ($locked->type !== StockAdjustmentType::OpeningBalance) {
                throw new DomainException('Dokumen ini bukan saldo awal.');
            }

            if ($locked->created_by === $approver->getKey()) {
                throw new DomainException('Pembuat saldo awal tidak boleh menyetujui dokumennya sendiri.');
            }

            if ($locked->status === StockAdjustmentStatus::Posted) {
                return $this->posting->post($locked, $approver, $idempotencyKey);
            }

            if ($locked->status !== StockAdjustmentStatus::PendingApproval || $locked->approvalRequest === null) {
                throw new DomainException('Saldo awal tidak sedang menunggu persetujuan.');
            }

            $approval = $this->approvals->approve($locked->approvalRequest, $approver, $decisionNotes);
            if ($approval->status !== ApprovalRequest::STATUS_APPROVED) {
                throw new DomainException('Persetujuan saldo awal gagal.');
            }

            $locked->update([
                'status' => StockAdjustmentStatus::Approved,
                'approved_by' => $approver->getKey(),
                'approved_at' => now(),
            ]);

            return $this->posting->post($locked->fresh(), $approver, $idempotencyKey);
        }, 3);
    }
}
