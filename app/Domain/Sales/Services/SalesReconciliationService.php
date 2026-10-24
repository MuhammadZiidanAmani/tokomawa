<?php

namespace App\Domain\Sales\Services;

use App\Domain\Sales\Models\CashShift;
use App\Domain\Sales\Models\Sale;

class SalesReconciliationService
{
    /** @return array<string,string|bool> */
    public function reconcileSale(Sale $sale): array
    {
        $itemTotal = $sale->items()->sum('line_total');
        $paymentTotal = $sale->payment()->value('amount') ?? '0.00';
        $cashTotal = $sale->cashMovement()->value('amount_delta') ?? '0.00';
        $itemDifference = bcsub((string) $sale->grand_total, (string) $itemTotal, 2);
        $paymentDifference = bcsub((string) $sale->grand_total, (string) $paymentTotal, 2);
        $cashDifference = bcsub((string) $sale->grand_total, (string) $cashTotal, 2);

        return [
            'item_difference' => $itemDifference,
            'payment_difference' => $paymentDifference,
            'cash_difference' => $cashDifference,
            'is_reconciled' => bccomp($itemDifference, '0', 2) === 0
                && bccomp($paymentDifference, '0', 2) === 0
                && bccomp($cashDifference, '0', 2) === 0,
        ];
    }

    /** @return array<string,string|bool> */
    public function reconcileShift(CashShift $shift): array
    {
        $openingFloat = $shift->cashMovements()->where('movement_type', 'opening_float')->sum('amount_delta');
        $saleCash = $shift->cashMovements()->where('movement_type', 'sale')->sum('amount_delta');
        $postedSales = $shift->sales()->where('status', 'posted')->sum('grand_total');
        $difference = bcsub((string) $saleCash, (string) $postedSales, 2);

        return [
            'opening_float' => bcadd((string) $openingFloat, '0', 2),
            'sale_cash' => bcadd((string) $saleCash, '0', 2),
            'expected_drawer_cash' => bcadd((string) $openingFloat, (string) $saleCash, 2),
            'sale_difference' => $difference,
            'is_reconciled' => bccomp($difference, '0', 2) === 0,
        ];
    }
}
