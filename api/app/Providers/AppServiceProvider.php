<?php

declare(strict_types=1);

namespace App\Providers;

use App\Services\IgrejaContext;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IgrejaContext::class);
    }

    public function boot(): void
    {
        //
    }
}
