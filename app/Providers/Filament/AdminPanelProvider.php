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
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;
use Inerba\DbConfig\DbConfigPlugin;
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
                'primary' => Color::Zinc,
                'gray' => Color::Slate,
            ])
            ->font('Inter')
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth('full')
            ->navigationGroups([
                'Database',
                'Storage',
                'Integrations',
                'Settings',
            ])
            ->plugin(
                FileManagerPlugin::make()
                    ->withoutSchemaExample()
            )
            ->plugin(
                FilamentSpatieLaravelBackupPlugin::make()
                    ->usingPolingInterval('10s')
                    ->noTimeout()
            )
            ->plugin(
                DbConfigPlugin::make()
            )
            ->navigationItems([
                NavigationItem::make('API Docs')
                    ->url('/docs/api')
                    ->icon('heroicon-o-book-open')
                    ->group('Integrations')
                    ->sort(99)
                    ->openUrlInNewTab(),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
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
