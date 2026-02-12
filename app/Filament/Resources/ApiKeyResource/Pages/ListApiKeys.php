<?php

namespace App\Filament\Resources\ApiKeyResource\Pages;

use App\Filament\Resources\ApiKeyResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListApiKeys extends ListRecords
{
    protected static string $resource = ApiKeyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('view_docs')
                ->label('API Documentation')
                ->icon('heroicon-o-book-open')
                ->color('gray')
                ->url('/docs/api')
                ->openUrlInNewTab(),
            Actions\CreateAction::make()
                ->label('Generate New Token'),
        ];
    }
}
