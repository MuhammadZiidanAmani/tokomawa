<?php

namespace App\Policies;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Warehouse;
use App\Models\User;

class WarehousePolicy
{
    public function view(User $user, Warehouse $warehouse): bool
    {
        return $user->hasPermission('branches.view') && $user->canAccessBranch($warehouse->branch);
    }

    public function create(User $user, Branch $branch): bool
    {
        return $user->hasPermission('warehouses.manage') && $user->canAccessBranch($branch);
    }

    public function update(User $user, Warehouse $warehouse): bool
    {
        return $user->hasPermission('warehouses.manage') && $user->canAccessBranch($warehouse->branch);
    }
}
