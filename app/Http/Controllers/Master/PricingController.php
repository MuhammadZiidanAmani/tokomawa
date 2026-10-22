<?php

namespace App\Http\Controllers\Master;

use App\Domain\Access\DTOs\AuditContext;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\Unit;
use App\Domain\Pricing\Enums\TaxRoundingMode;
use App\Domain\Pricing\Models\PriceList;
use App\Domain\Pricing\Models\ProductPrice;
use App\Domain\Pricing\Models\TaxRule;
use App\Domain\Pricing\Services\PricingManager;
use App\Domain\Shared\Support\IndonesiaFormatter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Master\StoreProductPriceRequest;
use App\Http\Requests\Master\UpsertPriceListRequest;
use App\Http\Requests\Master\UpsertTaxRuleRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PricingController extends Controller
{
    public function index(Request $request, IndonesiaFormatter $formatter): View
    {
        Gate::authorize('pricing.view');
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return view('master.pricing.index', [
            'priceLists' => PriceList::query()->withCount('prices')->orderBy('priority')->orderBy('name')->paginate(15, pageName: 'lists_page')->withQueryString(),
            'prices' => ProductPrice::query()->with(['product', 'priceList', 'unit'])->latest('effective_from')->paginate(20, pageName: 'prices_page')->withQueryString(),
            'taxRules' => $user->can('tax.view')
                ? TaxRule::query()->withCount('products')->orderBy('code')->orderByDesc('effective_from')->paginate(15, pageName: 'tax_page')->withQueryString()
                : null,
            'products' => Product::query()->where('is_active', true)->orderBy('name')->get(),
            'units' => Unit::query()->where('is_active', true)->orderBy('name')->get(),
            'roundingModes' => TaxRoundingMode::cases(),
            'formatter' => $formatter,
        ]);
    }

    public function storePriceList(UpsertPriceListRequest $request, PricingManager $pricing): RedirectResponse
    {
        Gate::authorize('create', PriceList::class);
        $pricing->savePriceList(null, $request->validated(), $this->auditContext($request));

        return back()->with('status', 'Strata harga berhasil disimpan.');
    }

    public function updatePriceList(
        UpsertPriceListRequest $request,
        PriceList $priceList,
        PricingManager $pricing,
    ): RedirectResponse {
        Gate::authorize('update', $priceList);
        $pricing->savePriceList($priceList, $request->validated(), $this->auditContext($request));

        return back()->with('status', 'Strata harga berhasil diperbarui.');
    }

    public function storePrice(StoreProductPriceRequest $request, PricingManager $pricing): RedirectResponse
    {
        Gate::authorize('pricing.manage');
        $pricing->createProductPrice($request->validated(), $this->auditContext($request));

        return back()->with('status', 'Harga efektif berhasil ditambahkan.');
    }

    public function storeTaxRule(UpsertTaxRuleRequest $request, PricingManager $pricing): RedirectResponse
    {
        Gate::authorize('create', TaxRule::class);
        $pricing->saveTaxRule(null, $request->validated(), $this->auditContext($request));

        return back()->with('status', 'Aturan pajak berhasil disimpan.');
    }

    public function updateTaxRule(
        UpsertTaxRuleRequest $request,
        TaxRule $taxRule,
        PricingManager $pricing,
    ): RedirectResponse {
        Gate::authorize('update', $taxRule);
        $pricing->saveTaxRule($taxRule, $request->validated(), $this->auditContext($request));

        return back()->with('status', 'Aturan pajak berhasil diperbarui.');
    }

    private function auditContext(Request $request): AuditContext
    {
        return new AuditContext($request->user(), null, $request->string('reason')->toString(), $request->ip(), $request->userAgent());
    }
}
