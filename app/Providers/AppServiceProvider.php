<?php

namespace App\Providers;

use App\Domain\Organization\Models\Branch;
use App\Domain\Organization\Models\Register;
use App\Domain\Organization\Models\Warehouse;
use App\Domain\Organization\Support\CurrentBranchContext;
use App\Models\User;
use App\Policies\BranchPolicy;
use App\Policies\RegisterPolicy;
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
    }
}
