<?php

namespace App\Filament\Pages;

use App\Settings\GeneralSettings;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class StorageSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cloud';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings & Access';

    protected static ?string $title = 'Storage Configuration';

    protected static ?string $navigationLabel = 'Storage';

    protected static ?string $slug = 'settings/storage';

    protected string $view = 'filament.pages.storage-settings';

    public ?array $data = [];

    public function mount(GeneralSettings $settings): void
    {
        $this->form->fill([
            'storage_driver' => $settings->storage_driver ?? 'local',
            'aws_access_key_id' => $settings->aws_access_key_id,
            'aws_secret_access_key' => $settings->aws_secret_access_key,
            'aws_default_region' => $settings->aws_default_region ?? 'us-east-1',
            'aws_bucket' => $settings->aws_bucket,
            'endpoint' => $settings->aws_endpoint,
            'use_path_style_endpoint' => $settings->aws_use_path_style === 'true' ? 'true' : 'false',
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->components([
                \Filament\Schemas\Components\Section::make('Storage Driver')
                    ->description('Choose where your application should permanently store files.')
                    ->schema([
                        Forms\Components\Select::make('storage_driver')
                            ->label('Active Driver')
                            ->options([
                                'local' => 'Local Disk (Server Storage)',
                                's3' => 'Amazon S3 (Cloud Storage)',
                            ])
                            ->required()
                            ->live()
                            ->native(false),
                    ]),

                \Filament\Schemas\Components\Section::make('Amazon S3 Configuration')
                    ->description('Detailed credentials for your S3-compatible cloud storage.')
                    ->visible(fn ($get) => $get('storage_driver') === 's3')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('aws_access_key_id')
                            ->label('Access Key ID')
                            ->placeholder('AKIA...')
                            ->required(fn ($get) => $get('storage_driver') === 's3'),

                        Forms\Components\TextInput::make('aws_secret_access_key')
                            ->label('Secret Access Key')
                            ->password()
                            ->revealable()
                            ->required(fn ($get) => $get('storage_driver') === 's3'),

                        Forms\Components\TextInput::make('aws_default_region')
                            ->label('Default Region')
                            ->placeholder('us-east-1')
                            ->default('us-east-1')
                            ->required(fn ($get) => $get('storage_driver') === 's3'),

                        Forms\Components\TextInput::make('aws_bucket')
                            ->label('S3 Bucket Name')
                            ->placeholder('my-app-storage')
                            ->required(fn ($get) => $get('storage_driver') === 's3'),

                        Forms\Components\TextInput::make('endpoint')
                            ->label('Endpoint URL (Optional)')
                            ->placeholder('https://s3.amazonaws.com')
                            ->helperText('Override if using MinIO, R2, or DigitalOcean Spaces.')
                            ->columnSpanFull(),

                        Forms\Components\Select::make('use_path_style_endpoint')
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

    public function submit(GeneralSettings $settings): void
    {
        $data = $this->form->getState();

        $settings->storage_driver = $data['storage_driver'];
        $settings->aws_access_key_id = $data['aws_access_key_id'] ?? null;
        $settings->aws_secret_access_key = $data['aws_secret_access_key'] ?? null;
        $settings->aws_default_region = $data['aws_default_region'] ?? 'us-east-1';
        $settings->aws_bucket = $data['aws_bucket'] ?? null;
        $settings->aws_endpoint = $data['endpoint'] ?? null;
        $settings->aws_use_path_style = $data['use_path_style_endpoint'] ?? 'false';
        $settings->aws_url = $settings->aws_url ?? null;

        $settings->save();

        Notification::make()
            ->title('Settings Saved Successfully')
            ->success()
            ->send();
    }
}
