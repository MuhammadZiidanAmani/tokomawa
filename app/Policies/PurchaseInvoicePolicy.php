<?php

namespace App\Policies;

use App\Domain\AccountsPayable\Enums\PurchaseInvoiceStatus;
use App\Domain\AccountsPayable\Models\PurchaseInvoice;
use App\Models\User;

class PurchaseInvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('purchase_invoices.view');
    }

    public function view(User $user, PurchaseInvoice $invoice): bool
    {
        return $user->hasPermission('purchase_invoices.view') && $user->canAccessBranch($invoice->branch);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('purchase_invoices.create');
    }

    public function update(User $user, PurchaseInvoice $invoice): bool
    {
        return $user->hasPermission('purchase_invoices.update') && $invoice->status === PurchaseInvoiceStatus::Draft && $user->canAccessBranch($invoice->branch);
    }

    public function post(User $user, PurchaseInvoice $invoice): bool
    {
        return $user->hasPermission('purchase_invoices.post') && $invoice->status === PurchaseInvoiceStatus::Draft && $user->canAccessBranch($invoice->branch);
    }

    public function delete(User $user, PurchaseInvoice $invoice): bool
    {
        return false;
    }
}
