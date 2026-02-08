<?php

namespace App\Filament\Resources\StorageFiles\Pages;

use App\Filament\Resources\StorageFiles\StorageFileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageStorageFiles extends ManageRecords
{
    protected static string $resource = StorageFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
