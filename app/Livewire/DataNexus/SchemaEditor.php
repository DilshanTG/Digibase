<?php

namespace App\Livewire\DataNexus;

use App\Models\DynamicModel;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Livewire\Component;

class SchemaEditor extends Component implements HasForms
{
    use InteractsWithForms;

    public ?int $modelId = null;

    public ?array $data = [];

    public function mount(int $modelId): void
    {
        $this->modelId = $modelId;
        $this->loadData();
    }

    protected function loadData(): void
    {
        $model = DynamicModel::find($this->modelId);
        $this->form->fill($model->toArray());
    }

    public function form(Form $form): Form
    {
        // Reusing the schema logic from DynamicModelResource
        return $form
            ->schema([
                Section::make('Schema Definition')
                    ->icon('heroicon-o-list-bullet')
                    ->schema([
                        Forms\Components\Repeater::make('fields')
                            ->relationship('fields')
                            ->schema([
                                Forms\Components\TextInput::make('name')->required(),
                                Forms\Components\Select::make('type')
                                    ->options(['string' => 'String', 'text' => 'Text', 'integer' => 'Integer', 'boolean' => 'Boolean', 'date' => 'Date', 'datetime' => 'DateTime', 'json' => 'JSON', 'file' => 'File', 'image' => 'Image'])
                                    ->required(),
                                Forms\Components\Checkbox::make('is_required')->label('Req'),
                                Forms\Components\Checkbox::make('is_unique')->label('Unq'),
                            ])->columns(4),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $model = DynamicModel::find($this->modelId);
        $model->update($this->form->getState());

        Notification::make()
            ->title('Schema Updated')
            ->success()
            ->send();
    }

    public function render()
    {
        return view('livewire.data-nexus.schema-editor');
    }
}
