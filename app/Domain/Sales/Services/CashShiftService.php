<?php

namespace App\Domain\Sales\Services;

use App\Domain\Access\DTOs\AuditContext;
use App\Domain\Access\Services\AuditLogger;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Register;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Sales\Enums\CashMovementType;
use App\Domain\Sales\Enums\CashShiftStatus;
use App\Domain\Sales\Models\CashMovement;
use App\Domain\Sales\Models\CashShift;
use App\Domain\Shared\Services\IdempotencyService;
use App\Domain\Shared\Services\OutboxRecorder;
use App\Models\User;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;

class CashShiftService
{
    public function __construct(
        private readonly IdempotencyService $idempotency,
        private readonly AuditLogger $auditLogger,
        private readonly OutboxRecorder $outbox,
    ) {}

    public function open(
        User $actor,
        Branch $branch,
        int $registerId,
        int $warehouseId,
        string $openingFloat,
        string $idempotencyKey,
    ): CashShift {
        if (! $actor->is_active || ! $actor->hasPermission('cash_shifts.open') || ! $actor->canAccessBranch($branch)) {
            throw new AuthorizationException;
        }

        $amount = bcadd($openingFloat, '0', 2);
        if (bccomp($amount, '0', 2) < 0) {
            throw new DomainException('Modal awal tidak boleh bernilai negatif.');
        }

        $result = $this->idempotency->execute(
            'branch:'.$branch->getKey(),
            'cash_shift.open',
            $idempotencyKey,
            ['register_id' => $registerId, 'warehouse_id' => $warehouseId, 'opening_float' => $amount],
            function () use ($actor, $branch, $registerId, $warehouseId, $amount, $idempotencyKey): array {
                $register = Register::query()->whereKey($registerId)->lockForUpdate()->firstOrFail();
                $warehouse = Warehouse::query()->whereKey($warehouseId)->lockForUpdate()->firstOrFail();
                if ($register->branch_id !== $branch->getKey() || $warehouse->branch_id !== $branch->getKey()) {
                    throw new AuthorizationException;
                }
                if (! $register->is_active || ! $warehouse->is_active) {
                    throw new DomainException('Register dan gudang harus aktif untuk membuka shift.');
                }
                if (CashShift::query()->where('active_register_key', 'register:'.$register->getKey())->exists()) {
                    throw new DomainException('Register sedang digunakan oleh shift aktif.');
                }
                if (CashShift::query()->where('active_cashier_key', 'cashier:'.$actor->getKey())->exists()) {
                    throw new DomainException('Kasir masih memiliki shift aktif.');
                }

                $openedAt = now();
                $shift = CashShift::query()->create([
                    'branch_id' => $branch->getKey(),
                    'register_id' => $register->getKey(),
                    'warehouse_id' => $warehouse->getKey(),
                    'cashier_id' => $actor->getKey(),
                    'status' => CashShiftStatus::Open,
                    'opening_float' => $amount,
                    'opened_at' => $openedAt,
                    'active_register_key' => 'register:'.$register->getKey(),
                    'active_cashier_key' => 'cashier:'.$actor->getKey(),
                    'opening_key' => $idempotencyKey,
                    'opened_by' => $actor->getKey(),
                ]);
                CashMovement::query()->create([
                    'branch_id' => $branch->getKey(),
                    'register_id' => $register->getKey(),
                    'cash_shift_id' => $shift->getKey(),
                    'movement_type' => CashMovementType::OpeningFloat,
                    'amount_delta' => $amount,
                    'source_key' => 'cash-shift:'.$shift->public_id.':opening',
                    'description' => 'Modal awal shift kasir',
                    'occurred_at' => $openedAt,
                    'created_by' => $actor->getKey(),
                ]);
                $this->auditLogger->record(
                    new AuditContext($actor, $branch, 'Membuka shift kasir.'),
                    'cash_shift.opened',
                    'cash_shift',
                    $shift->public_id,
                    after: [
                        'register_id' => $register->public_id,
                        'warehouse_id' => $warehouse->public_id,
                        'cashier_id' => $actor->getKey(),
                        'opening_float' => $amount,
                    ],
                );
                $this->outbox->record($branch, 'cash_shift.opened', 'cash_shift', $shift->public_id, [
                    'register_id' => $register->public_id,
                    'warehouse_id' => $warehouse->public_id,
                ]);

                return ['cash_shift_id' => $shift->public_id];
            },
            $actor,
            201,
        );

        return CashShift::query()->where('public_id', $result->payload['cash_shift_id'])->firstOrFail();
    }
}
