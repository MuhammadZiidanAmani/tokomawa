<?php

namespace Tests\Unit\Domain\Shared\Support;

use App\Domain\Shared\Support\IndonesiaFormatter;
use Carbon\CarbonImmutable;
use Tests\TestCase;

class IndonesiaFormatterTest extends TestCase
{
    public function test_formats_positive_and_negative_idr_without_float(): void
    {
        $formatter = new IndonesiaFormatter;

        $this->assertSame('Rp 1.250.000', $formatter->currency(1_250_000));
        $this->assertSame('-Rp 40.000', $formatter->currency(-40_000));
    }

    public function test_formats_utc_time_for_asia_jakarta_display(): void
    {
        $formatter = new IndonesiaFormatter;
        $utcDateTime = CarbonImmutable::parse('2026-10-22 01:30:00', 'UTC');

        $this->assertSame('22 Okt 2026, 08:30 WIB', $formatter->dateTime($utcDateTime));
    }

    public function test_formats_decimal_quantity_without_float_conversion(): void
    {
        $formatter = new IndonesiaFormatter;

        $this->assertSame('1.234.567,125', $formatter->quantity('1234567.125'));
        $this->assertSame('-12,5', $formatter->quantity('-12.500'));
        $this->assertSame('0', $formatter->quantity('0.000'));
    }
}
