<?php

namespace App\Providers;

use App\Services\ShoppingCart;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
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
        if (! $this->app->runningInConsole()) {
            $forwardedHost = trim((string) request()->header('X-Forwarded-Host', ''));
            $forwardedProto = strtolower((string) request()->header('X-Forwarded-Proto', ''));
            $host = $forwardedHost !== '' ? $forwardedHost : (string) request()->getHttpHost();
            $isSecureRequest = request()->isSecure()
                || $forwardedProto === 'https'
                || str_contains($host, 'trycloudflare.com')
                || str_contains($host, 'sharedwithexpose.com');

            if ($isSecureRequest) {
                URL::forceScheme('https');
            }

            $appUrl = ($isSecureRequest ? 'https://' : 'http://').$host;
            config([
                'app.url' => $appUrl,
                'app.asset_url' => $appUrl,
                'filesystems.disks.public.url' => rtrim($appUrl, '/').'/storage',
            ]);
            URL::forceRootUrl(config('app.url'));
        }

        Paginator::defaultView('pagination::tailwind');
        Paginator::defaultSimpleView('pagination::simple-tailwind');

        View::composer('components.layouts.store', function ($view): void {
            $view->with('cartCount', app(ShoppingCart::class)->totalQuantity());
        });

    }
}
