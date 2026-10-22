<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Access\Services\SessionAuthenticationService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function store(
        LoginRequest $request,
        SessionAuthenticationService $authentication,
    ): RedirectResponse {
        $authentication->login(
            request: $request,
            credentials: $request->safe()->only(['email', 'password']),
            remember: $request->boolean('remember'),
        );

        return redirect()->intended(route('dashboard'));
    }

    public function destroy(
        Request $request,
        SessionAuthenticationService $authentication,
    ): RedirectResponse {
        $authentication->logout($request);

        return redirect()->route('login')->with('status', 'Anda telah keluar dengan aman.');
    }
}
