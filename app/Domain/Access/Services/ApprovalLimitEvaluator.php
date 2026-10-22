<?php

namespace App\Domain\Access\Services;

use App\Domain\Access\Models\ApprovalLimit;
use App\Domain\Organization\Models\Branch;
use App\Models\User;

class ApprovalLimitEvaluator
{
    public function canApprove(User $user, Branch $branch, string $permissionCode, string $amount): bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        if (! $user->canAccessBranch($branch) || ! $user->hasPermission('approvals.approve')) {
            return false;
        }

        return ApprovalLimit::query()
            ->whereBelongsTo($branch)
            ->where('is_active', true)
            ->whereHas('role.users', fn ($query) => $query->whereKey($user->getKey()))
            ->whereHas('permission', fn ($query) => $query->where('code', $permissionCode))
            ->get()
            ->contains(function (ApprovalLimit $limit) use ($amount): bool {
                $aboveMinimum = bccomp($amount, $limit->minimum_amount, 2) >= 0;
                $belowMaximum = $limit->maximum_amount === null
                    || bccomp($amount, $limit->maximum_amount, 2) <= 0;

                return $aboveMinimum && $belowMaximum;
            });
    }
}
