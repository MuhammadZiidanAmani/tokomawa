<?php

namespace App\Http\Controllers\Master;

use App\Domain\Access\DTOs\AuditContext;
use App\Domain\Catalog\Models\Unit;
use App\Domain\Catalog\Services\CatalogManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\UpsertUnitRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class UnitController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('catalog.view');
        $search = trim($request->string('search')->toString());
        $units = Unit::query()->withCount(['baseProducts', 'productUnits'])
            ->when($search !== '', fn ($query) => $query->where(fn ($nested) => $nested->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")))
            ->orderBy('name')->paginate(20)->withQueryString();

        return view('master.units.index', compact('units'));
    }

    public function store(UpsertUnitRequest $request, CatalogManager $catalog): RedirectResponse
    {
        $catalog->saveUnit(null, $request->validated(), $this->auditContext($request));

        return back()->with('status', 'Satuan berhasil disimpan.');
    }

    public function update(UpsertUnitRequest $request, Unit $unit, CatalogManager $catalog): RedirectResponse
    {
        $catalog->saveUnit($unit, $request->validated(), $this->auditContext($request));

        return back()->with('status', 'Satuan berhasil diperbarui.');
    }

    private function auditContext(Request $request): AuditContext
    {
        return new AuditContext($request->user(), null, $request->string('reason')->toString(), $request->ip(), $request->userAgent());
    }
}
