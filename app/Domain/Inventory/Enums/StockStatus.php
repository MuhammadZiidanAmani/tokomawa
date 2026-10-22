<?php

namespace App\Domain\Inventory\Enums;

enum StockStatus: string
{
    case Sellable = 'sellable';
    case Quarantine = 'quarantine';
    case Consignment = 'consignment';
}
