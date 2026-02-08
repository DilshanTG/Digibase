<?php

namespace App\Filament\Pages;

use App\Models\DynamicModel;
use App\Services\CodeGeneratorService;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class CodeGenerator extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-code-bracket';

    protected static ?string $navigationLabel = 'Code Generator';

    protected static ?string $title = 'Code Generator';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.code-generator';

    // Form state
    public ?int $model_id = null;
    public string $framework = 'react';
    public string $operation = 'all';
    public string $style = 'tailwind';
    public bool $typescript = true;

    // Output state
    public array $generatedFiles = [];
    public int $activeTab = 0;

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Generate Frontend Code')
                    ->description('Select a table and framework to generate ready-to-paste components.')
                    ->schema([
                        Select::make('model_id')
                            ->label('Select Table')
                            ->options(DynamicModel::pluck('display_name', 'id'))
                            ->required()
                            ->searchable()
                            ->live()
                            ->afterStateUpdated(fn () => $this->clearOutput()),

                        Select::make('framework')
                            ->options([
                                'react' => 'React',
                                'vue' => 'Vue 3',
                                'nextjs' => 'Next.js 14',
                                'nuxt' => 'Nuxt 3',
                            ])
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn () => $this->clearOutput()),

                        Select::make('operation')
                            ->label('Components')
                            ->options([
                                'all' => 'All (List + Create + Hook)',
                                'list' => 'List Component Only',
                                'create' => 'Create Form Only',
                                'hook' => 'API Hook Only',
                            ])
                            ->required(),

                        Toggle::make('typescript')
                            ->label('TypeScript')
                            ->default(true)
                            ->inline(false),
                    ])->columns(4),
            ]);
    }

    public function generate(): void
    {
        if (! $this->model_id) {
            Notification::make()
                ->warning()
                ->title('Select a table first')
                ->send();
            return;
        }

        try {
            $service = app(CodeGeneratorService::class);

            $this->generatedFiles = $service->generate(
                $this->model_id,
                $this->framework,
                $this->operation,
                $this->style,
                $this->typescript,
            );

            $this->activeTab = 0;

            Notification::make()
                ->success()
                ->title('Code generated!')
                ->body(count($this->generatedFiles) . ' file(s) ready.')
                ->send();
        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Generation failed')
                ->body($e->getMessage())
                ->send();
        }
    }

    public function setTab(int $index): void
    {
        $this->activeTab = $index;
    }

    public function clearOutput(): void
    {
        $this->generatedFiles = [];
        $this->activeTab = 0;
    }

    public function copyCode(int $index): void
    {
        $this->dispatch('copy-to-clipboard', code: $this->generatedFiles[$index]['code'] ?? '');

        Notification::make()
            ->success()
            ->title('Copied to clipboard!')
            ->send();
    }
}
