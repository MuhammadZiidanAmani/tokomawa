<?php

namespace Tests\Feature\Console\Commands;

use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class CreateUserCommandTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_command_creates_active_internal_user(): void
    {
        $this->seed();

        $this->artisan('retail:user-create')
            ->expectsQuestion('Nama lengkap', 'Admin Toko')
            ->expectsQuestion('Alamat email', 'ADMIN@example.test')
            ->expectsQuestion('Password (minimal 12 karakter)', 'SandiAman12345')
            ->expectsQuestion('Konfirmasi password', 'SandiAman12345')
            ->expectsOutput('Role Super Admin diberikan kepada pengguna pertama.')
            ->expectsOutput('Akun pengguna berhasil dibuat.')
            ->assertSuccessful();

        $user = User::query()->where('email', 'admin@example.test')->first();

        $this->assertNotNull($user);
        $this->assertTrue($user->is_active);
        $this->assertTrue(password_verify('SandiAman12345', $user->password));
        $this->assertTrue($user->hasRole('super_admin'));
    }

    public function test_command_rejects_duplicate_email(): void
    {
        User::factory()->create(['email' => 'admin@example.test']);

        $this->artisan('retail:user-create')
            ->expectsQuestion('Nama lengkap', 'Admin Lain')
            ->expectsQuestion('Alamat email', 'admin@example.test')
            ->expectsQuestion('Password (minimal 12 karakter)', 'SandiAman12345')
            ->expectsQuestion('Konfirmasi password', 'SandiAman12345')
            ->expectsOutputToContain('sudah digunakan')
            ->assertFailed();

        $this->assertSame(1, User::query()->where('email', 'admin@example.test')->count());
    }

    public function test_command_rejects_weak_password_without_creating_user(): void
    {
        $this->artisan('retail:user-create')
            ->expectsQuestion('Nama lengkap', 'Admin Toko')
            ->expectsQuestion('Alamat email', 'admin@example.test')
            ->expectsQuestion('Password (minimal 12 karakter)', 'pendek')
            ->expectsQuestion('Konfirmasi password', 'pendek')
            ->expectsOutputToContain('minimal 12 karakter')
            ->assertFailed();

        $this->assertSame(0, User::query()->where('email', 'admin@example.test')->count());
    }

    public function test_database_seeder_does_not_create_a_default_account(): void
    {
        $this->seed();

        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('roles', 8);
        $this->assertDatabaseCount('permissions', 12);
    }
}
