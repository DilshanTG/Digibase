<?php

namespace App\Filament\Resources\DynamicModelResource\Pages;

use App\Filament\Resources\DynamicModelResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListDynamicModels extends ListRecords
{
    protected static string $resource = DynamicModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('New Table'),
        ];
    }
}
