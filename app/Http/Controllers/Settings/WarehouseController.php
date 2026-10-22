<?php

namespace App\Http\Controllers\Settings;

use App\Domain\Access\DTOs\AuditContext;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Services\OrganizationManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\StoreWarehouseRequest;
use Illuminate\Http\RedirectResponse;

class WarehouseController extends Controller
{
    public function store(
        StoreWarehouseRequest $request,
        Branch $branch,
        OrganizationManager $organization,
    ): RedirectResponse {
        $organization->createWarehouse(
            $branch,
            $request->safe()->only(['code', 'name']),
            new AuditContext(
                actor: $request->user(),
                branch: $branch,
                reason: 'Membuat gudang pada cabang.',
                ipAddress: $request->ip(),
                userAgent: $request->userAgent(),
            ),
        );

        return back()->with('status', 'Gudang berhasil dibuat.');
    }
}
