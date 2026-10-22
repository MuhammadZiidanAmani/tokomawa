<?php

namespace App\Policies;

use App\Domain\Pricing\Models\PriceList;
use App\Models\User;

class PriceListPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('pricing.view');
    }

    public function view(User $user, PriceList $priceList): bool
    {
        return $user->hasPermission('pricing.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('pricing.manage');
    }

    public function update(User $user, PriceList $priceList): bool
    {
        return $user->hasPermission('pricing.manage');
    }

    public function delete(User $user, PriceList $priceList): bool
    {
        return false;
    }
}
