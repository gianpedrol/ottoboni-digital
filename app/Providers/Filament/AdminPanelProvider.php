<?php

namespace App\Providers\Filament;

use Filament\Auth\MultiFactor\App\AppAuthentication;
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
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('painel')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->login()
            ->brandName('Clínica Ottoboni')
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable(),
            ])
            ->databaseNotifications()
            ->colors([
                // Coral da marca (site da clínica). Os tons 600+ garantem
                // contraste com texto branco nos botões.
                'primary' => [
                    50 => 'oklch(0.975 0.012 28)',
                    100 => 'oklch(0.945 0.028 28)',
                    200 => 'oklch(0.890 0.055 28)',
                    300 => 'oklch(0.820 0.085 28)',
                    400 => 'oklch(0.740 0.115 28)',
                    500 => 'oklch(0.650 0.130 28)',
                    600 => 'oklch(0.550 0.130 28)',
                    700 => 'oklch(0.480 0.115 28)',
                    800 => 'oklch(0.420 0.100 28)',
                    900 => 'oklch(0.370 0.080 28)',
                    950 => 'oklch(0.270 0.060 28)',
                ],
                'gray' => Color::Stone,
            ])
            ->font('DM Sans')
            // Um modo só: a identidade da clínica é clara (branco, bege, coral)
            ->darkMode(false)
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups([
                'Indicadores',
                'Atendimento',
                'Clínica',
                'Financeiro',
                'Automações',
                'Follow-up',
                'Administração',
            ])
            ->renderHook(
                PanelsRenderHook::BODY_START,
                fn (): string => config('painel.prototipo') ? view('filament.prototipo-banner')->render() : '',
            )
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                \App\Filament\Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
