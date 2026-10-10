<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ((bool) env('APP_FORCE_HTTPS', false)) {
            URL::forceScheme('https');
        }

        Paginator::defaultView('pagination.app');
        Paginator::defaultSimpleView('pagination.simple');
    }
}
