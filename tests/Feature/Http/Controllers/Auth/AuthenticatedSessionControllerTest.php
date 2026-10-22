<?php

namespace Tests\Feature\Http\Controllers\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class AuthenticatedSessionControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_guest_can_open_login_page_without_public_registration(): void
    {
        $response = $this->get(route('login'));

        $response
            ->assertOk()
            ->assertSee('Masuk ke aplikasi')
            ->assertSee('Registrasi publik tidak tersedia')
            ->assertDontSee('Daftar akun');
    }

    public function test_valid_active_user_can_login_and_is_redirected_to_dashboard(): void
    {
        $user = User::factory()->create([
            'email' => 'owner@example.test',
            'password' => 'PasswordAman123',
        ]);

        $response = $this->post(route('login.store'), [
            'email' => 'OWNER@example.test',
            'password' => 'PasswordAman123',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_invalid_credentials_are_rejected_with_indonesian_message(): void
    {
        User::factory()->create([
            'email' => 'manager@example.test',
            'password' => 'PasswordAman123',
        ]);

        $response = $this->from(route('login'))->post(route('login.store'), [
            'email' => 'manager@example.test',
            'password' => 'PasswordSalah123',
        ]);

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'email' => 'Email, password, atau status akun tidak valid.',
            ]);
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->inactive()->create([
            'email' => 'inactive@example.test',
            'password' => 'PasswordAman123',
        ]);

        $response = $this->post(route('login.store'), [
            'email' => 'inactive@example.test',
            'password' => 'PasswordAman123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_requires_email_and_password(): void
    {
        $response = $this->from(route('login'))->post(route('login.store'));

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors([
                'email' => 'Alamat email wajib diisi.',
                'password' => 'Password wajib diisi.',
            ]);
    }

    public function test_repeated_failed_login_is_rate_limited(): void
    {
        User::factory()->create([
            'email' => 'limited@example.test',
            'password' => 'PasswordAman123',
        ]);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post(route('login.store'), [
                'email' => 'limited@example.test',
                'password' => 'PasswordSalah123',
            ])->assertSessionHasErrors('email');
        }

        $response = $this->post(route('login.store'), [
            'email' => 'limited@example.test',
            'password' => 'PasswordSalah123',
        ]);

        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString(
            'Terlalu banyak percobaan masuk.',
            session('errors')->first('email'),
        );
        $this->assertGuest();
    }

    public function test_authenticated_user_is_redirected_away_from_login_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('login'));

        $response->assertRedirect(route('dashboard'));
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', 'Anda telah keluar dengan aman.');
        $this->assertGuest();
    }
}
