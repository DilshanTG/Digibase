<?php

namespace App\Filament\Pages;

use BackedEnum;
use Inerba\DbConfig\AbstractPageSettings;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;

class SocialSettings extends AbstractPageSettings
{
    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    protected static ?string $title = 'Authentication';
    protected static string|\UnitEnum|null $navigationGroup = 'Settings';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-share';

    protected string $view = 'filament.pages.social-settings';

    protected function settingName(): string
    {
        return 'auth';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make('Google OAuth')
                    ->schema([
                        \Filament\Forms\Components\Toggle::make('google_enabled')
                            ->label('Enable Google Login'),
                        \Filament\Forms\Components\TextInput::make('google_client_id')
                            ->label('Client ID'),
                        \Filament\Forms\Components\TextInput::make('google_client_secret')
                            ->label('Client Secret')
                            ->password()
                            ->revealable(),
                    ]),
                \Filament\Schemas\Components\Section::make('GitHub OAuth')
                    ->schema([
                        \Filament\Forms\Components\Toggle::make('github_enabled')
                            ->label('Enable GitHub Login'),
                        \Filament\Forms\Components\TextInput::make('github_client_id')
                            ->label('Client ID'),
                        \Filament\Forms\Components\TextInput::make('github_client_secret')
                            ->label('Client Secret')
                            ->password()
                            ->revealable(),
                    ]),
            ])
            ->statePath('data');
    }
}
