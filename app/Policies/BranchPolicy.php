<?php

namespace App\Policies;

use App\Domain\Organization\Models\Branch;
use App\Models\User;

class BranchPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('branches.view');
    }

    public function view(User $user, Branch $branch): bool
    {
        return $user->hasPermission('branches.view') && $user->canAccessBranch($branch);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('branches.manage');
    }

    public function update(User $user, Branch $branch): bool
    {
        return $user->hasPermission('branches.manage') && $user->canAccessBranch($branch);
    }
}
