<?php

namespace App\Domain\Shared\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class IndonesiaFormatter
{
    public function currency(int $amount): string
    {
        $prefix = $amount < 0 ? '-Rp ' : 'Rp ';

        return $prefix.number_format(abs($amount), 0, ',', '.');
    }

    public function currencyDecimal(string $amount): string
    {
        $normalized = trim($amount);
        $negative = str_starts_with($normalized, '-');
        $unsigned = ltrim($normalized, '+-');
        [$integer, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
        $integer = ltrim($integer, '0');
        $integer = $integer === '' ? '0' : $integer;
        $fraction = rtrim(substr($fraction, 0, 2), '0');
        $formatted = number_format((int) $integer, 0, ',', '.');

        return ($negative ? '-Rp ' : 'Rp ').$formatted.($fraction !== '' ? ','.$fraction : '');
    }

    public function dateTime(CarbonInterface $dateTime): string
    {
        return CarbonImmutable::instance($dateTime)
            ->setTimezone((string) config('retail.display_timezone'))
            ->locale('id')
            ->translatedFormat('d M Y, H:i').' WIB';
    }
}
