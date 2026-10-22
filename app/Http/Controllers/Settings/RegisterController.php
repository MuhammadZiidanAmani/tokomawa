<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Access\DTOs\AuditContext;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Services\OrganizationManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreRegisterRequest;
use Illuminate\Http\RedirectResponse;

class RegisterController extends Controller
{
    public function store(
        StoreRegisterRequest $request,
        Branch $branch,
        OrganizationManager $organization,
    ): RedirectResponse {
        $organization->createRegister(
            $branch,
            $request->safe()->only(['code', 'name']),
            new AuditContext(
                actor: $request->user(),
                branch: $branch,
                reason: 'Membuat register kasir pada cabang.',
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            ),
        );

        return back()->with('status', 'Register berhasil dibuat.');
    }
}
