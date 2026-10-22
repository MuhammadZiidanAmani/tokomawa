<?php

namespace App\Policies;

use App\Domain\Pricing\Models\TaxRule;
use App\Models\User;

class TaxRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('tax.view');
    }

    public function view(User $user, TaxRule $taxRule): bool
    {
        return $user->hasPermission('tax.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('tax.manage');
    }

    public function update(User $user, TaxRule $taxRule): bool
    {
        return $user->hasPermission('tax.manage');
    }

    public function delete(User $user, TaxRule $taxRule): bool
    {
        return false;
    }
}
