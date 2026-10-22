<?php

namespace App\Domain\Inventory\Enums;

enum StockAdjustmentType: string
{
    case OpeningBalance = 'opening_balance';
    case WarehouseTransfer = 'warehouse_transfer';
    case Increase = 'increase';
    case Decrease = 'decrease';
}
