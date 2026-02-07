<?php

namespace App\Filament\Pages;

use BostjanOb\FilamentFileManager\Pages\FileManager;

class StorageManager extends FileManager
{
    protected static ?string $navigationIcon = 'heroicon-o-folder-open';

    protected static ?string $navigationLabel = 'File Manager';

    protected static ?string $slug = 'file-manager';

    protected static ?string $navigationGroup = 'System';

    protected string $disk = 'public';
}
