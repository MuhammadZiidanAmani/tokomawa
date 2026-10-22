<?php

namespace App\Domain\Access\Services;

use App\Models\User;
use Illuminate\Auth\AuthManager;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class SessionAuthenticationService
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function __construct(
        private readonly AuthManager $auth,
        private readonly RateLimiter $rateLimiter,
    ) {}

    /**
     * @param  array{email: string, password: string}  $credentials
     *
     * @throws ValidationException
     */
    public function login(Request $request, array $credentials, bool $remember): void
    {
        $throttleKey = $this->throttleKey($credentials['email'], $request->ip());

        if ($this->rateLimiter->tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = $this->rateLimiter->availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => trans('auth.throttle', [
                    'seconds' => $seconds,
                    'minutes' => (int) ceil($seconds / 60),
                ]),
            ]);
        }

        $authenticated = $this->auth->guard()->attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'is_active' => true,
        ], $remember);

        if (! $authenticated) {
            $this->rateLimiter->hit($throttleKey, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        $this->rateLimiter->clear($throttleKey);
        $request->session()->regenerate();

        $user = $this->auth->guard()->user();

        if ($user instanceof User) {
            $user->forceFill(['last_login_at' => now()])->saveQuietly();
        }
    }

    public function logout(Request $request): void
    {
        $this->auth->guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }

    private function throttleKey(string $email, ?string $ipAddress): string
    {
        return Str::transliterate(Str::lower($email).'|'.($ipAddress ?? 'unknown'));
    }
}
