<?php

namespace App\Domain\AccountsPayable\Enums;

enum PurchaseInvoiceStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft', self::Posted => 'Terposting'
        };
    }
}
