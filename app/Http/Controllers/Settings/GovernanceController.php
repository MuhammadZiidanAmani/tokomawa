<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Access\Models\ApprovalLimit;
use App\Domain\Access\Models\AuditLog;
use App\Domain\Access\Models\Permission;
use App\Domain\Access\Models\Role;
use App\Domain\Organization\Services\BranchAccessService;
use App\Domain\Organization\Support\CurrentBranchContext;
use App\Domain\Shared\Support\IndonesiaFormatter;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class GovernanceController extends Controller
{
    public function __invoke(
        Request $request,
        BranchAccessService $branchAccess,
        CurrentBranchContext $branchContext,
        IndonesiaFormatter $formatter,
    ): View {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        $branches = $branchAccess->accessibleQuery($user)
            ->with(['warehouses' => fn ($query) => $query->orderBy('name'), 'registers' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get();
        $currentBranch = $branchContext->get();
        $roles = $user->can('access.view')
            ? Role::query()->with('permissions')->where('is_active', true)->orderBy('name')->get()
            : collect();
        $permissions = $user->can('approval_limits.manage')
            ? Permission::query()->orderBy('module')->orderBy('name')->get()
            : collect();
        $users = null;
        if ($user->can('access.manage')) {
            $usersQuery = User::query()->with(['roles', 'branches']);

            if (! $user->hasRole('super_admin')) {
                $accessibleBranchIds = $branches->modelKeys();
                $usersQuery->whereHas(
                    'branches',
                    fn ($query) => $query->whereKey($accessibleBranchIds),
                );
            }

            $users = $usersQuery->orderBy('name')->paginate(20, pageName: 'users_page');
        }
        $approvalLimits = $currentBranch !== null && $user->can('approvals.view')
            ? ApprovalLimit::query()
                ->whereBelongsTo($currentBranch)
                ->with(['role', 'permission'])
                ->orderBy('role_id')
                ->orderBy('permission_id')
                ->get()
            : collect();
        $auditLogs = $currentBranch !== null && $user->can('audit.view')
            ? AuditLog::query()
                ->whereBelongsTo($currentBranch)
                ->with('actor')
                ->latest('created_at')
                ->paginate(20, pageName: 'audit_page')
            : null;

        return view('settings.governance', compact(
            'approvalLimits',
            'auditLogs',
            'branches',
            'currentBranch',
            'formatter',
            'permissions',
            'roles',
            'users',
        ));
    }
}
