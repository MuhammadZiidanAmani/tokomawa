<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Access\DTOs\AuditContext;
use App\Domain\Organization\Services\OrganizationManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreBranchRequest;
use Illuminate\Http\RedirectResponse;

class BranchController extends Controller
{
    public function store(StoreBranchRequest $request, OrganizationManager $organization): RedirectResponse
    {
        $organization->createBranch(
            $request->safe()->only(['code', 'name', 'timezone']),
            new AuditContext(
                actor: $request->user(),
                branch: null,
                reason: 'Membuat cabang operasional.',
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            ),
        );

        return back()->with('status', 'Cabang berhasil dibuat.');
    }
}
