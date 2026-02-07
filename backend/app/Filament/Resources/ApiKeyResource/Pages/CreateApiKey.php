<?php

namespace App\Filament\Resources\ApiKeyResource\Pages;

use App\Filament\Resources\ApiKeyResource;
use App\Models\User;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreateApiKey extends CreateRecord
{
    protected static string $resource = ApiKeyResource::class;

    // Override default create — we use Sanctum's createToken() instead of Eloquent::create()
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $user = User::findOrFail($data['tokenable_id']);
        $abilities = $data['abilities'] ?? ['*'];

        $token = $user->createToken($data['name'], $abilities);

        // Flash the plain-text token — user sees it ONCE
        Notification::make()
            ->success()
            ->title('API Token Generated')
            ->body("Copy this token now — it won't be shown again:\n\n**{$token->plainTextToken}**")
            ->persistent()
            ->send();

        return $token->accessToken;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
