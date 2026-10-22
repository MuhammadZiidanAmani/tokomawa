<?php

namespace App\Http\Controllers\Master;

use App\Domain\Access\DTOs\AuditContext;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Unit;
use App\Domain\Suppliers\Models\Supplier;
use App\Domain\Suppliers\Services\SupplierManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\UpsertSupplierProductRequest;
use App\Http\Requests\Master\UpsertSupplierRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class SupplierController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Supplier::class);
        $search = trim($request->string('search')->toString());
        $suppliers = Supplier::query()
            ->withCount(['supplierProducts', 'defaultProducts'])
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $nested) => $nested->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")->orWhere('phone', 'like', "%{$search}%")))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('is_active', $request->string('status')->toString() === 'active'))
            ->orderBy('name')->orderBy('id')->paginate(20)->withQueryString();

        return view('master.suppliers.index', compact('suppliers'));
    }

    public function create(): View
    {
        Gate::authorize('create', Supplier::class);

        return view('master.suppliers.form');
    }

    public function store(UpsertSupplierRequest $request, SupplierManager $suppliers): RedirectResponse
    {
        $supplier = $suppliers->saveSupplier(null, $request->validated(), $this->auditContext($request));

        return redirect()->route('master.suppliers.show', $supplier)->with('status', 'Supplier berhasil dibuat.');
    }

    public function show(Supplier $supplier): View
    {
        Gate::authorize('view', $supplier);
        $supplier->load(['supplierProducts' => fn ($query) => $query->with(['product', 'purchaseUnit'])->orderBy('priority')]);

        return view('master.suppliers.show', [
            'supplier' => $supplier,
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(),
            'units' => Unit::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function edit(Supplier $supplier): View
    {
        Gate::authorize('update', $supplier);

        return view('master.suppliers.form', compact('supplier'));
    }

    public function update(UpsertSupplierRequest $request, Supplier $supplier, SupplierManager $suppliers): RedirectResponse
    {
        $supplier = $suppliers->saveSupplier($supplier, $request->validated(), $this->auditContext($request));

        return redirect()->route('master.suppliers.show', $supplier)->with('status', 'Supplier berhasil diperbarui.');
    }

    public function storeProduct(
        UpsertSupplierProductRequest $request,
        Supplier $supplier,
        SupplierManager $suppliers,
    ): RedirectResponse {
        Gate::authorize('update', $supplier);
        $suppliers->saveSupplierProduct($supplier, $request->validated(), $this->auditContext($request));

        return back()->with('status', 'Relasi barang supplier berhasil disimpan.');
    }

    private function auditContext(Request $request): AuditContext
    {
        return new AuditContext($request->user(), null, $request->string('reason')->toString(), $request->ip(), $request->userAgent());
    }
}
