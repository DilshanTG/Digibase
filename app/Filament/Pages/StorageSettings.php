<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Section;
use Filament\Schemas\Schema;
use Filament\Pages\Page;
use Filament\Notifications\Notification;
use App\Models\SystemSetting;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Actions\Action;

class StorageSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cloud';
    protected static string|\UnitEnum|null $navigationGroup = 'Settings';
    protected static ?string $title = 'Storage Configuration';
    protected static ?string $navigationLabel = 'Storage';
    protected static ?string $slug = 'settings/storage';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'storage_driver' => SystemSetting::get('storage_driver', 'local'),
            'aws_access_key_id' => SystemSetting::get('aws_access_key_id'),
            'aws_secret_access_key' => SystemSetting::get('aws_secret_access_key'),
            'aws_default_region' => SystemSetting::get('aws_default_region', 'us-east-1'),
            'aws_bucket' => SystemSetting::get('aws_bucket'),
            'endpoint' => SystemSetting::get('aws_endpoint'),
            'use_path_style_endpoint' => SystemSetting::get('aws_use_path_style') === 'true',
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Storage Driver')
                    ->description('Choose where your application should permanently store files.')
                    ->schema([
                        Select::make('storage_driver')
                            ->label('Active Driver')
                            ->options([
                                'local' => 'Local Disk (Server Storage)',
                                's3' => 'Amazon S3 (Cloud Storage)',
                            ])
                            ->required()
                            ->live()
                            ->native(false),
                    ]),

                Section::make('Amazon S3 Configuration')
                    ->description('Detailed credentials for your S3-compatible cloud storage.')
                    ->visible(fn ($get) => $get('storage_driver') === 's3')
                    ->columns(2)
                    ->schema([
                        TextInput::make('aws_access_key_id')
                            ->label('Access Key ID')
                            ->placeholder('AKIA...')
                            ->required(fn ($get) => $get('storage_driver') === 's3'),

                        TextInput::make('aws_secret_access_key')
                            ->label('Secret Access Key')
                            ->password()
                            ->revealable()
                            ->required(fn ($get) => $get('storage_driver') === 's3'),

                        TextInput::make('aws_default_region')
                            ->label('Default Region')
                            ->placeholder('us-east-1')
                            ->default('us-east-1')
                            ->required(fn ($get) => $get('storage_driver') === 's3'),

                        TextInput::make('aws_bucket')
                            ->label('S3 Bucket Name')
                            ->placeholder('my-app-storage')
                            ->required(fn ($get) => $get('storage_driver') === 's3'),

                        TextInput::make('endpoint')
                            ->label('Endpoint URL (Optional)')
                            ->placeholder('https://s3.amazonaws.com')
                            ->helperText('Override if using MinIO, R2, or DigitalOcean Spaces.')
                            ->columnSpanFull(),

                        Select::make('use_path_style_endpoint')
                            ->label('Use Path Style')
                            ->options([
                                'true' => 'Yes (Required for MinIO)',
                                'false' => 'No (Standard S3)',
                            ])
                            ->default('false')
                            ->native(false),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Configuration')
                ->submit('save')
                ->color('primary'),
        ];
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        SystemSetting::set('storage_driver', $data['storage_driver'], 'storage');
        
        if ($data['storage_driver'] === 's3') {
            SystemSetting::set('aws_access_key_id', $data['aws_access_key_id'], 'storage');
            SystemSetting::set('aws_secret_access_key', $data['aws_secret_access_key'], 'storage', true);
            SystemSetting::set('aws_default_region', $data['aws_default_region'], 'storage');
            SystemSetting::set('aws_bucket', $data['aws_bucket'], 'storage');
            SystemSetting::set('aws_endpoint', $data['endpoint'], 'storage');
            SystemSetting::set('aws_use_path_style', $data['use_path_style_endpoint'] ? 'true' : 'false', 'storage');
        }

        Notification::make()
            ->title('Settings Saved Successfully')
            ->success()
            ->send();
    }
}
