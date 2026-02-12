<?php

namespace App\Filament\Pages;

use App\Models\DynamicModel;
use App\Services\CodeGeneratorService;
use Filament\Actions\Action;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use BackedEnum;
use UnitEnum;

class CodeGenerator extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';
    protected static ?string $navigationLabel = 'Vibe Code Generator';
    protected static ?string $title = 'Vibe Code Generator';
    protected static string|UnitEnum|null $navigationGroup = 'Developer Tools';
    protected static ?int $navigationSort = 4;
    protected string $view = 'filament.pages.code-generator';

    public ?int $model_id = null;
    public string $generatedCode = '';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('Generate Next.js Component')
                    ->description('Auto-generate production-ready Next.js components using the Digibase SDK.')
                    ->schema([
                        Select::make('model_id')
                            ->label('Select Table')
                            ->options(DynamicModel::pluck('display_name', 'id'))
                            ->required()
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn () => $this->generate()),

                        MarkdownEditor::make('generatedCode')
                            ->label('Generated Component')
                            ->disabled()
                            ->toolbarButtons([])
                            ->hiddenLabel()
                            ->extraAttributes(['class' => 'font-mono text-sm'])
                            ->visible(fn () => !empty($this->generatedCode)),
                    ]),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('copy')
                ->label('Copy Code')
                ->icon('heroicon-o-clipboard-document')
                ->color('primary')
                ->disabled(fn () => empty($this->generatedCode))
                ->action(function () {
                    $this->dispatch('copy-to-clipboard', code: $this->generatedCode);
                    Notification::make()
                        ->success()
                        ->title('Code copied to clipboard!')
                        ->send();
                }),
        ];
    }

    public function generate(): void
    {
        if (!$this->model_id) {
            $this->generatedCode = '';
            return;
        }

        try {
            $model = DynamicModel::findOrFail($this->model_id);
            $service = app(CodeGeneratorService::class);

            $code = $service->generateNextJsComponent($model);

            // Wrap in markdown code block for MarkdownEditor
            $this->generatedCode = "```tsx\n" . $code . "\n```";

            Notification::make()
                ->success()
                ->title('Component generated!')
                ->body('Next.js component ready to copy.')
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Generation failed')
                ->body($e->getMessage())
                ->send();

            $this->generatedCode = '';
        }
    }
}
