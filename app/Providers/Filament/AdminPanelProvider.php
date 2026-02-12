<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use MWGuerra\FileManager\FileManagerPlugin;
use Inerba\DbConfig\DbConfigPlugin;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use pxlrbt\FilamentSpotlight\SpotlightPlugin;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;
use ShuvroRoy\FilamentSpatieLaravelHealth\FilamentSpatieLaravelHealthPlugin;
use Jeffgreco13\FilamentBreezy\BreezyCore;
use Filament\Navigation\NavigationItem;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Indigo,
                'gray' => Color::Slate,
            ])
            ->font('Inter')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->sidebarCollapsibleOnDesktop()
            ->spa()
            ->maxContentWidth('full')
            ->navigationGroups([
                'Data Engine',
                'API & Integration',
                'Monitoring & Logs',
                'Settings & Access',
                'System',
            ])
            ->collapsibleNavigationGroups(true)
            ->plugin(
                FileManagerPlugin::make([
                    \MWGuerra\FileManager\Filament\Pages\FileManager::class,
                    \MWGuerra\FileManager\Filament\Pages\FileSystem::class,
                ])
                ->withoutSchemaExample()
            )
            ->plugin(
                DbConfigPlugin::make()
            )
            ->plugin(
                FilamentShieldPlugin::make()
                    ->navigationGroup('Settings & Access')
            )
            ->plugin(
                SpotlightPlugin::make()
            )
            ->plugin(
                FilamentSpatieLaravelBackupPlugin::make()
                    ->usingPage(\App\Filament\Pages\Backups::class)
                    ->noTimeout()
                    ->authorize(fn () => auth()->check() && auth()->id() === 1)
            )
            ->plugin(
                BreezyCore::make()
                    ->myProfile(
                        shouldRegisterUserMenu: true,
                        shouldRegisterNavigation: false,
                        hasAvatars: true,
                        slug: 'my-profile'
                    )
                    ->enableTwoFactorAuthentication()
            )
            ->plugin(
                FilamentSpatieLaravelHealthPlugin::make()
                    ->authorize(fn () => auth()->check() && auth()->id() === 1)
            )
            ->plugin(
                \Awcodes\Overlook\OverlookPlugin::make()
                    ->sort(2)
                    ->columns([
                        'default' => 1,
                        'sm' => 2,
                        'md' => 3,
                        'lg' => 4,
                        'xl' => 5,
                        '2xl' => null,
                    ])
                    ->includes([
                        \App\Filament\Resources\UserResource::class,
                        \App\Filament\Resources\ApiKeyResource::class,
                        \App\Filament\Resources\DynamicModelResource::class,
                        \App\Filament\Resources\Webhooks\WebhookResource::class,
                    ])
                    ->icons([
                        'users' => 'heroicon-o-users',
                        'api_keys' => 'heroicon-o-key',
                        'dynamic_models' => 'heroicon-o-cube',
                        'webhooks' => 'heroicon-o-paper-airplane',
                    ])
            )
            ->databaseNotifications()
            ->navigationItems([
                NavigationItem::make('API Docs')
                    ->url('/docs/api')
                    ->icon('heroicon-o-book-open')
                    ->group('Developers')
                    ->sort(99)
                    ->openUrlInNewTab(),
                NavigationItem::make('Pulse Monitor')
                    ->url('/pulse', shouldOpenInNewTab: true)
                    ->icon('heroicon-o-heart')
                    ->group('Monitoring & Logs')
                    ->sort(99)
                    ->visible(fn () => auth()->check() && auth()->id() === 1),
                NavigationItem::make('Log Viewer')
                    ->url(url('/log-viewer'), shouldOpenInNewTab: true)
                    ->icon('heroicon-o-bug-ant')
                    ->group('Monitoring & Logs')
                    ->sort(100)
                    ->visible(fn () => auth()->check() && (auth()->id() === 1 || auth()->user()->is_admin ?? false)),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
                \Awcodes\Overlook\Widgets\OverlookWidget::class,
            ])
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
            ])
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn () => app()->environment('local', 'testing') ? Blade::render(<<<'HTML'
                    <div class="mt-4 border-t pt-4">
                        <p class="text-xs text-center text-gray-500 mb-2 font-medium">DEV QUICK LOGIN</p>
                        <div class="grid grid-cols-1 gap-2">
                            @foreach(\App\Models\User::all() as $user)
                                <a href="{{ route('dev.login', $user->id) }}"
                                   class="block w-full px-3 py-2 text-sm text-center text-gray-700 bg-gray-50 border border-gray-200 rounded-lg hover:bg-gray-100 transition-colors">
                                    Login as <strong>{{ $user->name }}</strong>
                                    <span class="block text-xs text-gray-400">{{ $user->email }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                HTML) : ''
            );
    }
}
