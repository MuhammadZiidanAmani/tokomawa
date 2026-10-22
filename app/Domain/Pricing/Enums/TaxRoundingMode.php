<?php

namespace App\Domain\Pricing\Enums;

enum TaxRoundingMode: string
{
    case HalfUp = 'half_up';
    case HalfEven = 'half_even';
    case Down = 'down';
    case Up = 'up';

    public function label(): string
    {
        return match ($this) {
            self::HalfUp => 'Setengah ke atas',
            self::HalfEven => 'Setengah ke genap',
            self::Down => 'Ke bawah',
            self::Up => 'Ke atas',
        };
    }
}
