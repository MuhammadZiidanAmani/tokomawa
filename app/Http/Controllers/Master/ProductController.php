<?php

namespace App\Http\Controllers\Master;

use App\Domain\Access\DTOs\AuditContext;
use App\Domain\Catalog\Enums\ProductClassification;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Department;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Subcategory;
use App\Domain\Catalog\Models\Unit;
use App\Domain\Catalog\Services\CatalogManager;
use App\Domain\Pricing\Models\TaxRule;
use App\Domain\Shared\Support\IndonesiaFormatter;
use App\Domain\Suppliers\Models\Supplier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\UpsertProductRequest;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ProductController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Product::class);

        $search = trim($request->string('search')->toString());
        $products = Product::query()
            ->with(['brand', 'category', 'baseUnit', 'defaultSupplier', 'barcodes' => fn ($query) => $query->where('is_active', true)->orderByDesc('is_primary')])
            ->when($search !== '', function (Builder $query) use ($search): void {
                $query->where(function (Builder $nested) use ($search): void {
                    $nested->where('sku', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhereHas('barcodes', fn (Builder $barcodeQuery) => $barcodeQuery->where('barcode', 'like', "%{$search}%"));
                });
            })
            ->when($request->filled('category_id'), fn (Builder $query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->filled('status'), fn (Builder $query) => $query->where('is_active', $request->string('status')->toString() === 'active'))
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(20)
            ->withQueryString();

        return view('master.products.index', [
            'products' => $products,
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Product::class);

        return view('master.products.form', $this->formData());
    }

    public function store(UpsertProductRequest $request, CatalogManager $catalog): RedirectResponse
    {
        $product = $catalog->saveProduct(null, $request->validated(), $this->auditContext($request));

        return redirect()->route('master.products.show', $product)->with('status', 'Barang berhasil dibuat.');
    }

    public function show(Product $product, IndonesiaFormatter $formatter): View
    {
        Gate::authorize('view', $product);
        $product->load([
            'department', 'category', 'subcategory', 'brand', 'baseUnit', 'defaultSupplier', 'taxRule',
            'barcodes' => fn ($query) => $query->where('is_active', true)->orderByDesc('is_primary'),
            'units' => fn ($query) => $query->with('unit')->where('is_active', true)->orderBy('unit_id'),
            'supplierProducts' => fn ($query) => $query->with(['supplier', 'purchaseUnit'])->where('is_active', true)->orderBy('priority'),
            'locations' => fn ($query) => $query->with('shelfLocation.warehouse')->where('is_active', true),
            'prices' => fn ($query) => $query->with(['priceList', 'unit'])->latest('effective_from'),
        ]);

        return view('master.products.show', compact('formatter', 'product'));
    }

    public function edit(Product $product): View
    {
        Gate::authorize('update', $product);
        $product->load(['barcodes', 'units']);

        return view('master.products.form', [...$this->formData(), 'product' => $product]);
    }

    public function update(UpsertProductRequest $request, Product $product, CatalogManager $catalog): RedirectResponse
    {
        $product = $catalog->saveProduct($product, $request->validated(), $this->auditContext($request));

        return redirect()->route('master.products.show', $product)->with('status', 'Barang berhasil diperbarui.');
    }

    /** @return array<string, mixed> */
    private function formData(): array
    {
        return [
            'classifications' => ProductClassification::cases(),
            'departments' => Department::query()->where('is_active', true)->orderBy('name')->get(),
            'categories' => Category::query()->where('is_active', true)->orderBy('name')->get(),
            'subcategories' => Subcategory::query()->where('is_active', true)->orderBy('name')->get(),
            'brands' => Brand::query()->where('is_active', true)->orderBy('name')->get(),
            'units' => Unit::query()->where('is_active', true)->orderBy('name')->get(),
            'suppliers' => Supplier::query()->where('is_active', true)->orderBy('name')->get(),
            'taxRules' => TaxRule::query()->where('is_active', true)->orderBy('name')->orderByDesc('effective_from')->get(),
        ];
    }

    private function auditContext(Request $request): AuditContext
    {
        return new AuditContext(
            actor: $request->user(),
            branch: null,
            reason: $request->string('reason')->toString(),
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );
    }
}
