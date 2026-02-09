<?php

namespace App\Filament\Resources\ApiKeyResource\Pages;

use App\Filament\Resources\ApiKeyResource;
use App\Models\ApiKey;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateApiKey extends CreateRecord
{
    protected static string $resource = ApiKeyResource::class;

    /**
     * Mutate form data before creating the record.
     * This is where we generate the actual key.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set the current user as owner
        $data['user_id'] = Auth::id();

        // Generate the key based on type
        $type = $data['type'] ?? 'public';
        $data['key'] = ApiKey::generateKey($type);

        // Ensure scopes based on type if not set
        if (empty($data['scopes'])) {
            $data['scopes'] = $type === 'secret' 
                ? ['read', 'write', 'delete'] 
                : ['read'];
        }

        return $data;
    }

    /**
     * After creation, show the key to the user (ONLY TIME IT'S VISIBLE!)
     */
    protected function afterCreate(): void
    {
        $key = $this->record->key;
        $type = $this->record->type === 'secret' ? '🔐 Secret' : '🔓 Public';

        Notification::make()
            ->success()
            ->title('API Key Generated!')
            ->body("
                <div style='font-family: monospace; background: #1a1a2e; padding: 12px; border-radius: 8px; margin: 8px 0;'>
                    <strong style='color: #00d4ff;'>{$type} Key:</strong><br>
                    <code style='color: #a5f3fc; font-size: 14px; word-break: break-all;'>{$key}</code>
                </div>
                <p style='color: #ef4444; font-weight: bold;'>⚠️ Copy this key NOW! It won't be shown again.</p>
            ")
            ->persistent()
            ->actions([
                \Filament\Notifications\Actions\Action::make('copy')
                    ->label('📋 Copy Key')
                    ->color('primary')
                    ->extraAttributes([
                        'x-on:click' => "navigator.clipboard.writeText('{$key}'); \$tooltip('Copied to clipboard!')",
                    ]),
            ])
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return null; // We handle this in afterCreate()
    }
}
