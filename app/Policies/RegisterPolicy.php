<?php

namespace App\Policies;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Register;
use App\Models\User;

class RegisterPolicy
{
    public function view(User $user, Register $register): bool
    {
        return $user->hasPermission('branches.view') && $user->canAccessBranch($register->branch);
    }

    public function create(User $user, Branch $branch): bool
    {
        return $user->hasPermission('registers.manage') && $user->canAccessBranch($branch);
    }

    public function update(User $user, Register $register): bool
    {
        return $user->hasPermission('registers.manage') && $user->canAccessBranch($register->branch);
    }
}
