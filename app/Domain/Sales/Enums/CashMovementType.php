<?php

namespace App\Domain\Sales\Enums;

enum CashMovementType: string
{
    case OpeningFloat = 'opening_float';
    case Sale = 'sale';
}
