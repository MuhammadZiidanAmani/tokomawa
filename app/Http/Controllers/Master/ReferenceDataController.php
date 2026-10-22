<?php

namespace App\Http\Controllers\Master;

use App\Domain\Access\DTOs\AuditContext;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Department;
use App\Domain\Catalog\Models\Subcategory;
use App\Domain\Catalog\Services\CatalogManager;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\UpsertBrandRequest;
use App\Http\Requests\Master\UpsertCategoryRequest;
use App\Http\Requests\Master\UpsertDepartmentRequest;
use App\Http\Requests\Master\UpsertSubcategoryRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ReferenceDataController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('catalog.view');
        $search = trim($request->string('search')->toString());

        $departments = Department::query()
            ->withCount(['categories', 'products'])
            ->when($search !== '', fn ($query) => $query->where(fn ($nested) => $nested->where('code', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%")))
            ->orderBy('name')->paginate(15, pageName: 'departments_page')->withQueryString();
        $categories = Category::query()->with('department')->withCount(['subcategories', 'products'])
            ->orderBy('name')->paginate(15, pageName: 'categories_page')->withQueryString();
        $subcategories = Subcategory::query()->with('category.department')->withCount('products')
            ->orderBy('name')->paginate(15, pageName: 'subcategories_page')->withQueryString();
        $brands = Brand::query()->withCount('products')->orderBy('name')->paginate(15, pageName: 'brands_page')->withQueryString();

        return view('master.references.index', compact('departments', 'categories', 'subcategories', 'brands'));
    }

    public function storeDepartment(UpsertDepartmentRequest $request, CatalogManager $catalog): RedirectResponse
    {
        $catalog->saveDepartment(null, $request->validated(), $this->auditContext($request));

        return back()->with('status', 'Departemen berhasil disimpan.');
    }

    public function updateDepartment(UpsertDepartmentRequest $request, Department $department, CatalogManager $catalog): RedirectResponse
    {
        $catalog->saveDepartment($department, $request->validated(), $this->auditContext($request));

        return back()->with('status', 'Departemen berhasil diperbarui.');
    }

    public function storeCategory(UpsertCategoryRequest $request, CatalogManager $catalog): RedirectResponse
    {
        $catalog->saveCategory(null, $request->validated(), $this->auditContext($request));

        return back()->with('status', 'Kategori berhasil disimpan.');
    }

    public function updateCategory(UpsertCategoryRequest $request, Category $category, CatalogManager $catalog): RedirectResponse
    {
        $catalog->saveCategory($category, $request->validated(), $this->auditContext($request));

        return back()->with('status', 'Kategori berhasil diperbarui.');
    }

    public function storeSubcategory(UpsertSubcategoryRequest $request, CatalogManager $catalog): RedirectResponse
    {
        $catalog->saveSubcategory(null, $request->validated(), $this->auditContext($request));

        return back()->with('status', 'Subkategori berhasil disimpan.');
    }

    public function updateSubcategory(UpsertSubcategoryRequest $request, Subcategory $subcategory, CatalogManager $catalog): RedirectResponse
    {
        $catalog->saveSubcategory($subcategory, $request->validated(), $this->auditContext($request));

        return back()->with('status', 'Subkategori berhasil diperbarui.');
    }

    public function storeBrand(UpsertBrandRequest $request, CatalogManager $catalog): RedirectResponse
    {
        $catalog->saveBrand(null, $request->validated(), $this->auditContext($request));

        return back()->with('status', 'Merek berhasil disimpan.');
    }

    public function updateBrand(UpsertBrandRequest $request, Brand $brand, CatalogManager $catalog): RedirectResponse
    {
        $catalog->saveBrand($brand, $request->validated(), $this->auditContext($request));

        return back()->with('status', 'Merek berhasil diperbarui.');
    }

    private function auditContext(Request $request): AuditContext
    {
        return new AuditContext($request->user(), null, $request->string('reason')->toString(), $request->ip(), $request->userAgent());
    }
}
