<?php

namespace App\Domain\Organization\Services;

use App\Domain\Access\DTOs\AuditContext;
use App\Domain\Access\Services\AuditLogger;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Register;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Shared\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

class OrganizationManager
{
    public function __construct(
        private readonly AuditLogger $auditLogger,
        private readonly OutboxRecorder $outboxRecorder,
    ) {}

    /** @param array{code: string, name: string, timezone: string} $data */
    public function createBranch(array $data, AuditContext $context): Branch
    {
        return DB::transaction(function () use ($data, $context): Branch {
            $branch = Branch::query()->create([
                ...$data,
                'is_active' => true,
                'created_by' => $context->actor?->getKey(),
            ]);

            if ($context->actor !== null) {
                $isDefault = ! $context->actor->branches()->exists();
                $context->actor->branches()->syncWithoutDetaching([
                    $branch->getKey() => ['is_default' => $isDefault],
                ]);
            }

            $branchContext = new AuditContext(
                actor: $context->actor,
                branch: $branch,
                reason: $context->reason,
                ipAddress: $context->ipAddress,
                userAgent: $context->userAgent,
            );

            $this->auditLogger->record(
                $branchContext,
                'branch.created',
                'branch',
                $branch->public_id,
                after: $branch->only(['public_id', 'code', 'name', 'timezone', 'is_active']),
            );
            $this->outboxRecorder->record($branch, 'organization.branch_created', 'branch', $branch->public_id, [
                'code' => $branch->code,
                'name' => $branch->name,
            ]);

            return $branch;
        });
    }

    /** @param array{code: string, name: string} $data */
    public function createWarehouse(Branch $branch, array $data, AuditContext $context): Warehouse
    {
        return DB::transaction(function () use ($branch, $data, $context): Warehouse {
            $warehouse = $branch->warehouses()->create([
                ...$data,
                'is_active' => true,
                'created_by' => $context->actor?->getKey(),
            ]);

            $this->auditLogger->record(
                $context,
                'warehouse.created',
                'warehouse',
                $warehouse->public_id,
                after: $warehouse->only(['public_id', 'branch_id', 'code', 'name', 'is_active']),
            );

            return $warehouse;
        });
    }

    /** @param array{code: string, name: string} $data */
    public function createRegister(Branch $branch, array $data, AuditContext $context): Register
    {
        return DB::transaction(function () use ($branch, $data, $context): Register {
            $register = $branch->registers()->create([
                ...$data,
                'is_active' => true,
                'created_by' => $context->actor?->getKey(),
            ]);

            $this->auditLogger->record(
                $context,
                'register.created',
                'register',
                $register->public_id,
                after: $register->only(['public_id', 'branch_id', 'code', 'name', 'is_active']),
            );

            return $register;
        });
    }
}
