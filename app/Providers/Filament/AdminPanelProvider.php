<?php

namespace App\Providers\Filament;

use App\Filament\Pages;
use App\Filament\Resources\CustomActivitylogResource;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SetLocale;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationGroup;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\SpatieLaravelTranslatablePlugin;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Rmsramos\Activitylog\ActivitylogPlugin;

// ... your other imports

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->authGuard('web')
            ->path('/')
            ->login()
            ->unsavedChangesAlerts()
            ->databaseTransactions()
            ->brandLogo(asset('images/logo.png'))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('images/logo.png'))
            ->brandName('Study Zone')
            ->databaseNotifications()
            ->colors([
                'primary' => Color::Amber,
                'secondary' => '#063c54',
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,

            ])

            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([])
            ->middleware([
                'web',
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                'active' => EnsureUserIsActive::class,
                SetLocale::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->navigationGroups([
                NavigationGroup::make()->label('Admin Management'),
                NavigationGroup::make()->label('City Management'),
                NavigationGroup::make()->label('Student Management'),
            ])
            ->plugins([
                ActivitylogPlugin::make()
                    ->resource(CustomActivitylogResource::class)
                   ->isRestoreActionHidden(true)
        ->isResourceActionHidden(false),

                SpatieLaravelTranslatablePlugin::make()->defaultLocales(['en', 'ar']),
            ])
            ->renderHook('panels::body.start', fn () => view('filament.hooks.qr-code-script'))
            // ->renderHook(
            //     'panels::user-menu.before',
            //     fn () => view('filament.components.language-switcher')
            // )
            ->renderHook(
                'panels::user-menu.before',
                fn () => view('filament.components.language-dropdown')
            )

            // RTL Support
            ->renderHook(
                'panels::head.start',
                fn () => view('filament.components.rtl-styles')
            );

    }
}
