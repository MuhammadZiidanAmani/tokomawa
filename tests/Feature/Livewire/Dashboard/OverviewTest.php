<?php

namespace Tests\Feature\Livewire\Dashboard;

use App\Livewire\Dashboard\Overview;
use Livewire\Livewire;
use Tests\TestCase;

class OverviewTest extends TestCase
{
    public function test_component_renders_foundation_status_without_fake_business_metrics(): void
    {
        Livewire::test(Overview::class)
            ->assertSee('Fondasi aplikasi sudah aktif')
            ->assertSee('Tidak ada data transaksi simulasi')
            ->assertSee('Moving Weighted Average');
    }
}
