<?php

namespace App\Domain\Purchasing\Enums;

enum GoodsReceiptStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Posted => 'Terposting',
            self::Cancelled => 'Dibatalkan',
        };
    }
}
