<?php

namespace App\Http\Middleware;

use App\Domain\Organization\Models\Branch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureBranchAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $branch = $request->route('branch');

        if (! $branch instanceof Branch || ! $request->user()?->canAccessBranch($branch)) {
            abort(404);
        }

        return $next($request);
    }
}
