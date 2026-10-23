<?php

namespace App\Policies;

use App\Domain\Inventory\Models\StockAdjustment;
use App\Models\User;

class StockAdjustmentPolicy
{
    public function view(User $user, StockAdjustment $adjustment): bool
    {
        return $user->hasPermission('inventory.view')
            && $adjustment->branch()->whereHas('users', fn ($query) => $query->whereKey($user->getKey()))->exists();
    }

    public function approve(User $user, StockAdjustment $adjustment): bool
    {
        return $user->hasPermission('inventory.opening.approve')
            && $adjustment->branch()->whereHas('users', fn ($query) => $query->whereKey($user->getKey()))->exists();
    }
}
