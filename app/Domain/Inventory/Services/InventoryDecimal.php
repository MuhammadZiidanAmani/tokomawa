<?php

namespace App\Domain\Inventory\Services;

class InventoryDecimal
{
    public static function quantity(string $value): string
    {
        return bcadd($value, '0', 3);
    }

    public static function cost(string $value): string
    {
        return bcadd($value, '0', 6);
    }

    public static function value(string $quantity, string $unitCost): string
    {
        return bcmul($quantity, $unitCost, 6);
    }

    public static function averageFromValue(string $inventoryValue, string $quantity): string
    {
        if (bccomp($quantity, '0', 3) <= 0) {
            return '0.000000';
        }

        return bcdiv($inventoryValue, $quantity, 6);
    }
}
