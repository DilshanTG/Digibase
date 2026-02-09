<?php

namespace App\Filament\Resources\DynamicModelResource\RelationManagers;

use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use App\Models\DynamicModel;
use Filament\Schemas\Schema;

// 👇 v4 COMPATIBLE IMPORTS (Matches your UserResource)
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn; // Columns are still in Tables namespace

class RelationshipsRelationManager extends RelationManager
{
    protected static string $relationship = 'relationships';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Forms\Components\Select::make('type')
                    ->label('Relationship Type')
                    ->options([
                        'hasMany' => 'Has Many (1 -> M)',
                        'belongsTo' => 'Belongs To (M -> 1)',
                        'hasOne' => 'Has One (1 -> 1)',
                    ])
                    ->required()
                    ->native(false),

                Forms\Components\Select::make('related_model_id')
                    ->label('Related Model')
                    ->options(DynamicModel::where('id', '!=', $this->getOwnerRecord()->id)->pluck('display_name', 'id'))
                    ->searchable()
                    ->required()
                    ->reactive(),

                Forms\Components\TextInput::make('foreign_key')
                    ->label('Foreign Key Column')
                    ->placeholder('e.g. customer_id')
                    ->helperText('Leave empty to auto-guess (e.g. model_id)'),
                    
                Forms\Components\TextInput::make('method_name')
                    ->label('API Method Name')
                    ->placeholder('e.g. orders')
                    ->helperText('This will be the key in the API JSON response')
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'hasMany' => 'success',
                        'belongsTo' => 'info',
                        'hasOne' => 'warning',
                        default => 'gray',
                    }),
                
                TextColumn::make('relatedModel.display_name')
                    ->label('Connected To'),

                TextColumn::make('method_name')
                    ->label('API Key')
                    ->icon('heroicon-m-code-bracket'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
