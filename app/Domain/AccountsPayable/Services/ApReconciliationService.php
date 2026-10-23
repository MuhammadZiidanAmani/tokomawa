<?php

namespace App\Domain\AccountsPayable\Services;

use App\Domain\AccountsPayable\Enums\PurchaseInvoiceStatus;
use App\Domain\AccountsPayable\Models\ApEntry;
use App\Domain\AccountsPayable\Models\PurchaseInvoice;
use App\Domain\Organization\Models\Branch;

class ApReconciliationService
{
    /** @return array{ledger:string,projection:string,difference:string} */
    public function reconcile(Branch $branch): array
    {
        $ledger = (string) ApEntry::query()->where('branch_id', $branch->id)->sum('amount_delta');
        $projection = (string) PurchaseInvoice::query()->where('branch_id', $branch->id)->where('status', PurchaseInvoiceStatus::Posted->value)->sum('outstanding_amount');

        return ['ledger' => bcadd($ledger, '0', 2), 'projection' => bcadd($projection, '0', 2), 'difference' => bcsub($ledger, $projection, 2)];
    }
}
