<?php

namespace App\Filament\Pages;

use App\Models\DynamicModel;
use App\Services\CodeGeneratorService;
use Filament\Forms\Components\Actions;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Tabs;
use Filament\Forms\Components\Toggle;
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

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-code-bracket';
    protected static ?string $navigationLabel = 'Code Generator';
    protected static ?string $title = 'Code Generator';
    protected static string|UnitEnum|null $navigationGroup = 'Integrations';
    protected static ?int $navigationSort = 2;
    protected string $view = 'filament.pages.code-generator';

    public ?int $model_id = null;
    public string $framework = 'react';
    public string $operation = 'all';
    public string $style = 'tailwind';
    public bool $typescript = true;

    public array $generatedFiles = [];
    public int $activeTab = 0;

    // We remove the strict 'Form' type hint to allow 'Schema' objects if the framework passes them
    public function form($form): \Filament\Forms\Form|\Filament\Schemas\Schema
    {
        return $form
            ->schema([
                Tabs::make('Generator')
                    ->tabs([
                        Tabs\Tab::make('Component Generator')
                            ->icon('heroicon-o-cpu-chip')
                            ->schema([
                                Section::make('Configuration')
                                    ->description('Generate ready-to-paste CRUD components.')
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
                                    ])->columns(2),
                            ]),

                        Tabs\Tab::make('JavaScript SDK')
                            ->icon('heroicon-o-arrow-down-tray')
                            ->schema([
                                Section::make('Download SDK')
                                    ->description('The official JavaScript client for your Digibase backend.')
                                    ->schema([
                                        Actions::make([
                                            Action::make('download_sdk')
                                                ->label('Download digibase.js')
                                                ->icon('heroicon-o-arrow-down-tray')
                                                ->url(route('sdk.js'))
                                                ->openUrlInNewTab()
                                                ->color('primary'),
                                        ]),

                                        MarkdownEditor::make('example_usage')
                                            ->label('Usage Example')
                                            ->default($this->getSdkExample())
                                            ->disabled()
                                            ->toolbarButtons([]),
                                    ]),
                            ]),
                    ])
            ]);
    }

    protected function getSdkExample(): string
    {
        return <<<JS
```javascript
import Digibase from './digibase.js';

// Initialize
const db = new Digibase('http://localhost:8000');

// 1. Authentication
await db.auth.login('user@example.com', 'secret');

// 2. Get Data
const posts = await db.collection('posts')
    .where('is_published', 1)
    .sort('created_at', 'desc')
    .getAll();

// 3. Create Data
await db.collection('posts').create({
    title: 'My New Post',
    content: 'Hello World'
});
```
JS;
    }

    public function generate(): void
    {
        if (! $this->model_id) {
            Notification::make()->warning()->title('Select a table first')->send();
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
            Notification::make()->success()->title('Code generated!')->send();
        } catch (\Exception $e) {
            Notification::make()->danger()->title('Generation failed')->body($e->getMessage())->send();
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
        Notification::make()->success()->title('Copied!')->send();
    }
}
