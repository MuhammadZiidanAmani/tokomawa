<?php

namespace App\Console\Commands;

use App\Domain\Access\Models\Role;
use App\Models\User;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

#[Signature('retail:user-create')]
#[Description('Membuat akun pengguna internal tanpa membuka registrasi publik')]
class CreateUserCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $name = trim((string) $this->ask('Nama lengkap'));
        $email = mb_strtolower(trim((string) $this->ask('Alamat email')));
        $password = (string) $this->secret('Password (minimal 12 karakter)');
        $passwordConfirmation = (string) $this->secret('Konfirmasi password');

        $validator = Validator::make(
            [
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'password_confirmation' => $passwordConfirmation,
            ],
            [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'email', 'max:255', 'unique:users,email'],
                'password' => [
                    'required',
                    'confirmed',
                    Password::min(12)->letters()->mixedCase()->numbers(),
                ],
            ],
            [
                'password.confirmed' => 'Konfirmasi password tidak cocok.',
            ],
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            return self::FAILURE;
        }

        $isFirstUser = ! User::query()->exists();

        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'is_active' => true,
        ]);

        if ($isFirstUser) {
            $superAdminRole = Role::query()->where('code', 'super_admin')->first();

            if ($superAdminRole !== null) {
                $user->roles()->attach($superAdminRole);
                $this->info('Role Super Admin diberikan kepada pengguna pertama.');
            } else {
                $this->warn('Role belum tersedia. Jalankan php artisan db:seed lalu tetapkan role pengguna.');
            }
        }

        $this->info('Akun pengguna berhasil dibuat.');

        return self::SUCCESS;
    }
}
