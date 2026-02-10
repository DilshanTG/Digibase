<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use App\Models\SystemSetting;
use Illuminate\Support\Facades\Artisan;
use BackedEnum;
use UnitEnum;

use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;

class StorageSettings extends Page implements HasForms
{
    use InteractsWithForms;
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cloud';
    protected static string|UnitEnum|null $navigationGroup = 'Settings';
    protected static ?string $title = 'Storage Settings';
    protected static ?string $slug = 'settings/storage';

    public ?array $data = [];

    public function mount(): void
    {
        // Load existing settings
        $this->form->fill([
            'driver' => SystemSetting::get('storage.driver', 'local'),
            'bucket' => SystemSetting::get('storage.bucket'),
            'region' => SystemSetting::get('storage.region'),
            'endpoint' => SystemSetting::get('storage.endpoint'),
            'access_key' => SystemSetting::get('storage.access_key'),
            'secret_key' => SystemSetting::get('storage.secret_key'), // Will be decrypted by getter
            'public_url' => SystemSetting::get('storage.public_url'),
            'use_path_style' => SystemSetting::get('storage.use_path_style') === 'true',
        ]);
    }

    public function form($form)
    {
        return $form
            ->schema([
                Section::make('Storage Driver')
                    ->description('Select where your files will be stored.')
                    ->schema([
                        Select::make('driver')
                            ->options([
                                'local' => 'Local Storage (Public Disk)',
                                's3' => 'Amazon S3',
                                'r2' => 'Cloudflare R2',
                                'spaces' => 'DigitalOcean Spaces',
                                'minio' => 'MinIO / Self-Hosted S3',
                            ])
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn ($state, callable $set) => $state === 'local' ? $set('endpoint', null) : null),
                    ]),

                Section::make('Cloud Configuration')
                    ->description('Enter your S3-compatible storage credentials.')
                    ->visible(fn ($get) => $get('driver') !== 'local')
                    ->schema([
                        TextInput::make('bucket')
                            ->label('Bucket Name')
                            ->required(fn ($get) => $get('driver') !== 'local'),
                        
                        TextInput::make('region')
                            ->label('Region')
                            ->default('us-east-1')
                            ->required(fn ($get) => $get('driver') !== 'local'),

                        TextInput::make('endpoint')
                            ->label('Endpoint URL')
                            ->placeholder('https://<accountid>.r2.cloudflarestorage.com')
                            ->helperText('Required for R2, Spaces, and MinIO. Optional for AWS S3.')
                            ->url(),

                        TextInput::make('access_key')
                            ->label('Access Key ID')
                            ->required(fn ($get) => $get('driver') !== 'local'),

                        TextInput::make('secret_key')
                            ->label('Secret Access Key')
                            ->password()
                            ->revealable()
                            ->required(fn ($get) => $get('driver') !== 'local'),
                        
                        Select::make('use_path_style')
                            ->label('Use Path Style Endpoint')
                            ->options([
                                'true' => 'Yes', 
                                'false' => 'No',
                            ])
                            ->default('false'),

                        TextInput::make('public_url')
                            ->label('Public URL Root')
                            ->placeholder('https://files.yourdomain.com')
                            ->helperText('The base URL for accessing public files.'),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        // 1. Save Settings Securely
        SystemSetting::set('storage.driver', $data['driver'], 'storage');
        
        if ($data['driver'] !== 'local') {
            SystemSetting::set('storage.bucket', $data['bucket'], 'storage');
            SystemSetting::set('storage.region', $data['region'], 'storage');
            SystemSetting::set('storage.endpoint', $data['endpoint'], 'storage');
            SystemSetting::set('storage.access_key', $data['access_key'], 'storage', true); // Encrypted
            SystemSetting::set('storage.secret_key', $data['secret_key'], 'storage', true); // Encrypted
            SystemSetting::set('storage.use_path_style', $data['use_path_style'], 'storage');
            SystemSetting::set('storage.public_url', $data['public_url'], 'storage');
        }

        // 2. Clear Config Cache
        Artisan::call('config:clear');

        Notification::make()
            ->title('Storage settings saved')
            ->success()
            ->body('Your storage configuration has been updated.')
            ->send();
    }
}
