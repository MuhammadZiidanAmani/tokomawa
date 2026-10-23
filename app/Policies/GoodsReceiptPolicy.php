<?php

namespace App\Policies;

use App\Domain\Purchasing\Enums\GoodsReceiptStatus;
use App\Domain\Purchasing\Models\GoodsReceipt;
use App\Models\User;

class GoodsReceiptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('goods_receipts.view');
    }

    public function view(User $user, GoodsReceipt $goodsReceipt): bool
    {
        return $user->hasPermission('goods_receipts.view') && $this->hasBranchAccess($user, $goodsReceipt);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('goods_receipts.create');
    }

    public function update(User $user, GoodsReceipt $goodsReceipt): bool
    {
        return $user->hasPermission('goods_receipts.update')
            && $goodsReceipt->status === GoodsReceiptStatus::Draft
            && $this->hasBranchAccess($user, $goodsReceipt);
    }

    public function delete(User $user, GoodsReceipt $goodsReceipt): bool
    {
        return false;
    }

    public function restore(User $user, GoodsReceipt $goodsReceipt): bool
    {
        return false;
    }

    public function forceDelete(User $user, GoodsReceipt $goodsReceipt): bool
    {
        return false;
    }

    public function post(User $user, GoodsReceipt $goodsReceipt): bool
    {
        return $user->hasPermission('goods_receipts.post')
            && $goodsReceipt->status === GoodsReceiptStatus::Draft
            && $this->hasBranchAccess($user, $goodsReceipt);
    }

    public function cancel(User $user, GoodsReceipt $goodsReceipt): bool
    {
        return $user->hasPermission('goods_receipts.cancel')
            && $goodsReceipt->status === GoodsReceiptStatus::Draft
            && $this->hasBranchAccess($user, $goodsReceipt);
    }

    private function hasBranchAccess(User $user, GoodsReceipt $goodsReceipt): bool
    {
        return $goodsReceipt->branch()
            ->whereHas('users', fn ($query) => $query->whereKey($user->getKey()))
            ->exists();
    }
}
