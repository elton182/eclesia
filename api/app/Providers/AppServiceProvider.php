<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\SuperAdmin;
use App\Models\User;
use App\Services\IgrejaContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Spatie\Permission\PermissionRegistrar;
use Stancl\Tenancy\Events\TenancyInitialized;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IgrejaContext::class);
    }

    public function boot(): void
    {
        Gate::before(function ($user, string $ability) {
            if ($user instanceof SuperAdmin) {
                return true;
            }

            if (! $user instanceof User) {
                return null;
            }

            $previous = getPermissionsTeamId();
            setPermissionsTeamId(null);
            $isAdminTenant = $user->hasRole('admin-tenant');
            setPermissionsTeamId($previous);

            return $isAdminTenant ? true : null;
        });

        \Illuminate\Support\Facades\Event::listen(TenancyInitialized::class, function (): void {
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        });
    }
}
