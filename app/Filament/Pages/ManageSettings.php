<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Exceptions\Halt;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use UnitEnum;
use BackedEnum;

class ManageSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static string|UnitEnum|null $navigationGroup = 'Settings';
    protected static ?int $navigationSort = 3;
    protected static ?string $title = 'Platform Settings';

    protected string $view = 'filament.pages.manage-settings';

    // General Settings
    public $app_name; 
    public $app_description;
    public $logo_url = [];
    public $primary_color;
    public $support_email;

    // Social Auth - Google
    public $google_active = false;
    public $google_client_id;
    public $google_client_secret;
    public $google_redirect_uri;

    // Social Auth - GitHub
    public $github_active = false;
    public $github_client_id;
    public $github_client_secret;
    public $github_redirect_uri;

    public function mount(): void
    {
        // Load General settings
        $this->app_name = $this->getSetting('app_name', 'branding');
        $this->app_description = $this->getSetting('app_description', 'branding');
        
        $logo = $this->getSetting('logo_url', 'branding');
        $this->logo_url = $logo ? [$logo] : []; 

        $this->primary_color = $this->getSetting('primary_color', 'branding');
        $this->support_email = $this->getSetting('support_email', 'branding');

        // Load Auth settings
        $this->google_active = (bool) $this->getSetting('google_active', 'authentication');
        $this->google_client_id = $this->getSetting('google_client_id', 'authentication');
        $this->google_client_secret = $this->getSetting('google_client_secret', 'authentication');
        $this->google_redirect_uri = $this->getSetting('google_redirect_uri', 'authentication');

        $this->github_active = (bool) $this->getSetting('github_active', 'authentication');
        $this->github_client_id = $this->getSetting('github_client_id', 'authentication');
        $this->github_client_secret = $this->getSetting('github_client_secret', 'authentication');
        $this->github_redirect_uri = $this->getSetting('github_redirect_uri', 'authentication');
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Tabs::make('Settings')
                    ->tabs([
                        // TAB 1: General
                        Tabs\Tab::make('General')
                            ->icon('heroicon-o-cog-6-tooth')
                            ->schema([
                                Section::make('Branding')
                                    ->schema([
                                        TextInput::make('app_name')
                                            ->label('Application Name')
                                            ->required(),
                                        
                                        TextInput::make('app_description')
                                            ->label('Description'),

                                        FileUpload::make('logo_url')
                                            ->label('Logo')
                                            ->image()
                                            ->disk('public')
                                            ->directory('branding')
                                            ->visibility('public')
                                            ->maxSize(2048)
                                            ->preserveFilenames(),

                                        TextInput::make('primary_color')
                                            ->label('Primary Color')
                                            ->prefix('#')
                                            ->placeholder('6366f1'),

                                        TextInput::make('support_email')
                                            ->email()
                                            ->label('Support Email'),
                                    ])->columns(2),
                            ]),

                        // TAB 2: Authentication
                        Tabs\Tab::make('Authentication')
                            ->icon('heroicon-o-key')
                            ->schema([
                                // Google OAuth
                                Section::make('Google OAuth')
                                    ->description('Allow users to sign in with Google')
                                    ->schema([
                                        Toggle::make('google_active')
                                            ->label('Enable Google Login')
                                            ->live()
                                            ->columnSpanFull(),

                                        TextInput::make('google_client_id')
                                            ->label('Client ID')
                                            ->placeholder('xxxx.apps.googleusercontent.com')
                                            ->visible(fn ($get) => $get('google_active')),

                                        TextInput::make('google_client_secret')
                                            ->label('Client Secret')
                                            ->password()
                                            ->revealable()
                                            ->visible(fn ($get) => $get('google_active')),

                                        TextInput::make('google_redirect_uri')
                                            ->label('Callback URL')
                                            ->placeholder(url('/api/auth/google/callback'))
                                            ->helperText('Default: ' . url('/api/auth/google/callback'))
                                            ->visible(fn ($get) => $get('google_active')),
                                    ])->columns(2),

                                // GitHub OAuth
                                Section::make('GitHub OAuth')
                                    ->description('Allow users to sign in with GitHub')
                                    ->schema([
                                        Toggle::make('github_active')
                                            ->label('Enable GitHub Login')
                                            ->live()
                                            ->columnSpanFull(),

                                        TextInput::make('github_client_id')
                                            ->label('Client ID')
                                            ->visible(fn ($get) => $get('github_active')),

                                        TextInput::make('github_client_secret')
                                            ->label('Client Secret')
                                            ->password()
                                            ->revealable()
                                            ->visible(fn ($get) => $get('github_active')),

                                        TextInput::make('github_redirect_uri')
                                            ->label('Callback URL')
                                            ->placeholder(url('/api/auth/github/callback'))
                                            ->helperText('Default: ' . url('/api/auth/github/callback'))
                                            ->visible(fn ($get) => $get('github_active')),
                                    ])->columns(2),
                            ]),
                    ])->columnSpanFull(),
            ]);
    }

    public function save(): void
    {
        try {
            $data = $this->form->getState();

            // Extract logo path
            $logoPath = null;
            if (!empty($data['logo_url']) && is_array($data['logo_url'])) {
                $logoPath = array_values($data['logo_url'])[0];
            } elseif (!empty($data['logo_url']) && is_string($data['logo_url'])) {
                $logoPath = $data['logo_url'];
            }

            // Save Branding settings
            $brandingSettings = [
                'app_name' => $data['app_name'],
                'app_description' => $data['app_description'],
                'logo_url' => $logoPath,
                'primary_color' => $data['primary_color'],
                'support_email' => $data['support_email'],
            ];

            foreach ($brandingSettings as $key => $value) {
                Setting::updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => $value, 
                        'group' => 'branding',
                        'type' => 'string',
                        'is_public' => true,
                    ]
                );
            }

            // Save Authentication settings
            $authSettings = [
                'google_active' => $data['google_active'] ? '1' : '0',
                'google_client_id' => $data['google_client_id'],
                'google_client_secret' => $data['google_client_secret'],
                'google_redirect_uri' => $data['google_redirect_uri'],
                'github_active' => $data['github_active'] ? '1' : '0',
                'github_client_id' => $data['github_client_id'],
                'github_client_secret' => $data['github_client_secret'],
                'github_redirect_uri' => $data['github_redirect_uri'],
            ];

            foreach ($authSettings as $key => $value) {
                Setting::updateOrCreate(
                    ['key' => $key],
                    [
                        'value' => $value, 
                        'group' => 'authentication',
                        'type' => str_contains($key, '_active') ? 'boolean' : 'string',
                        'is_public' => false, // Keep secrets private
                    ]
                );
            }

            Notification::make() 
                ->title('Settings saved successfully')
                ->success()
                ->send();

        } catch (Halt $exception) {
            return;
        }
    }

    protected function getSetting($key, $group = 'branding')
    {
        $setting = Setting::where('key', $key)->where('group', $group)->first();
        return $setting ? $setting->value : null;
    }
}
