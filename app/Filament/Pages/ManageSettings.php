<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\ColorPicker;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use UnitEnum;

class ManageSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationLabel = 'Settings';

    protected static ?string $title = 'Platform Settings';

    protected static ?int $navigationSort = 10;

    protected static string|UnitEnum|null $navigationGroup = 'Admin';

    protected string $view = 'filament.pages.manage-settings';

    public $app_name = '';
    public $app_description = '';
    public $logo_url = [];
    public $primary_color = '';
    public $support_email = '';

    public function mount(): void
    {
        $this->app_name = $this->getSetting('app_name', config('app.name', 'Digibase'));
        $this->app_description = $this->getSetting('app_description', 'Self-hosted BaaS Platform');
        $this->logo_url = $this->getSetting('logo_url', []);
        if (is_string($this->logo_url) && !empty($this->logo_url)) {
            $this->logo_url = [$this->logo_url];
        } elseif (empty($this->logo_url)) {
            $this->logo_url = [];
        }
        $this->primary_color = $this->getSetting('primary_color', '#f59e0b');
        $this->support_email = $this->getSetting('support_email', '');
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Branding')
                    ->description('Customize how your platform looks and feels.')
                    ->schema([
                        TextInput::make('app_name')
                            ->label('Application Name')
                            ->required()
                            ->maxLength(100)
                            ->helperText('Shown in the browser tab, login page, and sidebar.'),

                        TextInput::make('app_description')
                            ->label('Tagline / Description')
                            ->maxLength(255)
                            ->helperText('Shown on the landing page.'),

                        FileUpload::make('logo_url')
                            ->label('Logo')
                            ->image()
                            ->directory('branding')
                            ->visibility('public')
                            ->preserveFilenames()
                            ->helperText('Upload your logo image.'),

                        ColorPicker::make('primary_color')
                            ->label('Primary Color')
                            ->helperText('Used for buttons, links, and accents.'),
                    ])->columns(2),

                Section::make('Contact')
                    ->schema([
                        TextInput::make('support_email')
                            ->label('Support Email')
                            ->email()
                            ->maxLength(255)
                            ->placeholder('support@example.com'),
                    ])->columns(2),
            ]);
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // Handle logo_url: Filament returns an array for FileUpload state
        $logo = $data['logo_url'];
        if (is_array($logo)) {
            $logo = array_values($logo)[0] ?? '';
        }

        $settings = [
            'app_name' => $data['app_name'],
            'app_description' => $data['app_description'],
            'logo_url' => $logo,
            'primary_color' => $data['primary_color'],
            'support_email' => $data['support_email'],
        ];

        foreach ($settings as $key => $value) {
            Setting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => json_encode($value),
                    'type' => 'string',
                    'group' => 'branding',
                    'is_public' => true,
                ]
            );
        }

        Notification::make()
            ->success()
            ->title('Settings saved')
            ->body('Your branding changes are live.')
            ->send();
    }

    private function getSetting(string $key, mixed $default = ''): mixed
    {
        $setting = Setting::where('key', $key)->first();

        if (! $setting) {
            return $default;
        }

        $value = $setting->value;

        // value is cast to json by the model, so it may already be decoded
        if (is_string($value)) {
            return $value;
        }

        return $value;
    }
}
