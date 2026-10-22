<?php

namespace Tests\Feature\Http\Controllers;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DashboardAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_is_redirected_to_login_when_opening_dashboard(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_open_dashboard(): void
    {
        $user = User::factory()->create(['name' => 'Pemilik Toko']);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Pemilik Toko')
            ->assertSee('Inventory & transaksi', escape: false)
            ->assertSee('aria-label="Navigasi"', escape: false)
            ->assertSee('Tutup navigasi');
    }

    public function test_root_redirects_to_protected_dashboard(): void
    {
        $response = $this->get('/');

        $response->assertRedirect('/dashboard');
    }

    public function test_root_route_rejects_non_get_requests(): void
    {
        $response = $this->post('/');

        $response->assertMethodNotAllowed();
    }
}
