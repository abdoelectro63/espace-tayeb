<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard;
use App\Models\ShippingSetting;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class DeliveryPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('delivery')
            ->path('delivery')
            ->login()
            ->brandLogo(fn (): string => $this->resolveBrandLogoUrl())
            ->darkModeBrandLogo(fn (): string => $this->resolveBrandLogoUrl())
            ->colors([
                'primary' => Color::Amber,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->homeUrl(function (): string {
                if (Route::has('filament.delivery.pages.delivery-orders')) {
                    return route('filament.delivery.pages.delivery-orders');
                }

                return '/delivery/delivery-orders';
            })
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn (): string => Blade::render("@vite(['resources/css/app.css'])")
            )
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }

    private function resolveBrandLogoUrl(): string
    {
        $branding = ShippingSetting::firstOrNull();
        $logoPath = $branding?->logo_path;
        $logoUrl = asset('images/logo.svg');

        if (filled($logoPath)) {
            $disk = Storage::disk('public');
            $logoUrl = $disk->url($logoPath);

            if ($disk->exists($logoPath)) {
                $logoUrl .= '?v='.$disk->lastModified($logoPath);
            }
        }

        return $logoUrl;
    }
}
