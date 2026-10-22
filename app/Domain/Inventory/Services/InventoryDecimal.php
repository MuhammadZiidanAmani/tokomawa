<?php

namespace App\Domain\Inventory\Services;

use DomainException;

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

    public static function weightedAverage(
        string $currentQuantity,
        string $currentAverage,
        string $incomingQuantity,
        string $incomingCost,
    ): string {
        $newQuantity = bcadd($currentQuantity, $incomingQuantity, 3);

        if (bccomp($newQuantity, '0', 3) <= 0) {
            throw new DomainException('Kuantitas hasil perhitungan HPP harus lebih dari nol.');
        }

        $currentValue = self::value($currentQuantity, $currentAverage);
        $incomingValue = self::value($incomingQuantity, $incomingCost);

        return bcdiv(bcadd($currentValue, $incomingValue, 6), $newQuantity, 6);
    }
}
