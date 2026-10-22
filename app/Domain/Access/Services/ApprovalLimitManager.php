<?php

namespace App\Domain\Access\Services;

use App\Domain\Access\DTOs\AuditContext;
use App\Domain\Access\Models\ApprovalLimit;
use App\Domain\Access\Models\Permission;
use App\Domain\Access\Models\Role;
use App\Domain\Organization\Models\Branch;
use Illuminate\Support\Facades\DB;

class ApprovalLimitManager
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function save(
        Branch $branch,
        Role $role,
        Permission $permission,
        string $minimumAmount,
        ?string $maximumAmount,
        AuditContext $context,
    ): ApprovalLimit {
        return DB::transaction(function () use (
            $branch,
            $role,
            $permission,
            $minimumAmount,
            $maximumAmount,
            $context,
        ): ApprovalLimit {
            $existing = ApprovalLimit::query()
                ->whereBelongsTo($branch)
                ->whereBelongsTo($role)
                ->whereBelongsTo($permission)
                ->lockForUpdate()
                ->first();
            $before = $existing?->only(['minimum_amount', 'maximum_amount', 'is_active']);

            $limit = ApprovalLimit::query()->updateOrCreate(
                [
                    'branch_id' => $branch->getKey(),
                    'role_id' => $role->getKey(),
                    'permission_id' => $permission->getKey(),
                ],
                [
                    'minimum_amount' => $minimumAmount,
                    'maximum_amount' => $maximumAmount,
                    'is_active' => true,
                    'created_by' => $context->actor?->getKey(),
                ],
            );

            $this->auditLogger->record(
                $context,
                'approval_limit.saved',
                'approval_limit',
                $limit->public_id,
                before: $before,
                after: $limit->only(['minimum_amount', 'maximum_amount', 'is_active']),
            );

            return $limit;
        });
    }
}
