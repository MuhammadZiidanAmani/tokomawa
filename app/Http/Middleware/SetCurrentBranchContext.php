<?php

namespace App\Http\Middleware;

use App\Domain\Organization\Services\BranchAccessService;
use App\Domain\Organization\Support\CurrentBranchContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

class SetCurrentBranchContext
{
    public function __construct(
        private readonly BranchAccessService $branchAccess,
        private readonly CurrentBranchContext $context,
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null) {
            $branches = $this->branchAccess->accessibleQuery($user)
                ->orderBy('name')
                ->orderBy('id')
                ->get();
            $currentBranch = $branches->firstWhere('public_id', $request->session()->get('current_branch'));

            if ($currentBranch === null) {
                $defaultBranchId = $user->branches()
                    ->wherePivot('is_default', true)
                    ->value('branches.id');
                $currentBranch = $branches->firstWhere('id', $defaultBranchId) ?? $branches->first();
            }

            $this->context->set($currentBranch);
            if ($currentBranch !== null) {
                $request->session()->put('current_branch', $currentBranch->public_id);
            }

            View::share('accessibleBranches', $branches);
            View::share('currentBranch', $currentBranch);
        }

        return $next($request);
    }
}
