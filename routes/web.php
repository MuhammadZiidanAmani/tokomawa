<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\CurrentBranchController;
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
