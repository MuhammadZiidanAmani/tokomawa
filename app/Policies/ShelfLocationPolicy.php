<?php

namespace App\Policies;

use App\Domain\Catalog\Models\ShelfLocation;
use App\Domain\Organization\Models\Branch;
use App\Models\User;

class ShelfLocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('locations.view');
    }

    public function view(User $user, ShelfLocation $shelfLocation): bool
    {
        return $user->hasPermission('locations.view') && $user->canAccessBranch($shelfLocation->branch);
    }

    public function create(User $user, Branch $branch): bool
    {
        return $user->hasPermission('locations.manage') && $user->canAccessBranch($branch);
    }

    public function update(User $user, ShelfLocation $shelfLocation): bool
    {
        return $user->hasPermission('locations.manage') && $user->canAccessBranch($shelfLocation->branch);
    }

    public function delete(User $user, ShelfLocation $shelfLocation): bool
    {
        return false;
    }
}
