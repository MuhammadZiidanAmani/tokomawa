<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Access\DTOs\AuditContext;
use App\Domain\Access\Services\AccessManager;
use App\Domain\Organization\Support\CurrentBranchContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreAccessAssignmentRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

class AccessAssignmentController extends Controller
{
    public function store(
        StoreAccessAssignmentRequest $request,
        AccessManager $accessManager,
        CurrentBranchContext $branchContext,
    ): RedirectResponse {
        $targetUser = User::query()->findOrFail($request->integer('user_id'));

        $accessManager->assign(
            $targetUser,
            array_map('intval', $request->validated('role_ids')),
            array_map('intval', $request->validated('branch_ids', [])),
            new AuditContext(
                actor: $request->user(),
                branch: $branchContext->get(),
                reason: $request->string('reason')->toString(),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            ),
        );

        return back()->with('status', 'Hak akses pengguna berhasil diperbarui.');
    }
}
