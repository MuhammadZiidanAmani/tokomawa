<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Access\DTOs\AuditContext;
use App\Domain\Access\Models\Permission;
use App\Domain\Access\Models\Role;
use App\Domain\Access\Services\ApprovalLimitManager;
use App\Domain\Organization\Models\Branch;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreApprovalLimitRequest;
use Illuminate\Http\RedirectResponse;

class ApprovalLimitController extends Controller
{
    public function store(
        StoreApprovalLimitRequest $request,
        Branch $branch,
        ApprovalLimitManager $approvalLimits,
    ): RedirectResponse {
        $role = Role::query()->findOrFail($request->integer('role_id'));
        $permission = Permission::query()->findOrFail($request->integer('permission_id'));

        $approvalLimits->save(
            $branch,
            $role,
            $permission,
            $request->string('minimum_amount')->toString(),
            $request->filled('maximum_amount') ? $request->string('maximum_amount')->toString() : null,
            new AuditContext(
                actor: $request->user(),
                branch: $branch,
                reason: $request->string('reason')->toString(),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            ),
        );

        return back()->with('status', 'Batas persetujuan berhasil disimpan.');
    }
}
