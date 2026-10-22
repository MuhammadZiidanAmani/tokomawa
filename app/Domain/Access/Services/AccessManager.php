<?php

namespace App\Domain\Access\Services;

use App\Domain\Access\DTOs\AuditContext;
use App\Domain\Access\Models\Role;
use App\Domain\Organization\Models\Branch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AccessManager
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    /**
     * @param  array<int, int>  $roleIds
     * @param  array<int, int>  $branchIds
     */
    public function assign(User $user, array $roleIds, array $branchIds, AuditContext $context): void
    {
        DB::transaction(function () use ($user, $roleIds, $branchIds, $context): void {
            $before = [
                'roles' => $user->roles()->orderBy('code')->pluck('code')->all(),
                'branches' => $user->branches()->orderBy('code')->pluck('code')->all(),
            ];

            $superAdminRoleId = Role::query()->where('code', 'super_admin')->value('id');

            if ($context->actor?->is($user) && $user->hasRole('super_admin') && ! in_array($superAdminRoleId, $roleIds, true)) {
                throw ValidationException::withMessages([
                    'role_ids' => 'Anda tidak dapat mencabut role Super Admin dari akun sendiri.',
                ]);
            }

            $user->roles()->sync($roleIds);

            $branchAssignments = [];
            foreach ($branchIds as $index => $branchId) {
                $branchAssignments[$branchId] = ['is_default' => $index === 0];
            }
            $user->branches()->sync($branchAssignments);

            $this->auditLogger->record(
                $context,
                'access.assignment_updated',
                'user',
                $user->getKey(),
                before: $before,
                after: [
                    'roles' => Role::query()->whereKey($roleIds)->orderBy('code')->pluck('code')->all(),
                    'branches' => Branch::query()->whereKey($branchIds)->orderBy('code')->pluck('code')->all(),
                ],
            );
        });
    }
}
