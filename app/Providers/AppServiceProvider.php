<?php

namespace App\Providers;

use App\Domain\AccountsPayable\Models\PurchaseInvoice;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ShelfLocation;
use App\Domain\Inventory\Models\StockAdjustment;
use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Register;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Organization\Support\CurrentBranchContext;
use App\Domain\Pricing\Models\PriceList;
use App\Domain\Pricing\Models\TaxRule;
use App\Domain\Purchasing\Models\GoodsReceipt;
use App\Domain\Purchasing\Models\PurchaseOrder;
use App\Domain\Sales\Models\CashShift;
use App\Domain\Sales\Models\Sale;
use App\Domain\Suppliers\Models\Supplier;
use App\Models\User;
use App\Policies\BranchPolicy;
use App\Policies\CashShiftPolicy;
use App\Policies\GoodsReceiptPolicy;
use App\Policies\PriceListPolicy;
use App\Policies\ProductPolicy;
use App\Policies\PurchaseInvoicePolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\RegisterPolicy;
use App\Policies\SalePolicy;
use App\Policies\ShelfLocationPolicy;
use App\Policies\StockAdjustmentPolicy;
use App\Policies\SupplierPolicy;
use App\Policies\TaxRulePolicy;
use App\Policies\WarehousePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(CurrentBranchContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        Gate::before(fn (User $user): ?bool => $user->hasRole('super_admin') ? true : null);

        foreach (array_keys((array) config('access.permissions')) as $permission) {
            Gate::define($permission, fn (User $user): bool => $user->hasPermission($permission));
        }

        Gate::policy(Branch::class, BranchPolicy::class);
        Gate::policy(Warehouse::class, WarehousePolicy::class);
        Gate::policy(Register::class, RegisterPolicy::class);
        Gate::policy(Product::class, ProductPolicy::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(ShelfLocation::class, ShelfLocationPolicy::class);
        Gate::policy(PriceList::class, PriceListPolicy::class);
        Gate::policy(TaxRule::class, TaxRulePolicy::class);
        Gate::policy(StockAdjustment::class, StockAdjustmentPolicy::class);
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(GoodsReceipt::class, GoodsReceiptPolicy::class);
        Gate::policy(PurchaseInvoice::class, PurchaseInvoicePolicy::class);
        Gate::policy(CashShift::class, CashShiftPolicy::class);
        Gate::policy(Sale::class, SalePolicy::class);
    }
}
