<?php

namespace App\Http\Controllers;

use App\Domain\Organization\Services\BranchAccessService;
use App\Http\Requests\SwitchCurrentBranchRequest;
use Illuminate\Http\RedirectResponse;

class CurrentBranchController extends Controller
{
    public function __invoke(
        SwitchCurrentBranchRequest $request,
        BranchAccessService $branchAccess,
    ): RedirectResponse {
        $branch = $branchAccess->findByPublicIdOrFail(
            $request->user(),
            $request->string('branch')->toString(),
        );
        $request->session()->put('current_branch', $branch->public_id);

        return back()->with('status', 'Cabang aktif berhasil diganti.');
    }
}
