<?php

namespace App\Http\Controllers\Master;

use App\Domain\Access\DTOs\AuditContext;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ShelfLocation;
use App\Domain\Catalog\Services\CatalogManager;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Support\CurrentBranchContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\UpsertProductLocationRequest;
use App\Http\Requests\Master\UpsertShelfLocationRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class LocationController extends Controller
{
    public function index(Request $request, CurrentBranchContext $branchContext): View
    {
        Gate::authorize('viewAny', ShelfLocation::class);
        $branch = $branchContext->get();
        $search = trim($request->string('search')->toString());
        $locations = ShelfLocation::query()
            ->with('warehouse')
            ->with(['productLocations' => fn ($query) => $query->with('product')->where('is_active', true)])
            ->when($branch !== null, fn (Builder $query) => $query->whereBelongsTo($branch))
            ->when($branch === null, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $nested) => $nested->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")))
            ->orderBy('warehouse_id')->orderBy('code')->paginate(20)->withQueryString();

        return view('master.locations.index', [
            'branch' => $branch,
            'locations' => $locations,
            'warehouses' => $branch?->warehouses()->where('is_active', true)->orderBy('name')->get() ?? collect(),
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function store(
        UpsertShelfLocationRequest $request,
        CurrentBranchContext $branchContext,
        CatalogManager $catalog,
    ): RedirectResponse {
        $branch = $this->requireBranch($branchContext);
        Gate::authorize('create', [ShelfLocation::class, $branch]);
        $catalog->saveShelfLocation(null, $branch, $request->validated(), $this->auditContext($request, $branch));

        return back()->with('status', 'Rak berhasil disimpan.');
    }

    public function update(
        UpsertShelfLocationRequest $request,
        ShelfLocation $location,
        CurrentBranchContext $branchContext,
        CatalogManager $catalog,
    ): RedirectResponse {
        $branch = $this->requireBranch($branchContext);
        abort_unless($location->branch_id === $branch->getKey(), 404);
        Gate::authorize('update', $location);
        $catalog->saveShelfLocation($location, $branch, $request->validated(), $this->auditContext($request, $branch));

        return back()->with('status', 'Rak berhasil diperbarui.');
    }

    public function assignProduct(
        UpsertProductLocationRequest $request,
        ShelfLocation $location,
        CurrentBranchContext $branchContext,
        CatalogManager $catalog,
    ): RedirectResponse {
        $branch = $this->requireBranch($branchContext);
        abort_unless($location->branch_id === $branch->getKey(), 404);
        Gate::authorize('update', $location);
        $catalog->assignProductLocation($location, $request->validated(), $this->auditContext($request, $branch));

        return back()->with('status', 'Penempatan barang berhasil disimpan.');
    }

    private function requireBranch(CurrentBranchContext $context): Branch
    {
        $branch = $context->get();
        abort_if($branch === null, 422, 'Cabang aktif belum dikonfigurasi.');

        return $branch;
    }

    private function auditContext(Request $request, Branch $branch): AuditContext
    {
        return new AuditContext($request->user(), $branch, $request->string('reason')->toString(), $request->ip(), $request->userAgent());
    }
}
