<?php

use App\Http\Controllers\AccountsPayable\AccountsPayableController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CurrentBranchController;
use App\Http\Controllers\Inventory\InventoryController;
use App\Http\Controllers\Inventory\OpeningBalanceController;
use App\Http\Controllers\Master\LocationController;
use App\Http\Controllers\Master\MasterDataController;
use App\Http\Controllers\Master\PricingController;
use App\Http\Controllers\Master\ProductController;
use App\Http\Controllers\Master\ReferenceDataController;
use App\Http\Controllers\Master\SupplierController;
use App\Http\Controllers\Master\UnitController;
use App\Http\Controllers\Purchasing\GoodsReceiptController;
use App\Http\Controllers\Purchasing\PurchaseInvoiceController;
use App\Http\Controllers\Purchasing\PurchaseOrderController;
use App\Http\Controllers\Purchasing\PurchaseOrderWorkflowController;
use App\Http\Controllers\Settings\AccessAssignmentController;
use App\Http\Controllers\Settings\ApprovalLimitController;
use App\Http\Controllers\Settings\BranchController;
use App\Http\Controllers\Settings\GovernanceController;
use App\Http\Controllers\Settings\RegisterController;
use App\Http\Controllers\Settings\WarehouseController;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Route;

Route::get('/', fn (): RedirectResponse => redirect()->route('dashboard'));

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::middleware('current.branch')->group(function (): void {
        Route::view('/dashboard', 'dashboard')->name('dashboard');
        Route::post('/cabang-aktif', CurrentBranchController::class)->name('current-branch.store');

        Route::prefix('persediaan')
            ->name('inventory.')
            ->middleware('permission:inventory.view')
            ->group(function (): void {
                Route::get('/', [InventoryController::class, 'index'])->name('index');
                Route::get('/saldo', [InventoryController::class, 'balances'])->name('balances');
                Route::get('/mutasi', [InventoryController::class, 'movements'])->name('movements');
                Route::get('/gudang/{warehouse}', [InventoryController::class, 'warehouse'])->name('warehouses.show');
                Route::get('/rekonsiliasi', [InventoryController::class, 'reconciliation'])
                    ->middleware('permission:inventory.reconcile')->name('reconciliation');
                Route::get('/saldo-awal', [OpeningBalanceController::class, 'index'])->name('opening.index');
                Route::post('/saldo-awal', [OpeningBalanceController::class, 'store'])
                    ->middleware('permission:inventory.opening.create')->name('opening.store');
                Route::post('/saldo-awal/{adjustment}/setujui', [OpeningBalanceController::class, 'approve'])
                    ->middleware('permission:inventory.opening.approve')->name('opening.approve');
            });

        Route::prefix('master')
            ->name('master.')
            ->middleware('permission:master.view')
            ->group(function (): void {
                Route::get('/', MasterDataController::class)->name('index');
                Route::resource('barang', ProductController::class)
                    ->parameters(['barang' => 'product'])
                    ->except('destroy')
                    ->names('products');

                Route::get('/referensi', [ReferenceDataController::class, 'index'])->name('references.index');
                Route::post('/departemen', [ReferenceDataController::class, 'storeDepartment'])->name('departments.store');
                Route::put('/departemen/{department}', [ReferenceDataController::class, 'updateDepartment'])->name('departments.update');
                Route::post('/kategori', [ReferenceDataController::class, 'storeCategory'])->name('categories.store');
                Route::put('/kategori/{category}', [ReferenceDataController::class, 'updateCategory'])->name('categories.update');
                Route::post('/subkategori', [ReferenceDataController::class, 'storeSubcategory'])->name('subcategories.store');
                Route::put('/subkategori/{subcategory}', [ReferenceDataController::class, 'updateSubcategory'])->name('subcategories.update');
                Route::post('/merek', [ReferenceDataController::class, 'storeBrand'])->name('brands.store');
                Route::put('/merek/{brand}', [ReferenceDataController::class, 'updateBrand'])->name('brands.update');

                Route::get('/satuan', [UnitController::class, 'index'])->name('units.index');
                Route::post('/satuan', [UnitController::class, 'store'])->name('units.store');
                Route::put('/satuan/{unit}', [UnitController::class, 'update'])->name('units.update');

                Route::resource('supplier', SupplierController::class)
                    ->parameters(['supplier' => 'supplier'])
                    ->except('destroy')
                    ->names('suppliers');
                Route::post('/supplier/{supplier}/barang', [SupplierController::class, 'storeProduct'])->name('suppliers.products.store');

                Route::get('/lokasi', [LocationController::class, 'index'])->name('locations.index');
                Route::post('/lokasi', [LocationController::class, 'store'])->name('locations.store');
                Route::put('/lokasi/{location}', [LocationController::class, 'update'])->name('locations.update');
                Route::post('/lokasi/{location}/barang', [LocationController::class, 'assignProduct'])->name('locations.products.store');

                Route::get('/harga-pajak', [PricingController::class, 'index'])->name('pricing.index');
                Route::post('/strata-harga', [PricingController::class, 'storePriceList'])->name('price-lists.store');
                Route::put('/strata-harga/{priceList}', [PricingController::class, 'updatePriceList'])->name('price-lists.update');
                Route::post('/harga', [PricingController::class, 'storePrice'])->name('prices.store');
                Route::post('/pajak', [PricingController::class, 'storeTaxRule'])->name('tax-rules.store');
                Route::put('/pajak/{taxRule}', [PricingController::class, 'updateTaxRule'])->name('tax-rules.update');
            });

        Route::prefix('pembelian')
            ->name('purchasing.')
            ->middleware('permission:purchase_orders.view')
            ->group(function (): void {
                Route::resource('purchase-order', PurchaseOrderController::class)
                    ->parameters(['purchase-order' => 'purchase_order'])
                    ->only(['index', 'create', 'store', 'show', 'edit', 'update'])
                    ->names('purchase-orders');
                Route::post('/purchase-order/{purchase_order}/ajukan', [PurchaseOrderWorkflowController::class, 'submit'])
                    ->name('purchase-orders.submit');
                Route::post('/purchase-order/{purchase_order}/setujui', [PurchaseOrderWorkflowController::class, 'approve'])
                    ->name('purchase-orders.approve');
                Route::post('/purchase-order/{purchase_order}/tolak', [PurchaseOrderWorkflowController::class, 'reject'])
                    ->name('purchase-orders.reject');
                Route::post('/purchase-order/{purchase_order}/kirim', [PurchaseOrderWorkflowController::class, 'send'])
                    ->name('purchase-orders.send');
                Route::post('/purchase-order/{purchase_order}/batalkan', [PurchaseOrderWorkflowController::class, 'cancel'])
                    ->name('purchase-orders.cancel');
            });

        Route::prefix('pembelian')->name('purchasing.')->middleware('permission:purchase_invoices.view')->group(function (): void {
            Route::resource('faktur-pembelian', PurchaseInvoiceController::class)
                ->parameters(['faktur-pembelian' => 'purchase_invoice'])
                ->only(['index', 'create', 'store', 'show', 'edit', 'update'])->names('purchase-invoices');
            Route::post('/faktur-pembelian/{purchase_invoice}/posting', [PurchaseInvoiceController::class, 'post'])->name('purchase-invoices.post');
        });
        Route::get('/keuangan/hutang-supplier', [AccountsPayableController::class, 'index'])->middleware('permission:accounts_payable.view')->name('accounts-payable.index');

        Route::prefix('pembelian')
            ->name('purchasing.')
            ->middleware('permission:goods_receipts.view')
            ->group(function (): void {
                Route::resource('penerimaan-barang', GoodsReceiptController::class)
                    ->parameters(['penerimaan-barang' => 'goods_receipt'])
                    ->only(['index', 'create', 'store', 'show', 'edit', 'update'])
                    ->names('goods-receipts');
                Route::post('/penerimaan-barang/{goods_receipt}/posting', [GoodsReceiptController::class, 'post'])
                    ->name('goods-receipts.post');
                Route::post('/penerimaan-barang/{goods_receipt}/batalkan', [GoodsReceiptController::class, 'cancel'])
                    ->name('goods-receipts.cancel');
            });

        Route::prefix('pengaturan')
            ->name('settings.')
            ->middleware('permission:settings.view')
            ->group(function (): void {
                Route::get('/', GovernanceController::class)->name('governance');
                Route::post('/cabang', [BranchController::class, 'store'])
                    ->middleware('permission:branches.manage')
                    ->name('branches.store');
                Route::post('/akses', [AccessAssignmentController::class, 'store'])
                    ->middleware('permission:access.manage')
                    ->name('access.store');

                Route::middleware('branch.access')->group(function (): void {
                    Route::post('/cabang/{branch}/gudang', [WarehouseController::class, 'store'])
                        ->middleware('permission:warehouses.manage')
                        ->name('warehouses.store');
                    Route::post('/cabang/{branch}/register', [RegisterController::class, 'store'])
                        ->middleware('permission:registers.manage')
                        ->name('registers.store');
                    Route::post('/cabang/{branch}/batas-persetujuan', [ApprovalLimitController::class, 'store'])
                        ->middleware('permission:approval_limits.manage')
                        ->name('approval-limits.store');
                });
            });
    });
});
