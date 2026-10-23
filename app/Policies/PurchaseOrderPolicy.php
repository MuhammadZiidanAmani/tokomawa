<?php

namespace App\Policies;

use App\Domain\Purchasing\Enums\PurchaseOrderStatus;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Models\User;

class PurchaseOrderPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('purchase_orders.view');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->hasPermission('purchase_orders.view') && $this->hasBranchAccess($user, $purchaseOrder);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('purchase_orders.create');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->hasPermission('purchase_orders.update')
            && $purchaseOrder->status === PurchaseOrderStatus::Draft
            && $this->hasBranchAccess($user, $purchaseOrder);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return false;
    }

    public function submit(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->hasPermission('purchase_orders.submit')
            && $purchaseOrder->status === PurchaseOrderStatus::Draft
            && $this->hasBranchAccess($user, $purchaseOrder);
    }

    public function approve(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->hasPermission('purchase_orders.approve')
            && $purchaseOrder->status === PurchaseOrderStatus::PendingApproval
            && $purchaseOrder->created_by !== $user->getKey()
            && $this->hasBranchAccess($user, $purchaseOrder);
    }

    public function send(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->hasPermission('purchase_orders.send')
            && $purchaseOrder->status === PurchaseOrderStatus::Approved
            && $this->hasBranchAccess($user, $purchaseOrder);
    }

    public function cancel(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $user->hasPermission('purchase_orders.cancel')
            && in_array($purchaseOrder->status, [
                PurchaseOrderStatus::Draft,
                PurchaseOrderStatus::Rejected,
                PurchaseOrderStatus::Approved,
                PurchaseOrderStatus::Sent,
            ], true)
            && $this->hasBranchAccess($user, $purchaseOrder);
    }

    private function hasBranchAccess(User $user, PurchaseOrder $purchaseOrder): bool
    {
        return $purchaseOrder->branch()
            ->whereHas('users', fn ($query) => $query->whereKey($user->getKey()))
            ->exists();
    }
}
