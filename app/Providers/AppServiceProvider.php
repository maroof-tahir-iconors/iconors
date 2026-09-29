<?php

namespace App\Providers;

use App\Services\Settings\SettingsService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One cached instance per request — the settings payload is read by
        // the layout, the header, the footer and most controllers.
        $this->app->singleton(SettingsService::class);
    }

    public function boot(): void
    {
        // Laravel 12 defaults to Tailwind pagination markup. Tailwind was
        // removed in favour of Bootstrap (brief §1), so paginators rendered
        // completely unstyled — bare "« Previous / Next »" links.
        Paginator::useBootstrapFive();
    }
}
