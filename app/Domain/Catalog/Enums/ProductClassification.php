<?php

namespace App\Domain\Catalog\Enums;

enum ProductClassification: string
{
    case Stock = 'stock';
    case NonStock = 'non_stock';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Stock => 'Barang stok',
            self::NonStock => 'Barang nonstok',
            self::Service => 'Jasa',
        };
    }
}
