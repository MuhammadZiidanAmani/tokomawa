<?php

namespace App\Domain\Organization\Services;

use App\Domain\Organization\Models\Branch;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class BranchAccessService
{
    /** @return Builder<Branch> */
    public function accessibleQuery(User $user): Builder
    {
        $query = Branch::query()->where('is_active', true);

        if ($user->hasRole('super_admin')) {
            return $query;
        }

        return $query->whereHas('users', fn (Builder $userQuery): Builder => $userQuery->whereKey($user->getKey()));
    }

    public function findByPublicIdOrFail(User $user, string $publicId): Branch
    {
        return $this->accessibleQuery($user)
            ->where('public_id', $publicId)
            ->firstOrFail();
    }
}
