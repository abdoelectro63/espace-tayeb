<?php

namespace App\Providers;

use App\Services\ShoppingCart;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\View;
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
        Paginator::defaultView('pagination::tailwind');
        Paginator::defaultSimpleView('pagination::simple-tailwind');

        View::composer('components.layouts.store', function ($view): void {
            $view->with('cartCount', app(ShoppingCart::class)->totalQuantity());
        });

        // Filament FileUpload / FilePond preview URLs use filesystems.disks.public.url (APP_URL + /storage).
        // If APP_URL is still http://localhost but the site is served on another host (e.g. Herd *.test),
        // previews never load and stay on "Loading". Point public disk URLs at the current request host.
        $this->app->booted(function (): void {
            if ($this->app->runningInConsole()) {
                return;
            }

            $host = request()->getSchemeAndHttpHost();
            if ($host === '') {
                return;
            }

            config([
                'filesystems.disks.public.url' => rtrim($host, '/').'/storage',
            ]);
        });
    }
}
