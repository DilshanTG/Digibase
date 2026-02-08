<?php

namespace App\Filament\Resources\StorageFiles;

use App\Filament\Resources\StorageFiles\Pages\ManageStorageFiles;
use App\Models\StorageFile;
use BackedEnum;
use UnitEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Forms;

class StorageFileResource extends Resource
{
    protected static ?string $model = StorageFile::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';
    protected static ?string $navigationLabel = 'File Security';
    protected static ?string $modelLabel = 'File';
    protected static ?string $pluralModelLabel = 'Files';
    protected static string|UnitEnum|null $navigationGroup = 'Storage';
    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Section::make('File Details')
                    ->schema([
                        Forms\Components\TextInput::make('original_name')
                            ->label('File Name')
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('mime_type')
                            ->label('Type')
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('bucket')
                            ->label('Bucket')
                            ->disabled(),
                        
                        Forms\Components\TextInput::make('folder')
                            ->label('Folder')
                            ->disabled(),
                    ])->columns(2),

                Forms\Components\Section::make('Access Control')
                    ->description('Control who can access this file')
                    ->schema([
                        Forms\Components\Toggle::make('is_public')
                            ->label('🌐 Public Access')
                            ->helperText('ON = Anyone with the URL can access. OFF = Only owner can download via API.')
                            ->default(false)
                            ->live(),
                        
                        Forms\Components\Placeholder::make('access_info')
                            ->content(function ($record) {
                                if ($record && $record->is_public) {
                                    return '✅ This file is publicly accessible via: /storage/' . $record->path;
                                }
                                return '🔒 This file is private. Access via API: /api/storage/' . ($record?->id ?? '{id}') . '/download';
                            }),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('original_name')
                    ->label('📄 File Name')
                    ->searchable()
                    ->sortable()
                    ->limit(30),

                Tables\Columns\TextColumn::make('mime_type')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => match (true) {
                        str_starts_with($state, 'image/') => '🖼️ Image',
                        str_starts_with($state, 'video/') => '🎥 Video',
                        str_starts_with($state, 'audio/') => '🎵 Audio',
                        $state === 'application/pdf' => '📕 PDF',
                        default => '📄 ' . explode('/', $state)[1] ?? 'File',
                    })
                    ->color(fn ($state) => match (true) {
                        str_starts_with($state, 'image/') => 'success',
                        str_starts_with($state, 'video/') => 'warning',
                        str_starts_with($state, 'audio/') => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('bucket')
                    ->label('Bucket')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('human_size')
                    ->label('Size'),

                Tables\Columns\ToggleColumn::make('is_public')
                    ->label('🌐 Public')
                    ->onColor('success')
                    ->offColor('danger')
                    ->afterStateUpdated(function ($record, $state) {
                        // Move file between disks when visibility changes
                        $newDisk = $state ? 'public' : 'local';
                        $oldDisk = $record->disk;

                        if ($newDisk !== $oldDisk) {
                            $content = \Storage::disk($oldDisk)->get($record->path);
                            \Storage::disk($newDisk)->put($record->path, $content);
                            \Storage::disk($oldDisk)->delete($record->path);
                            $record->update(['disk' => $newDisk]);
                        }
                    }),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Owner')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Uploaded')
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_public')
                    ->label('Visibility')
                    ->options([
                        '1' => '🌐 Public',
                        '0' => '🔒 Private',
                    ]),

                Tables\Filters\SelectFilter::make('bucket')
                    ->label('Bucket')
                    ->options(fn () => StorageFile::distinct()->pluck('bucket', 'bucket')->toArray()),

                Tables\Filters\Filter::make('images')
                    ->label('🖼️ Images Only')
                    ->query(fn ($query) => $query->where('mime_type', 'like', 'image/%')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageStorageFiles::route('/'),
        ];
    }
}
