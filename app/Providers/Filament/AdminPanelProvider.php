<?php

namespace App\Providers\Filament;

use App\Filament\Pages;
use App\Filament\Resources\CustomActivitylogResource;
use App\Filament\Resources\StudentResource\Pages\ListStudents;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\NoIndexAdminPanel;
use App\Http\Middleware\SetLocale;
use Filament\FontProviders\LocalFontProvider;
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
use Illuminate\Support\HtmlString;
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
            ->brandLogo(new HtmlString(
                '<img src="' . asset('images/logo.png') . '" alt="Study Zone" width="37" height="40" style="height: 100%; width: auto;" />'
            ))
            ->brandLogoHeight('2.5rem')
            ->favicon(asset('images/logo.png'))
            ->brandName('Study Zone')
            // Self-hosted Inter (see public/css/fonts/inter.css) instead of the
            // fonts.bunny.net CDN: removes the third-party stylesheet that had no
            // Subresource Integrity hash and lets the CSP drop that origin entirely.
            ->font('Inter', asset('css/fonts/inter.css') . '?v=1', LocalFontProvider::class)
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
                NoIndexAdminPanel::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->navigationGroups([
                NavigationGroup::make()->label(__('general.navigation.admin_management')),
                NavigationGroup::make()->label(__('general.navigation.city_management')),
                NavigationGroup::make()->label(__('general.navigation.student_management')),
            ])
            ->plugins([
                ActivitylogPlugin::make()
                    ->resource(CustomActivitylogResource::class)
                   ->isRestoreActionHidden(true)
        ->isResourceActionHidden(false),

                SpatieLaravelTranslatablePlugin::make()->defaultLocales(['en', 'ar']),
            ])
            ->renderHook(
                'panels::body.start',
                fn () => view('filament.hooks.qr-code-script'),
                scopes: [ListStudents::class],
            )
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
            )

            ->renderHook(
                'panels::head.end',
                fn () => view('filament.hooks.seo-meta')
            );

    }
}
