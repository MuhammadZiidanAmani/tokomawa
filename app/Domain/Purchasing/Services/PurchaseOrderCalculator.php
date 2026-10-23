<?php

namespace App\Domain\Purchasing\Services;

use DomainException;

class PurchaseOrderCalculator
{
    public function quantity(string $quantity): string
    {
        if (preg_match('/^\d{1,15}(\.\d{1,3})?$/', $quantity) !== 1 || bccomp($quantity, '0', 3) <= 0) {
            throw new DomainException('Kuantitas harus lebih dari nol dengan maksimal tiga angka desimal.');
        }

        return bcadd($quantity, '0', 3);
    }

    public function conversionFactor(string $factor): string
    {
        if (preg_match('/^\d{1,12}(\.\d{1,6})?$/', $factor) !== 1 || bccomp($factor, '0', 6) <= 0) {
            throw new DomainException('Faktor konversi satuan tidak valid.');
        }

        return bcadd($factor, '0', 6);
    }

    public function money(string $amount): string
    {
        if (preg_match('/^\d{1,16}(\.\d{1,2})?$/', $amount) !== 1 || bccomp($amount, '0', 2) < 0) {
            throw new DomainException('Harga beli tidak valid.');
        }

        return bcadd($amount, '0', 2);
    }

    public function baseQuantity(string $quantity, string $conversionFactor): string
    {
        return bcmul($this->quantity($quantity), $this->conversionFactor($conversionFactor), 3);
    }

    public function subtotal(string $quantity, string $unitPrice): string
    {
        return bcround(bcmul($this->quantity($quantity), $this->money($unitPrice), 5), 2);
    }
}
