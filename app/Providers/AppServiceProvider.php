<?php

namespace App\Providers;

use App\Models\FooterLogo;
use App\Models\Menu;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\ShippingSetting;
use App\Observers\MenuItemObserver;
use App\Observers\MenuObserver;
use App\Observers\PageObserver;
use App\Services\MenuService;
use App\Services\ShoppingCart;
use App\Settings\FooterSettings;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Windows: Blade's atomic replace() uses rename(.tmp → .php); Defender/AV or concurrent
        // compiles can make rename() fail with "Access is denied". Fall back to a direct write.
        $this->app->extend('files', function (Filesystem $files): Filesystem {
            return new class extends Filesystem
            {
                public function replace($path, $content, $mode = null): void
                {
                    try {
                        parent::replace($path, $content, $mode);
                    } catch (Throwable $e) {
                        if (! $this->shouldUseWindowsReplaceFallback($e)) {
                            throw $e;
                        }
                        $this->replaceWithoutRename($path, $content, $mode);
                    }
                }

                private function shouldUseWindowsReplaceFallback(Throwable $e): bool
                {
                    if (PHP_OS_FAMILY !== 'Windows') {
                        return false;
                    }

                    $message = $e->getMessage();

                    return str_contains($message, 'rename')
                        || str_contains($message, 'Access is denied');
                }

                private function replaceWithoutRename(string $path, string $content, ?int $mode): void
                {
                    clearstatcache(true, $path);

                    $path = realpath($path) ?: $path;

                    file_put_contents($path, $content);

                    if (! is_null($mode)) {
                        @chmod($path, $mode);
                    } else {
                        @chmod($path, 0777 - umask());
                    }
                }
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (config('app.env') === 'production') {
            URL::forceScheme('https');
        }

        Page::observe(PageObserver::class);
        Menu::observe(MenuObserver::class);
        MenuItem::observe(MenuItemObserver::class);

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

            // PaaS (e.g. Laravel Cloud) may not mark the request secure; Filament/Filepond still need
            // https URLs for /storage previews on an https page — avoid mixed-content / broken image loads.
            $isLocalHost = str_contains($host, 'localhost')
                || str_contains($host, '127.0.0.1')
                || str_ends_with($host, '.test')
                || str_ends_with($host, '.local');
            $useHttps = $isSecureRequest
                || (app()->environment('production') && ! $isLocalHost);

            $appUrl = ($useHttps ? 'https://' : 'http://').$host;
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

            $menuService = app(MenuService::class);

            $view->with([
                'topMenu' => $menuService->menuForLocation(Menu::LOCATION_TOP_MENU),
                'footerMenu1' => $menuService->menuForLocation(Menu::LOCATION_FOOTER_1),
                'footerMenu2' => $menuService->menuForLocation(Menu::LOCATION_FOOTER_2),
                'footerMenu3' => $menuService->menuForLocation(Menu::LOCATION_FOOTER_3),
                'footerSettings' => app(FooterSettings::class),
                'footerLogoUrl' => FooterLogo::query()->first()?->getFirstMediaUrl('logo'),
                'storeLogoUrl' => ShippingSetting::storeLogoUrl(),
            ]);
        });

    }
}
