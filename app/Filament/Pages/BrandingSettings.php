<?php

namespace App\Filament\Pages;

use BackedEnum;
use Inerba\DbConfig\AbstractPageSettings;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;

class BrandingSettings extends AbstractPageSettings
{
    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    protected static ?string $title = 'Branding';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    // protected ?string $subheading = ''; // Uncomment if you want to set a custom subheading

    // protected static ?string $slug = 'branding-settings'; // Uncomment if you want to set a custom slug

    protected string $view = 'filament.pages.branding-settings';

    protected function settingName(): string
    {
        return 'branding';
    }

    /**
     * Provide default values.
     *
     * @return array<string, mixed>
     */
    public function getDefaultData(): array
    {
        return [];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\TextInput::make('site_name')
                    ->label('Site Name')
                    ->placeholder('Digibase'),
                \Filament\Forms\Components\FileUpload::make('site_logo')
                    ->label('Logo')
                    ->image()
                    ->directory('branding'),
                \Filament\Forms\Components\ColorPicker::make('primary_color')
                    ->label('Primary Color'),
            ])
            ->statePath('data');
    }
}
