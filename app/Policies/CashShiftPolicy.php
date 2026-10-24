<?php

namespace App\Policies;

use App\Domain\Sales\Models\CashShift;
use App\Models\User;

class CashShiftPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('cash_shifts.view');
    }

    public function view(User $user, CashShift $cashShift): bool
    {
        return $user->hasPermission('cash_shifts.view')
            && $this->hasBranchAccess($user, $cashShift)
            && ($cashShift->cashier_id === $user->getKey() || $user->hasPermission('cash_shifts.use_any'));
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('cash_shifts.open');
    }

    private function hasBranchAccess(User $user, CashShift $cashShift): bool
    {
        return $cashShift->branch()
            ->whereHas('users', fn ($query) => $query->whereKey($user->getKey()))
            ->exists();
    }
}
