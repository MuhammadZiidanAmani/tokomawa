<?php

namespace App\Domain\AccountsPayable\Enums;

enum PurchaseType: string
{
    case Cash = 'cash';
    case Credit = 'credit';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Tunai', self::Credit => 'Kredit'
        };
    }
}
