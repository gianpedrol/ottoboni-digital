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
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/**
 * Painel separado para o treinamento das agentes de IA (/ia, login próprio).
 *
 * Fica fora do /painel de propósito: quem treina a IA não precisa ver
 * atendimentos e relatórios, e o painel comercial pode ser apresentado ao
 * cliente sem essa área aparecer. Mesma tabela de usuários e mesmos papéis,
 * então juntar depois é só mover os recursos para o AdminPanelProvider.
 */
class IaPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('ia')
            ->path('ia')
            ->login()
            ->brandName('Treinamento das IAs')
            ->multiFactorAuthentication([
                AppAuthentication::make()->recoverable(),
            ])
            ->databaseNotifications()
            ->databaseNotificationsPolling('30s')
            ->colors([
                'primary' => Color::Indigo,
            ])
            ->navigationGroups([
                'Revisão',
                'Treinamento',
                'Configuração',
            ])
            ->discoverResources(in: app_path('Filament/Ia/Resources'), for: 'App\Filament\Ia\Resources')
            ->discoverPages(in: app_path('Filament/Ia/Pages'), for: 'App\Filament\Ia\Pages')
            ->discoverWidgets(in: app_path('Filament/Ia/Widgets'), for: 'App\Filament\Ia\Widgets')
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
