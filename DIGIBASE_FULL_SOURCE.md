# Digibase - Full Source Code Dump
Generated at: Wed Feb 11 18:40:32 +0530 2026

## File: app/Console/Commands/DigibaseNuke.php
```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;

class DigibaseNuke extends Command
{
    protected $signature = 'digibase:nuke {--force : Skip confirmation}';

    protected $description = 'Forcefully clear all Digibase caches, application cache, views, routes, and re-optimize the system.';

    public function handle(): int
    {
        if (!$this->option('force') && !$this->confirm('This will wipe ALL caches. Continue?', true)) {
            $this->info('Aborted.');
            return self::SUCCESS;
        }

        $this->components->info('Nuking Digibase caches...');

        // 1. Flush the dedicated digibase cache store
        $this->nukeDigibaseStore();

        // 2. Flush the digibase file cache directory directly (belt + suspenders)
        $this->nukeDigibaseFiles();

        // 3. Clear application cache
        $this->callSilently('cache:clear');
        $this->components->task('Application cache cleared');

        // 4. Clear compiled views
        $this->callSilently('view:clear');
        $this->components->task('Compiled views cleared');

        // 5. Clear route cache
        $this->callSilently('route:clear');
        $this->components->task('Route cache cleared');

        // 6. Clear config cache
        $this->callSilently('config:clear');
        $this->components->task('Config cache cleared');

        // 7. Clear event cache
        $this->callSilently('event:clear');
        $this->components->task('Event cache cleared');

        // 8. Re-optimize: cache config + routes + views
        $this->callSilently('config:cache');
        $this->callSilently('route:cache');
        $this->components->task('System re-optimized (config + routes)');

        $this->newLine();
        $this->components->info('Digibase has been nuked and rebuilt. All systems nominal.');

        return self::SUCCESS;
    }

    protected function nukeDigibaseStore(): void
    {
        try {
            $store = Cache::store('digibase');
            $driver = config('cache.stores.digibase.driver', 'file');

            // If tagging is supported, flush by tag first
            if (in_array($driver, ['redis', 'memcached', 'dynamodb'])) {
                $store->tags(['digibase'])->flush();
            }

            // Then flush the entire store regardless
            $store->flush();

            $this->components->task('Digibase cache store flushed');
        } catch (\Throwable $e) {
            $this->components->warn("Digibase store flush failed: {$e->getMessage()}");
        }
    }

    protected function nukeDigibaseFiles(): void
    {
        $path = storage_path('framework/cache/digibase');

        if (File::isDirectory($path)) {
            File::cleanDirectory($path);
            $this->components->task('Digibase file cache directory wiped');
        }
    }
}

```

## File: app/Console/Commands/PruneApiAnalytics.php
```php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PruneApiAnalytics extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'api:prune-analytics';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Prune API analytics records older than 30 days';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $days = 30;
        $cutoff = now()->subDays($days);

        $this->info("Pruning API analytics records older than {$days} days ({$cutoff->toDateTimeString()})...");

        try {
            $count = DB::table('api_analytics')
                ->where('created_at', '<', $cutoff)
                ->delete();

            $this->info("Deleted {$count} records.");
            
            Log::info("Pruned {$count} API analytics records older than {$cutoff->toDateTimeString()}");

        } catch (\Exception $e) {
            $this->error("Failed to prune records: " . $e->getMessage());
            Log::error("Failed to prune API analytics: " . $e->getMessage());
            return 1;
        }

        return 0;
    }
}

```

## File: app/Events/ModelActivity.php
```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class ModelActivity implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets;

    public string $type;
    public string $modelName;
    public $data;
    public $user;

    /**
     * Create a new event instance.
     */
    public function __construct(string $type, string $modelName, $data, $user = null)
    {
        $this->type = $type;
        $this->modelName = $modelName;
        $this->data = $data;
        $this->user = $user;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel('digibase.activity'),
        ];
    }

    public function broadcastAs(): string
    {
        return 'ModelActivity';
    }
}

```

## File: app/Events/ModelChanged.php
```php
<?php

namespace App\Events;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Real-time model change event.
 *
 * Broadcasts on a private channel so only authenticated users
 * with valid API key access can receive updates.
 *
 * Channel: private-data.{table}
 * Event name: model.changed
 */
class ModelChanged implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param string $table The table/model name
     * @param string $action The action: 'created', 'updated', 'deleted'
     * @param array|null $data The record data (already filtered for hidden fields)
     */
    public function __construct(
        public string $table,
        public string $action,
        public ?array $data = null
    ) {}

    /**
     * Broadcast on a private channel so authorization is enforced.
     */
    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("data.{$this->table}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'model.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'table' => $this->table,
            'action' => $this->action,
            'data' => $this->data,
            'timestamp' => now()->toIso8601String(),
        ];
    }
}

```

## File: app/Filament/Pages/ApiDocumentation.php
```php
<?php

namespace App\Filament\Pages;

use App\Models\DynamicModel;
use App\Services\ApiDocumentationService;
use Filament\Pages\Page;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use BackedEnum;
use UnitEnum;

class ApiDocumentation extends Page
{
    protected string $view = 'filament.pages.api-documentation';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';
    protected static string|UnitEnum|null $navigationGroup = 'Developer';
    protected static ?int $navigationSort = 1;
    protected static ?string $title = 'API Documentation';

    public ?int $selectedModelId = null;
    public ?array $documentation = null;
    
    // Try It Out state
    public string $testApiKey = '';
    public string $testRequestBody = '{}';
    public ?array $testResponse = null;
    public ?int $testStatusCode = null;
    public bool $testLoading = false;

    public function mount(): void
    {
        $this->selectedModelId = request()->query('model');
        
        if ($this->selectedModelId) {
            $this->loadDocumentation();
        }
    }

    public function schema(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('selectedModelId')
                    ->label('Select Table')
                    ->options(
                        DynamicModel::where('user_id', auth()->id())
                            ->where('is_active', true)
                            ->pluck('display_name', 'id')
                    )
                    ->searchable()
                    ->live()
                    ->afterStateUpdated(fn () => $this->loadDocumentation()),
            ]);
    }

    public function loadDocumentation(): void
    {
        if (!$this->selectedModelId) {
            $this->documentation = null;
            return;
        }

        $model = DynamicModel::find($this->selectedModelId);
        
        if (!$model || $model->user_id !== auth()->id()) {
            $this->documentation = null;
            return;
        }

        $service = new ApiDocumentationService();
        $this->documentation = $service->generateDocumentation($model);
        
        // Reset test state
        $this->testResponse = null;
        $this->testStatusCode = null;
        $this->testRequestBody = json_encode($this->documentation['examples']['javascript']['create'] ?? [], JSON_PRETTY_PRINT);
    }
    
    public function downloadOpenApiSpec(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $model = DynamicModel::find($this->selectedModelId);
        
        if (!$model || $model->user_id !== auth()->id()) {
            abort(403);
        }
        
        $service = new ApiDocumentationService();
        $spec = $service->generateOpenApiSpec($model);
        
        $filename = 'openapi-' . $model->table_name . '.json';
        
        return response()->streamDownload(function () use ($spec) {
            echo json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }
    
    public function testEndpoint(string $method, string $endpoint): void
    {
        $this->testLoading = true;
        $this->testResponse = null;
        $this->testStatusCode = null;
        
        try {
            $model = DynamicModel::find($this->selectedModelId);
            
            if (!$model || $model->user_id !== auth()->id()) {
                $this->testResponse = ['error' => 'Unauthorized'];
                $this->testStatusCode = 403;
                return;
            }
            
            $url = config('app.url') . $endpoint;
            
            $request = \Illuminate\Support\Facades\Http::withHeaders([
                'x-api-key' => $this->testApiKey,
                'Accept' => 'application/json',
            ]);
            
            if (in_array($method, ['POST', 'PUT', 'PATCH'])) {
                $body = json_decode($this->testRequestBody, true);
                $response = $request->$method($url, $body);
            } else {
                $response = $request->$method($url);
            }
            
            $this->testStatusCode = $response->status();
            $this->testResponse = $response->json();
            
        } catch (\Exception $e) {
            $this->testResponse = ['error' => $e->getMessage()];
            $this->testStatusCode = 500;
        } finally {
            $this->testLoading = false;
        }
    }
}

```

## File: app/Filament/Pages/Backups.php
```php
<?php

namespace App\Filament\Pages;

use ShuvroRoy\FilamentSpatieLaravelBackup\Pages\Backups as BaseBackups;

class Backups extends BaseBackups
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-circle-stack';

    public static function getNavigationGroup(): string | \UnitEnum | null
    {
        return 'System';
    }

    public static function getNavigationSort(): ?int
    {
        return 98;
    }
}

```

## File: app/Filament/Pages/BrandingSettings.php
```php
<?php

namespace App\Filament\Pages;

use BackedEnum;
use Inerba\DbConfig\AbstractPageSettings;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;

class BrandingSettings extends AbstractPageSettings
{
    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    protected static ?string $title = 'Branding';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    // protected ?string $subheading = ''; // Uncomment if you want to set a custom subheading

    // protected static ?string $slug = 'branding-settings'; // Uncomment if you want to set a custom slug

    protected string $view = 'filament.pages.branding-settings';

    protected function settingName(): string
    {
        return 'branding';
    }

    /**
     * Provide default values.
     *
     * @return array<string, mixed>
     */
    public function getDefaultData(): array
    {
        return [];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\TextInput::make('site_name')
                    ->label('Site Name')
                    ->placeholder('Digibase'),
                \Filament\Forms\Components\FileUpload::make('site_logo')
                    ->label('Logo')
                    ->image()
                    ->directory('branding'),
                \Filament\Forms\Components\ColorPicker::make('primary_color')
                    ->label('Primary Color'),
            ])
            ->statePath('data');
    }
}

```

## File: app/Filament/Pages/CodeGenerator.php
```php
<?php

namespace App\Filament\Pages;

use App\Models\DynamicModel;
use App\Services\CodeGeneratorService;
use Filament\Schemas\Components\Actions;
use Filament\Actions\Action;
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

```

## File: app/Filament/Pages/DataExplorer.php
```php
<?php

namespace App\Filament\Pages;

use App\Models\DynamicModel;
use App\Models\DynamicRecord;
use Filament\Pages\Page;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\SpatieMediaLibraryImageColumn;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use pxlrbt\FilamentExcel\Actions\Pages\ExportAction;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use BackedEnum;
use UnitEnum;

class DataExplorer extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-table-cells';
    protected static string|UnitEnum|null $navigationGroup = 'Database';
    protected static ?int $navigationSort = 2;
    protected static ?string $title = 'Data Explorer';
    protected static bool $shouldRegisterNavigation = true;

    protected string $view = 'filament.pages.data-explorer';

    public ?string $tableId = null;
    public bool $isSpreadsheet = false;

    public function mount(): void
    {
        if (!$this->tableId) {
            $this->tableId = request()->query('tableId') ?? request()->query('tableid');
        }
        $this->isSpreadsheet = (bool) request()->query('spreadsheet');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('apiDocs')
                ->label('API Docs')
                ->icon('heroicon-o-book-open')
                ->color('info')
                ->url(fn () => route('filament.admin.pages.api-documentation', ['model' => $this->tableId]))
                ->openUrlInNewTab()
                ->visible(fn () => $this->tableId !== null),
            Action::make('toggleSpreadsheet')
                ->label($this->isSpreadsheet ? 'Standard View' : 'Spreadsheet View')
                ->icon($this->isSpreadsheet ? 'heroicon-o-table-cells' : 'heroicon-o-squares-2x2')
                ->color($this->isSpreadsheet ? 'gray' : 'primary')
                ->action(fn () => $this->isSpreadsheet = ! $this->isSpreadsheet)
                ->visible(fn () => $this->tableId !== null),
            ExportAction::make()
                ->label('Export to Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('success')
                ->exports([
                    \pxlrbt\FilamentExcel\Exports\ExcelExport::make()
                        ->fromTable()
                        ->withFilename($this->tableId . '-' . date('Y-m-d') . '.xlsx'),
                ])
                ->visible(fn () => $this->tableId !== null),
        ];
    }

    public function table(Table $table): Table
    {
        // 1. If no table is selected, show an empty state
        if (! $this->tableId) {
            return $table->query(DynamicModel::query()->where('id', 0))->heading('Select a table to view data');
        }

        // 2. Load the Dynamic Model definition
        $dynamicModel = DynamicModel::find($this->tableId);
        if (! $dynamicModel) {
            return $table->query(DynamicModel::query()->where('id', 0));
        }

        // 2.1 Check if physical table exists
        if (! Schema::hasTable($dynamicModel->table_name)) {
            return $table
                ->query(DynamicModel::query()->where('id', 0))
                ->emptyStateHeading("Database table '{$dynamicModel->table_name}' not found.")
                ->emptyStateDescription("Please go back to Table Builder and ensure the table is properly created.")
                ->columns([
                    TextColumn::make('status')
                        ->getStateUsing(fn () => 'Missing Table')
                        ->badge()
                        ->color('danger'),
                ]);
        }

        // 3. Build Dynamic Columns
        $columns = [];
        $columns[] = TextColumn::make('id')->sortable();

        if ($dynamicModel->fields->isNotEmpty()) {
            foreach ($dynamicModel->fields as $field) {
                // Use SpatieMediaLibraryImageColumn for file/image fields
                if (in_array($field->type, ['file', 'image'])) {
                    $columns[] = SpatieMediaLibraryImageColumn::make($field->name)
                        ->label($field->display_name ?? Str::headline($field->name))
                        ->collection('files')
                        ->conversion('thumb')
                        ->circular(false)
                        ->stacked()
                        ->limit(3);
                } else {
                    // Use TextColumn for safe display (XSS protection)
                    $columns[] = TextColumn::make($field->name)
                        ->label($field->display_name ?? Str::headline($field->name))
                        ->sortable()
                        ->searchable()
                        ->limit(50); // Truncate long text
                }
            }
        }
        
        $columns[] = TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true);

        // 4. Configure the Table
        return $table
            ->query(function () use ($dynamicModel) {
                return (new DynamicRecord())->setDynamicTable($dynamicModel->table_name)->newQuery();
            })
            ->columns($columns)
            ->heading($dynamicModel->display_name . " Data")
            ->headerActions([
                CreateAction::make()
                    ->schema($this->getDynamicForm($dynamicModel))
                    ->using(function (array $data) use ($dynamicModel) {
                        $record = new DynamicRecord();
                        $record->setDynamicTable($dynamicModel->table_name);
                        $record->fill($data);
                        $record->save();
                        return $record;
                    }),
            ])
            ->actions([
                EditAction::make()
                    ->schema($this->getDynamicForm($dynamicModel))
                    ->using(function ($record, array $data) use ($dynamicModel) {
                        $record->setTable($dynamicModel->table_name);
                        $record->fill($data);
                        $record->save();
                        return $record;
                    }),
                DeleteAction::make()
                    ->using(function ($record) use ($dynamicModel) {
                        $record->setTable($dynamicModel->table_name); 
                        $record->delete();
                    }),
            ]);
    }

    /**
     * Generate dynamic form fields based on DynamicModel schema.
     * Now uses Spatie Media Library for file uploads.
     */
    protected function getDynamicForm($dynamicModel): array
    {
        $fields = [];
        if ($dynamicModel->fields->isNotEmpty()) {
            foreach ($dynamicModel->fields as $field) {
                $component = match ($field->type) {
                    'boolean' => Toggle::make($field->name),
                    'date' => DatePicker::make($field->name),
                    'datetime' => DateTimePicker::make($field->name),
                    
                    // 🎯 UPGRADED: Use Spatie Media Library for files
                    'file' => SpatieMediaLibraryFileUpload::make($field->name)
                        ->collection('files')
                        ->multiple()
                        ->maxFiles(5)
                        ->maxSize(10240) // 10MB
                        ->downloadable()
                        ->openable()
                        ->previewable()
                        ->reorderable()
                        ->disk('digibase_storage'),
                    
                    // 🎯 UPGRADED: Use Spatie Media Library for images with optimization
                    'image' => SpatieMediaLibraryFileUpload::make($field->name)
                        ->collection('images')
                        ->image()
                        ->imageEditor()
                        ->imageEditorAspectRatios([
                            null,
                            '16:9',
                            '4:3',
                            '1:1',
                        ])
                        ->multiple()
                        ->maxFiles(10)
                        ->maxSize(5120) // 5MB
                        ->downloadable()
                        ->openable()
                        ->previewable()
                        ->reorderable()
                        ->disk('digibase_storage')
                        ->conversion('preview'),
                    
                    default => TextInput::make($field->name),
                };

                $fields[] = $component
                    ->label($field->display_name ?? Str::headline($field->name))
                    ->required($field->is_required);
            }
        }
        return $fields;
    }
}

```

## File: app/Filament/Pages/SocialSettings.php
```php
<?php

namespace App\Filament\Pages;

use BackedEnum;
use Inerba\DbConfig\AbstractPageSettings;
use Filament\Schemas\Components;
use Filament\Schemas\Schema;

class SocialSettings extends AbstractPageSettings
{
    /**
     * @var array<string, mixed> | null
     */
    public ?array $data = [];

    protected static ?string $title = 'Authentication';
    protected static string|\UnitEnum|null $navigationGroup = 'Settings';
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-share';

    protected string $view = 'filament.pages.social-settings';

    protected function settingName(): string
    {
        return 'auth';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Schemas\Components\Section::make('Google OAuth')
                    ->schema([
                        \Filament\Forms\Components\Toggle::make('google_enabled')
                            ->label('Enable Google Login'),
                        \Filament\Forms\Components\TextInput::make('google_client_id')
                            ->label('Client ID'),
                        \Filament\Forms\Components\TextInput::make('google_client_secret')
                            ->label('Client Secret')
                            ->password()
                            ->revealable(),
                    ]),
                \Filament\Schemas\Components\Section::make('GitHub OAuth')
                    ->schema([
                        \Filament\Forms\Components\Toggle::make('github_enabled')
                            ->label('Enable GitHub Login'),
                        \Filament\Forms\Components\TextInput::make('github_client_id')
                            ->label('Client ID'),
                        \Filament\Forms\Components\TextInput::make('github_client_secret')
                            ->label('Client Secret')
                            ->password()
                            ->revealable(),
                    ]),
            ])
            ->statePath('data');
    }
}

```

## File: app/Filament/Pages/SqlPlayground.php
```php
<?php

namespace App\Filament\Pages;

use Filament\Actions\Action;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use BackedEnum;
use UnitEnum;
use App\Models\DynamicModel;
use App\Models\DynamicField;
use App\Models\DynamicRelationship; // 👈 CRITICAL IMPORT
use Illuminate\Support\Facades\Schema as LaravelSchema;
use Illuminate\Support\Str;
use Filament\Forms\Components\Select;

class SqlPlayground extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-command-line';
    protected static string|UnitEnum|null $navigationGroup = 'System';
    protected static ?string $navigationLabel = 'SQL Playground';
    protected static ?string $title = 'SQL Playground';
    protected string $view = 'filament.pages.sql-playground';

    public ?string $query = '';
    public ?array $results = [];
    public ?string $message = '';

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Run SQL Commands')
                    ->description('Execute raw SQL (CREATE, DROP, SELECT). Use with Caution.')
                    ->schema([
                        Textarea::make('query')
                            ->label('SQL Query')
                            ->placeholder("SELECT * FROM users LIMIT 5;\n-- or --\nCREATE TABLE logs (id int);")
                            ->rows(10)
                            ->required(),
                    ])
                    ->footerActions([
                        Action::make('execute')
                            ->label('Run Query')
                            ->icon('heroicon-o-play')
                            ->color('danger')
                            ->action(fn () => $this->runQuery()),
                    ]),
            ]);
    }

    protected array $protectedTables = [
        'users', 'personal_access_tokens', 'password_reset_tokens',
        'sessions', 'migrations', 'roles', 'permissions',
        'role_has_permissions', 'model_has_roles', 'model_has_permissions',
    ];

    public function runQuery()
    {
        $this->results = [];
        $this->message = '';

        try {
            $sql = trim($this->query);
            if (empty($sql)) return;

            // Strip comments to prevent bypass
            $cleanSql = preg_replace('/--.*$/m', '', $sql);
            $cleanSql = preg_replace('/#.*$/m', '', $cleanSql);
            $cleanSql = preg_replace('/\/\*[\s\S]*?\*\//', '', $cleanSql);
            $cleanSql = preg_replace('/\s+/', ' ', trim($cleanSql));

            // Block queries that touch protected system tables
            foreach ($this->protectedTables as $table) {
                if (preg_match('/\b' . preg_quote($table, '/') . '\b/i', $cleanSql)) {
                    Notification::make()->danger()->title('Blocked')->body("Access to system table '{$table}' is restricted.")->send();
                    return;
                }
            }

            // Block multiple statements (prevent piggy-backing)
            if (preg_match('/;\s*\S/', $cleanSql)) {
                Notification::make()->danger()->title('Blocked')->body('Multiple statements are not allowed.')->send();
                return;
            }

            if (stripos($cleanSql, 'SELECT') === 0 || stripos($cleanSql, 'SHOW') === 0 || stripos($cleanSql, 'DESCRIBE') === 0 || stripos($cleanSql, 'WITH') === 0) {
                $this->results = json_decode(json_encode(DB::select($sql)), true);
                $count = count($this->results);
                Notification::make()->success()->title('Query Loaded')->body("$count rows found.")->send();
            } else {
                // For non-SELECT queries, block truly destructive operations on any table
                $destructive = ['DROP DATABASE', 'DROP SCHEMA', 'TRUNCATE'];
                foreach ($destructive as $keyword) {
                    if (stripos($cleanSql, $keyword) !== false) {
                        Notification::make()->danger()->title('Blocked')->body("'{$keyword}' is not allowed.")->send();
                        return;
                    }
                }

                DB::unprepared($sql);
                $this->message = "Command executed successfully.";
                Notification::make()->success()->title('Executed')->send();
            }

        } catch (\Exception $e) {
            Notification::make()->danger()->title('SQL Error')->body($e->getMessage())->send();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import_table')
                ->label('Import Tables (Auto-Link)')
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->form([
                    Select::make('tables')
                        ->label('Select Tables to Import')
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->options(function () {
                            $tables = LaravelSchema::getTableListing();
                            $exclude = [
                                'migrations', 'users', 'password_reset_tokens', 'failed_jobs', 
                                'personal_access_tokens', 'sessions', 'cache', 'cache_locks', 
                                'jobs', 'job_batches', 'sqlite_sequence', 'permissions', 'roles', 
                                'model_has_permissions', 'model_has_roles', 'role_has_permissions', 
                                'media', 'db_config', 'webhooks', 'dynamic_models', 'dynamic_fields', 
                                'dynamic_relationships', 'model_activity', 'storage_files', 'file_system_items'
                            ];
                            
                            $options = [];
                            foreach ($tables as $rawTableName) {
                                $cleanName = Str::afterLast($rawTableName, '.');
                                if (Str::startsWith($cleanName, 'sqlite_')) continue;
                                if (in_array($cleanName, $exclude)) continue;
                                $options[$rawTableName] = Str::headline($cleanName) . " ($cleanName)";
                            }
                            return $options;
                        })
                        ->required(),
                ])
                ->action(function (array $data) {
                    $selectedTables = $data['tables'];
                    $importedIds = [];
                    $count = 0;

                    // PHASE 1: Create Models & Fields
                    foreach ($selectedTables as $rawTableName) {
                        try {
                            // 1. Clean the Table Name (Remove 'main.' if pure table exists)
                            $cleanName = Str::afterLast($rawTableName, '.');
                            $realTableName = LaravelSchema::hasTable($cleanName) ? $cleanName : $rawTableName;
                            
                            DB::transaction(function () use ($realTableName, $cleanName, &$importedIds) {
                                // Create/Find Model
                                $model = DynamicModel::firstOrCreate(
                                    ['name' => $cleanName], // Match by clean name (slug)
                                    [
                                        'table_name' => $realTableName, // Save the WORKING table name
                                        'display_name' => Str::headline($cleanName),
                                        'is_active' => true,
                                        'generate_api' => true,
                                        'user_id' => auth()->id(),
                                        'list_rule' => 'true', // Default to Public for easier testing
                                        'view_rule' => 'true',
                                    ]
                                );
                                
                                // Update table_name if it was wrong before
                                if ($model->table_name !== $realTableName) {
                                    $model->update(['table_name' => $realTableName]);
                                }

                                $importedIds[$realTableName] = $model->id; // Map Real Name -> ID

                                // Sync Columns
                                $model->fields()->delete();
                                $columns = LaravelSchema::getColumns($realTableName);
                                
                                foreach ($columns as $col) {
                                    $colName = $col['name'];
                                    if ($colName === 'id') continue; 

                                    $type = match(strtolower($col['type_name'])) {
                                        'int', 'integer', 'bigint', 'tinyint' => 'integer',
                                        'bool', 'boolean' => 'boolean',
                                        'date', 'datetime', 'timestamp' => 'date',
                                        'json' => 'json',
                                        default => 'text',
                                    };

                                    DynamicField::create([
                                        'dynamic_model_id' => $model->id,
                                        'name' => $colName,
                                        'display_name' => Str::headline($colName),
                                        'type' => $type,
                                        'is_required' => !($col['nullable']),
                                    ]);
                                }
                            });
                            $count++;
                        } catch (\Exception $e) { continue; }
                    }

                    // PHASE 2: Auto-Detect Relationships (Fixed Logic)
                    foreach ($importedIds as $tableName => $currentModelId) {
                        // Get Foreign Keys
                        $fks = DB::select("PRAGMA foreign_key_list('$tableName')");

                        foreach ($fks as $fk) {
                            $parentTableName = $fk->table; // e.g. 'authors'
                            $localCol = $fk->from;         // e.g. 'author_id'
                            
                            // Find Parent Model (Try both raw name and clean name)
                            $parentModel = DynamicModel::where('table_name', $parentTableName)
                                ->orWhere('name', $parentTableName)
                                ->first();

                            if ($parentModel) {
                                // 1. BelongsTo (Child -> Parent)
                                // "Book belongs to Author"
                                $belongsToName = Str::camel(Str::singular($parentModel->name)); // 'author'
                                
                                DynamicRelationship::firstOrCreate([
                                    'dynamic_model_id' => $currentModelId,
                                    'related_model_id' => $parentModel->id,
                                    'type' => 'belongsTo',
                                    'name' => $belongsToName, // FIX: Use 'name', not 'method_name'
                                ], [
                                    'foreign_key' => $localCol,
                                    'local_key' => 'id',
                                ]);

                                // 2. HasMany (Parent -> Child)
                                // "Author has many Books"
                                $currentModelName = DynamicModel::find($currentModelId)->name;
                                $hasManyName = Str::camel(Str::plural($currentModelName)); // 'books'

                                DynamicRelationship::firstOrCreate([
                                    'dynamic_model_id' => $parentModel->id,
                                    'related_model_id' => $currentModelId,
                                    'type' => 'hasMany',
                                    'name' => $hasManyName, // FIX: Use 'name', not 'method_name'
                                ], [
                                    'foreign_key' => $localCol,
                                    'local_key' => 'id',
                                ]);
                            }
                        }
                    }

                    Notification::make()->success()->title("$count Tables Imported & Linked Correctly!")->send();
                }),
        ];
    }
}

```

## File: app/Filament/Pages/StorageSettings.php
```php
<?php

namespace App\Filament\Pages;

use App\Settings\GeneralSettings;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

class StorageSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cloud';
    protected static string|\UnitEnum|null $navigationGroup = 'Settings';
    protected static ?string $title = 'Storage Configuration';
    protected static ?string $navigationLabel = 'Storage';
    protected static ?string $slug = 'settings/storage';

    protected string $view = 'filament.pages.storage-settings';

    public ?array $data = [];

    public function mount(GeneralSettings $settings): void
    {
        $this->form->fill([
            'storage_driver' => $settings->storage_driver ?? 'local',
            'aws_access_key_id' => $settings->aws_access_key_id,
            'aws_secret_access_key' => $settings->aws_secret_access_key,
            'aws_default_region' => $settings->aws_default_region ?? 'us-east-1',
            'aws_bucket' => $settings->aws_bucket,
            'endpoint' => $settings->aws_endpoint,
            'use_path_style_endpoint' => $settings->aws_use_path_style === 'true' ? 'true' : 'false',
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->components([
                \Filament\Schemas\Components\Section::make('Storage Driver')
                    ->description('Choose where your application should permanently store files.')
                    ->schema([
                        Forms\Components\Select::make('storage_driver')
                            ->label('Active Driver')
                            ->options([
                                'local' => 'Local Disk (Server Storage)',
                                's3' => 'Amazon S3 (Cloud Storage)',
                            ])
                            ->required()
                            ->live()
                            ->native(false),
                    ]),

                \Filament\Schemas\Components\Section::make('Amazon S3 Configuration')
                    ->description('Detailed credentials for your S3-compatible cloud storage.')
                    ->visible(fn ($get) => $get('storage_driver') === 's3')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('aws_access_key_id')
                            ->label('Access Key ID')
                            ->placeholder('AKIA...')
                            ->required(fn ($get) => $get('storage_driver') === 's3'),

                        Forms\Components\TextInput::make('aws_secret_access_key')
                            ->label('Secret Access Key')
                            ->password()
                            ->revealable()
                            ->required(fn ($get) => $get('storage_driver') === 's3'),

                        Forms\Components\TextInput::make('aws_default_region')
                            ->label('Default Region')
                            ->placeholder('us-east-1')
                            ->default('us-east-1')
                            ->required(fn ($get) => $get('storage_driver') === 's3'),

                        Forms\Components\TextInput::make('aws_bucket')
                            ->label('S3 Bucket Name')
                            ->placeholder('my-app-storage')
                            ->required(fn ($get) => $get('storage_driver') === 's3'),

                        Forms\Components\TextInput::make('endpoint')
                            ->label('Endpoint URL (Optional)')
                            ->placeholder('https://s3.amazonaws.com')
                            ->helperText('Override if using MinIO, R2, or DigitalOcean Spaces.')
                            ->columnSpanFull(),

                        Forms\Components\Select::make('use_path_style_endpoint')
                            ->label('Use Path Style')
                            ->options([
                                'true' => 'Yes (Required for MinIO)',
                                'false' => 'No (Standard S3)',
                            ])
                            ->default('false')
                            ->native(false),
                    ]),
            ])
            ->statePath('data');
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Configuration')
                ->submit('save')
                ->color('primary'),
        ];
    }

    public function submit(GeneralSettings $settings): void
    {
        $data = $this->form->getState();

        $settings->storage_driver = $data['storage_driver'];
        
        if ($data['storage_driver'] === 's3') {
            $settings->aws_access_key_id = $data['aws_access_key_id'];
            $settings->aws_secret_access_key = $data['aws_secret_access_key'];
            $settings->aws_default_region = $data['aws_default_region'];
            $settings->aws_bucket = $data['aws_bucket'];
            $settings->aws_endpoint = $data['endpoint'];
            $settings->aws_use_path_style = $data['use_path_style_endpoint'];
        }

        $settings->save();

        Notification::make()
            ->title('Settings Saved Successfully')
            ->success()
            ->send();
    }
}

```

## File: app/Filament/Resources/ActivityLogResource.php
```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use Spatie\Activitylog\Models\Activity;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;
use BackedEnum;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';
    protected static string|UnitEnum|null $navigationGroup = 'System';
    protected static ?string $modelLabel = 'Activity Log';
    protected static ?string $pluralModelLabel = 'Activity Logs';

    public static function canCreate(): bool
    {
        return false;
    }

    /*
     * Access Control: Only Super Admin (ID 1)
     */
    public static function canViewAny(): bool
    {
        return auth()->check() && auth()->id() === 1;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                    
                Tables\Columns\TextColumn::make('log_name')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                Tables\Columns\TextColumn::make('description')
                    ->searchable()
                    ->limit(50),

                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Subject')
                    ->searchable()
                    ->toggleable(),

                Tables\Columns\TextColumn::make('subject_id')
                    ->label('Subject ID')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('causer.name')
                    ->label('User')
                    ->searchable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                //
            ])
            ->actions([
                // View action requires Infolist/Form which seems missing in this environment
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ManageActivityLogs::route('/'),
        ];
    }
}

```

## File: app/Filament/Resources/ActivityLogResource/Pages/ManageActivityLogs.php
```php
<?php

namespace App\Filament\Resources\ActivityLogResource\Pages;

use App\Filament\Resources\ActivityLogResource;
use Filament\Resources\Pages\ManageRecords;

class ManageActivityLogs extends ManageRecords
{
    protected static string $resource = ActivityLogResource::class;
}

```

## File: app/Filament/Resources/ApiKeyResource.php
```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ApiKeyResource\Pages;
use App\Models\ApiKey;
use App\Models\DynamicModel;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Components\Section;
use Filament\Actions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use BackedEnum;
use UnitEnum;

class ApiKeyResource extends Resource
{
    protected static ?string $model = ApiKey::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationLabel = 'API Keys';

    protected static ?string $modelLabel = 'API Key';

    protected static string|UnitEnum|null $navigationGroup = 'Developers';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Key Configuration')
                    ->description('Configure your API key settings')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Key Name')
                            ->placeholder('e.g., Mobile App, Frontend, Partner Integration')
                            ->required()
                            ->maxLength(255)
                            ->helperText('A friendly name to identify this key'),

                        Forms\Components\Select::make('type')
                            ->label('Key Type')
                            ->options([
                                'public' => '🔓 Public (Read-Only) - pk_xxx',
                                'secret' => '🔐 Secret (Full Access) - sk_xxx',
                            ])
                            ->default('public')
                            ->required()
                            ->live()
                            ->helperText('Public keys can only read. Secret keys can create, update, and delete.')
                            ->disabledOn('edit'),

                        Forms\Components\CheckboxList::make('scopes')
                            ->label('Permissions')
                            ->options([
                                'read' => '📖 Read - View & list data',
                                'write' => '✏️ Write - Create & update data',
                                'delete' => '🗑️ Delete - Remove data',
                            ])
                            ->default(fn ($get) => $get('type') === 'secret' 
                                ? ['read', 'write', 'delete'] 
                                : ['read'])
                            ->columns(3)
                            ->helperText('Select what this key can do'),

                        Forms\Components\Select::make('allowed_tables')
                            ->label('Allowed Tables')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options(function () {
                                return DynamicModel::where('is_active', true)
                                    ->pluck('display_name', 'table_name')
                                    ->toArray();
                            })
                            ->helperText('Leave empty to allow access to ALL tables. Select specific tables to restrict.'),

                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Expiration Date')
                            ->nullable()
                            ->minDate(now()->addHour())
                            ->helperText('Leave empty for no expiration'),

                        Forms\Components\TextInput::make('rate_limit')
                            ->label('Rate Limit (requests/minute)')
                            ->numeric()
                            ->default(60)
                            ->minValue(1)
                            ->maxValue(1000)
                            ->helperText('Maximum API calls per minute'),
                    ])
                    ->columns(2),

                Section::make('Status')
                    ->schema([
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Deactivate to temporarily disable this key'),
                    ])
                    ->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->icon('heroicon-o-key'),

                Tables\Columns\TextColumn::make('masked_key')
                    ->label('Key')
                    ->copyable()
                    ->copyableState(fn ($record) => $record->key)
                    ->copyMessage('🔑 API Key copied!')
                    ->fontFamily('mono')
                    ->color('gray'),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Type')
                    ->colors([
                        'gray' => 'public',
                        'danger' => 'secret',
                    ])
                    ->icons([
                        'heroicon-o-lock-open' => 'public',
                        'heroicon-o-lock-closed' => 'secret',
                    ]),

                Tables\Columns\TextColumn::make('scopes')
                    ->label('Scopes')
                    ->badge()
                    ->separator(',')
                    ->color('primary'),

                Tables\Columns\TextColumn::make('allowed_tables')
                    ->label('Tables')
                    ->badge()
                    ->separator(',')
                    ->color('warning')
                    ->placeholder('All Tables')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean()
                    ->trueIcon('heroicon-o-check-circle')
                    ->falseIcon('heroicon-o-x-circle')
                    ->trueColor('success')
                    ->falseColor('danger'),

                Tables\Columns\TextColumn::make('last_used_at')
                    ->label('Last Used')
                    ->dateTime('M j, Y H:i')
                    ->placeholder('Never')
                    ->sortable()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('expires_at')
                    ->label('Expires')
                    ->dateTime('M j, Y')
                    ->placeholder('Never')
                    ->sortable()
                    ->color(fn ($record) => $record->expires_at?->isPast() ? 'danger' : 'gray'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('M j, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->options([
                        'public' => 'Public Keys',
                        'secret' => 'Secret Keys',
                    ]),
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->boolean()
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only'),
            ])
            ->actions([
                Actions\Action::make('copy')
                    ->label('Copy Key')
                    ->icon('heroicon-o-clipboard')
                    ->color('gray')
                    ->action(fn () => null) // Handled by JS
                    ->extraAttributes(fn ($record) => [
                        'x-on:click' => "navigator.clipboard.writeText('{$record->key}'); \$tooltip('Copied!')",
                    ]),
                Actions\Action::make('toggle')
                    ->label(fn ($record) => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn ($record) => $record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->color(fn ($record) => $record->is_active ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->action(fn ($record) => $record->update(['is_active' => !$record->is_active])),
                Actions\EditAction::make(),
                Actions\DeleteAction::make()
                    ->label('Revoke'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Revoke Selected'),
                ]),
            ])
            ->emptyStateHeading('No API Keys')
            ->emptyStateDescription('Create your first API key to start using the API.')
            ->emptyStateIcon('heroicon-o-key');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListApiKeys::route('/'),
            'create' => Pages\CreateApiKey::route('/create'),
            'edit' => Pages\EditApiKey::route('/{record}/edit'),
        ];
    }
}

```

## File: app/Filament/Resources/ApiKeyResource/Pages/CreateApiKey.php
```php
<?php

namespace App\Filament\Resources\ApiKeyResource\Pages;

use App\Filament\Resources\ApiKeyResource;
use App\Models\ApiKey;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreateApiKey extends CreateRecord
{
    protected static string $resource = ApiKeyResource::class;

    /**
     * Mutate form data before creating the record.
     * This is where we generate the actual key.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        // Set the current user as owner
        $data['user_id'] = Auth::id();

        // Generate the key based on type
        $type = $data['type'] ?? 'public';
        $data['key'] = ApiKey::generateKey($type);

        // Ensure scopes based on type if not set
        if (empty($data['scopes'])) {
            $data['scopes'] = $type === 'secret' 
                ? ['read', 'write', 'delete'] 
                : ['read'];
        }

        return $data;
    }

    /**
     * After creation, show the key to the user (ONLY TIME IT'S VISIBLE!)
     */
    protected function afterCreate(): void
    {
        $key = $this->record->key;
        $type = $this->record->type === 'secret' ? '🔐 Secret' : '🔓 Public';

        Notification::make()
            ->success()
            ->title('API Key Generated!')
            ->body("
                <div style='font-family: monospace; background: #1a1a2e; padding: 12px; border-radius: 8px; margin: 8px 0;'>
                    <strong style='color: #00d4ff;'>{$type} Key:</strong><br>
                    <code style='color: #a5f3fc; font-size: 14px; word-break: break-all;'>{$key}</code>
                </div>
                <p style='color: #ef4444; font-weight: bold;'>⚠️ Copy this key NOW! It won't be shown again.</p>
            ")
            ->persistent()
            ->actions([
                \Filament\Actions\Action::make('copy')
                    ->label('📋 Copy Key')
                    ->color('primary')
                    ->extraAttributes([
                        'x-on:click' => "navigator.clipboard.writeText('{$key}'); \$tooltip('Copied to clipboard!')",
                    ]),
            ])
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return null; // We handle this in afterCreate()
    }
}

```

## File: app/Filament/Resources/ApiKeyResource/Pages/EditApiKey.php
```php
<?php

namespace App\Filament\Resources\ApiKeyResource\Pages;

use App\Filament\Resources\ApiKeyResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditApiKey extends EditRecord
{
    protected static string $resource = ApiKeyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}

```

## File: app/Filament/Resources/ApiKeyResource/Pages/ListApiKeys.php
```php
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
            Actions\CreateAction::make()
                ->label('Generate New Token'),
        ];
    }
}

```

## File: app/Filament/Resources/DynamicModelResource.php
```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DynamicModelResource\Pages;
use App\Filament\Resources\DynamicModelResource\RelationManagers;
use App\Models\DynamicModel;
use Filament\Forms;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Schema as DbSchema;
use Illuminate\Database\Schema\Blueprint;
use BackedEnum;
use UnitEnum;
use Illuminate\Support\Str;

class DynamicModelResource extends Resource
{
    protected static ?string $model = DynamicModel::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-circle-stack';
    protected static ?string $navigationLabel = 'Table Builder';
    protected static ?string $modelLabel = 'Database Table';
    protected static string|UnitEnum|null $navigationGroup = 'Database';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Tabs::make('Table Configuration')
                    ->tabs([
                        Tabs\Tab::make('Definition')
                            ->icon('heroicon-o-table-cells')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Table Name (SQL)')
                                    ->required()
                                    ->maxLength(255)
                                    ->placeholder('e.g. blog_posts')
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Set $set, ?string $state) {
                                        if ($state) {
                                            $slug = Str::slug($state, '_');
                                            $set('name', $slug);
                                            $set('table_name', $slug);
                                            $set('display_name', Str::headline($state));
                                        }
                                    })
                                    ->helperText('This will be the physical table name in your database.'),

                                Forms\Components\TextInput::make('display_name')
                                    ->label('Display Name')
                                    ->placeholder('e.g. Blog Posts')
                                    ->maxLength(255),

                                Forms\Components\Hidden::make('table_name'),
                            ])->columns(2),

                        Tabs\Tab::make('Configuration')
                            ->icon('heroicon-o-cog')
                            ->schema([
                                Forms\Components\Toggle::make('is_active')
                                    ->label('Active')
                                    ->default(true),
                                Forms\Components\Toggle::make('generate_api')
                                    ->label('Generate API')
                                    ->default(true),
                                Forms\Components\Toggle::make('has_timestamps')
                                    ->label('Timestamps (created_at, updated_at)')
                                    ->default(true),
                                Forms\Components\Toggle::make('has_soft_deletes')
                                    ->label('Soft Deletes (deleted_at)')
                                    ->default(false),
                            ])->columns(2),

                        Tabs\Tab::make('Columns')
                            ->icon('heroicon-o-list-bullet')
                            ->schema([
                                Forms\Components\Repeater::make('fields')
                                    ->relationship('fields')
                                    ->schema([
                                        Forms\Components\TextInput::make('name')
                                            ->label('Column Name')
                                            ->required()
                                            ->placeholder('e.g. title')
                                            ->live(onBlur: true)
                                            ->afterStateUpdated(fn (Set $set, ?string $state) =>
                                                $set('display_name', $state ? Str::headline($state) : '')
                                            ),

                                        Forms\Components\Hidden::make('display_name'),

                                        Forms\Components\Select::make('type')
                                            ->options([
                                                'string' => 'String (Short Text)',
                                                'text' => 'Text (Long Description)',
                                                'integer' => 'Integer (Number)',
                                                'boolean' => 'Boolean (True/False)',
                                                'date' => 'Date',
                                                'datetime' => 'Date & Time',
                                                'json' => 'JSON / Object',
                                                'file' => 'File / Image',
                                            ])
                                            ->required(),

                                        Forms\Components\Checkbox::make('is_required')
                                            ->label('Required')
                                            ->default(false),

                                        Forms\Components\Checkbox::make('is_unique')
                                            ->label('Unique')
                                            ->default(false),

                                        Forms\Components\Checkbox::make('is_hidden')
                                            ->label('Hidden (API)')
                                            ->helperText('Hide from API responses')
                                            ->default(false),

                                        Forms\Components\TagsInput::make('validation_rules')
                                            ->label('Custom Rules')
                                            ->placeholder('Add rule (e.g. email, min:8)')
                                            ->helperText('Laravel validation rules. Press Enter to add.')
                                            ->suggestions([
                                                'email',
                                                'url', 
                                                'numeric',
                                                'min:1',
                                                'max:255',
                                                'regex:/^[a-z]+$/i',
                                                'alpha',
                                                'alpha_num',
                                                'confirmed',
                                                'uuid',
                                            ]),
                                    ])
                                    ->columns(5)
                                    ->addActionLabel('Add New Column')
                                    ->grid(1)
                                    ->defaultItems(1)
                            ]),



                        Tabs\Tab::make('Advanced')
                            ->icon('heroicon-o-code-bracket')
                            ->schema([
                                Forms\Components\CodeEditor::make('settings')
                                    ->label('Settings (JSON)')
                                    ->helperText('Advanced config in JSON format.')
                                    ->language(Language::Json),
                            ]),

                        Tabs\Tab::make('Access Rules')
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                Section::make('Row Level Security (RLS)')
                                    ->description('Define who can access data in this table. Select a preset or choose "Custom" for advanced rules.')
                                    ->schema([
                                        Forms\Components\Select::make('list_rule')
                                            ->label('📋 List (View All)')
                                            ->options([
                                                '' => '🔒 Admin Only (Default)',
                                                'true' => '🌐 Public (Anyone)',
                                                'auth.id != null' => '🔑 Authenticated Users',
                                                'auth.id == user_id' => '👤 Owner Only',
                                            ])
                                            ->helperText('Who can view the collection of records'),

                                        Forms\Components\Select::make('view_rule')
                                            ->label('👁️ View (Single Record)')
                                            ->options([
                                                '' => '🔒 Admin Only (Default)',
                                                'true' => '🌐 Public (Anyone)',
                                                'auth.id != null' => '🔑 Authenticated Users',
                                                'auth.id == user_id' => '👤 Owner Only',
                                            ])
                                            ->helperText('Who can view a single record'),

                                        Forms\Components\Select::make('create_rule')
                                            ->label('➕ Create')
                                            ->options([
                                                '' => '🔒 Admin Only (Default)',
                                                'true' => '🌐 Public (Anyone)',
                                                'auth.id != null' => '🔑 Authenticated Users',
                                            ])
                                            ->helperText('Who can create new records'),

                                        Forms\Components\Select::make('update_rule')
                                            ->label('✏️ Update')
                                            ->options([
                                                '' => '🔒 Admin Only (Default)',
                                                'true' => '🌐 Public (Anyone)',
                                                'auth.id != null' => '🔑 Authenticated Users',
                                                'auth.id == user_id' => '👤 Owner Only',
                                            ])
                                            ->helperText('Who can update records'),

                                        Forms\Components\Select::make('delete_rule')
                                            ->label('🗑️ Delete')
                                            ->options([
                                                '' => '🔒 Admin Only (Default)',
                                                'auth.id != null' => '🔑 Authenticated Users',
                                                'auth.id == user_id' => '👤 Owner Only',
                                            ])
                                            ->helperText('Who can delete records'),
                                    ])->columns(2),
                            ]),
                    ])->columnSpanFull()
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\Layout\Stack::make([
                    Tables\Columns\Layout\Split::make([
                        Tables\Columns\Layout\Stack::make([
                            Tables\Columns\TextColumn::make('display_name')
                                ->weight('bold')
                                ->size('lg')
                                ->searchable()
                                ->sortable()
                                ->icon('heroicon-o-table-cells')
                                ->iconColor('primary'),
                        ])->space(1),
                        
                        Tables\Columns\Layout\Stack::make([
                            Tables\Columns\TextColumn::make('quick_actions')
                                ->label('')
                                ->formatStateUsing(fn () => '')
                                ->extraAttributes(['class' => 'flex gap-2 justify-end']),
                            Tables\Columns\TextColumn::make('table_name')
                                ->badge()
                                ->color('gray')
                                ->icon('heroicon-o-circle-stack')
                                ->size('sm'),
                        ])->alignment('end')->space(1),
                    ]),
                    
                    Tables\Columns\Layout\Split::make([
                        Tables\Columns\Layout\Stack::make([
                            Tables\Columns\TextColumn::make('stats')
                                ->label('Statistics')
                                ->formatStateUsing(function (DynamicModel $record) {
                                    $recordCount = 0;
                                    $lastActivity = null;
                                    
                                    try {
                                        if (DbSchema::hasTable($record->table_name)) {
                                            $recordCount = \DB::table($record->table_name)->count();
                                            
                                            if ($record->has_timestamps) {
                                                $lastRecord = \DB::table($record->table_name)
                                                    ->orderBy('updated_at', 'desc')
                                                    ->first();
                                                if ($lastRecord && isset($lastRecord->updated_at)) {
                                                    $lastActivity = \Carbon\Carbon::parse($lastRecord->updated_at)->diffForHumans();
                                                }
                                            }
                                        }
                                    } catch (\Exception $e) {
                                        // Table might not exist yet
                                    }
                                    
                                    $stats = [];
                                    $stats[] = "📊 {$recordCount} records";
                                    $stats[] = "📋 {$record->fields->count()} fields";
                                    if ($lastActivity) {
                                        $stats[] = "🕐 Updated {$lastActivity}";
                                    }
                                    
                                    return implode(' • ', $stats);
                                })
                                ->color('gray')
                                ->size('sm'),
                        ]),
                        
                        Tables\Columns\Layout\Stack::make([
                            Tables\Columns\TextColumn::make('badges')
                                ->formatStateUsing(function (DynamicModel $record) {
                                    $badges = [];
                                    
                                    if ($record->is_active) {
                                        $badges[] = '✅ Active';
                                    } else {
                                        $badges[] = '⏸️ Inactive';
                                    }
                                    
                                    if ($record->generate_api) {
                                        $badges[] = '🔌 API';
                                    }
                                    
                                    if ($record->has_timestamps) {
                                        $badges[] = '⏰ Timestamps';
                                    }
                                    
                                    if ($record->has_soft_deletes) {
                                        $badges[] = '🗑️ Soft Delete';
                                    }
                                    
                                    return implode(' ', $badges);
                                })
                                ->size('xs')
                                ->color('gray'),
                        ])->alignment('end'),
                    ]),
                ])->space(2),
            ])
            ->contentGrid([
                'md' => 1,
                'xl' => 1,
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status')
                    ->placeholder('All Tables')
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only'),
                    
                Tables\Filters\TernaryFilter::make('generate_api')
                    ->label('API Status')
                    ->placeholder('All Tables')
                    ->trueLabel('API Enabled')
                    ->falseLabel('API Disabled'),
                    
                Tables\Filters\SelectFilter::make('has_timestamps')
                    ->label('Features')
                    ->options([
                        '1' => 'With Timestamps',
                        '0' => 'Without Timestamps',
                    ]),
            ])
            ->actions([
                // Quick Actions (shown on the right side of cards)
                Action::make('view_data')
                    ->label('View Data')
                    ->icon('heroicon-o-table-cells')
                    ->color('success')
                    ->button()
                    ->outlined()
                    ->url(fn (DynamicModel $record) => \App\Filament\Pages\DataExplorer::getUrl(['tableId' => $record->id])),
                    
                Action::make('spreadsheet_edit')
                    ->label('Spreadsheet')
                    ->icon('heroicon-o-squares-2x2')
                    ->color('warning')
                    ->button()
                    ->outlined()
                    ->url(fn (DynamicModel $record) => \App\Filament\Pages\DataExplorer::getUrl(['tableId' => $record->id, 'spreadsheet' => true]))
                    ->tooltip('Edit data in spreadsheet view with Univer.js'),
                    
                Action::make('api_docs')
                    ->label('API Docs')
                    ->icon('heroicon-o-book-open')
                    ->color('info')
                    ->button()
                    ->outlined()
                    ->url(fn (DynamicModel $record) => \App\Filament\Pages\ApiDocumentation::getUrl(['model' => $record->id]))
                    ->openUrlInNewTab(),
                    
                Action::make('json_preview')
                    ->label('JSON Schema')
                    ->icon('heroicon-o-code-bracket')
                    ->color('gray')
                    ->button()
                    ->outlined()
                    ->modalHeading(fn (DynamicModel $record) => $record->display_name . ' - JSON Schema')
                    ->modalContent(function (DynamicModel $record) {
                        $schema = [
                            'table' => $record->table_name,
                            'display_name' => $record->display_name,
                            'description' => $record->description,
                            'features' => [
                                'timestamps' => $record->has_timestamps,
                                'soft_deletes' => $record->has_soft_deletes,
                                'api_enabled' => $record->generate_api,
                            ],
                            'fields' => $record->fields->map(function ($field) {
                                return [
                                    'name' => $field->name,
                                    'type' => $field->type,
                                    'display_name' => $field->display_name,
                                    'required' => $field->is_required,
                                    'unique' => $field->is_unique,
                                    'default' => $field->default_value,
                                    'validation' => $field->validation_rules,
                                ];
                            })->toArray(),
                            'security' => [
                                'list_rule' => $record->list_rule,
                                'view_rule' => $record->view_rule,
                                'create_rule' => $record->create_rule,
                                'update_rule' => $record->update_rule,
                                'delete_rule' => $record->delete_rule,
                            ],
                        ];
                        
                        return view('filament.components.json-preview', [
                            'json' => json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                        ]);
                    })
                    ->modalWidth('4xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close'),
                    
                Action::make('export_schema')
                    ->label('Export')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->button()
                    ->outlined()
                    ->action(function (DynamicModel $record) {
                        $schema = [
                            'table' => $record->table_name,
                            'display_name' => $record->display_name,
                            'description' => $record->description,
                            'features' => [
                                'timestamps' => $record->has_timestamps,
                                'soft_deletes' => $record->has_soft_deletes,
                                'api_enabled' => $record->generate_api,
                            ],
                            'fields' => $record->fields->map(function ($field) {
                                return [
                                    'name' => $field->name,
                                    'type' => $field->type,
                                    'display_name' => $field->display_name,
                                    'required' => $field->is_required,
                                    'unique' => $field->is_unique,
                                    'default' => $field->default_value,
                                    'validation' => $field->validation_rules,
                                ];
                            })->toArray(),
                            'security' => [
                                'list_rule' => $record->list_rule,
                                'view_rule' => $record->view_rule,
                                'create_rule' => $record->create_rule,
                                'update_rule' => $record->update_rule,
                                'delete_rule' => $record->delete_rule,
                            ],
                        ];
                        
                        $filename = 'schema-' . $record->table_name . '.json';
                        
                        return response()->streamDownload(function () use ($schema) {
                            echo json_encode($schema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                        }, $filename, [
                            'Content-Type' => 'application/json',
                        ]);
                    }),
                    
                // Divider
                Action::make('divider_1')
                    ->label('')
                    ->disabled()
                    ->extraAttributes(['class' => 'border-l border-gray-300 dark:border-gray-600 h-8 mx-2']),
                
                // Icon Actions (existing)
                Action::make('sync_db')
                    ->icon('heroicon-o-arrow-path')
                    ->iconButton()
                    ->tooltip('Sync Database Schema')
                    ->color('info')
                    ->requiresConfirmation()
                    ->action(function (DynamicModel $record) {
                        $tableName = $record->table_name;
                        
                        if (!DbSchema::hasTable($tableName)) {
                            // CREATE NEW TABLE
                            DbSchema::create($tableName, function (Blueprint $table) use ($record) {
                                $table->id();
                                foreach ($record->fields as $field) {
                                    $type = $field->getDatabaseType();
                                    $column = $table->{$type}($field->name);
                                    if (!$field->is_required || in_array($field->type, ['file', 'image'])) $column->nullable();
                                    if ($field->is_unique) $column->unique();
                                }
                                $table->timestamps();
                                
                                // CRITICAL: Add soft deletes column if enabled
                                if ($record->has_soft_deletes) {
                                    $table->softDeletes();
                                }
                            });
                            Notification::make()->success()->title('Table Created')->send();
                        } else {
                            // UPDATE EXISTING TABLE - Add missing columns
                            $columnsAdded = 0;
                            
                            DbSchema::table($tableName, function (Blueprint $table) use ($record, $tableName, &$columnsAdded) {
                                foreach ($record->fields as $field) {
                                    if (!DbSchema::hasColumn($tableName, $field->name)) {
                                        $type = $field->getDatabaseType();
                                        $column = $table->{$type}($field->name);
                                        if (!$field->is_required || in_array($field->type, ['file', 'image'])) $column->nullable();
                                        $columnsAdded++;
                                    }
                                }
                                
                                // Add soft deletes if enabled and column doesn't exist
                                if ($record->has_soft_deletes && !DbSchema::hasColumn($tableName, 'deleted_at')) {
                                    $table->softDeletes();
                                    $columnsAdded++;
                                }
                            });
                            
                            if ($columnsAdded > 0) {
                                Notification::make()->success()->title("{$columnsAdded} column(s) added")->send();
                            } else {
                                Notification::make()->info()->title('Schema is up to date')->send();
                            }
                        }
                    }),
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Edit Table'),
                DeleteAction::make()
                    ->iconButton()
                    ->tooltip('Delete Table'),
                Action::make('destroy_table')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->iconButton()
                    ->tooltip('Destroy DB Table (Nuclear Option)')
                    ->requiresConfirmation()
                    ->modalHeading('⚠️ NUCLEAR OPTION: Destroy Database Table')
                    ->modalDescription('This will PERMANENTLY DELETE the physical table and ALL DATA. This cannot be undone.')
                    ->form([
                        Forms\Components\TextInput::make('confirmation_name')
                            ->label(fn ($record) => "Type '{$record->table_name}' to confirm")
                            ->required()
                            ->rule(fn ($record) => function ($attribute, $value, $fail) use ($record) {
                                if ($value !== $record->table_name) {
                                    $fail('The table name does not match.');
                                }
                            }),
                    ])
                    ->action(function ($record, array $data) {
                        // 1. Drop the Physical Table
                        DbSchema::dropIfExists($record->table_name);
                        
                        // 2. Delete the Dynamic Model Record
                        $record->delete();

                        Notification::make()
                            ->title('Table Destroyed')
                            ->body("The table '{$record->table_name}' has been wiped from the database.")
                            ->danger()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->recordUrl(fn (DynamicModel $record) => \App\Filament\Pages\DataExplorer::getUrl(['tableId' => $record->id]))
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->poll('30s');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\RelationshipsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListDynamicModels::route('/'),
            'create' => Pages\CreateDynamicModel::route('/create'),
            'edit' => Pages\EditDynamicModel::route('/{record}/edit'),
        ];
    }
}

```

## File: app/Filament/Resources/DynamicModelResource/Pages/CreateDynamicModel.php
```php
<?php

namespace App\Filament\Resources\DynamicModelResource\Pages;

use App\Filament\Resources\DynamicModelResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Filament\Notifications\Notification;

class CreateDynamicModel extends CreateRecord
{
    protected static string $resource = DynamicModelResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['user_id'] = auth()->id();

        // Ensure table_name is set
        if (empty($data['table_name'])) {
            $data['table_name'] = $data['name'];
        }

        // Ensure display_name is set
        if (empty($data['display_name'])) {
            $data['display_name'] = \Illuminate\Support\Str::headline($data['name']);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        $model = $this->record;
        $tableName = $model->table_name;

        if (Schema::hasTable($tableName)) {
            Notification::make()
                ->warning()
                ->title('Table Exists')
                ->body("The table '{$tableName}' already exists. Only metadata was saved.")
                ->send();
            return;
        }

        try {
            Schema::create($tableName, function (Blueprint $table) use ($model) {
                $table->id();

                foreach ($model->fields as $field) {
                    $column = match ($field->type) {
                        'string' => $table->string($field->name),
                        'file', 'image' => $table->string($field->name)->nullable(),
                        'text' => $table->text($field->name),
                        'integer' => $table->integer($field->name),
                        'boolean' => $table->boolean($field->name),
                        'date' => $table->date($field->name),
                        'datetime' => $table->dateTime($field->name),
                        default => $table->string($field->name),
                    };

                    if (! $field->is_required) {
                        $column->nullable();
                    }
                    if ($field->is_unique) {
                        $column->unique();
                    }
                }

                $table->timestamps();
            });

            Notification::make()
                ->success()
                ->title('Table Created Successfully')
                ->body("Database table '{$tableName}' is now ready!")
                ->send();

        } catch (\Exception $e) {
            Notification::make()
                ->danger()
                ->title('Error Creating Table')
                ->body($e->getMessage())
                ->send();
        }
    }
}

```

## File: app/Filament/Resources/DynamicModelResource/Pages/EditDynamicModel.php
```php
<?php

namespace App\Filament\Resources\DynamicModelResource\Pages;

use App\Filament\Resources\DynamicModelResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Filament\Notifications\Notification;

class EditDynamicModel extends EditRecord
{
    protected static string $resource = DynamicModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->before(function ($record) {
                    if (Schema::hasTable($record->table_name)) {
                        Schema::dropIfExists($record->table_name);
                    }
                }),
        ];
    }

    protected function afterSave(): void
    {
        $model = $this->record;
        $tableName = $model->table_name;

        if (Schema::hasTable($tableName)) {
            $currentColumns = Schema::getColumnListing($tableName);
            $added = 0;

            Schema::table($tableName, function (Blueprint $table) use ($model, $currentColumns, &$added) {
                foreach ($model->fields as $field) {
                    if (! in_array($field->name, $currentColumns)) {
                        $column = match ($field->type) {
                            'string', 'file' => $table->string($field->name),
                            'text' => $table->text($field->name),
                            'integer' => $table->integer($field->name),
                            'boolean' => $table->boolean($field->name),
                            'date' => $table->date($field->name),
                            'datetime' => $table->dateTime($field->name),
                            default => $table->string($field->name),
                        };

                        // New columns on existing table should be nullable
                        $column->nullable();
                        $added++;
                    }
                }
            });

            if ($added > 0) {
                Notification::make()
                    ->success()
                    ->title('Schema Updated')
                    ->body("Added {$added} new column(s) to the database.")
                    ->send();
            }
        }
    }
}

```

## File: app/Filament/Resources/DynamicModelResource/Pages/ListDynamicModels.php
```php
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
            Actions\CreateAction::make(),
        ];
    }
}

```

## File: app/Filament/Resources/DynamicModelResource/RelationManagers/RelationshipsRelationManager.php
```php
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

```

## File: app/Filament/Resources/UserResource.php
```php
<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Schemas\Components\Section;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use BackedEnum;
use Illuminate\Support\Facades\Hash;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('User Info')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        Forms\Components\TextInput::make('password')
                            ->password()
                            ->dehydrateStateUsing(fn (?string $state) => $state ? Hash::make($state) : null)
                            ->dehydrated(fn (?string $state) => filled($state))
                            ->required(fn (string $operation) => $operation === 'create')
                            ->maxLength(255)
                            ->helperText('Leave blank to keep current password.'),

                        Forms\Components\Select::make('roles')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable(),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('roles.name')
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('M j, Y')
                    ->sortable(),
            ])
            ->filters([])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}

```

## File: app/Filament/Resources/UserResource/Pages/CreateUser.php
```php
<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

```

## File: app/Filament/Resources/UserResource/Pages/EditUser.php
```php
<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

```

## File: app/Filament/Resources/UserResource/Pages/ListUsers.php
```php
<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
        ];
    }
}

```

## File: app/Filament/Resources/Webhooks/Pages/ManageWebhooks.php
```php
<?php

namespace App\Filament\Resources\Webhooks\Pages;

use App\Filament\Resources\Webhooks\WebhookResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageWebhooks extends ManageRecords
{
    protected static string $resource = WebhookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

```

## File: app/Filament/Resources/Webhooks/WebhookResource.php
```php
<?php

namespace App\Filament\Resources\Webhooks;

use App\Filament\Resources\Webhooks\Pages\ManageWebhooks;
use App\Models\DynamicModel;
use App\Models\Webhook;
use BackedEnum;
use UnitEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Fieldset;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Forms;

class WebhookResource extends Resource
{
    protected static ?string $model = Webhook::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationLabel = 'Webhooks';
    protected static ?string $modelLabel = 'Webhook';
    protected static ?string $pluralModelLabel = 'Webhooks';
    protected static string|UnitEnum|null $navigationGroup = 'Developers';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Main Info Section
                Section::make('Webhook Details')
                    ->icon('heroicon-o-bolt')
                    ->schema([
                        Grid::make(2)->schema([
                            Forms\Components\Select::make('dynamic_model_id')
                                ->label('Target Table')
                                ->options(DynamicModel::pluck('display_name', 'id'))
                                ->required()
                                ->searchable()
                                ->placeholder('Select a table...'),

                            Forms\Components\TextInput::make('name')
                                ->label('Webhook Name')
                                ->placeholder('e.g., Notify Slack')
                                ->maxLength(255),
                        ]),

                        Forms\Components\TextInput::make('url')
                            ->label('Endpoint URL')
                            ->required()
                            ->url()
                            ->placeholder('https://your-server.com/webhook')
                            ->columnSpanFull(),
                    ]),

                // Events Section
                Section::make('Trigger Events')
                    ->icon('heroicon-o-sparkles')
                    ->description('Select which events should fire this webhook')
                    ->schema([
                        Forms\Components\CheckboxList::make('events')
                            ->label('')
                            ->options([
                                'created' => 'Record Created',
                                'updated' => 'Record Updated',
                                'deleted' => 'Record Deleted',
                            ])
                            ->default(['created', 'updated', 'deleted'])
                            ->columns(3)
                            ->required(),
                    ]),

                // Security Section
                Section::make('Security')
                    ->icon('heroicon-o-shield-check')
                    ->collapsed()
                    ->schema([
                        Forms\Components\TextInput::make('secret')
                            ->label('HMAC Secret')
                            ->password()
                            ->revealable()
                            ->placeholder('Optional signing key')
                            ->helperText('Payloads will be signed with X-Webhook-Signature header'),

                        Forms\Components\KeyValue::make('headers')
                            ->label('Custom Headers')
                            ->keyLabel('Header')
                            ->valueLabel('Value')
                            ->addActionLabel('Add Header'),
                    ]),

                // Status Toggle
                Forms\Components\Toggle::make('is_active')
                    ->label('Webhook Active')
                    ->default(true)
                    ->inline(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('dynamicModel.display_name')
                    ->label('Table')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Name')
                    ->searchable()
                    ->placeholder('—')
                    ->limit(20),

                Tables\Columns\TextColumn::make('url')
                    ->label('URL')
                    ->limit(30)
                    ->copyable()
                    ->tooltip(fn ($record) => $record->url),

                Tables\Columns\TextColumn::make('events')
                    ->label('Events')
                    ->badge()
                    ->formatStateUsing(fn ($state) => is_array($state) ? count($state) . ' events' : '—')
                    ->color('info'),

                Tables\Columns\IconColumn::make('has_secret')
                    ->label('Signed')
                    ->getStateUsing(fn ($record) => !empty($record->secret))
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-lock-open')
                    ->trueColor('success')
                    ->falseColor('gray'),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active')
                    ->onColor('success')
                    ->offColor('danger'),

                Tables\Columns\TextColumn::make('failure_count')
                    ->label('Failures')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state >= 10 => 'danger',
                        $state >= 5 => 'warning',
                        $state > 0 => 'gray',
                        default => 'success',
                    })
                    ->formatStateUsing(fn ($state) => $state > 0 ? $state : '✓'),

                Tables\Columns\TextColumn::make('last_triggered_at')
                    ->label('Last Trigger')
                    ->dateTime('M j, H:i')
                    ->placeholder('Never')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('dynamic_model_id')
                    ->label('Table')
                    ->options(DynamicModel::pluck('display_name', 'id')),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Status'),
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
            'index' => ManageWebhooks::route('/'),
        ];
    }
}

```

## File: app/Filament/Widgets/ApiErrorStats.php
```php
<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use App\Models\ApiAnalytics;

class ApiErrorStats extends ChartWidget
{
    protected ?string $heading = 'Failed Requests (Last 7 Days)';

    protected static ?int $sort = 11;

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        $data = ApiAnalytics::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('status_code', '>=', 400) // Filter only errors
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date');

        return [
            'datasets' => [
                [
                    'label' => 'Failed Requests (Errors)',
                    'data' => $data->values()->toArray(),
                    'borderColor' => '#ef4444', // Red color for errors
                    'backgroundColor' => 'rgba(239, 68, 68, 0.1)',
                    'fill' => true,
                ],
            ],
            'labels' => $data->keys()->toArray(),
        ];
    }
}

```

## File: app/Filament/Widgets/ApiTrafficChart.php
```php
<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ApiTrafficChart extends ChartWidget
{
    protected ?string $heading = 'API Traffic (Last 24 Hours)';

    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = '15s';

    protected function getType(): string
    {
        return 'line';
    }

    protected function getData(): array
    {
        // Fetch last 7 days of data
        $data = \App\Models\ApiAnalytics::query()
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date');

        return [
            'datasets' => [
                [
                    'label' => 'API Requests (Last 7 Days)',
                    'data' => $data->values()->toArray(),
                    'backgroundColor' => '#3b82f6',
                    'borderColor' => '#3b82f6',
                ],
            ],
            'labels' => $data->keys()->toArray(),
        ];
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => ['precision' => 0],
                ],
            ],
            'plugins' => [
                'legend' => ['display' => false],
            ],
        ];
    }
}

```

## File: app/Filament/Widgets/StatsOverviewWidget.php
```php
<?php

namespace App\Filament\Widgets;

use App\Models\DynamicModel;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class StatsOverviewWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalRequests = \App\Models\ApiAnalytics::count();
        $totalErrors = \App\Models\ApiAnalytics::where('status_code', '>=', 400)->count();
        $activeKeys = \App\Models\ApiKey::where('is_active', true)->count();

        return [
            \Filament\Widgets\StatsOverviewWidget\Stat::make('Total API Requests', $totalRequests)
                ->description('All time requests')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->chart([7, 2, 10, 3, 15, 4, 17])
                ->color('success'),

            \Filament\Widgets\StatsOverviewWidget\Stat::make('Failed Requests', $totalErrors)
                ->description('4xx & 5xx Errors')
                ->descriptionIcon('heroicon-m-exclamation-triangle')
                ->color('danger'),

            \Filament\Widgets\StatsOverviewWidget\Stat::make('Active API Keys', $activeKeys)
                ->description('Keys currently in use')
                ->color('primary'),
        ];
    }

    private function getDatabaseHealth(): string
    {
        try {
            DB::select('SELECT 1');
            return 'Healthy';
        } catch (\Exception $e) {
            return 'Error';
        }
    }
}

```

## File: app/Filament/Widgets/TopTablesWidget.php
```php
<?php

namespace App\Filament\Widgets;

use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class TopTablesWidget extends BaseWidget
{
    protected static ?int $sort = 12;

    protected int|string|array $columnSpan = 'full';

    protected function getTableQuery(): Builder
    {
        return \App\Models\ApiAnalytics::query()
            ->select('table_name', DB::raw('count(*) as total_requests'))
            ->whereNotNull('table_name')
            ->where('table_name', '!=', '-')
            ->groupBy('table_name')
            ->orderByDesc('total_requests')
            ->limit(5);
    }

    protected function getTableColumns(): array
    {
        return [
            Tables\Columns\TextColumn::make('table_name')
                ->label('Table Name')
                ->description('Database table name'),
            Tables\Columns\TextColumn::make('total_requests')
                ->label('Hits')
                ->sortable()
                ->badge()
                ->color('success'),
        ];
    }
}

```

## File: app/Filament/Widgets/UniverSheetWidget.php
```php
<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;
use App\Models\DynamicModel;
use App\Models\DynamicRecord;
use App\Models\ApiKey;
use Illuminate\Support\Facades\Log;

class UniverSheetWidget extends Widget
{
    protected string $view = 'filament.widgets.univer-sheet';
    protected int|string|array $columnSpan = 'full';

    public ?string $tableId = null;

    public function mount()
    {
        // Try to get tableId from query string if not set
        if (!$this->tableId) {
            $this->tableId = request()->query('tableId') ?? request()->query('tableid');
        }
    }

    protected function getViewData(): array
    {
        if (!$this->tableId) {
            return ['hasData' => false];
        }

        $dynamicModel = DynamicModel::with('fields')->find($this->tableId);

        if (!$dynamicModel) {
            return ['hasData' => false];
        }

        // 1. Fetch Schema (Fields)
        $schema = $dynamicModel->fields->map(function ($field) {
            return [
                'name' => $field->name,
                'type' => $field->type,
                'label' => $field->label ?? $field->name,
                'id' => $field->id,
            ];
        })->toArray();

        // 2. Fetch Records (Data)
        try {
            $modelClass = new DynamicRecord();
            $modelClass->setDynamicTable($dynamicModel->table_name);
            
            // Fetch ID + all fields defined in schema
            $records = $modelClass->limit(1000)->get()->toArray();
        } catch (\Exception $e) {
            Log::error("Univer Widget Data Error: " . $e->getMessage());
            $records = [];
        }

        // 3. API Context - Use a key owned by the current user, not any random active key
        $apiKey = ApiKey::where('is_active', true)
            ->where('user_id', auth()->id())
            ->first()?->key ?? '';

        return [
            'hasData' => true,
            'tableId' => $this->tableId,
            'tableName' => $dynamicModel->table_name,
            'schema' => $schema,
            'tableData' => $records,
            'saveUrl' => url('/api/v1/data/' . $dynamicModel->table_name),
            'csrfToken' => csrf_token(),
            'apiToken' => $apiKey,
        ];
    }
}

```

## File: app/Http/Controllers/Api/ApiKeyController.php
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiKeyController extends Controller
{
    /**
     * List all API keys (Personal Access Tokens) for the user.
     */
    public function index(Request $request): JsonResponse
    {
        $tokens = $request->user()->tokens()->orderBy('created_at', 'desc')->get();
        return response()->json($tokens);
    }

    /**
     * Generate a new API key.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'abilities' => 'nullable|array',
            'expires_at' => 'nullable|date|after:today',
        ]);

        $abilities = $request->input('abilities', ['*']);
        $expiresAt = $request->input('expires_at') ? new \DateTime($request->input('expires_at')) : null;

        $token = $request->user()->createToken(
            $request->name, 
            $abilities,
            $expiresAt
        );

        return response()->json([
            'message' => 'API Key generated successfully',
            'token' => $token->plainTextToken,
            'name' => $request->name
        ], 201);
    }

    /**
     * Revoke (Delete) an API key.
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $token = $request->user()->tokens()->where('id', $id)->first();

        if (!$token) {
            return response()->json(['message' => 'Token not found'], 404);
        }

        $token->delete();

        return response()->json(['message' => 'API Key revoked successfully']);
    }
}

```

## File: app/Http/Controllers/Api/AuthController.php
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if (!Auth::attempt($request->only('email', 'password'))) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are incorrect.'],
            ]);
        }

        $user = User::where('email', $request->email)->firstOrFail();
        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user' => $user,
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Successfully logged out',
        ]);
    }

    public function user(Request $request)
    {
        return response()->json($request->user());
    }

    public function forgotPassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $status = Password::sendResetLink(
            $request->only('email')
        );

        if ($status === Password::RESET_LINK_SENT) {
            return response()->json([
                'message' => 'Password reset link sent to your email.',
            ]);
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8|confirmed',
        ]);

        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json([
                'message' => 'Password has been reset successfully.',
            ]);
        }

        throw ValidationException::withMessages([
            'email' => [__($status)],
        ]);
    }

    /**
     * Redirect to OAuth provider.
     */
    public function redirectToProvider(string $provider)
    {
        // Validate provider name
        $validProviders = ['google', 'github'];
        if (!in_array($provider, $validProviders)) {
            return response()->json([
                'message' => 'Invalid provider',
                'valid_providers' => $validProviders,
            ], 400);
        }

        // Check if provider is enabled in settings
        $isActive = \App\Models\Setting::where('key', "{$provider}_active")
            ->where('group', 'authentication')
            ->value('value');

        if (!$isActive || $isActive === '0') {
            return response()->json([
                'message' => ucfirst($provider) . ' login is not enabled',
                'error' => 'provider_disabled',
            ], 403);
        }

        // Redirect to provider
        return \Laravel\Socialite\Facades\Socialite::driver($provider)->redirect();
    }

    /**
     * Handle OAuth provider callback.
     */
    public function handleProviderCallback(string $provider)
    {
        // Validate provider
        $validProviders = ['google', 'github'];
        if (!in_array($provider, $validProviders)) {
            return response()->json(['message' => 'Invalid provider'], 400);
        }

        // Check if provider is enabled
        $isActive = \App\Models\Setting::where('key', "{$provider}_active")
            ->where('group', 'authentication')
            ->value('value');

        if (!$isActive || $isActive === '0') {
            return response()->json([
                'message' => ucfirst($provider) . ' login is not enabled',
            ], 403);
        }

        try {
            $socialUser = \Laravel\Socialite\Facades\Socialite::driver($provider)->user();

            // Find or create user
            $user = User::where('email', $socialUser->getEmail())->first();

            if (!$user) {
                $user = User::create([
                    'name' => $socialUser->getName() ?? $socialUser->getNickname() ?? 'User',
                    'email' => $socialUser->getEmail(),
                    'password' => Hash::make(\Illuminate\Support\Str::random(24)),
                    'email_verified_at' => now(),
                ]);
            }

            // Update provider info
            $user->update([
                "{$provider}_id" => $socialUser->getId(),
            ]);

            // Create token
            $token = $user->createToken('social_auth_token')->plainTextToken;

            // Return JSON or redirect based on Accept header
            if (request()->wantsJson()) {
                return response()->json([
                    'user' => $user,
                    'token' => $token,
                ]);
            }

            // Redirect to frontend with token in URL fragment (not sent to server in referrer headers)
            return redirect()->to(
                config('app.frontend_url', '/') . '#token=' . $token
            );

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Authentication failed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available OAuth providers.
     */
    public function getProviders()
    {
        $google = \App\Models\Setting::where('key', 'google_active')
            ->where('group', 'authentication')
            ->value('value');

        $github = \App\Models\Setting::where('key', 'github_active')
            ->where('group', 'authentication')
            ->value('value');

        return response()->json([
            'providers' => [
                'google' => (bool) $google && $google !== '0',
                'github' => (bool) $github && $github !== '0',
            ],
        ]);
    }
}

```

## File: app/Http/Controllers/Api/CodeGeneratorController.php
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CodeGeneratorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CodeGeneratorController extends Controller
{
    protected CodeGeneratorService $generator;

    public function __construct(CodeGeneratorService $generator)
    {
        $this->generator = $generator;
    }

    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'model_id' => 'required|exists:dynamic_models,id',
            'framework' => 'required|string|in:react,vue,nextjs,nuxt',
            'operation' => 'required|string|in:all,list,create,hook',
            'style' => 'string|in:tailwind,bootstrap',
            'typescript' => 'boolean'
        ]);

        $files = $this->generator->generate(
            $request->model_id,
            $request->framework,
            $request->operation,
            $request->get('style', 'tailwind'),
            $request->boolean('typescript', true)
        );

        return response()->json([
            'files' => $files
        ]);
    }
}

```

## File: app/Http/Controllers/Api/CoreDataController.php
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\ModelActivity;
use App\Models\DynamicModel;
use App\Models\Webhook;
use App\Services\UrlValidator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use App\Models\DynamicRecord;
use App\Http\Traits\TurboCache;
use Spatie\QueryBuilder\QueryBuilder;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\AllowedSort;

/**
 * Core Data Controller - Unified API Engine for Digibase
 * 
 * Integrations:
 * - 🛡️ Iron Dome: API key validation with scopes
 * - 🩺 Schema Doctor: Dynamic validation rules
 * - ⚡ Turbo Cache: Automated caching/invalidation
 * - 📡 Live Wire: Real-time event broadcasting via DynamicRecordObserver
 * - 🔒 Transaction Wrapper: Atomic operations for data integrity
 * - 🎯 Type-Safe Casting: Strict type enforcement before database writes
 */
class CoreDataController extends Controller
{
    use TurboCache;

    /**
     * Get the dynamic model by name (slug) OR table_name.
     */
    protected function getModel(string $tableName): ?DynamicModel
    {
        return DynamicModel::where(function($q) use ($tableName) {
                $q->where('name', $tableName)->orWhere('table_name', $tableName);
            })
            ->where('is_active', true)
            ->where('generate_api', true)
            ->with('fields')
            ->first();
    }

    /**
     * Extract the ownership field name from an RLS rule like "auth.id == user_id".
     */
    protected function extractOwnershipField(?string $rule): ?string
    {
        if (empty($rule)) {
            return null;
        }

        $rule = trim(strtolower($rule));

        if (preg_match('/^auth\.id\s*==\s*(\w+)$/', $rule, $matches)) {
            $fieldName = $matches[1];
            if ($fieldName !== 'null') {
                return $fieldName;
            }
        }

        return null;
    }

    /**
     * Validate an RLS rule expression safely.
     */
    protected function validateRule(?string $rule, ?object $record = null): bool
    {
        // Default to true (allow) if no specific RLS rule is defined,
        // relying on the primary API Key / Auth middleware for security.
        if (empty($rule)) return true;
        
        $rule = trim(strtolower($rule));
        if ($rule === 'true') return true;
        if ($rule === 'false') return false;

        // Check both Sanctum auth AND API Key user
        $authId = auth('sanctum')->id() ?? request()->attributes->get('api_key_user')?->id;

        if ($rule === 'auth.id != null' || $rule === 'auth.id !== null') {
            return $authId !== null;
        }
        if ($rule === 'auth.id == null' || $rule === 'auth.id === null') {
            return $authId === null;
        }

        if (preg_match('/^auth\.id\s*(==|!=|===|!==)\s*(\w+)$/', $rule, $matches)) {
            $op = $matches[1];
            $field = $matches[2];
            
            if (!$record) return $authId !== null;

            $val = is_object($record) ? ($record->$field ?? null) : ($record[$field] ?? null);
            
            return match($op) {
                '==' => (string)$authId === (string)$val,
                '===' => (string)$authId === (string)$val,
                '!=' => (string)$authId !== (string)$val,
                '!==' => (string)$authId !== (string)$val,
                default => false,
            };
        }

        if (str_contains($rule, '&&')) {
            $parts = array_map('trim', explode('&&', $rule));
            foreach ($parts as $part) {
                if (!$this->validateRule($part, $record)) return false;
            }
            return true;
        }

        if (str_contains($rule, '||')) {
            $parts = array_map('trim', explode('||', $rule));
            foreach ($parts as $part) {
                if ($this->validateRule($part, $record)) return true;
            }
            return false;
        }

        return false;
    }

    /**
     * Trigger webhooks for a dynamic model event.
     */
    protected function triggerWebhooks(int $modelId, string $event, array $data): void
    {
        $webhooks = Webhook::where('dynamic_model_id', $modelId)
            ->where('is_active', true)
            ->get();

        foreach ($webhooks as $webhook) {
            if (!$webhook->shouldTrigger($event)) {
                continue;
            }

            $urlCheck = UrlValidator::validateWebhookUrl($webhook->url);
            if (!$urlCheck['valid']) {
                Log::warning("Webhook SSRF blocked: {$webhook->url}", [
                    'reason' => $urlCheck['reason'],
                    'webhook_id' => $webhook->id,
                ]);
                continue;
            }

            $recordData = $data['record'] ?? $data;
            if (is_array($recordData)) {
                $sensitiveKeys = ['password', 'token', 'secret', 'key', 'auth', 'credential', 'remember_token'];
                $recordData = array_filter($recordData, function ($v, $k) use ($sensitiveKeys) {
                    $lowerKey = strtolower($k);
                    if (in_array($lowerKey, $sensitiveKeys)) return false;
                    foreach ($sensitiveKeys as $sensitive) {
                        if (str_contains($lowerKey, $sensitive)) return false;
                    }
                    return true;
                }, ARRAY_FILTER_USE_BOTH);
            }

            $payload = [
                'event' => $event,
                'table' => $data['table'] ?? null,
                'data' => $recordData,
                'timestamp' => now()->toIso8601String(),
            ];

            $headers = [
                'Content-Type' => 'application/json',
                'User-Agent' => 'Digibase-Webhook/1.0',
                'X-Webhook-Event' => $event,
            ];

            $signature = $webhook->generateSignature($payload);
            if ($signature) {
                $headers['X-Webhook-Signature'] = 'sha256=' . $signature;
            }

            if (!empty($webhook->headers)) {
                $headers = array_merge($headers, $webhook->headers);
            }

            try {
                Http::timeout(10)
                    ->withHeaders($headers)
                    ->async()
                    ->post($webhook->url, $payload)
                    ->then(
                        function ($response) use ($webhook) {
                            if ($response->successful()) {
                                $webhook->recordSuccess();
                            } else {
                                $webhook->recordFailure();
                                Log::warning("Webhook failed: {$webhook->url}", [
                                    'status' => $response->status(),
                                    'body' => $response->body(),
                                ]);
                            }
                        },
                        function ($exception) use ($webhook) {
                            $webhook->recordFailure();
                            Log::error("Webhook error: {$webhook->url}", [
                                'error' => $exception->getMessage(),
                            ]);
                        }
                    );
            } catch (\Exception $e) {
                Log::error("Webhook dispatch error: {$webhook->url}", [
                    'error' => $e->getMessage(),
                ]);
                $webhook->recordFailure();
            }
        }
    }

    /**
     * 🩺 SCHEMA DOCTOR: Build validation rules from dynamic fields.
     */
    protected function buildValidationRules(DynamicModel $model, bool $isUpdate = false, ?int $recordId = null): array
    {
        $rules = [];

        foreach ($model->fields as $field) {
            $fieldRules = [];

            if ($field->is_required && !$isUpdate) {
                $fieldRules[] = 'required';
            } else {
                $fieldRules[] = 'nullable';
            }

            switch ($field->type) {
                case 'string':
                case 'slug':
                    $fieldRules[] = 'string';
                    $fieldRules[] = 'max:255';
                    break;
                case 'text':
                case 'richtext':
                case 'markdown':
                    $fieldRules[] = 'string';
                    break;
                case 'email':
                    $fieldRules[] = 'email';
                    break;
                case 'url':
                    $fieldRules[] = 'url';
                    break;
                case 'integer':
                case 'bigint':
                    $fieldRules[] = 'integer';
                    break;
                case 'float':
                case 'decimal':
                case 'money':
                    $fieldRules[] = 'numeric';
                    break;
                case 'boolean':
                case 'checkbox':
                    $fieldRules[] = 'boolean';
                    break;
                case 'date':
                    $fieldRules[] = 'date';
                    break;
                case 'datetime':
                case 'timestamp':
                    $fieldRules[] = 'date';
                    break;
                case 'time':
                    $fieldRules[] = 'date_format:H:i:s';
                    break;
                case 'json':
                case 'array':
                    $fieldRules[] = 'array';
                    break;
                case 'enum':
                case 'select':
                    if (!empty($field->options)) {
                        $fieldRules[] = 'in:' . implode(',', $field->options);
                    }
                    break;
                case 'uuid':
                    $fieldRules[] = 'uuid';
                    break;
            }

            if ($field->is_unique) {
                $uniqueRule = 'unique:' . $model->table_name . ',' . $field->name;
                if ($isUpdate && $recordId) {
                    $uniqueRule .= ',' . $recordId;
                }
                $fieldRules[] = $uniqueRule;
            }

            if (!empty($field->validation_rules) && is_array($field->validation_rules)) {
                $fieldRules = array_merge($fieldRules, $field->validation_rules);
            }

            $rules[$field->name] = $fieldRules;
        }

        return $rules;
    }

    /**
     * 🎯 TYPE-SAFE CASTING: Cast value to correct PHP type based on DynamicField schema.
     * Prevents SQLite type-affinity issues.
     */
    protected function castValue(mixed $value, \App\Models\DynamicField $field): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($field->type) {
            'integer', 'bigint' => (int) $value,
            'float', 'decimal', 'money' => (float) $value,
            'boolean', 'checkbox' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
            'json', 'array' => is_array($value) ? json_encode($value) : $value,
            'date' => is_string($value) ? date('Y-m-d', strtotime($value)) : $value,
            'datetime', 'timestamp' => is_string($value) ? date('Y-m-d H:i:s', strtotime($value)) : $value,
            'time' => is_string($value) ? date('H:i:s', strtotime($value)) : $value,
            default => $value,
        };
    }

    /**
     * 🔒 TRANSACTION WRAPPER: Execute mutation in atomic transaction.
     *
     * Uses DB::transaction() with 5 retry attempts to handle SQLite
     * "database is locked" errors under concurrent write load.
     * Each retry is automatically attempted by Laravel's transaction handler.
     */
    protected function executeInTransaction(callable $callback): mixed
    {
        return DB::transaction(function () use ($callback) {
            return $callback();
        }, 5);
    }


    /**
     * 🔗 RELATION RESOLVER: Register dynamic relations on DynamicRecord and return
     * the namespaced keys that were registered + the allowed include names.
     *
     * Returns ['includeMap' => ['books' => 'test_authors__books', ...], 'eagerLoad' => [...]]
     */
    protected function resolveIncludes(DynamicModel $model, string $tableName): array
    {
        $includeMap = [];
        $allowedIncludes = [];

        foreach ($model->relationships()->with('relatedModel')->get() as $relDef) {
            if (!$relDef->relatedModel) continue;

            $relationName = $relDef->name;
            $uniqueRelKey = $tableName . '__' . $relationName;
            $includeMap[$relationName] = $uniqueRelKey;
            $allowedIncludes[] = $relationName;

            DynamicRecord::resolveRelationUsing($uniqueRelKey, function ($instance) use ($relDef) {
                $relatedTable = $relDef->relatedModel->table_name;
                $foreignKey = $relDef->foreign_key;
                $localKey = $relDef->local_key ?? 'id';
                $qualify = fn($col, $table) => str_contains($col, '.') ? $col : "$table.$col";

                if ($relDef->type === 'hasMany') {
                    $foreignKey = $foreignKey ?: Str::singular($instance->getTable()) . '_id';
                    $foreignKey = $qualify($foreignKey, $relatedTable);
                    $relation = $instance->hasMany(DynamicRecord::class, $foreignKey, $localKey);
                    $relation->getRelated()->setTable($relatedTable);
                    $relation->getQuery()->from($relatedTable);
                    return $relation;
                }

                if ($relDef->type === 'belongsTo') {
                    $foreignKey = $foreignKey ?: Str::singular($relDef->relatedModel->table_name) . '_id';
                    $relation = $instance->belongsTo(DynamicRecord::class, $foreignKey, $localKey, $relDef->name);
                    $relation->getRelated()->setTable($relatedTable);
                    $relation->getQuery()->from($relatedTable);
                    return $relation;
                }

                if ($relDef->type === 'hasOne') {
                    $foreignKey = $foreignKey ?: Str::singular($instance->getTable()) . '_id';
                    $foreignKey = $qualify($foreignKey, $relatedTable);
                    $relation = $instance->hasOne(DynamicRecord::class, $foreignKey, $localKey);
                    $relation->getRelated()->setTable($relatedTable);
                    $relation->getQuery()->from($relatedTable);
                    return $relation;
                }

                return null;
            });
        }

        return compact('includeMap', 'allowedIncludes');
    }



    /**
     * List all records from a dynamic model.
     * ⚡ TURBO CACHE: Cached with automatic invalidation
     * 🏎️ Powered by Spatie QueryBuilder for standardized filtering/sorting
     */
    public function index(Request $request, string $tableName): JsonResponse
    {
        $model = $this->getModel($tableName);

        if (!$model) {
            return response()->json(['message' => 'Model not found'], 404);
        }

        if (!$this->validateRule($model->list_rule)) {
            return response()->json(['message' => 'Access denied by security rules'], 403);
        }

        $tableName = $model->table_name;

        if (!Schema::hasTable($tableName)) {
            return response()->json(['message' => 'Table does not exist'], 404);
        }

        return $this->cached($tableName, $request, function () use ($request, $model, $tableName) {
            return $this->executeIndexQuery($request, $model, $tableName);
        });
    }

    /**
     * Execute the actual index query (extracted for caching).
     * 🏎️ Uses Spatie QueryBuilder for clean filter/sort/include handling.
     */
    protected function executeIndexQuery(Request $request, DynamicModel $model, string $tableName): JsonResponse
    {
        $baseQuery = (new DynamicRecord)->setDynamicTable($tableName)->newQuery();

        if ($model->has_soft_deletes) {
            $baseQuery->whereNull('deleted_at');
        }

        $ownershipField = $this->extractOwnershipField($model->list_rule);
        if ($ownershipField) {
            $baseQuery->where($ownershipField, auth('sanctum')->id());
        }

        // Build allowed fields from dynamic model schema
        $allFields = $model->fields->pluck('name')->toArray();
        $systemFields = ['id', 'created_at', 'updated_at'];

        // Allowed filters: filterable fields get exact match, searchable fields get partial
        $allowedFilters = [];
        foreach ($model->fields as $field) {
            if ($field->is_filterable) {
                $allowedFilters[] = AllowedFilter::exact($field->name);
            }
            if ($field->is_searchable) {
                $allowedFilters[] = AllowedFilter::partial($field->name);
            }
        }

        // Allowed sorts: sortable fields + system fields
        $sortableFields = $model->fields->where('is_sortable', true)->pluck('name')->toArray();
        $allowedSorts = array_merge($sortableFields, $systemFields);

        // Register dynamic relations and get allowed includes
        $resolved = $this->resolveIncludes($model, $tableName);

        // 🔍 Global search support (backward-compatible with ?search= param)
        if ($request->has('search') && $request->search) {
            $searchTerm = $request->search;
            $searchableFields = $model->fields->where('is_searchable', true)->pluck('name')->toArray();
            if (!empty($searchableFields)) {
                $baseQuery->where(function ($q) use ($searchableFields, $searchTerm) {
                    foreach ($searchableFields as $f) {
                        $q->orWhere($f, 'LIKE', $searchTerm . '%');
                    }
                });
            }
        }

        // Map user-facing include names to internal namespaced keys
        $requestedIncludes = $request->has('include')
            ? array_map('trim', explode(',', $request->get('include')))
            : [];
        $eagerLoad = [];
        foreach ($requestedIncludes as $inc) {
            if (isset($resolved['includeMap'][$inc])) {
                $eagerLoad[] = $resolved['includeMap'][$inc];
            }
        }
        if (!empty($eagerLoad)) {
            $baseQuery->with($eagerLoad);
        }

        // Build QueryBuilder on top of the prepared base query
        $perPage = min($request->get('per_page', 15), 100);

        // NOTE: We do NOT use ->with('media') here because polymorphic loading 
        // on DynamicRecord with varying table names can be unreliable in eager loading keys.
        // We perform a manual eager load below.

        $data = QueryBuilder::for($baseQuery)
            ->allowedFilters($allowedFilters)
            ->allowedSorts($allowedSorts)
            ->defaultSort('-id')
            ->paginate($perPage)
            ->appends($request->query());

        // Manual Eager Load Media
        $ids = $data->getCollection()->pluck('id')->toArray();
        $mediaClass = config('media-library.media_model', \Spatie\MediaLibrary\MediaCollections\Models\Media::class);
        $allMedia = collect();
        
        if (!empty($ids) && class_exists($mediaClass)) {
            $allMedia = $mediaClass::where('model_type', $tableName)
                ->whereIn('model_id', $ids)
                ->get()
                ->groupBy('model_id');
        }

        // Post-process: hide fields
        $hiddenFields = $model->fields->where('is_hidden', true)->pluck('name')->toArray();
        // Use map to preserve all fields, even if types are duplicate
        $fileFields = $model->fields->whereIn('type', ['image', 'file'])->map(fn($f) => ['name' => $f->name, 'type' => $f->type]);

        $results = $data->getCollection()->map(function ($item) use ($hiddenFields, $fileFields, $allMedia) {
            if (method_exists($item, 'makeHidden')) {
                $item->makeHidden($hiddenFields);
            }
            
            $attributes = $item->toArray();
            
            // Get media for this items
            $mediaItems = $allMedia->get($item->id, collect())->sortByDesc('created_at')->values();

            // Inject media URLs into image/file fields
            if ($fileFields->isNotEmpty() && $mediaItems->isNotEmpty()) {
                
                // Map over the fields logic
                foreach ($fileFields as $fieldDef) {
                    $fieldName = Str::snake($fieldDef['name']);
                    $type = $fieldDef['type'];
                    
                    // Simple heuristic: If field is null, grab the latest media item
                    if (array_key_exists($fieldName, $attributes) && empty($attributes[$fieldName])) {
                        
                        $targetCollection = $type === 'image' ? 'images' : 'files';
                        
                        // Try to find in target collection first
                        $media = $mediaItems->firstWhere('collection_name', $targetCollection);
                        
                        // Fallback to 'files' collection if looking for image
                        if (!$media && $type === 'image') {
                            $media = $mediaItems->firstWhere('collection_name', 'files');
                        }
                        
                         // Fallback to 'images' collection if looking for file
                         if (!$media && $type === 'file') {
                            $media = $mediaItems->firstWhere('collection_name', 'images');
                        }
                        
                        if ($media) {
                            $attributes[$fieldName] = $media->getUrl();
                        }
                    }
                }
                
                // Also append full media object for completeness
                $attributes['media'] = $mediaItems->map(fn($m) => [
                    'id' => $m->id,
                    'url' => $m->getUrl(),
                    'name' => $m->file_name,
                    'mime_type' => $m->mime_type,
                    'collection' => $m->collection_name
                ]);
            }



            return $attributes;
        });

        return response()->json([
            'data' => $results,
            'meta' => [
                'current_page' => $data->currentPage(),
                'last_page' => $data->lastPage(),
                'per_page' => $data->perPage(),
                'total' => $data->total(),
            ],
        ]);
    }

    /**
     * Get a single record from a dynamic model.
     */
    public function show(Request $request, string $tableName, int $id): JsonResponse
    {
        $model = $this->getModel($tableName);

        if (!$model) {
            return response()->json(['message' => 'Model not found'], 404);
        }

        $tableName = $model->table_name;

        if (!Schema::hasTable($tableName)) {
            return response()->json(['message' => 'Table does not exist'], 404);
        }

        $query = (new DynamicRecord)->setDynamicTable($tableName)->newQuery();

        if ($model->has_soft_deletes) {
            $query->whereNull('deleted_at');
        }

        // Resolve includes using shared helper
        $resolved = $this->resolveIncludes($model, $tableName);
        $requestedIncludes = $request->has('include')
            ? array_map('trim', explode(',', $request->get('include')))
            : [];
        foreach ($requestedIncludes as $inc) {
            if (isset($resolved['includeMap'][$inc])) {
                $query->with($resolved['includeMap'][$inc]);
            }
        }

        $record = $query->find($id);

        if (!$record) {
            return response()->json(['message' => 'Record not found'], 404);
        }

        if (!$this->validateRule($model->view_rule, $record)) {
            return response()->json(['message' => 'Access denied by security rules'], 403);
        }
        
        // Eager load media explicitly for this record
        // Using manual load to bypass potential polymorphic issues
        $mediaClass = config('media-library.media_model', \Spatie\MediaLibrary\MediaCollections\Models\Media::class);
        $mediaCollection = collect();
        if (class_exists($mediaClass)) {
            $mediaCollection = $mediaClass::where('model_type', $tableName)
                ->where('model_id', $id)
                ->get();
        }

        $hiddenFields = $model->fields->where('is_hidden', true)->pluck('name')->toArray();
        $record->makeHidden($hiddenFields);

        $attributes = $record->toArray();
        $fileFields = $model->fields->whereIn('type', ['image', 'file'])->map(fn($f) => ['name' => $f->name, 'type' => $f->type]);
        $mediaItems = $mediaCollection->sortByDesc('created_at')->values();

        // Inject media URLs into image/file fields
        if ($fileFields->isNotEmpty() && $mediaItems->isNotEmpty()) {
            foreach ($fileFields as $fieldDef) {
                $fieldName = Str::snake($fieldDef['name']);
                $type = $fieldDef['type'];
                
                if (array_key_exists($fieldName, $attributes) && empty($attributes[$fieldName])) {
                    $targetCollection = $type === 'image' ? 'images' : 'files';
                    $media = $mediaItems->firstWhere('collection_name', $targetCollection);

                    if (!$media && $type === 'image') {
                        $media = $mediaItems->firstWhere('collection_name', 'files');
                    }
                    if (!$media && $type === 'file') {
                        $media = $mediaItems->firstWhere('collection_name', 'images');
                    }
                    
                    if ($media) {
                        $attributes[$fieldName] = $media->getUrl();
                    }
                }
            }
            
            $attributes['media'] = $mediaItems->map(fn($m) => [
                'id' => $m->id,
                'url' => $m->getUrl(),
                'name' => $m->file_name,
                'mime_type' => $m->mime_type,
                'collection' => $m->collection_name
            ]);
        }



        return response()->json(['data' => $attributes]);
    }

    /**
     * Helper to normalize snake_case inputs to real field names.
     */
    protected function normalizeInput(Request $request, DynamicModel $model): void
    {
        $inputUpdates = [];
        $fileUpdates = [];

        foreach ($model->fields as $field) {
            $snakeName = Str::snake($field->name);
            
            if ($snakeName === $field->name) continue;

            // Handle File Fields strictly
            if (in_array($field->type, ['file', 'image'])) {
                if ($request->hasFile($snakeName) && !$request->hasFile($field->name)) {
                     $file = $request->file($snakeName);
                     $fileUpdates[$field->name] = $file;
                     // ALSO merge into input to ensure it bypasses any convertedFiles cache
                     $inputUpdates[$field->name] = $file;
                }
                continue; 
            }

            // Handle Normal Data Fields
            if ($request->has($snakeName) && !$request->has($field->name)) {
                $inputUpdates[$field->name] = $request->input($snakeName);
            }
        }

        if (!empty($inputUpdates)) {
            $request->merge($inputUpdates);
        }
        
        if (!empty($fileUpdates)) {
             foreach ($fileUpdates as $key => $file) {
                 $request->files->set($key, $file);
             }
        }
    }

    /**
     * Create a new record in a dynamic model.
     * 🔒 TRANSACTION WRAPPER: Atomic operation
     * 🎯 TYPE-SAFE CASTING: Strict type enforcement
     * 📁 FILE UPLOAD SUPPORT: Spatie Media Library integration
     */
    public function store(Request $request, string $tableName): JsonResponse
    {
        $model = $this->getModel($tableName);

        if (!$model) {
            return response()->json(['message' => 'Model not found'], 404);
        }

        if (!$this->validateRule($model->create_rule)) {
            return response()->json(['message' => 'Access denied by security rules'], 403);
        }
        
        // Normalize Request Inputs (snake_case -> Real Name)
        $this->normalizeInput($request, $model);
        
        $tableName = $model->table_name;

        if (!Schema::hasTable($tableName)) {
            return response()->json(['message' => 'Table does not exist'], 404);
        }

        $rules = $this->buildValidationRules($model);
        
        Log::info('DEBUG STORE', [
            'hasFile_Image' => $request->hasFile('Image'),
            'file_Image' => $request->file('Image'), // Might not serialize well
            'all_Image_set' => isset($request->all()['Image']),
            'all_keys' => array_keys($request->all()),
            'files_keys' => array_keys($request->allFiles()),
        ]);

        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            // Define file fields outside closure so they are available for response construction
            $allFileFields = $model->fields->whereIn('type', ['image', 'file']);

            $record = $this->executeInTransaction(function () use ($request, $model, $tableName, $allFileFields) {
                $data = [];
                
                foreach ($model->fields as $field) {
                    // Skip file/image fields - handle them separately after save
                    if (in_array($field->type, ['file', 'image'])) {
                        continue;
                    }
                    
                    if ($request->has($field->name)) {
                        $data[$field->name] = $this->castValue($request->input($field->name), $field);
                    } elseif ($field->default_value !== null) {
                        $data[$field->name] = $field->default_value;
                    }
                }

                if ($model->has_timestamps) {
                    $data['created_at'] = now();
                    $data['updated_at'] = now();
                }

                $record = new DynamicRecord();
                $record->setDynamicTable($tableName);
                $record->timestamps = false;
                $record->fill($data);
                $record->save();
                
                // 📸 Handle File Uploads using Spatie Media Library
                foreach ($allFileFields as $field) {
                    $file = $request->file($field->name);
                    
                    // Fallback to input bag if file bag is empty (due to normalization cache issues)
                    if (!$file) {
                        $inputVal = $request->input($field->name);
                        if ($inputVal instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                            $file = $inputVal;
                        }
                    }

                    if ($file) {
                        $record->addMedia($file)
                               ->toMediaCollection($field->type === 'image' ? 'images' : 'files', 'digibase_storage');
                    }
                }
                
                return $record;
            });

            event(new ModelActivity('created', $model->name, $record, $request->user()));

            $this->triggerWebhooks($model->id, 'created', [
                'table' => $tableName,
                'record' => $record->toArray(),
            ]);

            // Include media URLs in response
            $responseData = $record->toArray();
            if (method_exists($record, 'getMedia')) {
                // Reload media to get the newly uploaded one
                $record->load('media');
                
                $responseData['media'] = $record->media->map(function($m) {
                    return [
                        'id' => $m->id,
                        'url' => $m->getUrl(),
                        'name' => $m->file_name,
                        'mime_type' => $m->mime_type,
                        'collection' => $m->collection_name
                    ];
                });
                
                // Also inject into fields (heuristic)
                foreach ($allFileFields as $field) {
                     $fieldName = Str::snake($field->name);
                     if (empty($responseData[$fieldName])) {
                         // Very basic mapping for fresh upload
                         // We look for the media item with the same file name or just use the latest?
                         // Using latest is safest simple heuristic for single file upload per request
                         $m = $record->media->sortByDesc('created_at')->first(); 
                         // Check strictly if mapped? No, Spatie doesn't map to column easily without custom properties.
                         // But we just uploaded it.
                         if ($m) $responseData[$fieldName] = $m->getUrl();
                     }
                }
            }
            
            // Response is already clean due to DynamicRecord::toArray() override

            return response()->json(['data' => $responseData], 201);
        } catch (\Exception $e) {
            Log::error('Record creation failed', [
                'table' => $tableName,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'message' => 'Failed to create record',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Update a record in a dynamic model.
     * 🔒 TRANSACTION WRAPPER: Atomic operation
     * 🎯 TYPE-SAFE CASTING: Strict type enforcement
     * 📁 FILE UPLOAD SUPPORT: Spatie Media Library integration
     */
    public function update(Request $request, string $tableName, int $id): JsonResponse
    {
        $model = $this->getModel($tableName);

        if (!$model) {
            return response()->json(['message' => 'Model not found'], 404);
        }
        
        $tableName = $model->table_name;

        if (!Schema::hasTable($tableName)) {
            return response()->json(['message' => 'Table does not exist'], 404);
        }

        $record = DB::table($tableName)->where('id', $id)->first();

        if (!$record) {
            return response()->json(['message' => 'Record not found'], 404);
        }

        if (!$this->validateRule($model->update_rule, $record)) {
            return response()->json(['message' => 'Access denied by security rules'], 403);
        }

        // Normalize Request Inputs (snake_case -> Real Name)
        $this->normalizeInput($request, $model);

        $rules = $this->buildValidationRules($model, true, $id);
        $validator = Validator::make($request->all(), $rules);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        try {
            $updatedRecord = $this->executeInTransaction(function () use ($request, $model, $tableName, $id) {
                $data = [];
                $fileFields = [];
                
                foreach ($model->fields as $field) {
                    // Skip file/image fields - handle them separately
                    if (in_array($field->type, ['file', 'image'])) {
                        if ($request->hasFile($field->name)) {
                            $fileFields[] = $field;
                        }
                        continue;
                    }
                    
                    if ($request->has($field->name)) {
                        $data[$field->name] = $this->castValue($request->input($field->name), $field);
                    }
                }

                if ($model->has_timestamps) {
                    $data['updated_at'] = now();
                }

                $recordInstance = new DynamicRecord();
                $recordInstance->setDynamicTable($tableName);
                
                $pdoRecord = $recordInstance->findOrFail($id);
                $pdoRecord->setDynamicTable($tableName);
                $pdoRecord->timestamps = false;
                
                if (!empty($data)) {
                    $pdoRecord->update($data);
                }

                // 📸 Handle File Uploads using Spatie Media Library
                $fileFields = $model->fields->whereIn('type', ['image', 'file']);
                foreach ($fileFields as $field) {
                    $file = $request->file($field->name);
                    
                    // Fallback to input bag if file bag is empty
                    if (!$file) {
                        $inputVal = $request->input($field->name);
                        if ($inputVal instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                            $file = $inputVal;
                        }
                    }

                    if ($file) {
                        // Clear existing media if replacing
                        if ($request->input('replace_' . $field->name, false)) {
                            $pdoRecord->clearMediaCollection($field->type === 'image' ? 'images' : 'files');
                        }
                        
                        $pdoRecord->addMedia($file)
                                  ->toMediaCollection($field->type === 'image' ? 'images' : 'files', 'digibase_storage');
                    }
                }

                return $pdoRecord;
            });

            event(new ModelActivity('updated', $model->name, $updatedRecord, $request->user()));

            $this->triggerWebhooks($model->id, 'updated', [
                'table' => $tableName,
                'record' => $updatedRecord->toArray(),
            ]);

            // Include media URLs in response
            $responseData = $updatedRecord->toArray();
            if (method_exists($updatedRecord, 'getMedia')) {
                $responseData['media'] = [
                    'files' => $updatedRecord->getMedia('files')->map(fn($media) => [
                        'id' => $media->id,
                        'name' => $media->name,
                        'file_name' => $media->file_name,
                        'mime_type' => $media->mime_type,
                        'size' => $media->size,
                        'url' => $media->getUrl(),
                    ]),
                    'images' => $updatedRecord->getMedia('images')->map(fn($media) => [
                        'id' => $media->id,
                        'name' => $media->name,
                        'file_name' => $media->file_name,
                        'mime_type' => $media->mime_type,
                        'size' => $media->size,
                        'url' => $media->getUrl(),
                        'thumb_url' => $media->hasGeneratedConversion('thumb') ? $media->getUrl('thumb') : null,
                        'preview_url' => $media->hasGeneratedConversion('preview') ? $media->getUrl('preview') : null,
                    ]),
                ];
            }

            return response()->json(['data' => $responseData]);
        } catch (\Exception $e) {
            Log::error('Record update failed', [
                'table' => $tableName,
                'id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return response()->json([
                'message' => 'Failed to update record',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Delete a record from a dynamic model.
     * 🔒 TRANSACTION WRAPPER: Atomic operation
     */
    public function destroy(Request $request, string $tableName, int $id): JsonResponse
    {
        $model = $this->getModel($tableName);

        if (!$model) {
            return response()->json(['message' => 'Model not found'], 404);
        }
        
        $tableName = $model->table_name;

        if (!Schema::hasTable($tableName)) {
            return response()->json(['message' => 'Table does not exist'], 404);
        }

        $record = DB::table($tableName)->where('id', $id)->first();

        if (!$record) {
            return response()->json(['message' => 'Record not found'], 404);
        }

        if (!$this->validateRule($model->delete_rule, $record)) {
            return response()->json(['message' => 'Access denied by security rules'], 403);
        }

        $recordData = (array) $record;

        try {
            $this->executeInTransaction(function () use ($model, $tableName, $id) {
                $recordInstance = new DynamicRecord();
                $recordInstance->setDynamicTable($tableName);
                
                $pdoRecord = $recordInstance->findOrFail($id);
                $pdoRecord->setDynamicTable($tableName);
                $pdoRecord->timestamps = false;

                if ($model->has_soft_deletes) {
                    $softDeleteData = ['deleted_at' => now()];
                    if ($model->has_timestamps) {
                        $softDeleteData['updated_at'] = now();
                    }
                    $pdoRecord->update($softDeleteData);
                } else {
                    $pdoRecord->delete();
                }
            });

            event(new ModelActivity('deleted', $model->name, ['id' => $id], $request->user()));

            $this->triggerWebhooks($model->id, 'deleted', [
                'table' => $tableName,
                'record' => $recordData,
            ]);

            return response()->json(['message' => 'Record deleted successfully']);
        } catch (\Exception $e) {
            Log::error('Record deletion failed', [
                'table' => $tableName,
                'id' => $id,
                'error' => $e->getMessage(),
            ]);
            
            return response()->json([
                'message' => 'Failed to delete record',
                'error' => config('app.debug') ? $e->getMessage() : 'Internal server error',
            ], 500);
        }
    }

    /**
     * Get model schema information.
     */
    public function schema(Request $request, string $tableName): JsonResponse
    {
        $model = $this->getModel($tableName);

        if (!$model) {
            return response()->json(['message' => 'Model not found'], 404);
        }

        if (!$this->validateRule($model->list_rule)) {
            return response()->json(['message' => 'Access denied by security rules'], 403);
        }

        return response()->json([
            'model' => [
                'name' => $model->name,
                'display_name' => $model->display_name,
                'table_name' => $model->table_name,
                'description' => $model->description,
                'has_timestamps' => $model->has_timestamps,
                'has_soft_deletes' => $model->has_soft_deletes,
            ],
            'fields' => $model->fields->map(function ($field) {
                return [
                    'name' => $field->name,
                    'display_name' => $field->display_name,
                    'type' => $field->type,
                    'is_required' => $field->is_required,
                    'is_unique' => $field->is_unique,
                    'is_searchable' => $field->is_searchable,
                    'is_filterable' => $field->is_filterable,
                    'is_sortable' => $field->is_sortable,
                    'default_value' => $field->default_value,
                    'options' => $field->options,
                    'validation_rules' => $field->validation_rules,
                    'is_hidden' => $field->is_hidden ?? false,
                ];
            }),
            'endpoints' => [
                'list' => [
                    'method' => 'GET',
                    'url' => "/api/v1/data/{$tableName}",
                    'description' => 'List all records with pagination, search, and filters',
                ],
                'create' => [
                    'method' => 'POST',
                    'url' => "/api/v1/data/{$tableName}",
                    'description' => 'Create a new record',
                ],
                'show' => [
                    'method' => 'GET',
                    'url' => "/api/v1/data/{$tableName}/{id}",
                    'description' => 'Get a single record by ID',
                ],
                'update' => [
                    'method' => 'PUT',
                    'url' => "/api/v1/data/{$tableName}/{id}",
                    'description' => 'Update a record by ID',
                ],
                'delete' => [
                    'method' => 'DELETE',
                    'url' => "/api/v1/data/{$tableName}/{id}",
                    'description' => 'Delete a record by ID',
                ],
            ],
        ]);
    }
}

```

## File: app/Http/Controllers/Api/DatabaseController.php
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DynamicModel;
use App\Models\DynamicRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DatabaseController extends Controller
{
    /**
     * System tables that should be hidden from regular users.
     * These contain sensitive data that shouldn't be exposed.
     */
    protected array $protectedTables = [
        'users',
        'password_reset_tokens',
        'personal_access_tokens',
        'sessions',
        'cache',
        'cache_locks',
        'jobs',
        'job_batches',
        'failed_jobs',
        'migrations',
        'roles',
        'permissions',
        'role_has_permissions',
        'model_has_roles',
        'model_has_permissions',
        'dynamic_models',
        'dynamic_fields',
        'dynamic_relationships',
        'webhooks',
        'settings',
        'storage_files',
        'api_keys',
        'db_config',
        'file_system_items',
    ];

    /**
     * Check if a table is protected/sensitive.
     */
    protected function isProtectedTable(string $tableName): bool
    {
        return in_array($tableName, $this->protectedTables);
    }

    /**
     * Get all tables in the database (filters out protected system tables).
     */
    public function tables(Request $request): JsonResponse
    {
        $tables = Schema::getTables();

        $tableInfo = collect($tables)
            ->filter(function ($table) {
                // Filter out protected system tables
                return !$this->isProtectedTable($table['name']);
            })
            ->map(function ($table) {
                $tableName = $table['name'];

                // Get row count
                $rowCount = DB::table($tableName)->count();

                // Check if it's a dynamic model table
                $dynamicModel = DynamicModel::where('table_name', $tableName)->first();

                return [
                    'name' => $tableName,
                    'rows' => $rowCount,
                    'is_dynamic' => $dynamicModel !== null,
                    'dynamic_model_id' => $dynamicModel?->id,
                    'dynamic_model_name' => $dynamicModel?->display_name,
                ];
            })->values();

        return response()->json(['data' => $tableInfo]);
    }

    /**
     * Get table structure/schema.
     */
    public function structure(Request $request, string $tableName): JsonResponse
    {
        if (!Schema::hasTable($tableName)) {
            return response()->json(['message' => 'Table not found'], 404);
        }

        // Block access to protected system tables
        if ($this->isProtectedTable($tableName)) {
            return response()->json(['message' => 'Access to this table is restricted'], 403);
        }

        $columns = Schema::getColumns($tableName);
        $indexes = Schema::getIndexes($tableName);

        $columnInfo = collect($columns)->map(function ($column) {
            return [
                'name' => $column['name'],
                'type' => $column['type'],
                'nullable' => $column['nullable'],
                'default' => $column['default'],
                'auto_increment' => $column['auto_increment'] ?? false,
            ];
        });

        $indexInfo = collect($indexes)->map(function ($index) {
            return [
                'name' => $index['name'],
                'columns' => $index['columns'],
                'unique' => $index['unique'],
                'primary' => $index['primary'] ?? false,
            ];
        });

        // Check if dynamic model
        $dynamicModel = DynamicModel::where('table_name', $tableName)->first();

        return response()->json([
            'table' => $tableName,
            'columns' => $columnInfo,
            'indexes' => $indexInfo,
            'is_dynamic' => $dynamicModel !== null,
            'dynamic_model' => $dynamicModel ? [
                'id' => $dynamicModel->id,
                'name' => $dynamicModel->name,
                'display_name' => $dynamicModel->display_name,
            ] : null,
        ]);
    }

    /**
     * Get table data with pagination.
     */
    public function data(Request $request, string $tableName): JsonResponse
    {
        if (!Schema::hasTable($tableName)) {
            return response()->json(['message' => 'Table not found'], 404);
        }

        // Block access to protected system tables
        if ($this->isProtectedTable($tableName)) {
            return response()->json(['message' => 'Access to this table is restricted'], 403);
        }

        $perPage = min($request->get('per_page', 25), 100);
        $page = $request->get('page', 1);
        $sortBy = $request->get('sort', 'id');
        $sortDir = $request->get('direction', 'desc');

        // Get columns to validate sort field
        $columns = collect(Schema::getColumns($tableName))->pluck('name')->toArray();

        if (!in_array($sortBy, $columns)) {
            $sortBy = $columns[0] ?? 'id';
        }

        $query = DB::table($tableName);

        // Apply search if provided
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($columns, $search) {
                foreach ($columns as $column) {
                    $q->orWhere($column, 'LIKE', "%{$search}%");
                }
            });
        }

        // Apply column filters
        foreach ($columns as $column) {
            if ($request->has("filter_{$column}") && $request->input("filter_{$column}") !== null) {
                $query->where($column, $request->input("filter_{$column}"));
            }
        }

        $total = $query->count();
        $data = $query
            ->orderBy($sortBy, $sortDir === 'asc' ? 'asc' : 'desc')
            ->skip(($page - 1) * $perPage)
            ->take($perPage)
            ->get();

        return response()->json([
            'data' => $data,
            'columns' => $columns,
            'meta' => [
                'current_page' => (int) $page,
                'per_page' => $perPage,
                'total' => $total,
                'last_page' => ceil($total / $perPage),
            ],
        ]);
    }

    /**
     * Sanitize SQL by removing comments that could be used to bypass keyword detection.
     */
    protected function removeComments(string $sql): string
    {
        // Remove single-line comments (-- and #)
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/#.*$/m', '', $sql);

        // Remove multi-line comments (/* */)
        $sql = preg_replace('/\/\*[\s\S]*?\*\//', '', $sql);

        return $sql;
    }

    /**
     * Execute a read-only SQL query.
     */
    public function query(Request $request): JsonResponse
    {
        $request->validate([
            'sql' => 'required|string|max:5000',
        ]);

        $sql = trim($request->sql);

        // Remove comments that could be used to bypass keyword detection
        $sanitizedSql = $this->removeComments($sql);

        // Normalize whitespace for better pattern matching
        $normalizedSql = preg_replace('/\s+/', ' ', $sanitizedSql);

        // Only allow SELECT queries for safety (must start with SELECT)
        if (!preg_match('/^\s*SELECT\s/i', $normalizedSql)) {
            return response()->json([
                'message' => 'Only SELECT queries are allowed',
            ], 400);
        }

        // Block dangerous keywords
        $dangerous = [
            'INSERT', 'UPDATE', 'DELETE', 'DROP', 'ALTER', 'CREATE', 'TRUNCATE',
            'GRANT', 'REVOKE', 'EXEC', 'EXECUTE', 'CALL', 'INTO OUTFILE',
            'INTO DUMPFILE', 'LOAD_FILE', 'BENCHMARK', 'SLEEP', 'WAITFOR',
        ];
        foreach ($dangerous as $keyword) {
            // Use word boundary matching to catch keywords
            if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $normalizedSql)) {
                return response()->json([
                    'message' => "Query contains forbidden keyword: {$keyword}",
                ], 400);
            }
        }

        // Block queries against protected tables
        foreach ($this->protectedTables as $table) {
            if (preg_match('/\b' . preg_quote($table, '/') . '\b/i', $normalizedSql)) {
                return response()->json([
                    'message' => "Access to table '{$table}' is restricted",
                ], 403);
            }
        }

        // Block multiple statements (semicolon followed by another statement)
        if (preg_match('/;\s*\S/', $normalizedSql)) {
            return response()->json([
                'message' => 'Multiple statements are not allowed',
            ], 400);
        }

        try {
            $startTime = microtime(true);
            $results = DB::select($sql);
            $executionTime = round((microtime(true) - $startTime) * 1000, 2);

            $columns = [];
            if (!empty($results)) {
                $columns = array_keys((array) $results[0]);
            }

            return response()->json([
                'data' => $results,
                'columns' => $columns,
                'rows_count' => count($results),
                'execution_time_ms' => $executionTime,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Query error: ' . $e->getMessage(),
            ], 400);
        }
    }

    /**
     * Get database statistics.
     */
    public function stats(Request $request): JsonResponse
    {
        $tables = Schema::getTables();

        $totalRows = 0;
        $tableStats = [];

        foreach ($tables as $table) {
            $count = DB::table($table['name'])->count();
            $totalRows += $count;
            $tableStats[] = [
                'name' => $table['name'],
                'rows' => $count,
            ];
        }

        // Sort by row count
        usort($tableStats, fn($a, $b) => $b['rows'] - $a['rows']);

        // Get dynamic models count
        $dynamicModelsCount = DynamicModel::count();

        return response()->json([
            'total_tables' => count($tables),
            'total_rows' => $totalRows,
            'dynamic_models' => $dynamicModelsCount,
            'tables' => array_slice($tableStats, 0, 10), // Top 10 tables
        ]);
    }

    /**
     * Insert a row into a table (for dynamic models only).
     */
    public function insertRow(Request $request, string $tableName): JsonResponse
    {
        // Only allow inserting into dynamic model tables
        $dynamicModel = DynamicModel::where('table_name', $tableName)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$dynamicModel) {
            return response()->json(['message' => 'Unauthorized or table not found'], 403);
        }

        // Only allow fields that are defined in the dynamic model (prevent injection of system columns)
        $allowedFields = $dynamicModel->fields()->pluck('name')->toArray();
        $data = $request->only($allowedFields);

        if ($dynamicModel->has_timestamps) {
            $data['created_at'] = now();
            $data['updated_at'] = now();
        }

        try {
            // Use Eloquent so DynamicRecordObserver fires (cache + real-time)
            $record = new DynamicRecord();
            $record->setDynamicTable($tableName);
            $record->timestamps = false;
            $record->fill($data);
            $record->save();

            return response()->json(['data' => $record->fresh()], 201);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Insert error: ' . $e->getMessage()], 400);
        }
    }

    /**
     * Update a row in a table (for dynamic models only).
     */
    public function updateRow(Request $request, string $tableName, int $id): JsonResponse
    {
        $dynamicModel = DynamicModel::where('table_name', $tableName)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$dynamicModel) {
            return response()->json(['message' => 'Unauthorized or table not found'], 403);
        }

        // Only allow fields that are defined in the dynamic model (prevent injection of system columns)
        $allowedFields = $dynamicModel->fields()->pluck('name')->toArray();
        $data = $request->only($allowedFields);

        if ($dynamicModel->has_timestamps) {
            $data['updated_at'] = now();
        }

        try {
            // Use Eloquent so DynamicRecordObserver fires (cache + real-time)
            $record = (new DynamicRecord())->setDynamicTable($tableName)->findOrFail($id);
            $record->setDynamicTable($tableName);
            $record->timestamps = false;
            $record->update($data);

            return response()->json(['data' => $record->fresh()]);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Update error: ' . $e->getMessage()], 400);
        }
    }

    /**
     * Delete a row from a table (for dynamic models only).
     */
    public function deleteRow(Request $request, string $tableName, int $id): JsonResponse
    {
        $dynamicModel = DynamicModel::where('table_name', $tableName)
            ->where('user_id', $request->user()->id)
            ->first();

        if (!$dynamicModel) {
            return response()->json(['message' => 'Unauthorized or table not found'], 403);
        }

        try {
            // Use Eloquent so DynamicRecordObserver fires (cache + real-time)
            $record = (new DynamicRecord())->setDynamicTable($tableName)->findOrFail($id);
            $record->setDynamicTable($tableName);
            $record->timestamps = false;

            if ($dynamicModel->has_soft_deletes) {
                $record->update(['deleted_at' => now()]);
            } else {
                $record->delete();
            }

            return response()->json(['message' => 'Row deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['message' => 'Delete error: ' . $e->getMessage()], 400);
        }
    }
}

```

## File: app/Http/Controllers/Api/DynamicModelController.php
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DynamicField;
use App\Models\DynamicModel;
use App\Services\MigrationGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class DynamicModelController extends Controller
{
    /**
     * Get all models for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $models = DynamicModel::where('user_id', $request->user()->id)
            ->with(['fields'])
            ->withCount('fields')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json($models);
    }

    /**
     * Get a specific model with its fields and relationships.
     */
    public function show(Request $request, DynamicModel $dynamicModel): JsonResponse
    {
        if ($dynamicModel->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $dynamicModel->load(['fields', 'relationships.relatedModel']);

        return response()->json($dynamicModel);
    }

    /**
     * Create a new model with fields.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|regex:/^[a-zA-Z][a-zA-Z0-9]*$/',
            'display_name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'has_timestamps' => 'boolean',
            'has_soft_deletes' => 'boolean',
            'generate_api' => 'boolean',
            'fields' => 'required|array|min:1',
            'fields.*.name' => 'required|string|max:255|regex:/^[a-z][a-z0-9_]*$/',
            'fields.*.display_name' => 'required|string|max:255',
            'fields.*.type' => 'required|string|in:string,text,richtext,integer,float,decimal,boolean,date,datetime,time,json,enum,select,email,url,phone,slug,uuid,file,image,password,color,encrypted,markdown,point',
            'fields.*.description' => 'nullable|string',
            'fields.*.is_required' => 'boolean',
            'fields.*.is_unique' => 'boolean',
            'fields.*.is_indexed' => 'boolean',
            'fields.*.is_searchable' => 'boolean',
            'fields.*.is_filterable' => 'boolean',
            'fields.*.is_sortable' => 'boolean',
            'fields.*.show_in_list' => 'boolean',
            'fields.*.show_in_detail' => 'boolean',
            'fields.*.default_value' => 'nullable|string',
            'fields.*.options' => 'nullable|array',
            'relationships' => 'nullable|array',
            'relationships.*.name' => 'required|string|max:255',
            'relationships.*.type' => 'required|string|in:belongsTo,hasMany',
            'relationships.*.related_model_id' => 'required|exists:dynamic_models,id',
            'relationships.*.foreign_key' => 'nullable|string|max:255',
        ]);

        // Generate table name from model name
        $tableName = Str::snake(Str::pluralStudly($validated['name']));

        // Check if table already exists
        if (DynamicModel::where('table_name', $tableName)->exists()) {
            return response()->json([
                'message' => 'A model with this name already exists.',
                'errors' => ['name' => ['A model with this name already exists.']]
            ], 422);
        }

        DB::beginTransaction();

        try {
            // Create the model
            $model = DynamicModel::create([
                'user_id' => $request->user()->id,
                'name' => $validated['name'],
                'table_name' => $tableName,
                'display_name' => $validated['display_name'],
                'description' => $validated['description'] ?? null,
                'icon' => $validated['icon'] ?? 'table',
                'has_timestamps' => $validated['has_timestamps'] ?? true,
                'has_soft_deletes' => $validated['has_soft_deletes'] ?? false,
                'generate_api' => $validated['generate_api'] ?? true,
            ]);

            // Create fields
            foreach ($validated['fields'] as $index => $fieldData) {
                DynamicField::create([
                    'dynamic_model_id' => $model->id,
                    'name' => $fieldData['name'],
                    'display_name' => $fieldData['display_name'],
                    'type' => $fieldData['type'],
                    'description' => $fieldData['description'] ?? null,
                    'is_required' => $fieldData['is_required'] ?? false,
                    'is_unique' => $fieldData['is_unique'] ?? false,
                    'is_indexed' => $fieldData['is_indexed'] ?? false,
                    'is_searchable' => $fieldData['is_searchable'] ?? true,
                    'is_filterable' => $fieldData['is_filterable'] ?? true,
                    'is_sortable' => $fieldData['is_sortable'] ?? true,
                    'show_in_list' => $fieldData['show_in_list'] ?? true,
                    'show_in_detail' => $fieldData['show_in_detail'] ?? true,
                    'default_value' => $fieldData['default_value'] ?? null,
                    'options' => $fieldData['options'] ?? null,
                    'order' => $index,
                ]);
            }

            // Generate and run migration
            $this->generateMigration($model);

            DB::commit();

            $model->load('fields');

            return response()->json($model, 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create model: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a model.
     */
    public function update(Request $request, DynamicModel $dynamicModel): JsonResponse
    {
        if ($dynamicModel->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'display_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'is_active' => 'boolean',
            'generate_api' => 'boolean',
        ]);

        $dynamicModel->update($validated);

        return response()->json($dynamicModel);
    }

    /**
     * Delete a model and its table.
     */
    public function destroy(Request $request, DynamicModel $dynamicModel): JsonResponse
    {
        if ($dynamicModel->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        DB::beginTransaction();

        try {
            // Drop the table if it exists
            if (DB::getSchemaBuilder()->hasTable($dynamicModel->table_name)) {
                DB::getSchemaBuilder()->drop($dynamicModel->table_name);
            }

            // Delete the model (fields and relationships cascade)
            $dynamicModel->delete();

            DB::commit();

            return response()->json(['message' => 'Model deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to delete model: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get available field types.
     */
    public function fieldTypes(): JsonResponse
    {
        $types = [
            ['value' => 'string', 'label' => 'String', 'description' => 'Short text (max 255 chars)', 'icon' => 'font'],
            ['value' => 'text', 'label' => 'Text', 'description' => 'Long text content', 'icon' => 'align-left'],
            ['value' => 'richtext', 'label' => 'Rich Text', 'description' => 'HTML formatted content', 'icon' => 'edit'],
            ['value' => 'integer', 'label' => 'Integer', 'description' => 'Whole numbers', 'icon' => 'hash'],
            ['value' => 'float', 'label' => 'Float', 'description' => 'Decimal numbers', 'icon' => 'percent'],
            ['value' => 'decimal', 'label' => 'Decimal', 'description' => 'Precise decimal numbers', 'icon' => 'dollar-sign'],
            ['value' => 'boolean', 'label' => 'Boolean', 'description' => 'True/False values', 'icon' => 'toggle-left'],
            ['value' => 'date', 'label' => 'Date', 'description' => 'Date without time', 'icon' => 'calendar'],
            ['value' => 'datetime', 'label' => 'DateTime', 'description' => 'Date with time', 'icon' => 'clock'],
            ['value' => 'time', 'label' => 'Time', 'description' => 'Time only', 'icon' => 'clock'],
            ['value' => 'json', 'label' => 'JSON', 'description' => 'JSON data structure', 'icon' => 'code'],
            ['value' => 'enum', 'label' => 'Enum', 'description' => 'Fixed set of values', 'icon' => 'list'],
            ['value' => 'select', 'label' => 'Select', 'description' => 'Dropdown selection', 'icon' => 'chevron-down'],
            ['value' => 'email', 'label' => 'Email', 'description' => 'Email address', 'icon' => 'mail'],
            ['value' => 'url', 'label' => 'URL', 'description' => 'Web URL', 'icon' => 'link'],
            ['value' => 'phone', 'label' => 'Phone', 'description' => 'Phone number', 'icon' => 'phone'],
            ['value' => 'slug', 'label' => 'Slug', 'description' => 'URL-friendly string', 'icon' => 'link-2'],
            ['value' => 'uuid', 'label' => 'UUID', 'description' => 'Unique identifier', 'icon' => 'key'],
            ['value' => 'password', 'label' => 'Password', 'description' => 'Hashed secure password', 'icon' => 'lock-closed'],
            ['value' => 'color', 'label' => 'Color', 'description' => 'HEX color value', 'icon' => 'swatch'],
            ['value' => 'encrypted', 'label' => 'Encrypted', 'description' => 'AES-256 encrypted string', 'icon' => 'shield-check'],
            ['value' => 'markdown', 'label' => 'Markdown', 'description' => 'Markdown formatted text', 'icon' => 'document-text'],
            ['value' => 'point', 'label' => 'Location (Point)', 'description' => 'GPS coordinates (Lat/Lng)', 'icon' => 'map-pin'],
            ['value' => 'file', 'label' => 'File', 'description' => 'File upload', 'icon' => 'paper-clip'],
            ['value' => 'image', 'label' => 'Image', 'description' => 'Image upload', 'icon' => 'photo'],
        ];

        return response()->json($types);
    }

    /**
     * Add new fields to an existing model and update the database table.
     */
    public function addFields(Request $request, DynamicModel $dynamicModel): JsonResponse
    {
        if ($dynamicModel->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'fields' => 'required|array|min:1',
            'fields.*.name' => 'required|string|max:255|regex:/^[a-z][a-z0-9_]*$/',
            'fields.*.display_name' => 'required|string|max:255',
            'fields.*.type' => 'required|string|in:string,text,richtext,integer,float,decimal,boolean,date,datetime,time,json,enum,select,email,url,phone,slug,uuid,file,image',
            'fields.*.description' => 'nullable|string',
            'fields.*.is_required' => 'boolean',
            'fields.*.is_unique' => 'boolean',
            'fields.*.is_indexed' => 'boolean',
            'fields.*.is_searchable' => 'boolean',
            'fields.*.is_filterable' => 'boolean',
            'fields.*.is_sortable' => 'boolean',
            'fields.*.show_in_list' => 'boolean',
            'fields.*.show_in_detail' => 'boolean',
            'fields.*.default_value' => 'nullable|string',
            'fields.*.options' => 'nullable|array',
        ]);

        // Check if any field already exists
        $existingFieldNames = $dynamicModel->fields()->pluck('name')->toArray();
        foreach ($validated['fields'] as $fieldData) {
            if (in_array($fieldData['name'], $existingFieldNames)) {
                return response()->json([
                    'message' => "Field '{$fieldData['name']}' already exists on this model."
                ], 422);
            }
        }

        DB::beginTransaction();

        try {
            $newFields = [];
            $lastOrder = $dynamicModel->fields()->max('order') ?? -1;

            foreach ($validated['fields'] as $index => $fieldData) {
                $field = DynamicField::create([
                    'dynamic_model_id' => $dynamicModel->id,
                    'name' => $fieldData['name'],
                    'display_name' => $fieldData['display_name'],
                    'type' => $fieldData['type'],
                    'description' => $fieldData['description'] ?? null,
                    'is_required' => $fieldData['is_required'] ?? false,
                    'is_unique' => $fieldData['is_unique'] ?? false,
                    'is_indexed' => $fieldData['is_indexed'] ?? false,
                    'is_searchable' => $fieldData['is_searchable'] ?? true,
                    'is_filterable' => $fieldData['is_filterable'] ?? true,
                    'is_sortable' => $fieldData['is_sortable'] ?? true,
                    'show_in_list' => $fieldData['show_in_list'] ?? true,
                    'show_in_detail' => $fieldData['show_in_detail'] ?? true,
                    'default_value' => $fieldData['default_value'] ?? null,
                    'options' => $fieldData['options'] ?? null,
                    'order' => $lastOrder + $index + 1,
                ]);
                $newFields[] = $field;
            }

            // Generate "add columns" migration
            $this->generateAddColumnsMigration($dynamicModel, $newFields);

            DB::commit();

            return response()->json([
                'message' => 'Fields added successfully',
                'model' => $dynamicModel->load('fields')
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to add fields: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update an existing field.
     */
    public function updateField(Request $request, DynamicModel $dynamicModel, DynamicField $field): JsonResponse
    {
        if ($dynamicModel->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'display_name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'is_required' => 'boolean',
            'is_unique' => 'boolean',
            'is_searchable' => 'boolean',
            'is_filterable' => 'boolean',
            'is_sortable' => 'boolean',
            'show_in_list' => 'boolean',
            'show_in_detail' => 'boolean',
            'default_value' => 'nullable|string',
            'options' => 'nullable|array',
        ]);

        $field->update($validated);

        return response()->json([
            'message' => 'Field updated successfully',
            'field' => $field
        ]);
    }

    /**
     * Delete a field and remove its column from the database.
     */
    public function destroyField(Request $request, DynamicModel $dynamicModel, DynamicField $field): JsonResponse
    {
        if ($dynamicModel->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Final safety check: Don't allow deleting 'id' or timestamp fields if they exist as dynamic fields
        if (in_array($field->name, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
            return response()->json(['message' => 'Cannot delete system protected fields.'], 422);
        }

        DB::beginTransaction();

        try {
            // Drop column from DB
            if (DB::getSchemaBuilder()->hasColumn($dynamicModel->table_name, $field->name)) {
                DB::getSchemaBuilder()->table($dynamicModel->table_name, function ($table) use ($field) {
                    $table->dropColumn($field->name);
                });
            }

            // Delete field record
            $field->delete();

            DB::commit();

            return response()->json(['message' => 'Field deleted successfully']);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to delete field: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Generate migration for adding columns to an existing table.
     */
    protected function generateAddColumnsMigration(DynamicModel $model, array $newFields): void
    {
        $tableName = $model->table_name;
        $fieldDefinitions = '';
        foreach ($newFields as $field) {
            $fieldDefinitions .= $this->buildFieldDefinition($field);
        }

        $migrationContent = <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('{$tableName}', function (Blueprint \$table) {
            {$fieldDefinitions}
        });
    }

    public function down(): void
    {
        Schema::table('{$tableName}', function (Blueprint \$table) {
            // Rollback not fully supported for dynamic additions yet
        });
    }
};
PHP;

        $migrationName = date('Y_m_d_His') . '_add_columns_to_' . $tableName . '_table.php';
        $migrationPath = database_path('migrations/' . $migrationName);

        file_put_contents($migrationPath, $migrationContent);

        // Run the migration
        Artisan::call('migrate', [
            '--path' => 'database/migrations/' . $migrationName,
            '--force' => true,
        ]);
    }

    /**
     * Generate migration for a model.
     */
    protected function generateMigration(DynamicModel $model): void
    {
        $model->load('fields');

        $migrationContent = $this->buildMigrationContent($model);
        $migrationName = date('Y_m_d_His') . '_create_' . $model->table_name . '_table.php';
        $migrationPath = database_path('migrations/' . $migrationName);

        file_put_contents($migrationPath, $migrationContent);

        // Run the migration
        Artisan::call('migrate', [
            '--path' => 'database/migrations/' . $migrationName,
            '--force' => true,
        ]);
    }

    /**
     * Build migration file content.
     */
    protected function buildMigrationContent(DynamicModel $model): string
    {
        $model->load(['fields', 'relationships.relatedModel']);
        $tableName = $model->table_name;
        $fields = $model->fields;
        $relationships = $model->relationships;

        $fieldDefinitions = '';
        foreach ($fields as $field) {
            $fieldDefinitions .= $this->buildFieldDefinition($field);
        }

        $relDefinitions = '';
        foreach ($relationships as $rel) {
            $relDefinitions .= $this->buildRelationshipDefinition($rel);
        }

        $timestamps = $model->has_timestamps ? "\$table->timestamps();\n            " : '';
        $softDeletes = $model->has_soft_deletes ? "\$table->softDeletes();\n            " : '';

        return <<<PHP
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('{$tableName}', function (Blueprint \$table) {
            \$table->id();
            {$fieldDefinitions}
            {$relDefinitions}
            {$timestamps}
            {$softDeletes}
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('{$tableName}');
    }
};
PHP;
    }

    /**
     * Build field definition for migration.
     */
    protected function buildFieldDefinition(DynamicField $field): string
    {
        $method = match ($field->type) {
            'string', 'email', 'url', 'phone', 'slug', 'password', 'color', 'encrypted' => 'string',
            'text', 'richtext', 'markdown' => 'text',
            'integer' => 'integer',
            'float' => 'float',
            'decimal' => 'decimal',
            'boolean' => 'boolean',
            'date' => 'date',
            'datetime' => 'dateTime',
            'time' => 'time',
            'json' => 'json',
            'enum', 'select' => 'string',
            'uuid' => 'uuid',
            'file', 'image' => 'string',
            'point' => 'geometry',
            default => 'string',
        };

        $definition = "\$table->{$method}('{$field->name}')";

        if (!$field->is_required || in_array($field->type, ['file', 'image'])) {
            $definition .= '->nullable()';
        }

        if ($field->is_unique) {
            $definition .= '->unique()';
        }

        if ($field->is_indexed) {
            $definition .= '->index()';
        }

        if ($field->default_value !== null && $field->default_value !== '') {
            $defaultValue = match ($field->type) {
                'boolean' => $field->default_value === 'true' || $field->default_value === '1' ? 'true' : 'false',
                'integer', 'float', 'decimal' => $field->default_value,
                default => "'{$field->default_value}'",
            };
            $definition .= "->default({$defaultValue})";
        }

        return $definition . ";\n            ";
    }

    /**
     * Build relationship definition for migration.
     */
    protected function buildRelationshipDefinition($rel): string
    {
        if ($rel->type === 'belongsTo') {
            $foreignKey = $rel->foreign_key ?: Str::snake($rel->name) . '_id';
            $relatedTable = $rel->relatedModel->table_name;
            return "\$table->foreignId('{$foreignKey}')->nullable()->constrained('{$relatedTable}')->nullOnDelete();\n            ";
        }
        return '';
    }
}

```

## File: app/Http/Controllers/Api/MigrationController.php
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MigrationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MigrationController extends Controller
{
    protected MigrationService $migrationService;

    public function __construct(MigrationService $migrationService)
    {
        $this->migrationService = $migrationService;
    }

    /**
     * Get all migrations status.
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'data' => $this->migrationService->getStatus()
        ]);
    }

    /**
     * Run migrations.
     */
    public function migrate(Request $request): JsonResponse
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $result = $this->migrationService->migrate();
        return response()->json($result);
    }

    /**
     * Rollback migrations.
     */
    public function rollback(Request $request): JsonResponse
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $result = $this->migrationService->rollback();
        return response()->json($result);
    }
}

```

## File: app/Http/Controllers/Api/RoleController.php
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Display a listing of roles.
     */
    public function index(Request $request): JsonResponse
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $roles = Role::with('permissions')->get();
        return response()->json($roles);
    }

    /**
     * Store a newly created role.
     */
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'name' => 'required|string|unique:roles,name',
            'permissions' => 'array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        $role = Role::create(['name' => $request->name]);

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return response()->json($role->load('permissions'), 201);
    }

    /**
     * Display the specified role.
     */
    public function show(Request $request, Role $role): JsonResponse
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($role->load('permissions'));
    }

    /**
     * Update the specified role.
     */
    public function update(Request $request, Role $role): JsonResponse
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Prevent renaming core roles
        if (in_array($role->name, ['admin', 'user'])) {
            return response()->json(['message' => 'Core roles cannot be updated'], 400);
        }

        $request->validate([
            'name' => 'sometimes|required|string|unique:roles,name,' . $role->id,
            'permissions' => 'array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);

        if ($request->has('name')) {
            $role->name = $request->name;
            $role->save();
        }

        if ($request->has('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        return response()->json($role->load('permissions'));
    }

    /**
     * Remove the specified role.
     */
    public function destroy(Request $request, Role $role): JsonResponse
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Prevent deleting core roles
        if (in_array($role->name, ['admin', 'user'])) {
            return response()->json(['message' => 'Core roles cannot be deleted'], 400);
        }

        $role->delete();

        return response()->json(['message' => 'Role deleted successfully']);
    }

    /**
     * List all available permissions.
     */
    public function permissions(Request $request): JsonResponse
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $permissions = Permission::all();
        return response()->json($permissions);
    }
}

```

## File: app/Http/Controllers/Api/SdkController.php
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Response;

class SdkController extends Controller
{
    public function generate()
    {
        // Auto-detect the API base URL
        $baseUrl = config('app.url');

        $js = <<<JS
/**
 * Digibase JavaScript SDK
 * auto-generated
 */
class Digibase {
    constructor(baseUrl = '$baseUrl') {
        this.baseUrl = baseUrl.replace(/\/$/, '');
        this.token = localStorage.getItem('digibase_token');
    }

    setToken(token) {
        this.token = token;
        if (token) {
            localStorage.setItem('digibase_token', token);
        } else {
            localStorage.removeItem('digibase_token');
        }
    }

    async request(method, endpoint, data = null, headers = {}, responseType = 'json') {
        const url = `\${this.baseUrl}/api\${endpoint}`;
        
        const config = {
            method,
            headers: {
                'Accept': 'application/json',
                ...headers
            }
        };

        // Only set Content-Type for JSON requests with body
        if (data && responseType === 'json') {
            config.headers['Content-Type'] = 'application/json';
        }

        if (this.token) {
            config.headers['Authorization'] = `Bearer \${this.token}`;
        }

        if (data) {
            config.body = JSON.stringify(data);
        }

        const response = await fetch(url, config);

        if (!response.ok) {
            // Try to parse error as JSON
            let errorBody;
            try {
                errorBody = await response.json();
            } catch (e) {
                throw { status: response.status, message: response.statusText };
            }
            throw { status: response.status, ...errorBody };
        }

        // Handle different response types
        if (responseType === 'blob') {
            return await response.blob();
        }
        if (responseType === 'text') {
            return await response.text();
        }
        return await response.json();
    }

    // --- Auth ---
    get auth() {
        return {
            login: async (email, password) => {
                const res = await this.request('POST', '/login', { email, password });
                this.setToken(res.token);
                return res.user;
            },
            register: async (name, email, password, password_confirmation) => {
                const res = await this.request('POST', '/register', { name, email, password, password_confirmation });
                this.setToken(res.token);
                return res.user;
            },
            logout: async () => {
                try {
                    await this.request('POST', '/logout');
                } finally {
                    this.setToken(null);
                }
            },
            user: async () => {
                return await this.request('GET', '/user');
            },
            isAuthenticated: () => {
                return !!this.token;
            }
        };
    }

    // --- Data Collection ---
    collection(name) {
        return new CollectionQuery(this, name);
    }
    
    // --- Storage ---
    get storage() {
        return {
            getFileUrl: (path) => {
                if (!path) return '';
                if (path.startsWith('http')) return path;
                return `\${this.baseUrl}/storage/\${path}`;
            },
            getPrivateUrl: (id) => {
                return `\${this.baseUrl}/api/storage/\${id}/download`;
            },
            download: async (id) => {
                // For private files - returns Blob for binary download
                return await this.request('GET', `/storage/\${id}/download`, null, {}, 'blob');
            },
            list: async (params = {}) => {
                const queryString = new URLSearchParams(params).toString();
                return await this.request('GET', `/storage\${queryString ? '?' + queryString : ''}`);
            },
            upload: async (file, options = {}) => {
                const formData = new FormData();
                formData.append('file', file);
                if (options.bucket) formData.append('bucket', options.bucket);
                if (options.folder) formData.append('folder', options.folder);
                if (options.is_public) formData.append('is_public', '1');

                const url = `\${this.baseUrl}/api/storage`;
                const headers = { 'Accept': 'application/json' };
                if (this.token) {
                    headers['Authorization'] = `Bearer \${this.token}`;
                }

                const response = await fetch(url, {
                    method: 'POST',
                    headers,
                    body: formData
                });
                const result = await response.json();
                if (!response.ok) {
                    throw { status: response.status, ...result };
                }
                return result;
            },
            delete: async (id) => {
                return await this.request('DELETE', `/storage/\${id}`);
            }
        };
    }
}

class CollectionQuery {
    constructor(client, collection) {
        this.client = client;
        this.collection = collection;
        this.params = new URLSearchParams();
    }

    where(field, value) {
        this.params.append(field, value);
        return this;
    }

    sort(field, direction = 'asc') {
        this.params.append('sort', field);
        this.params.append('direction', direction);
        return this;
    }

    page(page) {
        this.params.append('page', page);
        return this;
    }
    
    perPage(count) {
        this.params.append('per_page', count);
        return this;
    }
    
    include(relations) {
        this.params.append('include', relations);
        return this;
    }

    async getAll() {
        const queryString = this.params.toString();
        const endpoint = `/data/\${this.collection}\${queryString ? '?' + queryString : ''}`;
        return await this.client.request('GET', endpoint);
    }

    async getOne(id) {
        return await this.client.request('GET', `/data/\${this.collection}/\${id}`);
    }

    async create(data) {
        return await this.client.request('POST', `/data/\${this.collection}`, data);
    }

    async update(id, data) {
        return await this.client.request('PUT', `/data/\${this.collection}/\${id}`, data);
    }

    async delete(id) {
        return await this.client.request('DELETE', `/data/\${this.collection}/\${id}`);
    }
}

export default Digibase;
JS;

        return Response::make($js, 200, [
            'Content-Type' => 'application/javascript',
            'Content-Disposition' => 'attachment; filename="digibase.js"',
        ]);
    }
}

```

## File: app/Http/Controllers/Api/UserController.php
```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;

class UserController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): JsonResponse
    {
        // Only admins can see all users
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $users = User::with('roles')->get();
        return response()->json($users);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request): JsonResponse
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'role' => ['required', 'string', 'exists:roles,name'],
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        $user->assignRole($request->role);

        return response()->json($user->load('roles'), 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->hasRole('admin') && $request->user()->id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        return response()->json($user->load('roles'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->hasRole('admin') && $request->user()->id !== $user->id) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'string', 'lowercase', 'email', 'max:255', 'unique:users,email,'.$user->id],
            'password' => ['sometimes', 'confirmed', Rules\Password::defaults()],
            'role' => ['sometimes', 'required', 'string', 'exists:roles,name'],
        ]);

        if ($request->has('name')) {
            $user->name = $request->name;
        }

        if ($request->has('email')) {
            $user->email = $request->email;
        }

        if ($request->has('password')) {
            $user->password = Hash::make($request->password);
        }

        $user->save();

        if ($request->has('role') && $request->user()->hasRole('admin')) {
            $user->syncRoles([$request->role]);
        }

        return response()->json($user->load('roles'));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, User $user): JsonResponse
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Prevent self-deletion
        if ($request->user()->id === $user->id) {
            return response()->json(['message' => 'Cannot delete your own account'], 400);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }
}

```

## File: app/Http/Controllers/Controller.php
```php
<?php

namespace App\Http\Controllers;

abstract class Controller
{
    //
}

```

## File: app/Http/Middleware/ApiRateLimiter.php
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ApiRateLimiter
{
    /**
     * Handle an incoming request with dynamic rate limiting based on API key.
     *
     * Bypass: Authenticated Filament admin users (session auth) and requests
     * with the X-Digibase-Internal header are never rate-limited.
     * The admin must be a god.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // ── GOD MODE: bypass rate limit for admin panel users ──
        if ($this->isAdminOrInternal($request)) {
            $response = $next($request);

            return $response->withHeaders([
                'X-RateLimit-Limit' => 'unlimited',
                'X-RateLimit-Remaining' => 'unlimited',
            ]);
        }

        $apiKey = $request->attributes->get('api_key');

        if (!$apiKey) {
            return $this->applyDefaultRateLimit($request, $next);
        }

        // Get rate limit from API key (default to 60 if not set)
        $maxAttempts = $apiKey->rate_limit ?? 60;
        $decayMinutes = 1;

        // Create unique key for this API key
        $key = 'api:' . $apiKey->id;

        // Check rate limit
        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'message' => 'Too many requests. Please slow down.',
                'retry_after' => $seconds,
                'limit' => $maxAttempts,
                'remaining' => 0,
            ], 429)->withHeaders([
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => 0,
                'X-RateLimit-Reset' => now()->addSeconds($seconds)->timestamp,
                'Retry-After' => $seconds,
            ]);
        }

        // Hit the rate limiter
        RateLimiter::hit($key, $decayMinutes * 60);

        $remaining = $maxAttempts - RateLimiter::attempts($key);

        $response = $next($request);

        // Add rate limit headers to response
        return $response->withHeaders([
            'X-RateLimit-Limit' => $maxAttempts,
            'X-RateLimit-Remaining' => max(0, $remaining),
            'X-RateLimit-Reset' => now()->addMinutes($decayMinutes)->timestamp,
        ]);
    }

    /**
     * Check if the request comes from a logged-in Filament admin (session auth)
     * or carries the internal bypass flag.
     */
    protected function isAdminOrInternal(Request $request): bool
    {
        // 1. Session-authenticated Filament admin user (e.g. UniverSheetWidget bulk edits)
        if (auth()->check()) {
            $user = auth()->user();

            // Filament exposes a canAccessPanel() contract; any user that can
            // reach the admin panel is considered an admin for rate-limit purposes.
            if (method_exists($user, 'canAccessPanel')) {
                // canAccessPanel expects a Panel instance; grab the default panel
                try {
                    $panel = \Filament\Facades\Filament::getCurrentPanel()
                          ?? \Filament\Facades\Filament::getDefaultPanel();

                    if ($panel && $user->canAccessPanel($panel)) {
                        return true;
                    }
                } catch (\Throwable) {
                    // Filament not booted yet — fall through
                }
            }

            // Fallback: check for a simple admin flag / role
            if (property_exists($user, 'is_admin') && $user->is_admin) {
                return true;
            }

            if (method_exists($user, 'hasRole') && $user->hasRole('admin')) {
                return true;
            }
        }

        // 2. Internal flag header — only trust this from server-side requests
        //    (the header is stripped at the edge / load-balancer level)
        if ($request->header('X-Digibase-Internal') === config('app.key')) {
            return true;
        }

        return false;
    }

    /**
     * Apply default rate limit when no API key is present.
     */
    protected function applyDefaultRateLimit(Request $request, Closure $next): Response
    {
        $key = 'api:' . $request->ip();
        $maxAttempts = 60;
        $decayMinutes = 1;

        if (RateLimiter::tooManyAttempts($key, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($key);

            return response()->json([
                'message' => 'Too many requests. Please slow down.',
                'retry_after' => $seconds,
            ], 429);
        }

        RateLimiter::hit($key, $decayMinutes * 60);

        return $next($request);
    }
}

```

## File: app/Http/Middleware/LogApiActivity.php
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Terminable middleware: logs every API hit to the api_analytics table.
 *
 * The terminate() method runs AFTER the response has been sent to the client,
 * so the database insert never adds latency to the user's request.
 */
class LogApiActivity
{
    protected float $startTime;

    public function handle(Request $request, Closure $next): Response
    {
        $this->startTime = microtime(true);

        return $next($request);
    }

    /**
     * Called after the response has been sent to the browser.
     */
    public function terminate(Request $request, Response $response): void
    {
        try {
            $durationMs = (int) round((microtime(true) - $this->startTime) * 1000);

            // Extract table name from the route parameter
            $tableName = $request->route('tableName') ?? '-';

            DB::table('api_analytics')->insert([
                'user_id' => auth('sanctum')->id() ?? auth()->id(),
                'table_name' => $tableName,
                'method' => $request->method(),
                'status_code' => $response->getStatusCode(),
                'duration_ms' => $durationMs,
                'ip_address' => $request->ip(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Never let analytics logging break the application
            Log::warning('API analytics logging failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}

```

## File: app/Http/Middleware/VerifyApiKey.php
```php
<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;
use App\Models\ApiKey;

class VerifyApiKey
{
    /**
     * Handle an incoming request.
     *
     * Verifies API keys using an indexed O(1) hash lookup via key_hash column
     * instead of loading all keys into memory.
     */
    public function handle(Request $request, Closure $next, ?string $requiredScope = null): Response
    {
        // 1. Extract API Key from Request
        $token = $this->extractToken($request);

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'API Key required. Provide via Authorization: Bearer <key> header or ?api_key= query param.',
                'error_code' => 'MISSING_API_KEY',
            ], 401);
        }

        // 2. Find the Key via indexed hash lookup — O(1) instead of O(n)
        //    Computes SHA-256 of token, looks up by indexed key_hash column,
        //    then verifies with hash_equals() to prevent timing attacks.
        $apiKey = ApiKey::findByToken($token);

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid API Key',
                'error_code' => 'INVALID_API_KEY',
            ], 401);
        }

        // 3. Check if Key is Valid (active + not expired)
        if (!$apiKey->isValid()) {
            $reason = !$apiKey->is_active ? 'Key is deactivated' : 'Key has expired';
            return response()->json([
                'success' => false,
                'message' => $reason,
                'error_code' => 'API_KEY_INVALID',
            ], 403);
        }

        // 4. Check Scope Permission (if required)
        if ($requiredScope && !$apiKey->hasScope($requiredScope)) {
            return response()->json([
                'success' => false,
                'message' => "Insufficient permissions. Required scope: {$requiredScope}",
                'error_code' => 'INSUFFICIENT_SCOPE',
            ], 403);
        }

        // 5. Check Method-Based Permissions
        $methodScope = $this->getMethodScope($request->method());
        if ($methodScope && !$apiKey->hasScope($methodScope)) {
            return response()->json([
                'success' => false,
                'message' => "This API key cannot perform {$request->method()} operations. Required scope: {$methodScope}",
                'error_code' => 'METHOD_NOT_ALLOWED',
            ], 403);
        }

        // 6. Check Table-Level Access (if route has a tableName parameter)
        $tableName = $request->route('tableName');
        if ($tableName && !$apiKey->hasTableAccess($tableName)) {
            return response()->json([
                'success' => false,
                'message' => "This API key does not have access to table '{$tableName}'",
                'error_code' => 'TABLE_ACCESS_DENIED',
            ], 403);
        }

        // 7. Record Usage (throttled: update at most once per minute per key)
        $usageCacheKey = "api_key_usage:{$apiKey->id}";
        if (!Cache::has($usageCacheKey)) {
            $apiKey->recordUsage();
            Cache::put($usageCacheKey, true, 60);
        }

        // 8. Attach Key to Request for use in Controllers
        $request->attributes->set('api_key', $apiKey);
        $request->attributes->set('api_key_user', $apiKey->user);

        return $next($request);
    }

    /**
     * Extract token from various sources.
     */
    protected function extractToken(Request $request): ?string
    {
        // Priority 1: Authorization: Bearer header
        if ($bearer = $request->bearerToken()) {
            return $bearer;
        }

        // Priority 2: X-API-Key header
        if ($header = $request->header('X-API-Key')) {
            return $header;
        }

        // Priority 3: Query parameter (for testing/simple clients)
        if ($query = $request->query('api_key')) {
            return $query;
        }

        return null;
    }

    /**
     * Map HTTP method to required scope.
     */
    protected function getMethodScope(string $method): ?string
    {
        return match (strtoupper($method)) {
            'GET', 'HEAD', 'OPTIONS' => 'read',
            'POST' => 'write',
            'PUT', 'PATCH' => 'write',
            'DELETE' => 'delete',
            default => null,
        };
    }
}

```

## File: app/Http/Traits/TurboCache.php
```php
<?php

namespace App\Http\Traits;

use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;

/**
 * Turbo Cache Trait
 *
 * Smart response caching for Dynamic Data API.
 * Uses a dedicated 'digibase' cache store so invalidation
 * never nukes sessions, routes, or other application cache.
 */
trait TurboCache
{
    protected int $cacheDuration = 300;

    /**
     * Get the dedicated Digibase cache store.
     */
    protected function cacheStore()
    {
        return Cache::store('digibase');
    }

    /**
     * Get cached response or execute callback and cache result.
     */
    protected function cached(string $tableName, Request $request, callable $callback): mixed
    {
        if ($this->shouldSkipCache($request)) {
            return $callback();
        }

        $cacheKey = $this->buildCacheKey($tableName, $request);
        $store = $this->cacheStore();

        if ($this->supportsTagging()) {
            $tags = $this->getCacheTags($tableName);
            return $store->tags($tags)->remember($cacheKey, $this->cacheDuration, $callback);
        }

        return $store->remember($cacheKey, $this->cacheDuration, $callback);
    }

    /**
     * Build a unique cache key based on request parameters + auth context.
     */
    protected function buildCacheKey(string $tableName, Request $request): string
    {
        $params = $request->only([
            'include',
            'search',
            'page',
            'per_page',
            'sort',
            'order',
            'direction',
            'filter',
        ]);

        // Include auth context so RLS-filtered results aren't shared across users
        $authId = auth('sanctum')->id() ?? 'anon';
        $params['_auth'] = $authId;

        // Include API key ID so different keys with different scopes get different caches
        $apiKey = $request->attributes->get('api_key');
        $params['_key'] = $apiKey?->id ?? 'none';

        ksort($params);

        $hash = md5(json_encode($params));

        return "digibase:data:{$tableName}:{$hash}";
    }

    protected function getCacheTags(string $tableName): array
    {
        return [
            'digibase',
            "digibase:{$tableName}",
        ];
    }

    /**
     * Clear cache for a specific table.
     */
    protected function clearTableCache(string $tableName): void
    {
        $store = $this->cacheStore();

        if ($this->supportsTagging()) {
            $store->tags(["digibase:{$tableName}"])->flush();
        } else {
            // Flush only the dedicated digibase store — safe, no collateral damage
            $store->flush();
        }
    }

    /**
     * Clear all Digibase API cache.
     */
    protected function clearAllCache(): void
    {
        $store = $this->cacheStore();

        if ($this->supportsTagging()) {
            $store->tags(['digibase'])->flush();
        } else {
            $store->flush();
        }
    }

    /**
     * Check if the digibase cache store supports tagging.
     */
    protected function supportsTagging(): bool
    {
        $driver = config('cache.stores.digibase.driver', 'file');
        return in_array($driver, ['redis', 'memcached', 'dynamodb']);
    }

    protected function shouldSkipCache(Request $request): bool
    {
        if (!$request->isMethod('GET')) {
            return true;
        }

        if ($request->boolean('nocache')) {
            return true;
        }

        if ($request->header('Cache-Control') === 'no-cache') {
            return true;
        }

        return false;
    }

    protected function setCacheDuration(int $seconds): self
    {
        $this->cacheDuration = $seconds;
        return $this;
    }

    protected function withCacheHeaders($response, int $maxAge = 60): mixed
    {
        if (method_exists($response, 'header')) {
            $response->header('Cache-Control', "public, max-age={$maxAge}");
            $response->header('X-Cache-Status', 'HIT');
        }
        return $response;
    }
}

```

## File: app/Models/ApiAnalytics.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ApiAnalytics extends Model
{
    protected $table = 'api_analytics';
    protected $guarded = [];
    public $timestamps = false; // Using manual created_at in middleware

    protected $casts = [
        'created_at' => 'datetime',
    ];
}

```

## File: app/Models/ApiKey.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiKey extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'key',
        'key_hash',         // SHA-256 of key for indexed O(1) lookup
        'type',             // 'public' or 'secret'
        'scopes',           // JSON array: ['read', 'write', 'delete']
        'allowed_tables',   // JSON array: ['posts', 'comments'] or null/empty for all
        'rate_limit',       // Requests per minute
        'is_active',
        'expires_at',
        'last_used_at',
    ];

    protected $casts = [
        'scopes' => 'array',
        'allowed_tables' => 'array',
        'is_active' => 'boolean',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
    ];

    protected $hidden = [
        'key', // Never expose the full key in responses
    ];

    protected static function booted(): void
    {
        static::saving(function (ApiKey $apiKey) {
            // Auto-compute key_hash whenever the key is set or changed
            if ($apiKey->isDirty('key') && $apiKey->key) {
                $apiKey->key_hash = hash('sha256', $apiKey->key);
            }
        });
    }

    /**
     * Find an API key by its plain-text token using indexed hash lookup.
     * Returns null if not found or inactive.
     */
    public static function findByToken(string $token): ?self
    {
        $hash = hash('sha256', $token);

        $apiKey = static::where('key_hash', $hash)
            ->where('is_active', true)
            ->first();

        // Final constant-time verification to prevent any hash collision attack
        if ($apiKey && hash_equals($apiKey->key, $token)) {
            return $apiKey;
        }

        return null;
    }

    /**
     * The user who owns this API key.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate a new API key.
     * 
     * @param string $type 'public' or 'secret'
     * @return string The plain text key (only shown once!)
     */
    public static function generateKey(string $type = 'public'): string
    {
        $prefix = $type === 'secret' ? 'sk_' : 'pk_';
        return $prefix . Str::random(32);
    }

    /**
     * Check if key has a specific scope.
     */
    public function hasScope(string $scope): bool
    {
        $scopes = $this->scopes ?? [];
        return in_array('*', $scopes) || in_array($scope, $scopes);
    }

    /**
     * Check if key can read data.
     */
    public function canRead(): bool
    {
        return $this->hasScope('read') || $this->hasScope('*');
    }

    /**
     * Check if key can write data.
     */
    public function canWrite(): bool
    {
        return $this->hasScope('write') || $this->hasScope('*');
    }

    /**
     * Check if key can delete data.
     */
    public function canDelete(): bool
    {
        return $this->hasScope('delete') || $this->hasScope('*');
    }

    /**
     * Check if key has access to a specific table.
     * Null/empty allowed_tables means access to all tables.
     */
    public function hasTableAccess(string $tableName): bool
    {
        $allowed = $this->allowed_tables;

        // Null or empty array = unrestricted access to all tables
        if (empty($allowed)) {
            return true;
        }

        return in_array($tableName, $allowed);
    }

    /**
     * Check if the key is valid (active and not expired).
     */
    public function isValid(): bool
    {
        if (!$this->is_active) {
            return false;
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return true;
    }

    /**
     * Record that this key was used.
     */
    public function recordUsage(): void
    {
        $this->update(['last_used_at' => now()]);
    }

    /**
     * Get masked key for display (pk_xxxx...xxxx).
     */
    public function getMaskedKeyAttribute(): string
    {
        $key = $this->key;
        if (strlen($key) < 10) {
            return str_repeat('*', strlen($key));
        }
        return substr($key, 0, 6) . '...' . substr($key, -4);
    }

    /**
     * Scope: Active keys only.
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Scope: Non-expired keys only.
     */
    public function scopeNotExpired($query)
    {
        return $query->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        });
    }
}

```

## File: app/Models/DynamicField.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DynamicField extends Model
{
    use HasFactory;

    protected $fillable = [
        'dynamic_model_id',
        'name',
        'display_name',
        'type',
        'description',
        'is_required',
        'is_unique',
        'is_indexed',
        'is_searchable',
        'is_filterable',
        'is_sortable',
        'show_in_list',
        'show_in_detail',
        'is_hidden',
        'default_value',
        'validation_rules',
        'options',
        'order',
    ];

    protected function casts(): array
    {
        return [
            'is_required' => 'boolean',
            'is_unique' => 'boolean',
            'is_indexed' => 'boolean',
            'is_searchable' => 'boolean',
            'is_filterable' => 'boolean',
            'is_sortable' => 'boolean',
            'show_in_list' => 'boolean',
            'show_in_detail' => 'boolean',
            'is_hidden' => 'boolean',
            'validation_rules' => 'array',
            'options' => 'array',
        ];
    }

    public function dynamicModel(): BelongsTo
    {
        return $this->belongsTo(DynamicModel::class);
    }

    /**
     * Get the database column type for this field type.
     */
    public function getDatabaseType(): string
    {
        return match ($this->type) {
            'string', 'email', 'url', 'phone', 'slug', 'password', 'color', 'encrypted' => 'string',
            'text', 'richtext', 'markdown' => 'text',
            'integer' => 'integer',
            'bigint' => 'bigInteger',
            'float' => 'float',
            'decimal', 'money' => 'decimal',
            'boolean', 'checkbox' => 'boolean',
            'date' => 'date',
            'datetime', 'timestamp' => 'dateTime',
            'time' => 'time',
            'json', 'array' => 'json',
            'uuid' => 'uuid',
            'enum', 'select' => 'string',
            'file', 'image' => 'string',
            'point' => 'geometry',
            default => 'string',
        };
    }
}

```

## File: app/Models/DynamicModel.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DynamicModel extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'table_name',
        'display_name',
        'description',
        'icon',
        'is_active',
        'has_timestamps',
        'has_soft_deletes',
        'generate_api',
        'settings',
        // RLS Rules
        'list_rule',
        'view_rule',
        'create_rule',
        'update_rule',
        'delete_rule',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'has_timestamps' => 'boolean',
            'has_soft_deletes' => 'boolean',
            'generate_api' => 'boolean',
            'settings' => 'array',
            'is_syncing' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function fields(): HasMany
    {
        return $this->hasMany(DynamicField::class)->orderBy('order');
    }

    public function relationships(): HasMany
    {
        return $this->hasMany(DynamicRelationship::class);
    }

    public function relatedTo(): HasMany
    {
        return $this->hasMany(DynamicRelationship::class, 'related_model_id');
    }
}

```

## File: app/Models/DynamicRecord.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Activitylog\LogOptions;

/**
 * DynamicRecord: A flexible model for dynamic tables.
 * 
 * Now supports Spatie Media Library for professional file handling.
 * Event broadcasting and cache clearing are handled by
 * DynamicRecordObserver (registered in AppServiceProvider).
 */
class DynamicRecord extends Model implements HasMedia
{
    use InteractsWithMedia;
    use LogsActivity;

    protected $guarded = [];

    /**
     * Allow setting the table name dynamically at runtime.
     * 
     * @param string $tableName
     * @return $this
     */
    public function setDynamicTable(string $tableName): self
    {
        $this->setTable($tableName);
        return $this;
    }

    /**
     * Override getMorphClass to support dynamic tables in Spatie Media Library.
     */
    public function getMorphClass()
    {
        return $this->getTable();
    }

    /**
     * Register media collections for this model.
     * Supports multiple file types with automatic optimization.
     */
    public function registerMediaCollections(): void
    {
        // General files collection
        $this->addMediaCollection('files')
            ->useDisk('digibase_storage')
            ->acceptsMimeTypes([
                // Images
                'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
                // Documents
                'application/pdf', 'application/msword', 
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'application/vnd.ms-excel',
                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'text/plain', 'text/csv',
                // Archives
                'application/zip', 'application/x-rar-compressed',
                // Media
                'video/mp4', 'video/webm', 'audio/mpeg', 'audio/wav',
            ]);

        // Images collection with automatic optimization
        $this->addMediaCollection('images')
            ->useDisk('digibase_storage')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
            ->registerMediaConversions(function () {
                $this->addMediaConversion('thumb')
                    ->width(150)
                    ->height(150)
                    ->sharpen(10)
                    ->nonQueued();

                $this->addMediaConversion('preview')
                    ->width(800)
                    ->height(600)
                    ->sharpen(10)
                    ->nonQueued();
            });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs()
            ->useLogName($this->getTable())
            ->logAll();
    }
    /**
     * Override toArray to strict snake_case keys globally.
     * Also strips internal table prefixes from joined relations.
     */
    public function toArray()
    {
        $attributes = parent::toArray();
        $cleaned = [];
        $prefix = $this->getTable() . '__';

        foreach ($attributes as $key => $value) {
            // 1. Strip Prefix (e.g. mobile_phones__Phone Name -> Phone Name)
            if (is_string($key) && str_starts_with($key, $prefix)) {
                $key = substr($key, strlen($prefix));
            }
            
            // 2. Snake Case Conversion (e.g. Phone Name -> phone_name)
            $cleaned[\Illuminate\Support\Str::snake($key)] = $value;
        }

        return $cleaned;
    }
}

```

## File: app/Models/DynamicRelationship.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DynamicRelationship extends Model
{
    use HasFactory;

    protected $fillable = [
        'dynamic_model_id',
        'related_model_id',
        'name',
        'type',
        'foreign_key',
        'local_key',
        'pivot_table',
        'method_name', // 👈 THIS WAS MISSING (Crucial Fix)
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'settings' => 'array',
        ];
    }

    public function dynamicModel(): BelongsTo
    {
        return $this->belongsTo(DynamicModel::class);
    }

    public function relatedModel(): BelongsTo
    {
        return $this->belongsTo(DynamicModel::class, 'related_model_id');
    }
}

```

## File: app/Models/SystemSetting.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class SystemSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'group',
        'is_encrypted',
        'description',
    ];

    protected $casts = [
        'is_encrypted' => 'boolean',
    ];

    /**
     * Get a setting value, decrypting if necessary.
     */
    public static function get(string $key, $default = null)
    {
        try {
            $setting = static::where('key', $key)->first();

            if (!$setting) {
                return $default;
            }

            if ($setting->is_encrypted) {
                try {
                    return Crypt::decryptString($setting->value);
                } catch (\Exception $e) {
                    Log::error("Failed to decrypt setting {$key}: " . $e->getMessage());
                    return $default;
                }
            }

            return $setting->value;
        } catch (\Exception $e) {
            // Should fail gracefully if the table doesn't exist yet (e.g. during migration)
            return $default;
        }
    }

    /**
     * Set a setting value, encrypting if specified.
     */
    public static function set(string $key, $value, string $group = 'system', bool $encrypt = false, ?string $description = null)
    {
        $payload = [
            'value' => $encrypt ? Crypt::encryptString($value) : $value,
            'group' => $group,
            'is_encrypted' => $encrypt,
        ];

        if ($description) {
            $payload['description'] = $description;
        }

        static::updateOrCreate(
            ['key' => $key],
            $payload
        );
    }
}

```

## File: app/Models/User.php
```php
<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Jeffgreco13\FilamentBreezy\Traits\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function canAccessPanel(\Filament\Panel $panel): bool
    {
        return true;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&color=7F9CF5&background=EBF4FF';
    }
}

```

## File: app/Models/Webhook.php
```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Webhook extends Model
{
    use HasFactory;

    protected $fillable = [
        'dynamic_model_id',
        'name',
        'url',
        'secret',
        'events',
        'headers',
        'is_active',
        'last_triggered_at',
        'failure_count',
    ];

    protected function casts(): array
    {
        return [
            'events' => 'array',
            'headers' => 'array',
            'is_active' => 'boolean',
            'last_triggered_at' => 'datetime',
        ];
    }

    /**
     * Get the dynamic model this webhook belongs to.
     */
    public function dynamicModel(): BelongsTo
    {
        return $this->belongsTo(DynamicModel::class);
    }

    /**
     * Check if this webhook should trigger for a given event.
     */
    public function shouldTrigger(string $event): bool
    {
        return $this->is_active && in_array($event, $this->events ?? []);
    }

    /**
     * Generate HMAC signature for payload verification.
     */
    public function generateSignature(array $payload): ?string
    {
        if (empty($this->secret)) {
            return null;
        }

        return hash_hmac('sha256', json_encode($payload), $this->secret);
    }

    /**
     * Record a successful trigger.
     */
    public function recordSuccess(): void
    {
        $this->update([
            'last_triggered_at' => now(),
            'failure_count' => 0,
        ]);
    }

    /**
     * Record a failed trigger.
     */
    public function recordFailure(): void
    {
        $this->increment('failure_count');
        $this->refresh();

        // Auto-disable after 10 consecutive failures
        if ($this->failure_count >= 10) {
            $this->update(['is_active' => false]);
        }
    }
}

```

## File: app/Observers/DynamicRecordObserver.php
```php
<?php

namespace App\Observers;

use App\Models\DynamicModel;
use App\Models\DynamicRecord;
use App\Events\ModelChanged;
use Illuminate\Support\Facades\Cache;

/**
 * Central nervous system for DynamicRecord lifecycle.
 *
 * Handles:
 * - Real-time event broadcasting (with hidden field filtering)
 * - Cache invalidation (using dedicated digibase store)
 *
 * Performance: Hidden field lookups are cached in-process (static array)
 * AND in the digibase cache store (60s TTL) to eliminate N+1 queries
 * during bulk operations.
 */
class DynamicRecordObserver
{
    /**
     * In-process static cache — survives across multiple observer calls
     * within the same request/bulk operation. Zero database queries after
     * the first call for a given table.
     */
    protected static array $hiddenFieldsCache = [];

    public function created(DynamicRecord $record): void
    {
        $tableName = $record->getTable();

        if ($tableName) {
            $data = $this->filterHiddenFields($tableName, $record->toArray());
            event(new ModelChanged($tableName, 'created', $data));
            $this->clearTableCache($tableName);
        }
    }

    public function updated(DynamicRecord $record): void
    {
        $tableName = $record->getTable();

        if ($tableName) {
            // Detect soft delete
            if ($record->wasChanged('deleted_at') && !empty($record->getAttribute('deleted_at'))) {
                event(new ModelChanged($tableName, 'deleted', ['id' => $record->id]));
                $this->clearTableCache($tableName);
                return;
            }

            $data = $this->filterHiddenFields($tableName, $record->toArray());
            event(new ModelChanged($tableName, 'updated', $data));
            $this->clearTableCache($tableName);
        }
    }

    public function deleted(DynamicRecord $record): void
    {
        $tableName = $record->getTable();

        if ($tableName) {
            event(new ModelChanged($tableName, 'deleted', ['id' => $record->id]));
            $this->clearTableCache($tableName);
        }
    }

    /**
     * Remove fields marked as is_hidden in the DynamicModel schema
     * before broadcasting. Prevents leaking passwords, tokens, etc.
     *
     * Uses a two-tier cache:
     * 1. Static in-process array (free, survives the entire request)
     * 2. Digibase cache store with 60s TTL (survives across requests)
     */
    protected function filterHiddenFields(string $tableName, array $data): array
    {
        $hiddenFields = $this->getHiddenFields($tableName);
        $sensitivePatterns = ['password', 'secret', 'token', 'key', 'credential'];

        foreach ($data as $key => $value) {
            if (in_array($key, $hiddenFields)) {
                unset($data[$key]);
                continue;
            }

            foreach ($sensitivePatterns as $pattern) {
                if (stripos($key, $pattern) !== false) {
                    unset($data[$key]);
                    break;
                }
            }
        }

        return $data;
    }

    /**
     * Get hidden field names for a table, with two-tier caching.
     *
     * Tier 1: Static array — zero-cost for bulk operations within one request.
     * Tier 2: Digibase cache store (60s TTL) — avoids DB hit across requests.
     * Tier 3: Database query — only on cold cache.
     */
    protected function getHiddenFields(string $tableName): array
    {
        // Tier 1: In-process static cache
        if (isset(static::$hiddenFieldsCache[$tableName])) {
            return static::$hiddenFieldsCache[$tableName];
        }

        // Tier 2: Digibase cache store
        $cacheKey = "observer:hidden_fields:{$tableName}";

        try {
            $fields = Cache::store('digibase')->remember($cacheKey, 60, function () use ($tableName) {
                // Tier 3: Database query (only runs on cold cache)
                $model = DynamicModel::where('table_name', $tableName)
                    ->with('fields')
                    ->first();

                if (!$model) {
                    return [];
                }

                return $model->fields
                    ->where('is_hidden', true)
                    ->pluck('name')
                    ->map(fn($name) => \Illuminate\Support\Str::snake($name))
                    ->toArray();
            });
        } catch (\Throwable) {
            $fields = [];
        }

        // Store in static cache for the rest of this request
        static::$hiddenFieldsCache[$tableName] = $fields;

        return $fields;
    }

    /**
     * Clear cache for a specific table using the dedicated digibase store.
     * Also clears the observer's hidden fields cache for immediate effect.
     */
    protected function clearTableCache(string $tableName): void
    {
        $store = Cache::store('digibase');
        $driver = config('cache.stores.digibase.driver', 'file');

        if (in_array($driver, ['redis', 'memcached', 'dynamodb'])) {
            $store->tags(["digibase:{$tableName}"])->flush();
        } else {
            // For file/database: flush the entire dedicated digibase store.
            // This is safe because it only contains API cache, not sessions/routes.
            $store->flush();
        }

        // Also clear the observer's cached hidden fields for this table
        // so schema changes take effect immediately
        unset(static::$hiddenFieldsCache[$tableName]);
        $store->forget("observer:hidden_fields:{$tableName}");
    }
}

```

## File: app/Policies/ApiKeyPolicy.php
```php
<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\ApiKey;
use Illuminate\Auth\Access\HandlesAuthorization;

class ApiKeyPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:ApiKey');
    }

    public function view(AuthUser $authUser, ApiKey $apiKey): bool
    {
        return $authUser->can('View:ApiKey');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:ApiKey');
    }

    public function update(AuthUser $authUser, ApiKey $apiKey): bool
    {
        return $authUser->can('Update:ApiKey');
    }

    public function delete(AuthUser $authUser, ApiKey $apiKey): bool
    {
        return $authUser->can('Delete:ApiKey');
    }

    public function restore(AuthUser $authUser, ApiKey $apiKey): bool
    {
        return $authUser->can('Restore:ApiKey');
    }

    public function forceDelete(AuthUser $authUser, ApiKey $apiKey): bool
    {
        return $authUser->can('ForceDelete:ApiKey');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:ApiKey');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:ApiKey');
    }

    public function replicate(AuthUser $authUser, ApiKey $apiKey): bool
    {
        return $authUser->can('Replicate:ApiKey');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:ApiKey');
    }

}
```

## File: app/Policies/DynamicModelPolicy.php
```php
<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\DynamicModel;
use Illuminate\Auth\Access\HandlesAuthorization;

class DynamicModelPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:DynamicModel');
    }

    public function view(AuthUser $authUser, DynamicModel $dynamicModel): bool
    {
        return $authUser->can('View:DynamicModel');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:DynamicModel');
    }

    public function update(AuthUser $authUser, DynamicModel $dynamicModel): bool
    {
        return $authUser->can('Update:DynamicModel');
    }

    public function delete(AuthUser $authUser, DynamicModel $dynamicModel): bool
    {
        return $authUser->can('Delete:DynamicModel');
    }

    public function restore(AuthUser $authUser, DynamicModel $dynamicModel): bool
    {
        return $authUser->can('Restore:DynamicModel');
    }

    public function forceDelete(AuthUser $authUser, DynamicModel $dynamicModel): bool
    {
        return $authUser->can('ForceDelete:DynamicModel');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:DynamicModel');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:DynamicModel');
    }

    public function replicate(AuthUser $authUser, DynamicModel $dynamicModel): bool
    {
        return $authUser->can('Replicate:DynamicModel');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:DynamicModel');
    }

}
```

## File: app/Policies/FileSystemItemPolicy.php
```php
<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use MWGuerra\FileManager\Models\FileSystemItem;
use Illuminate\Auth\Access\HandlesAuthorization;

class FileSystemItemPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:FileSystemItem');
    }

    public function view(AuthUser $authUser, FileSystemItem $fileSystemItem): bool
    {
        return $authUser->can('View:FileSystemItem');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:FileSystemItem');
    }

    public function update(AuthUser $authUser, FileSystemItem $fileSystemItem): bool
    {
        return $authUser->can('Update:FileSystemItem');
    }

    public function delete(AuthUser $authUser, FileSystemItem $fileSystemItem): bool
    {
        return $authUser->can('Delete:FileSystemItem');
    }

    public function restore(AuthUser $authUser, FileSystemItem $fileSystemItem): bool
    {
        return $authUser->can('Restore:FileSystemItem');
    }

    public function forceDelete(AuthUser $authUser, FileSystemItem $fileSystemItem): bool
    {
        return $authUser->can('ForceDelete:FileSystemItem');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:FileSystemItem');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:FileSystemItem');
    }

    public function replicate(AuthUser $authUser, FileSystemItem $fileSystemItem): bool
    {
        return $authUser->can('Replicate:FileSystemItem');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:FileSystemItem');
    }

}
```

## File: app/Policies/RolePolicy.php
```php
<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Spatie\Permission\Models\Role;
use Illuminate\Auth\Access\HandlesAuthorization;

class RolePolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Role');
    }

    public function view(AuthUser $authUser, Role $role): bool
    {
        return $authUser->can('View:Role');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Role');
    }

    public function update(AuthUser $authUser, Role $role): bool
    {
        return $authUser->can('Update:Role');
    }

    public function delete(AuthUser $authUser, Role $role): bool
    {
        return $authUser->can('Delete:Role');
    }

    public function restore(AuthUser $authUser, Role $role): bool
    {
        return $authUser->can('Restore:Role');
    }

    public function forceDelete(AuthUser $authUser, Role $role): bool
    {
        return $authUser->can('ForceDelete:Role');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Role');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Role');
    }

    public function replicate(AuthUser $authUser, Role $role): bool
    {
        return $authUser->can('Replicate:Role');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Role');
    }

}
```

## File: app/Policies/UserPolicy.php
```php
<?php

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:User');
    }

    public function view(AuthUser $authUser): bool
    {
        return $authUser->can('View:User');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:User');
    }

    public function update(AuthUser $authUser): bool
    {
        return $authUser->can('Update:User');
    }

    public function delete(AuthUser $authUser): bool
    {
        return $authUser->can('Delete:User');
    }

    public function restore(AuthUser $authUser): bool
    {
        return $authUser->can('Restore:User');
    }

    public function forceDelete(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDelete:User');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:User');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:User');
    }

    public function replicate(AuthUser $authUser): bool
    {
        return $authUser->can('Replicate:User');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:User');
    }

}
```

## File: app/Policies/WebhookPolicy.php
```php
<?php

declare(strict_types=1);

namespace App\Policies;

use Illuminate\Foundation\Auth\User as AuthUser;
use App\Models\Webhook;
use Illuminate\Auth\Access\HandlesAuthorization;

class WebhookPolicy
{
    use HandlesAuthorization;
    
    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('ViewAny:Webhook');
    }

    public function view(AuthUser $authUser, Webhook $webhook): bool
    {
        return $authUser->can('View:Webhook');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('Create:Webhook');
    }

    public function update(AuthUser $authUser, Webhook $webhook): bool
    {
        return $authUser->can('Update:Webhook');
    }

    public function delete(AuthUser $authUser, Webhook $webhook): bool
    {
        return $authUser->can('Delete:Webhook');
    }

    public function restore(AuthUser $authUser, Webhook $webhook): bool
    {
        return $authUser->can('Restore:Webhook');
    }

    public function forceDelete(AuthUser $authUser, Webhook $webhook): bool
    {
        return $authUser->can('ForceDelete:Webhook');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('ForceDeleteAny:Webhook');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('RestoreAny:Webhook');
    }

    public function replicate(AuthUser $authUser, Webhook $webhook): bool
    {
        return $authUser->can('Replicate:Webhook');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('Reorder:Webhook');
    }

}
```

## File: app/Providers/AppServiceProvider.php
```php
<?php

namespace App\Providers;

use Filament\Facades\Filament;
use Filament\Support\Colors\Color;
use Dedoc\Scramble\Scramble;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\SecurityScheme;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\ServiceProvider;
use App\Models\DynamicRecord;
use App\Observers\DynamicRecordObserver;
use Laravel\Pulse\Facades\Pulse;
use Spatie\Health\Facades\Health;
use Spatie\Health\Checks\Checks\OptimizedAppCheck;
use Spatie\Health\Checks\Checks\DebugModeCheck;
use Spatie\Health\Checks\Checks\EnvironmentCheck;
use Spatie\Health\Checks\Checks\DatabaseCheck;
use Spatie\Health\Checks\Checks\UsedDiskSpaceCheck;
use App\Settings\GeneralSettings;
use App\Models\SystemSetting;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // 🧠 CENTRAL NERVOUS SYSTEM: Register the Observer
        DynamicRecord::observe(DynamicRecordObserver::class);

        // 🔒 SECURITY: Log Viewer Access Control
        $this->configureLogViewerSecurity();

        // 🩺 MONITORING: Pulse Dashboard Access Control
        $this->configurePulseSecurity();

        // 🩺 HEALTH: System Health Checks
        $this->configureHealthChecks();

        Scramble::extendOpenApi(function (OpenApi $openApi) {
            $openApi->secure(
                SecurityScheme::http('bearer')
            );
        });

        $this->configureBranding();
        $this->configureStorage();
    }

    /**
     * 🔒 SECURITY: Restrict Log Viewer to Admins Only
     * Only User ID 1 or users with is_admin flag can access logs.
     */
    private function configureLogViewerSecurity(): void
    {
        Gate::define('viewLogViewer', function ($user) {
            // Allow User ID 1 (super admin) or users with is_admin flag
            return $user->id === 1 || ($user->is_admin ?? false);
        });
    }

    private function configurePulseSecurity(): void
    {
        Gate::define('viewPulse', function ($user) {
            return $user->id === 1;
        });
    }

    /**
     * 🩺 HEALTH CHECKS: Register system health monitoring.
     * Dashboard available via Filament Health plugin.
     */
    private function configureHealthChecks(): void
    {
        $isProduction = app()->environment('production', 'staging');

        $checks = [
            DebugModeCheck::new()
                ->expectedToBe(!$isProduction),
            EnvironmentCheck::new()
                ->expectEnvironment(app()->environment()),
            DatabaseCheck::new(),
            UsedDiskSpaceCheck::new()
                ->warnWhenUsedSpaceIsAbovePercentage(70)
                ->failWhenUsedSpaceIsAbovePercentage(90),
        ];

        // Optimized App only matters in production (caching configs/routes)
        if ($isProduction) {
            $checks[] = OptimizedAppCheck::new();
        }

        Health::checks($checks);
    }

    private function configureBranding(): void
    {
        try {
            if (!function_exists('db_config')) return;

            // Branding
            $appName = db_config('branding.site_name');
            $logo = db_config('branding.site_logo');
            $primaryColor = db_config('branding.primary_color');

            // Override Laravel app name
            if ($appName) {
                config(['app.name' => $appName]);
            }

            // Override Filament panel branding dynamically
            Filament::serving(function () use ($appName, $logo, $primaryColor) {
                $panel = Filament::getCurrentPanel();

                if (! $panel) {
                    return;
                }

                if ($appName) {
                    $panel->brandName($appName);
                }

                if ($logo) {
                    $logoUrl = str_starts_with($logo, 'http') ? $logo : Storage::url($logo);
                    $panel->brandLogo($logoUrl)->brandLogoHeight('2rem');
                }

                if ($primaryColor) {
                    $color = trim($primaryColor, '"');
                    if ($color) {
                        $panel->colors(['primary' => Color::hex($color)]);
                    }
                }
            });
        } catch (\Exception) {
            return;
        }
    }

    /**
     * ☁️ UNIVERSAL STORAGE ADAPTER: Dynamic filesystem configuration.
     * Loads settings from DB and configures the 'digibase_storage' disk.
     */
    private function configureStorage(): void
    {
        try {
            // Early return if settings table doesn't exist yet
            if (!Schema::hasTable('spatie_settings')) return;

            $settings = app(GeneralSettings::class);

            // 🚀 MIGRATION: Migrate legacy SystemSettings if needed
            try {
                if (Schema::hasTable('system_settings') && \Illuminate\Support\Facades\DB::table('system_settings')->count() > 0 && \Illuminate\Support\Facades\DB::table('spatie_settings')->where('group', 'general')->doesntExist()) {
                    $legacy = \Illuminate\Support\Facades\DB::table('system_settings')->pluck('value', 'key');
                    
                    $settings->storage_driver = $legacy['storage_driver'] ?? 'local';
                    $settings->aws_access_key_id = $legacy['aws_access_key_id'] ?? null;
                    
                    try {
                        $settings->aws_secret_access_key = isset($legacy['aws_secret_access_key']) 
                            ? Crypt::decryptString($legacy['aws_secret_access_key']) 
                            : null;
                    } catch (\Exception $e) {
                        // If decryption fails, likely stored as plain text or invalid. Keep raw or null.
                        // Assuming raw if decryption fails is risky but better than crash. 
                        // Actually, if it fails, let's leave it null or try raw? 
                        // Let's fallback to null safely to allow admin to reset it.
                        \Illuminate\Support\Facades\Log::warning("Failed to decrypt aws_secret_access_key during migration: " . $e->getMessage());
                        $settings->aws_secret_access_key = null;
                    }

                    $settings->aws_default_region = $legacy['aws_default_region'] ?? 'us-east-1';
                    $settings->aws_bucket = $legacy['aws_bucket'] ?? null;
                    $settings->aws_endpoint = $legacy['aws_endpoint'] ?? null;
                    $settings->aws_use_path_style = $legacy['aws_use_path_style'] ?? 'false';
                    $settings->aws_url = $legacy['aws_url'] ?? null;
                    
                    $settings->save();
                    
                    // Drop the old table to complete the migration
                    Schema::dropIfExists('system_settings');
                }
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error("Settings migration failed: " . $e->getMessage());
            }

            $driver = $settings->storage_driver ?? 'local';
            
            // Build the configuration for our dynamic 'digibase_storage' disk
            $storageConfig = [
                'driver' => $driver,
                'visibility' => 'public',
                'throw' => false,
                'report' => false,
            ];

            if ($driver === 's3') {
                $storageConfig = array_merge($storageConfig, [
                    'key' => $settings->aws_access_key_id,
                    'secret' => $settings->aws_secret_access_key,
                    'region' => $settings->aws_default_region,
                    'bucket' => $settings->aws_bucket,
                    'endpoint' => $settings->aws_endpoint,
                    'use_path_style_endpoint' => $settings->aws_use_path_style === 'true',
                    'url' => $settings->aws_url,
                ]);

                // Also update default s3 and default disk for global Laravel operations
                config(['filesystems.default' => 's3']);
                config(['filesystems.disks.s3' => array_merge(config('filesystems.disks.s3', []), $storageConfig)]);
            } else {
                // Local configuration
                $storageConfig = array_merge($storageConfig, [
                    'root' => storage_path('app/public'),
                    'url' => rtrim(config('app.url', 'http://localhost'), '/').'/storage',
                ]);
            }

            // 🚀 Register the critical 'digibase_storage' disk used by the Core Engine
            config(['filesystems.disks.digibase_storage' => $storageConfig]);

            // 🚀 FORCE Livewire to use our storage disk for temp uploads to ensure visibility & S3 compatibility
            config(['livewire.temporary_file_upload.disk' => 'digibase_storage']);

        } catch (\Exception $e) {
             \Illuminate\Support\Facades\Log::error("Failed to configure storage: " . $e->getMessage());
        }
    }
}

```

## File: app/Providers/Filament/AdminPanelProvider.php
```php
<?php

namespace App\Providers\Filament;

use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Widgets;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use MWGuerra\FileManager\FileManagerPlugin;
use Inerba\DbConfig\DbConfigPlugin;
use BezhanSalleh\FilamentShield\FilamentShieldPlugin;
use pxlrbt\FilamentSpotlight\SpotlightPlugin;
use ShuvroRoy\FilamentSpatieLaravelBackup\FilamentSpatieLaravelBackupPlugin;
use ShuvroRoy\FilamentSpatieLaravelHealth\FilamentSpatieLaravelHealthPlugin;
use Jeffgreco13\FilamentBreezy\BreezyCore;
use Filament\Navigation\NavigationItem;
use Filament\View\PanelsRenderHook;
use Illuminate\Support\Facades\Blade;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => Color::Indigo,
                'gray' => Color::Slate,
            ])
            ->font('Inter')
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->sidebarCollapsibleOnDesktop()
            ->maxContentWidth('full')
            ->navigationGroups([
                'Administration',
                'Database',
                'Developers',
                'Settings',
                'System',
            ])
            ->plugin(
                FileManagerPlugin::make([
                    \MWGuerra\FileManager\Filament\Pages\FileManager::class,
                    \MWGuerra\FileManager\Filament\Pages\FileSystem::class,
                ])
                ->withoutSchemaExample()
            )
            ->plugin(
                DbConfigPlugin::make()
            )
            ->plugin(
                FilamentShieldPlugin::make()
            )
            ->plugin(
                SpotlightPlugin::make()
            )
            ->plugin(
                FilamentSpatieLaravelBackupPlugin::make()
                    ->usingPage(\App\Filament\Pages\Backups::class)
                    ->noTimeout()
                    ->authorize(fn () => auth()->check() && auth()->id() === 1)
            )
            ->plugin(
                BreezyCore::make()
                    ->myProfile(
                        shouldRegisterUserMenu: true,
                        shouldRegisterNavigation: false,
                        hasAvatars: true,
                        slug: 'my-profile'
                    )
                    ->enableTwoFactorAuthentication()
            )
            ->plugin(
                FilamentSpatieLaravelHealthPlugin::make()
                    ->authorize(fn () => auth()->check() && auth()->id() === 1)
            )
            ->databaseNotifications()
            ->navigationItems([
                NavigationItem::make('API Docs')
                    ->url('/docs/api')
                    ->icon('heroicon-o-book-open')
                    ->group('Developers')
                    ->sort(99)
                    ->openUrlInNewTab(),
                NavigationItem::make('Pulse Monitor')
                    ->url('/pulse', shouldOpenInNewTab: true)
                    ->icon('heroicon-o-heart')
                    ->group('System')
                    ->sort(99)
                    ->visible(fn () => auth()->check() && auth()->id() === 1),
                NavigationItem::make('Log Viewer')
                    ->url(url('/log-viewer'), shouldOpenInNewTab: true)
                    ->icon('heroicon-o-bug-ant')
                    ->group('System')
                    ->sort(100)
                    ->visible(fn () => auth()->check() && (auth()->id() === 1 || auth()->user()->is_admin ?? false)),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                Pages\Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->widgets([
                Widgets\AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->renderHook(
                PanelsRenderHook::AUTH_LOGIN_FORM_AFTER,
                fn () => app()->environment('local', 'testing') ? Blade::render(<<<'HTML'
                    <div class="mt-4 border-t pt-4">
                        <p class="text-xs text-center text-gray-500 mb-2 font-medium">DEV QUICK LOGIN</p>
                        <div class="grid grid-cols-1 gap-2">
                            @foreach(\App\Models\User::all() as $user)
                                <a href="{{ route('dev.login', $user->id) }}"
                                   class="block w-full px-3 py-2 text-sm text-center text-gray-700 bg-gray-50 border border-gray-200 rounded-lg hover:bg-gray-100 transition-colors">
                                    Login as <strong>{{ $user->name }}</strong>
                                    <span class="block text-xs text-gray-400">{{ $user->email }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                HTML) : ''
            );
    }
}

```

## File: app/Providers/SocialConfigServiceProvider.php
```php
<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;


class SocialConfigServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap services.
     * 
     * Override Laravel's services config with database settings.
     * This allows admins to configure OAuth without touching .env files.
     */
    public function boot(): void
    {
        // Use try-catch to satisfy older installations or migrations
        try {
            if (! function_exists('db_config')) {
                return;
            }

            // Google OAuth
            $googleId = db_config('auth.google_client_id');
            $googleSecret = db_config('auth.google_client_secret');
            
            if (db_config('auth.google_enabled') && $googleId && $googleSecret) {
                config([
                    'services.google.client_id' => $googleId,
                    'services.google.client_secret' => $googleSecret,
                    'services.google.redirect' => db_config('auth.google_redirect_uri') ?: url('/api/auth/google/callback'),
                ]);
            }

            // GitHub OAuth
            $githubId = db_config('auth.github_client_id');
            $githubSecret = db_config('auth.github_client_secret');

            if (db_config('auth.github_enabled') && $githubId && $githubSecret) {
                config([
                    'services.github.client_id' => $githubId,
                    'services.github.client_secret' => $githubSecret,
                    'services.github.redirect' => db_config('auth.github_redirect_uri') ?: url('/api/auth/github/callback'),
                ]);
            }

        } catch (\Exception $e) {
            // Silently fail if db_config is not ready
        }
    }
}

```

## File: app/Services/ApiDocumentationService.php
```php
<?php

namespace App\Services;

use App\Models\DynamicModel;
use App\Models\DynamicField;
use Illuminate\Support\Str;

class ApiDocumentationService
{
    /**
     * Generate complete API documentation for a dynamic model.
     */
    public function generateDocumentation(DynamicModel $model): array
    {
        $model->load('fields');
        
        return [
            'model' => [
                'name' => $model->name,
                'display_name' => $model->display_name,
                'description' => $model->description,
                'table_name' => $model->table_name,
            ],
            'endpoints' => $this->generateEndpoints($model),
            'authentication' => $this->generateAuthenticationDocs(),
            'examples' => $this->generateExamples($model),
            'schema' => $this->generateSchema($model),
            'responses' => $this->generateResponseExamples($model),
            'webhooks' => $this->generateWebhookDocs($model),
        ];
    }

    /**
     * Generate OpenAPI 3.0 specification.
     */
    public function generateOpenApiSpec(DynamicModel $model): array
    {
        $model->load('fields');
        $tableName = $model->table_name;
        
        return [
            'openapi' => '3.0.0',
            'info' => [
                'title' => config('app.name') . ' API - ' . $model->display_name,
                'description' => $model->description ?? "API for {$model->display_name} table",
                'version' => '1.0.0',
                'contact' => [
                    'name' => 'API Support',
                    'url' => config('app.url'),
                ],
            ],
            'servers' => [
                [
                    'url' => config('app.url') . '/api',
                    'description' => 'Production server',
                ],
            ],
            'security' => [
                ['ApiKeyAuth' => []],
            ],
            'components' => [
                'securitySchemes' => [
                    'ApiKeyAuth' => [
                        'type' => 'apiKey',
                        'in' => 'header',
                        'name' => 'x-api-key',
                        'description' => 'API key for authentication (pk_ for read-only, sk_ for full access)',
                    ],
                ],
                'schemas' => [
                    $model->name => $this->generateOpenApiSchema($model),
                    $model->name . 'Input' => $this->generateOpenApiInputSchema($model),
                    'Error' => [
                        'type' => 'object',
                        'properties' => [
                            'message' => ['type' => 'string'],
                            'errors' => ['type' => 'object'],
                        ],
                    ],
                    'PaginatedResponse' => [
                        'type' => 'object',
                        'properties' => [
                            'data' => [
                                'type' => 'array',
                                'items' => ['$ref' => "#/components/schemas/{$model->name}"],
                            ],
                            'meta' => [
                                'type' => 'object',
                                'properties' => [
                                    'current_page' => ['type' => 'integer'],
                                    'last_page' => ['type' => 'integer'],
                                    'per_page' => ['type' => 'integer'],
                                    'total' => ['type' => 'integer'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
            'paths' => $this->generateOpenApiPaths($model),
        ];
    }

    /**
     * Generate OpenAPI paths for all endpoints.
     */
    protected function generateOpenApiPaths(DynamicModel $model): array
    {
        $tableName = $model->table_name;
        $modelName = $model->name;
        
        return [
            "/data/{$tableName}" => [
                'get' => [
                    'summary' => "List all {$model->display_name} records",
                    'tags' => [$model->display_name],
                    'parameters' => [
                        ['name' => 'page', 'in' => 'query', 'schema' => ['type' => 'integer'], 'description' => 'Page number'],
                        ['name' => 'per_page', 'in' => 'query', 'schema' => ['type' => 'integer'], 'description' => 'Items per page'],
                        ['name' => 'sort', 'in' => 'query', 'schema' => ['type' => 'string'], 'description' => 'Sort field'],
                        ['name' => 'search', 'in' => 'query', 'schema' => ['type' => 'string'], 'description' => 'Search term'],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Successful response',
                            'content' => [
                                'application/json' => [
                                    'schema' => ['$ref' => '#/components/schemas/PaginatedResponse'],
                                ],
                            ],
                        ],
                        '403' => ['description' => 'Access denied', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                        '404' => ['description' => 'Model not found', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                    ],
                ],
                'post' => [
                    'summary' => "Create a new {$model->display_name} record",
                    'tags' => [$model->display_name],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => "#/components/schemas/{$modelName}Input"],
                            ],
                        ],
                    ],
                    'responses' => [
                        '201' => [
                            'description' => 'Record created',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'data' => ['$ref' => "#/components/schemas/{$modelName}"],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        '422' => ['description' => 'Validation failed', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                        '403' => ['description' => 'Access denied', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                    ],
                ],
            ],
            "/data/{$tableName}/{id}" => [
                'get' => [
                    'summary' => "Get a single {$model->display_name} record",
                    'tags' => [$model->display_name],
                    'parameters' => [
                        ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Successful response',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'data' => ['$ref' => "#/components/schemas/{$modelName}"],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        '404' => ['description' => 'Record not found', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                    ],
                ],
                'put' => [
                    'summary' => "Update a {$model->display_name} record",
                    'tags' => [$model->display_name],
                    'parameters' => [
                        ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ],
                    'requestBody' => [
                        'required' => true,
                        'content' => [
                            'application/json' => [
                                'schema' => ['$ref' => "#/components/schemas/{$modelName}Input"],
                            ],
                        ],
                    ],
                    'responses' => [
                        '200' => [
                            'description' => 'Record updated',
                            'content' => [
                                'application/json' => [
                                    'schema' => [
                                        'type' => 'object',
                                        'properties' => [
                                            'data' => ['$ref' => "#/components/schemas/{$modelName}"],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                        '422' => ['description' => 'Validation failed', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                        '404' => ['description' => 'Record not found', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                    ],
                ],
                'delete' => [
                    'summary' => "Delete a {$model->display_name} record",
                    'tags' => [$model->display_name],
                    'parameters' => [
                        ['name' => 'id', 'in' => 'path', 'required' => true, 'schema' => ['type' => 'integer']],
                    ],
                    'responses' => [
                        '200' => ['description' => 'Record deleted', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['message' => ['type' => 'string']]]]]],
                        '404' => ['description' => 'Record not found', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/Error']]]],
                    ],
                ],
            ],
        ];
    }

    /**
     * Generate OpenAPI schema for the model.
     */
    protected function generateOpenApiSchema(DynamicModel $model): array
    {
        $properties = [
            'id' => ['type' => 'integer', 'readOnly' => true],
        ];
        
        foreach ($model->fields as $field) {
            $properties[$field->name] = [
                'type' => $this->mapFieldTypeToJsonType($field->type),
                'description' => $field->description ?? $field->display_name,
            ];
        }
        
        if ($model->has_timestamps) {
            $properties['created_at'] = ['type' => 'string', 'format' => 'date-time', 'readOnly' => true];
            $properties['updated_at'] = ['type' => 'string', 'format' => 'date-time', 'readOnly' => true];
        }
        
        return [
            'type' => 'object',
            'properties' => $properties,
        ];
    }

    /**
     * Generate OpenAPI input schema (for POST/PUT).
     */
    protected function generateOpenApiInputSchema(DynamicModel $model): array
    {
        $properties = [];
        $required = [];
        
        foreach ($model->fields as $field) {
            $properties[$field->name] = [
                'type' => $this->mapFieldTypeToJsonType($field->type),
                'description' => $field->description ?? $field->display_name,
            ];
            
            if ($field->is_required) {
                $required[] = $field->name;
            }
        }
        
        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
        ];
    }

    /**
     * Generate response examples for different status codes.
     */
    protected function generateResponseExamples(DynamicModel $model): array
    {
        $sampleData = $this->generateSampleData($model);
        
        return [
            'success' => [
                '200' => [
                    'description' => 'Successful GET request',
                    'example' => [
                        'data' => array_merge(['id' => 1], $sampleData, [
                            'created_at' => '2026-02-09T10:30:00Z',
                            'updated_at' => '2026-02-09T10:30:00Z',
                        ]),
                    ],
                ],
                '201' => [
                    'description' => 'Resource created',
                    'example' => [
                        'data' => array_merge(['id' => 1], $sampleData, [
                            'created_at' => '2026-02-09T10:30:00Z',
                            'updated_at' => '2026-02-09T10:30:00Z',
                        ]),
                    ],
                ],
            ],
            'error' => [
                '400' => [
                    'description' => 'Bad Request',
                    'example' => [
                        'message' => 'Invalid request format',
                    ],
                ],
                '401' => [
                    'description' => 'Unauthorized',
                    'example' => [
                        'message' => 'Invalid or missing API key',
                    ],
                ],
                '403' => [
                    'description' => 'Forbidden',
                    'example' => [
                        'message' => 'Access denied by security rules',
                    ],
                ],
                '404' => [
                    'description' => 'Not Found',
                    'example' => [
                        'message' => 'Record not found',
                    ],
                ],
                '422' => [
                    'description' => 'Validation Error',
                    'example' => [
                        'message' => 'Validation failed',
                        'errors' => [
                            'email' => ['The email field is required.'],
                            'name' => ['The name must be at least 3 characters.'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Generate webhook documentation.
     */
    protected function generateWebhookDocs(DynamicModel $model): array
    {
        $sampleData = $this->generateSampleData($model);
        
        return [
            'description' => 'Webhooks allow you to receive real-time notifications when data changes in your tables.',
            'events' => [
                'created' => [
                    'description' => 'Triggered when a new record is created',
                    'payload' => [
                        'event' => 'created',
                        'table' => $model->table_name,
                        'data' => array_merge(['id' => 1], $sampleData),
                        'timestamp' => '2026-02-09T10:30:00Z',
                    ],
                ],
                'updated' => [
                    'description' => 'Triggered when a record is updated',
                    'payload' => [
                        'event' => 'updated',
                        'table' => $model->table_name,
                        'data' => array_merge(['id' => 1], $sampleData),
                        'timestamp' => '2026-02-09T10:30:00Z',
                    ],
                ],
                'deleted' => [
                    'description' => 'Triggered when a record is deleted',
                    'payload' => [
                        'event' => 'deleted',
                        'table' => $model->table_name,
                        'data' => ['id' => 1],
                        'timestamp' => '2026-02-09T10:30:00Z',
                    ],
                ],
            ],
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent' => 'Digibase-Webhook/1.0',
                'X-Webhook-Event' => 'created|updated|deleted',
                'X-Webhook-Signature' => 'sha256=<signature>',
            ],
            'signature_verification' => [
                'description' => 'Verify webhook authenticity using HMAC SHA256',
                'algorithm' => 'sha256',
                'example_code' => [
                    'php' => <<<'PHP'
$signature = hash_hmac('sha256', $payload, $webhookSecret);
$isValid = hash_equals($signature, $receivedSignature);
PHP,
                    'javascript' => <<<'JS'
const crypto = require('crypto');
const signature = crypto
  .createHmac('sha256', webhookSecret)
  .update(payload)
  .digest('hex');
const isValid = signature === receivedSignature;
JS,
                    'python' => <<<'PYTHON'
import hmac
import hashlib
signature = hmac.new(
    webhook_secret.encode(),
    payload.encode(),
    hashlib.sha256
).hexdigest()
is_valid = signature == received_signature
PYTHON,
                ],
            ],
        ];
    }

    /**
     * Generate endpoint documentation.
     */
    protected function generateEndpoints(DynamicModel $model): array
    {
        $tableName = $model->table_name;
        
        return [
            [
                'method' => 'GET',
                'path' => "/api/data/{$tableName}",
                'description' => "List all {$model->display_name} records",
                'parameters' => [
                    ['name' => 'page', 'type' => 'integer', 'required' => false, 'description' => 'Page number for pagination'],
                    ['name' => 'per_page', 'type' => 'integer', 'required' => false, 'description' => 'Items per page (default: 15)'],
                    ['name' => 'sort', 'type' => 'string', 'required' => false, 'description' => 'Sort field (prefix with - for descending)'],
                    ['name' => 'filter', 'type' => 'object', 'required' => false, 'description' => 'Filter conditions'],
                ],
            ],
            [
                'method' => 'GET',
                'path' => "/api/data/{$tableName}/{id}",
                'description' => "Get a single {$model->display_name} record by ID",
                'parameters' => [
                    ['name' => 'id', 'type' => 'integer', 'required' => true, 'description' => 'Record ID'],
                ],
            ],
            [
                'method' => 'POST',
                'path' => "/api/data/{$tableName}",
                'description' => "Create a new {$model->display_name} record",
                'parameters' => [],
                'body' => $this->generateRequestBody($model),
            ],
            [
                'method' => 'PUT',
                'path' => "/api/data/{$tableName}/{id}",
                'description' => "Update an existing {$model->display_name} record",
                'parameters' => [
                    ['name' => 'id', 'type' => 'integer', 'required' => true, 'description' => 'Record ID'],
                ],
                'body' => $this->generateRequestBody($model, false),
            ],
            [
                'method' => 'DELETE',
                'path' => "/api/data/{$tableName}/{id}",
                'description' => "Delete a {$model->display_name} record",
                'parameters' => [
                    ['name' => 'id', 'type' => 'integer', 'required' => true, 'description' => 'Record ID'],
                ],
            ],
        ];
    }

    /**
     * Generate request body schema.
     */
    protected function generateRequestBody(DynamicModel $model, bool $allRequired = true): array
    {
        $body = [];
        
        foreach ($model->fields as $field) {
            $body[] = [
                'name' => $field->name,
                'type' => $this->mapFieldTypeToJsonType($field->type),
                'required' => $allRequired ? $field->is_required : false,
                'description' => $field->description ?? $field->display_name,
                'validation' => $this->getFieldValidation($field),
            ];
        }
        
        return $body;
    }

    /**
     * Generate authentication documentation.
     */
    protected function generateAuthenticationDocs(): array
    {
        return [
            'type' => 'API Key',
            'header' => 'x-api-key',
            'description' => 'All API requests require an API key in the x-api-key header',
            'key_types' => [
                'pk_' => 'Public key - Read-only access',
                'sk_' => 'Secret key - Full access (read, write, delete)',
            ],
            'example' => 'x-api-key: sk_your_secret_key_here',
        ];
    }

    /**
     * Generate code examples.
     */
    protected function generateExamples(DynamicModel $model): array
    {
        $tableName = $model->table_name;
        $sampleData = $this->generateSampleData($model);
        
        return [
            'curl' => $this->generateCurlExamples($tableName, $sampleData),
            'javascript' => $this->generateJavaScriptExamples($tableName, $sampleData),
            'python' => $this->generatePythonExamples($tableName, $sampleData),
        ];
    }

    /**
     * Generate sample data for examples.
     */
    protected function generateSampleData(DynamicModel $model): array
    {
        $data = [];
        
        foreach ($model->fields as $field) {
            $data[$field->name] = $this->generateSampleValue($field);
        }
        
        return $data;
    }

    /**
     * Generate sample value based on field type.
     */
    protected function generateSampleValue(DynamicField $field): mixed
    {
        return match ($field->type) {
            'string', 'email', 'url', 'phone', 'slug' => "sample_{$field->name}",
            'text', 'richtext', 'markdown' => "This is sample {$field->name} content",
            'integer' => 42,
            'float', 'decimal' => 99.99,
            'boolean' => true,
            'date' => date('Y-m-d'),
            'datetime' => date('Y-m-d H:i:s'),
            'time' => date('H:i:s'),
            'json' => ['key' => 'value'],
            'enum', 'select' => $field->options[0] ?? 'option1',
            'uuid' => Str::uuid()->toString(),
            'password' => 'secure_password_123',
            'color' => '#3B82F6',
            default => "sample_value",
        };
    }

    /**
     * Generate cURL examples.
     */
    protected function generateCurlExamples(string $tableName, array $sampleData): array
    {
        $baseUrl = config('app.url');
        $jsonData = json_encode($sampleData, JSON_PRETTY_PRINT);
        
        return [
            'list' => <<<CURL
curl -X GET "{$baseUrl}/api/data/{$tableName}" \\
  -H "x-api-key: sk_your_secret_key_here" \\
  -H "Accept: application/json"
CURL,
            'get' => <<<CURL
curl -X GET "{$baseUrl}/api/data/{$tableName}/1" \\
  -H "x-api-key: sk_your_secret_key_here" \\
  -H "Accept: application/json"
CURL,
            'create' => <<<CURL
curl -X POST "{$baseUrl}/api/data/{$tableName}" \\
  -H "x-api-key: sk_your_secret_key_here" \\
  -H "Content-Type: application/json" \\
  -H "Accept: application/json" \\
  -d '{$jsonData}'
CURL,
            'update' => <<<CURL
curl -X PUT "{$baseUrl}/api/data/{$tableName}/1" \\
  -H "x-api-key: sk_your_secret_key_here" \\
  -H "Content-Type: application/json" \\
  -H "Accept: application/json" \\
  -d '{$jsonData}'
CURL,
            'delete' => <<<CURL
curl -X DELETE "{$baseUrl}/api/data/{$tableName}/1" \\
  -H "x-api-key: sk_your_secret_key_here" \\
  -H "Accept: application/json"
CURL,
        ];
    }

    /**
     * Generate JavaScript examples.
     */
    protected function generateJavaScriptExamples(string $tableName, array $sampleData): array
    {
        $baseUrl = config('app.url');
        $jsonData = json_encode($sampleData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        return [
            'list' => <<<JS
// Using digibase.js SDK
const digibase = new Digibase('{$baseUrl}', 'sk_your_secret_key_here');
const records = await digibase.from('{$tableName}').select();

// Using fetch API
const response = await fetch('{$baseUrl}/api/data/{$tableName}', {
  headers: {
    'x-api-key': 'sk_your_secret_key_here',
    'Accept': 'application/json'
  }
});
const data = await response.json();
JS,
            'get' => <<<JS
// Using digibase.js SDK
const record = await digibase.from('{$tableName}').select().eq('id', 1).single();

// Using fetch API
const response = await fetch('{$baseUrl}/api/data/{$tableName}/1', {
  headers: {
    'x-api-key': 'sk_your_secret_key_here',
    'Accept': 'application/json'
  }
});
const data = await response.json();
JS,
            'create' => <<<JS
// Using digibase.js SDK
const newRecord = await digibase.from('{$tableName}').insert({$jsonData});

// Using fetch API
const response = await fetch('{$baseUrl}/api/data/{$tableName}', {
  method: 'POST',
  headers: {
    'x-api-key': 'sk_your_secret_key_here',
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  },
  body: JSON.stringify({$jsonData})
});
const data = await response.json();
JS,
            'update' => <<<JS
// Using digibase.js SDK
const updated = await digibase.from('{$tableName}').update({$jsonData}).eq('id', 1);

// Using fetch API
const response = await fetch('{$baseUrl}/api/data/{$tableName}/1', {
  method: 'PUT',
  headers: {
    'x-api-key': 'sk_your_secret_key_here',
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  },
  body: JSON.stringify({$jsonData})
});
const data = await response.json();
JS,
            'delete' => <<<JS
// Using digibase.js SDK
await digibase.from('{$tableName}').delete().eq('id', 1);

// Using fetch API
const response = await fetch('{$baseUrl}/api/data/{$tableName}/1', {
  method: 'DELETE',
  headers: {
    'x-api-key': 'sk_your_secret_key_here',
    'Accept': 'application/json'
  }
});
JS,
        ];
    }

    /**
     * Generate Python examples.
     */
    protected function generatePythonExamples(string $tableName, array $sampleData): array
    {
        $baseUrl = config('app.url');
        $jsonData = json_encode($sampleData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        return [
            'list' => <<<PYTHON
import requests

headers = {
    'x-api-key': 'sk_your_secret_key_here',
    'Accept': 'application/json'
}

response = requests.get('{$baseUrl}/api/data/{$tableName}', headers=headers)
data = response.json()
PYTHON,
            'get' => <<<PYTHON
import requests

headers = {
    'x-api-key': 'sk_your_secret_key_here',
    'Accept': 'application/json'
}

response = requests.get('{$baseUrl}/api/data/{$tableName}/1', headers=headers)
data = response.json()
PYTHON,
            'create' => <<<PYTHON
import requests

headers = {
    'x-api-key': 'sk_your_secret_key_here',
    'Content-Type': 'application/json',
    'Accept': 'application/json'
}

payload = {$jsonData}

response = requests.post('{$baseUrl}/api/data/{$tableName}', headers=headers, json=payload)
data = response.json()
PYTHON,
            'update' => <<<PYTHON
import requests

headers = {
    'x-api-key': 'sk_your_secret_key_here',
    'Content-Type': 'application/json',
    'Accept': 'application/json'
}

payload = {$jsonData}

response = requests.put('{$baseUrl}/api/data/{$tableName}/1', headers=headers, json=payload)
data = response.json()
PYTHON,
            'delete' => <<<PYTHON
import requests

headers = {
    'x-api-key': 'sk_your_secret_key_here',
    'Accept': 'application/json'
}

response = requests.delete('{$baseUrl}/api/data/{$tableName}/1', headers=headers)
PYTHON,
        ];
    }

    /**
     * Generate JSON schema for the model.
     */
    protected function generateSchema(DynamicModel $model): array
    {
        $properties = [];
        $required = [];
        
        foreach ($model->fields as $field) {
            $properties[$field->name] = [
                'type' => $this->mapFieldTypeToJsonType($field->type),
                'description' => $field->description ?? $field->display_name,
            ];
            
            if ($field->is_required) {
                $required[] = $field->name;
            }
            
            // Add constraints
            if ($field->validation_rules) {
                $properties[$field->name]['constraints'] = $field->validation_rules;
            }
        }
        
        return [
            'type' => 'object',
            'properties' => $properties,
            'required' => $required,
        ];
    }

    /**
     * Map field type to JSON schema type.
     */
    protected function mapFieldTypeToJsonType(string $fieldType): string
    {
        return match ($fieldType) {
            'integer', 'bigint' => 'integer',
            'float', 'decimal' => 'number',
            'boolean' => 'boolean',
            'json', 'array' => 'object',
            'date', 'datetime', 'time' => 'string',
            default => 'string',
        };
    }

    /**
     * Get field validation rules.
     */
    protected function getFieldValidation(DynamicField $field): array
    {
        $validation = [];
        
        if ($field->is_required) {
            $validation[] = 'required';
        }
        
        if ($field->is_unique) {
            $validation[] = 'unique';
        }
        
        if ($field->validation_rules) {
            $validation = array_merge($validation, $field->validation_rules);
        }
        
        return $validation;
    }
}

```

## File: app/Services/CodeGeneratorService.php
```php
<?php

namespace App\Services;

use App\Models\DynamicModel;
use Illuminate\Support\Str;

class CodeGeneratorService
{
    public function generate($modelId, $framework, $operation, $style = 'tailwind', $typescript = true)
    {
        $model = DynamicModel::with('fields')->findOrFail($modelId);
        
        switch ($framework) {
            case 'react':
                return $this->generateReact($model, $operation, $style, $typescript);
            case 'vue':
                return $this->generateVue($model, $operation, $style, $typescript);
            case 'nextjs':
                return $this->generateNextJs($model, $operation, $style, $typescript);
            case 'nuxt':
                return $this->generateNuxt($model, $operation, $style, $typescript);
            default:
                return [['name' => 'Error.txt', 'code' => 'Framework not supported yet.']];
        }
    }

    protected function generateReact(DynamicModel $model, $operation, $style, $typescript)
    {
        $files = [];
        $modelName = $model->name;
        $displayName = $model->display_name;
        $tableName = $model->table_name;
        $ext = $typescript ? 'tsx' : 'jsx';

        if ($operation === 'list' || $operation === 'all') {
            $files[] = [
                'name' => "{$modelName}List.{$ext}",
                'code' => $this->getReactListTemplate($model, $style, $typescript),
                'description' => "Table component to display {$displayName} records."
            ];
        }

        if ($operation === 'create' || $operation === 'all') {
            $files[] = [
                'name' => "{$modelName}Create.{$ext}",
                'code' => $this->getReactCreateTemplate($model, $style, $typescript),
                'description' => "Form component to create new {$displayName} records."
            ];
        }

        if ($operation === 'hook' || $operation === 'all') {
            $files[] = [
                'name' => "use{$modelName}.ts",
                'code' => $this->getReactHookTemplate($model),
                'description' => "Custom React hook for CRUD operations using Axios."
            ];
        }

        return $files;
    }

    protected function getReactListTemplate(DynamicModel $model, $style, $typescript)
    {
        $modelName = $model->name;
        $tableName = $model->table_name;
        $fields = $model->fields;

        $tableHeaders = "";
        $tableCells = "";
        foreach ($fields->take(5) as $field) {
            $tableHeaders .= "                <th className=\"px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider\">{$field->display_name}</th>\n";
            $tableCells .= "                <td className=\"px-6 py-4 whitespace-nowrap text-sm text-gray-900\">{item.{$field->name}}</td>\n";
        }

        return <<<React
import React, { useState, useEffect } from 'react';
import axios from 'axios';

export const {$modelName}List = () => {
    const [data, setData] = useState([]);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        const fetchData = async () => {
            try {
                const response = await axios.get(`/api/data/{$tableName}`);
                setData(response.data.data || response.data);
            } catch (err) {
                console.error("Failed to fetch {$tableName}", err);
            } finally {
                setLoading(false);
            }
        };
        fetchData();
    }, []);

    if (loading) return <div className="p-8 text-center text-gray-500">Loading...</div>;

    return (
        <div className="overflow-x-auto bg-white rounded-lg shadow">
            <table className="min-w-full divide-y divide-gray-200">
                <thead className="bg-gray-50">
                    <tr>
{$tableHeaders}                        <th className="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody className="bg-white divide-y divide-gray-200">
                    {data.map((item: any) => (
                        <tr key={item.id} className="hover:bg-gray-50 transition-colors">
{$tableCells}                            <td className="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                <button className="text-indigo-600 hover:text-indigo-900 mr-3">Edit</button>
                                <button className="text-red-600 hover:text-red-900">Delete</button>
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
};
React;
    }

    protected function getReactCreateTemplate(DynamicModel $model, $style, $typescript)
    {
        $modelName = $model->name;
        $displayName = $model->display_name;
        $tableName = $model->table_name;
        $fields = $model->fields;

        $initialState = "";
        $formFields = "";
        foreach ($fields as $field) {
            $initialState .= "        {$field->name}: '',\n";
            $type = $field->type === 'email' ? 'email' : ($field->type === 'integer' ? 'number' : 'text');
            
            $formFields .= <<<HTML
            <div className="mb-4">
                <label className="block text-sm font-medium text-gray-700 mb-1">
                    {$field->display_name}
                </label>
                <input
                    type="{$type}"
                    value={formData.{$field->name}}
                    onChange={(e) => setFormData({ ...formData, {$field->name}: e.target.value })}
                    className="w-full px-3 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                    placeholder="Enter {$field->display_name}"
                />
            </div>\n
HTML;
        }

        return <<<React
import React, { useState } from 'react';
import axios from 'axios';

export const {$modelName}Create = ({ onSuccess }) => {
    const [formData, setFormData] = useState({
{$initialState}    });
    const [submitting, setSubmitting] = useState(false);

    const handleSubmit = async (e) => {
        e.preventDefault();
        setSubmitting(true);
        try {
            await axios.post(`/api/data/{$tableName}`, formData);
            if (onSuccess) onSuccess();
        } catch (err) {
            alert("Failed to create {$displayName}");
        } finally {
            setSubmitting(false);
        }
    };

    return (
        <form onSubmit={handleSubmit} className="max-w-lg bg-white p-6 rounded-xl shadow-lg">
            <h2 className="text-xl font-bold mb-6 text-gray-800">Create {$displayName}</h2>
            
{$formFields}
            <button
                type="submit"
                disabled={submitting}
                className="w-full bg-indigo-600 text-white py-2 px-4 rounded-md hover:bg-indigo-700 disabled:opacity-50 transition-colors"
            >
                {submitting ? 'Creating...' : 'Create {$displayName}'}
            </button>
        </form>
    );
};
React;
    }

    protected function getReactHookTemplate(DynamicModel $model)
    {
        $modelName = $model->name;
        $tableName = $model->table_name;

        return <<<React
import { useState, useCallback } from 'react';
import axios from 'axios';

export const use{$modelName} = () => {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState(null);

    const fetchAll = useCallback(async () => {
        setLoading(true);
        try {
            const res = await axios.get(`/api/data/{$tableName}`);
            return res.data.data;
        } catch (err) {
            setError(err);
            throw err;
        } finally {
            setLoading(false);
        }
    }, []);

    const create = async (data) => {
        setLoading(true);
        try {
            const res = await axios.post(`/api/data/{$tableName}`, data);
            return res.data;
        } catch (err) {
            setError(err);
            throw err;
        } finally {
            setLoading(false);
        }
    };

    const remove = async (id) => {
        setLoading(true);
        try {
            await axios.delete(`/api/data/{$tableName}/\${id}`);
        } catch (err) {
            setError(err);
            throw err;
        } finally {
            setLoading(false);
        }
    };

    return { fetchAll, create, remove, loading, error };
};
React;
    }

    protected function generateVue(DynamicModel $model, $operation, $style, $typescript)
    {
        $modelName = $model->name;
        $displayName = $model->display_name;
        $tableName = $model->table_name;
        $fields = $model->fields;

        $formFields = "";
        foreach ($fields as $field) {
            $formFields .= "          <div class=\"mb-4\">\n";
            $formFields .= "            <label class=\"block text-sm font-medium mb-1\">{$field->display_name}</label>\n";
            $formFields .= "            <input v-model=\"formData.{$field->name}\" type=\"text\" class=\"w-full px-3 py-2 border rounded-md\" />\n";
            $formFields .= "          </div>\n";
        }

        $code = <<<VUE
<script setup lang="ts">
import { ref, onMounted } from 'vue'
import axios from 'axios'

const data = ref([])
const formData = ref({})

onMounted(async () => {
  const res = await axios.get('/api/data/{$tableName}')
  data.value = res.data.data || res.data
})

const create = async () => {
  await axios.post('/api/data/{$tableName}', formData.value)
}
</script>

<template>
  <div class="p-6">
    <h1 class="text-2xl font-bold mb-4">{$displayName} Manager</h1>
    
    <form @submit.prevent="create" class="mb-8 p-4 border rounded-lg">
{$formFields}      <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded">Create</button>
    </form>
    
    <ul class="divide-y">
      <li v-for="item in data" :key="item.id" class="py-2">{{ JSON.stringify(item) }}</li>
    </ul>
  </div>
</template>
VUE;

        return [
            [
                'name' => "{$modelName}Manager.vue",
                'code' => $code,
                'description' => "Vue 3 Component with Composition API for {$displayName}."
            ]
        ];
    }

    protected function generateNextJs(DynamicModel $model, $operation, $style, $typescript)
    {
        $modelName = $model->name;
        $displayName = $model->display_name;
        $tableName = $model->table_name;
        
        $code = <<<NEXT
'use client'

import { useEffect, useState } from 'react'

export default function {$modelName}Page() {
  const [data, setData] = useState([])
  const [loading, setLoading] = useState(true)

  useEffect(() => {
    fetch('/api/data/{$tableName}')
      .then(res => res.json())
      .then(json => {
        setData(json.data || json)
        setLoading(false)
      })
  }, [])

  if (loading) return <div className="p-8">Loading...</div>

  return (
    <div className="p-8">
      <h1 className="text-3xl font-bold mb-6">{$displayName}</h1>
      <div className="grid gap-4">
        {data.map((item: any) => (
          <div key={item.id} className="p-4 border rounded-lg">
            {JSON.stringify(item)}
          </div>
        ))}
      </div>
    </div>
  )
}
NEXT;

        return [
            [
                'name' => "page.tsx",
                'code' => $code,
                'description' => "Next.js 14 App Router page component for {$displayName}."
            ]
        ];
    }

    protected function generateNuxt(DynamicModel $model, $operation, $style, $typescript)
    {
        $modelName = $model->name;
        $displayName = $model->display_name;
        $tableName = $model->table_name;
        
        $code = <<<NUXT
<script setup lang="ts">
const { data } = await useFetch('/api/data/{$tableName}')
</script>

<template>
  <div class="p-8">
    <h1 class="text-3xl font-bold mb-6">{$displayName}</h1>
    <div class="grid gap-4">
      <div v-for="item in data?.data || data" :key="item.id" class="p-4 border rounded-lg">
        {{ JSON.stringify(item) }}
      </div>
    </div>
  </div>
</template>
NUXT;

        return [
            [
                'name' => "{$modelName}.vue",
                'code' => $code,
                'description' => "Nuxt 3 page component with useFetch for {$displayName}."
            ]
        ];
    }
}

```

## File: app/Services/MigrationService.php
```php
<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MigrationService
{
    /**
     * Get all migrations and their status.
     */
    public function getStatus(): array
    {
        $migrationFiles = File::files(database_path('migrations'));
        $ranMigrations = DB::table('migrations')->pluck('migration')->toArray();

        $migrations = [];
        foreach ($migrationFiles as $file) {
            $filename = $file->getFilenameWithoutExtension();
            $migrations[] = [
                'name' => $filename,
                'status' => in_array($filename, $ranMigrations) ? 'ran' : 'pending',
                'batch' => in_array($filename, $ranMigrations) ? DB::table('migrations')->where('migration', $filename)->value('batch') : null,
                'created_at' => $this->extractDate($filename),
            ];
        }

        // Sort by name (which starts with date)
        usort($migrations, fn($a, $b) => strcmp($b['name'], $a['name']));

        return $migrations;
    }

    /**
     * Run all pending migrations.
     */
    public function migrate(): array
    {
        Artisan::call('migrate', ['--force' => true]);
        return [
            'output' => Artisan::output(),
            'status' => 'success'
        ];
    }

    /**
     * Rollback the last migration batch.
     */
    public function rollback(): array
    {
        Artisan::call('migrate:rollback', ['--force' => true]);
        return [
            'output' => Artisan::output(),
            'status' => 'success'
        ];
    }

    /**
     * Extract date from migration filename.
     */
    private function extractDate(string $filename): ?string
    {
        $parts = explode('_', $filename);
        if (count($parts) >= 4) {
            return "{$parts[0]}-{$parts[1]}-{$parts[2]} {$parts[3]}";
        }
        return null;
    }
}

```

## File: app/Services/UrlValidator.php
```php
<?php

namespace App\Services;

/**
 * Validates URLs against SSRF attacks.
 * Blocks private/internal IP ranges and dangerous schemes.
 */
class UrlValidator
{
    /**
     * Private/internal IP CIDR ranges that must be blocked.
     */
    protected static array $blockedCidrs = [
        '127.0.0.0/8',       // Loopback
        '10.0.0.0/8',        // Private Class A
        '172.16.0.0/12',     // Private Class B
        '192.168.0.0/16',    // Private Class C
        '169.254.0.0/16',    // Link-local (AWS metadata at 169.254.169.254)
        '0.0.0.0/8',         // Current network
        '100.64.0.0/10',     // Shared address space (CGN)
        '192.0.0.0/24',      // IETF Protocol Assignments
        '198.18.0.0/15',     // Benchmarking
        '224.0.0.0/4',       // Multicast
        '240.0.0.0/4',       // Reserved
        '::1/128',           // IPv6 loopback
        'fc00::/7',          // IPv6 unique local
        'fe80::/10',         // IPv6 link-local
    ];

    /**
     * Blocked hostnames.
     */
    protected static array $blockedHosts = [
        'localhost',
        'localhost.localdomain',
        '0.0.0.0',
        '[::1]',
        'metadata.google.internal',
        'metadata.google',
    ];

    /**
     * Validate a webhook URL is safe (not targeting internal resources).
     *
     * @return array{valid: bool, reason: ?string}
     */
    public static function validateWebhookUrl(string $url): array
    {
        // Must be http or https
        $parsed = parse_url($url);
        if (!$parsed || !isset($parsed['scheme']) || !isset($parsed['host'])) {
            return ['valid' => false, 'reason' => 'Invalid URL format'];
        }

        $scheme = strtolower($parsed['scheme']);
        if (!in_array($scheme, ['http', 'https'])) {
            return ['valid' => false, 'reason' => 'Only HTTP and HTTPS schemes are allowed'];
        }

        $host = strtolower($parsed['host']);

        // Check blocked hostnames
        if (in_array($host, self::$blockedHosts)) {
            return ['valid' => false, 'reason' => "Host '{$host}' is not allowed for webhooks"];
        }

        // Resolve hostname to IP and check against blocked ranges
        $ips = gethostbynamel($host);
        if ($ips === false) {
            // If DNS resolution fails, allow it (might resolve later)
            // but block obvious IP-based hosts
            if (filter_var($host, FILTER_VALIDATE_IP)) {
                $ips = [$host];
            } else {
                return ['valid' => true, 'reason' => null];
            }
        }

        foreach ($ips as $ip) {
            if (self::isPrivateIp($ip)) {
                return ['valid' => false, 'reason' => "Resolved IP '{$ip}' is in a private/reserved range"];
            }
        }

        return ['valid' => true, 'reason' => null];
    }

    /**
     * Check if an IP address falls within any blocked CIDR range.
     */
    protected static function isPrivateIp(string $ip): bool
    {
        // Use PHP's built-in filter for the common cases
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }

        // Additional check for link-local and CGN ranges not covered by FILTER_FLAG_NO_PRIV_RANGE
        $long = ip2long($ip);
        if ($long === false) {
            return false; // IPv6 — covered by the flags above for basic cases
        }

        // 169.254.0.0/16 (link-local, AWS/GCP metadata)
        if (($long & 0xFFFF0000) === ip2long('169.254.0.0')) {
            return true;
        }

        // 100.64.0.0/10 (CGN)
        if (($long & 0xFFC00000) === ip2long('100.64.0.0')) {
            return true;
        }

        return false;
    }
}

```

## File: app/Settings/GeneralSettings.php
```php
<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class GeneralSettings extends Settings
{
    public ?string $storage_driver = 'local';
    public ?string $aws_access_key_id = null;
    public ?string $aws_secret_access_key = null;
    public ?string $aws_default_region = 'us-east-1';
    public ?string $aws_bucket = null;
    public ?string $aws_endpoint = null;
    public ?string $aws_use_path_style = 'false';
    public ?string $aws_url = null;

    public static function group(): string
    {
        return 'general';
    }
}

```

## File: routes/api.php
```php
<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DatabaseController;
use App\Http\Controllers\Api\DynamicModelController;
use App\Http\Controllers\Api\CoreDataController;
use Illuminate\Support\Facades\Route;

// Public routes
Route::middleware('throttle:10,1')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);

    Route::get('/settings/public', function() {
        if (!function_exists('db_config')) return response()->json([], 503);
        
        return response()->json([
            'app_name' => db_config('branding.site_name') ?? 'Digibase',
            'logo_url' => db_config('branding.site_logo'),
            'features' => [
                'google_login' => db_config('auth.google_enabled') && !empty(db_config('auth.google_client_id')),
                'github_login' => db_config('auth.github_enabled') && !empty(db_config('auth.github_client_id')),
            ]
        ]);
    });
});

// Social OAuth
Route::get('/auth/providers', [AuthController::class, 'getProviders']);
Route::get('/auth/{provider}', [AuthController::class, 'redirectToProvider']);
Route::get('/auth/{provider}/callback', [AuthController::class, 'handleProviderCallback']);

// ============================================================================
// CORE DATA API (Unified)
// ============================================================================
// 🛡️ Iron Dome: API key validation
// 🩺 Schema Doctor: Validation
// ⚡ Turbo Cache: Caching
// 📡 Live Wire: Real-time
// ============================================================================

// V1 Prefix
Route::prefix('v1')->middleware(['api.key', App\Http\Middleware\ApiRateLimiter::class, App\Http\Middleware\LogApiActivity::class])->group(function () {
    Route::get('/data/{tableName}', [CoreDataController::class, 'index']);
    Route::get('/data/{tableName}/schema', [CoreDataController::class, 'schema']);
    Route::get('/data/{tableName}/{id}', [CoreDataController::class, 'show']);
    
    Route::post('/data/{tableName}', [CoreDataController::class, 'store']);
    Route::put('/data/{tableName}/{id}', [CoreDataController::class, 'update']);
    Route::delete('/data/{tableName}/{id}', [CoreDataController::class, 'destroy']);
});

// LEGACY COMPATIBILITY ROUTING (Mapped to CoreDataController)
// Note: We maintain these routes but point them to the new engine.
Route::middleware(['api.key', 'throttle:60,1', App\Http\Middleware\ApiRateLimiter::class])->group(function () {
    Route::get('/data/{tableName}', [CoreDataController::class, 'index']);
    Route::get('/data/{tableName}/schema', [CoreDataController::class, 'schema']);
    Route::get('/data/{tableName}/{id}', [CoreDataController::class, 'show']);
    
    Route::post('/data/{tableName}', [CoreDataController::class, 'store']);
    Route::put('/data/{tableName}/{id}', [CoreDataController::class, 'update']);
    Route::delete('/data/{tableName}/{id}', [CoreDataController::class, 'destroy']);
});

// Protected routes (Sanctum)
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Dynamic Models (Visual Model Creator)
    Route::get('/models/field-types', [DynamicModelController::class, 'fieldTypes']);
    Route::apiResource('models', DynamicModelController::class)->parameters([
        'models' => 'dynamicModel'
    ]);
    Route::post('/models/{dynamicModel}/fields', [DynamicModelController::class, 'addFields']);
    Route::put('/models/{dynamicModel}/fields/{field}', [DynamicModelController::class, 'updateField']);
    Route::delete('/models/{dynamicModel}/fields/{field}', [DynamicModelController::class, 'destroyField']);

    // Database Explorer
    Route::get('/database/stats', [DatabaseController::class, 'stats']);
    Route::get('/database/tables', [DatabaseController::class, 'tables']);
    Route::get('/database/tables/{tableName}/structure', [DatabaseController::class, 'structure']);
    Route::get('/database/tables/{tableName}/data', [DatabaseController::class, 'data']);
    Route::post('/database/tables/{tableName}/rows', [DatabaseController::class, 'insertRow']);
    Route::put('/database/tables/{tableName}/rows/{id}', [DatabaseController::class, 'updateRow']);
    Route::delete('/database/tables/{tableName}/rows/{id}', [DatabaseController::class, 'deleteRow']);
    Route::post('/database/query', [DatabaseController::class, 'query']);
    
    // Code Generator
    Route::post('/code/generate', [\App\Http\Controllers\Api\CodeGeneratorController::class, 'generate']);

    // User Management
    Route::apiResource('users', \App\Http\Controllers\Api\UserController::class);
    
    // Role & Permission Management
    Route::get('/permissions', [\App\Http\Controllers\Api\RoleController::class, 'permissions']);
    Route::apiResource('roles', \App\Http\Controllers\Api\RoleController::class);

    // API Key Management
    Route::get('/tokens', [\App\Http\Controllers\Api\ApiKeyController::class, 'index']);
    Route::post('/tokens', [\App\Http\Controllers\Api\ApiKeyController::class, 'store']);
    Route::delete('/tokens/{id}', [\App\Http\Controllers\Api\ApiKeyController::class, 'destroy']);

    // Migration Management
    Route::get('/migrations', [\App\Http\Controllers\Api\MigrationController::class, 'index']);
    Route::post('/migrations/run', [\App\Http\Controllers\Api\MigrationController::class, 'migrate']);
    Route::post('/migrations/rollback', [\App\Http\Controllers\Api\MigrationController::class, 'rollback']);
});

```

## File: routes/channels.php
```php
<?php

use App\Models\ApiKey;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

/**
 * Private data channel for real-time model updates.
 *
 * Channel: private-data.{tableName}
 * Authorization: User must own at least one active API key with read scope
 * and access to the requested table.
 */
Broadcast::channel('data.{tableName}', function ($user, $tableName) {
    // User must have at least one active, non-expired key with read access to this table
    $keys = ApiKey::where('user_id', $user->id)
        ->where('is_active', true)
        ->where(function ($q) {
            $q->whereNull('expires_at')
              ->orWhere('expires_at', '>', now());
        })
        ->get();

    foreach ($keys as $key) {
        if ($key->hasScope('read') && $key->hasTableAccess($tableName)) {
            return true;
        }
    }

    return false;
});

```

## File: routes/console.php
```php
<?php

use Illuminate\Support\Facades\Schedule;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// 📉 Auto-Pruning Scheduler
Schedule::command('api:prune-analytics')->daily();

```

## File: routes/web.php
```php
<?php

use App\Http\Controllers\Api\SdkController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

// JavaScript SDK
Route::get('/sdk/digibase.js', [SdkController::class, 'generate'])->name('sdk.js');

// Development Login Route
if (app()->environment('local', 'testing')) {
    Route::get('/dev/login/{id}', function ($id) {
        auth()->loginUsingId($id);
        return redirect('/admin');
    })->name('dev.login');
}

```

## File: config/activitylog.php
```php
<?php

return [

    /*
     * If set to false, no activities will be saved to the database.
     */
    'enabled' => env('ACTIVITY_LOGGER_ENABLED', true),

    /*
     * When the clean-command is executed, all recording activities older than
     * the number of days specified here will be deleted.
     */
    'delete_records_older_than_days' => 365,

    /*
     * If no log name is passed to the activity() helper
     * we use this default log name.
     */
    'default_log_name' => 'default',

    /*
     * You can specify an auth driver here that gets user models.
     * If this is null we'll use the current Laravel auth driver.
     */
    'default_auth_driver' => null,

    /*
     * If set to true, the subject returns soft deleted models.
     */
    'subject_returns_soft_deleted_models' => false,

    /*
     * This model will be used to log activity.
     * It should implement the Spatie\Activitylog\Contracts\Activity interface
     * and extend Illuminate\Database\Eloquent\Model.
     */
    'activity_model' => \Spatie\Activitylog\Models\Activity::class,

    /*
     * This is the name of the table that will be created by the migration and
     * used by the Activity model shipped with this package.
     */
    'table_name' => env('ACTIVITY_LOGGER_TABLE_NAME', 'activity_log'),

    /*
     * This is the database connection that will be used by the migration and
     * the Activity model shipped with this package. In case it's not set
     * Laravel's database.default will be used instead.
     */
    'database_connection' => env('ACTIVITY_LOGGER_DB_CONNECTION'),
];

```

## File: config/app.php
```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => 'UTC',

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];

```

## File: config/auth.php
```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Defaults
    |--------------------------------------------------------------------------
    |
    | This option defines the default authentication "guard" and password
    | reset "broker" for your application. You may change these values
    | as required, but they're a perfect start for most applications.
    |
    */

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'web'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Authentication Guards
    |--------------------------------------------------------------------------
    |
    | Next, you may define every authentication guard for your application.
    | Of course, a great default configuration has been defined for you
    | which utilizes session storage plus the Eloquent user provider.
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | Supported: "session"
    |
    */

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | User Providers
    |--------------------------------------------------------------------------
    |
    | All authentication guards have a user provider, which defines how the
    | users are actually retrieved out of your database or other storage
    | system used by the application. Typically, Eloquent is utilized.
    |
    | If you have multiple user tables or models you may configure multiple
    | providers to represent the model / table. These providers may then
    | be assigned to any extra authentication guards you have defined.
    |
    | Supported: "database", "eloquent"
    |
    */

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', App\Models\User::class),
        ],

        // 'users' => [
        //     'driver' => 'database',
        //     'table' => 'users',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Resetting Passwords
    |--------------------------------------------------------------------------
    |
    | These configuration options specify the behavior of Laravel's password
    | reset functionality, including the table utilized for token storage
    | and the user provider that is invoked to actually retrieve users.
    |
    | The expiry time is the number of minutes that each reset token will be
    | considered valid. This security feature keeps tokens short-lived so
    | they have less time to be guessed. You may change this as needed.
    |
    | The throttle setting is the number of seconds a user must wait before
    | generating more password reset tokens. This prevents the user from
    | quickly generating a very large amount of password reset tokens.
    |
    */

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => env('AUTH_PASSWORD_RESET_TOKEN_TABLE', 'password_reset_tokens'),
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Password Confirmation Timeout
    |--------------------------------------------------------------------------
    |
    | Here you may define the number of seconds before a password confirmation
    | window expires and users are asked to re-enter their password via the
    | confirmation screen. By default, the timeout lasts for three hours.
    |
    */

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];

```

## File: config/backup.php
```php
<?php

return [

    'backup' => [
        /*
         * The name of this application. You can use this name to monitor
         * the backups.
         */
        'name' => env('APP_NAME', 'laravel-backup'),

        'source' => [
            'files' => [
                /*
                 * The list of directories and files that will be included in the backup.
                 */
                'include' => [
                    storage_path('app/public'),
                ],

                /*
                 * These directories and files will be excluded from the backup.
                 *
                 * Directories used by the backup process will automatically be excluded.
                 */
                'exclude' => [],

                /*
                 * Determines if symlinks should be followed.
                 */
                'follow_links' => false,

                /*
                 * Determines if it should avoid unreadable folders.
                 */
                'ignore_unreadable_directories' => false,

                /*
                 * This path is used to make directories in resulting zip-file relative
                 * Set to `null` to include complete absolute path
                 * Example: base_path()
                 */
                'relative_path' => null,
            ],

            /*
             * The names of the connections to the databases that should be backed up
             * MySQL, PostgreSQL, SQLite and Mongo databases are supported.
             *
             * The content of the database dump may be customized for each connection
             * by adding a 'dump' key to the connection settings in config/database.php.
             * E.g.
             * 'mysql' => [
             *       ...
             *      'dump' => [
             *           'exclude_tables' => [
             *                'table_to_exclude_from_backup',
             *                'another_table_to_exclude'
             *            ]
             *       ],
             * ],
             *
             * If you are using only InnoDB tables on a MySQL server, you can
             * also supply the useSingleTransaction option to avoid table locking.
             *
             * E.g.
             * 'mysql' => [
             *       ...
             *      'dump' => [
             *           'useSingleTransaction' => true,
             *       ],
             * ],
             *
             * For a complete list of available customization options, see https://github.com/spatie/db-dumper
             */
            'databases' => [
                'sqlite',
            ],
        ],

        /*
         * The database dump can be compressed to decrease disk space usage.
         *
         * Out of the box Laravel-backup supplies
         * Spatie\DbDumper\Compressors\GzipCompressor::class.
         *
         * You can also create custom compressor. More info on that here:
         * https://github.com/spatie/db-dumper#using-compression
         *
         * If you do not want any compressor at all, set it to null.
         */
        'database_dump_compressor' => null,

        /*
         * If specified, the database dumped file name will contain a timestamp (e.g.: 'Y-m-d-H-i-s').
         */
        'database_dump_file_timestamp_format' => null,

        /*
         * The base of the dump filename, either 'database' or 'connection'
         *
         * If 'database' (default), the dumped filename will contain the database name.
         * If 'connection', the dumped filename will contain the connection name.
         */
        'database_dump_filename_base' => 'database',

        /*
         * The file extension used for the database dump files.
         *
         * If not specified, the file extension will be .archive for MongoDB and .sql for all other databases
         * The file extension should be specified without a leading .
         */
        'database_dump_file_extension' => '',

        'destination' => [
            /*
             * The compression algorithm to be used for creating the zip archive.
             *
             * If backing up only database, you may choose gzip compression for db dump and no compression at zip.
             *
             * Some common algorithms are listed below:
             * ZipArchive::CM_STORE (no compression at all; set 0 as compression level)
             * ZipArchive::CM_DEFAULT
             * ZipArchive::CM_DEFLATE
             * ZipArchive::CM_BZIP2
             * ZipArchive::CM_XZ
             *
             * For more check https://www.php.net/manual/zip.constants.php and confirm it's supported by your system.
             */
            'compression_method' => ZipArchive::CM_DEFAULT,

            /*
             * The compression level corresponding to the used algorithm; an integer between 0 and 9.
             *
             * Check supported levels for the chosen algorithm, usually 1 means the fastest and weakest compression,
             * while 9 the slowest and strongest one.
             *
             * Setting of 0 for some algorithms may switch to the strongest compression.
             */
            'compression_level' => 9,

            /*
             * The filename prefix used for the backup zip file.
             */
            'filename_prefix' => '',

            /*
             * The disk names on which the backups will be stored.
             */
            'disks' => [
                'local',
            ],

            /*
             * Determines whether to allow backups to continue when some targets fail instead of failing completely.
             */
            'continue_on_failure' => false,
        ],

        /*
         * The directory where the temporary files will be stored.
         */
        'temporary_directory' => storage_path('app/backup-temp'),

        /*
         * The password to be used for archive encryption.
         * Set to `null` to disable encryption.
         */
        'password' => env('BACKUP_ARCHIVE_PASSWORD'),

        /*
         * The encryption algorithm to be used for archive encryption.
         * You can set it to `null` or `false` to disable encryption.
         *
         * When set to 'default', we'll use ZipArchive::EM_AES_256 if it is
         * available on your system.
         */
        'encryption' => 'default',

        /*
         * The number of attempts, in case the backup command encounters an exception
         */
        'tries' => 1,

        /*
         * The number of seconds to wait before attempting a new backup if the previous try failed
         * Set to `0` for none
         */
        'retry_delay' => 0,
    ],

    /*
     * You can get notified when specific events occur. Out of the box you can use 'mail' and 'slack'.
     * For Slack you need to install laravel/slack-notification-channel.
     *
     * You can also use your own notification classes, just make sure the class is named after one of
     * the `Spatie\Backup\Notifications\Notifications` classes.
     */
    'notifications' => [
        'notifications' => [
            \Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification::class => ['mail'],
        ],

        /*
         * Here you can specify the notifiable to which the notifications should be sent. The default
         * notifiable will use the variables specified in this config file.
         */
        'notifiable' => \Spatie\Backup\Notifications\Notifiable::class,

        'mail' => [
            'to' => 'your@example.com',

            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                'name' => env('MAIL_FROM_NAME', 'Example'),
            ],
        ],

        'slack' => [
            'webhook_url' => '',

            /*
             * If this is set to null the default channel of the webhook will be used.
             */
            'channel' => null,

            'username' => null,

            'icon' => null,
        ],

        'discord' => [
            'webhook_url' => '',

            /*
             * If this is an empty string, the name field on the webhook will be used.
             */
            'username' => '',

            /*
             * If this is an empty string, the avatar on the webhook will be used.
             */
            'avatar_url' => '',
        ],
    ],

    /*
     * Here you can specify which backups should be monitored.
     * If a backup does not meet the specified requirements the
     * UnHealthyBackupWasFound event will be fired.
     */
    'monitor_backups' => [
        [
            'name' => env('APP_NAME', 'laravel-backup'),
            'disks' => ['local'],
            'health_checks' => [
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class => 1,
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes::class => 5000,
            ],
        ],

        /*
        [
            'name' => 'name of the second app',
            'disks' => ['local', 's3'],
            'health_checks' => [
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class => 1,
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes::class => 5000,
            ],
        ],
        */
    ],

    'cleanup' => [
        /*
         * The strategy that will be used to cleanup old backups. The default strategy
         * will keep all backups for a certain amount of days. After that period only
         * a daily backup will be kept. After that period only weekly backups will
         * be kept and so on.
         *
         * No matter how you configure it the default strategy will never
         * delete the newest backup.
         */
        'strategy' => \Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy::class,

        'default_strategy' => [
            /*
             * The number of days for which backups must be kept.
             */
            'keep_all_backups_for_days' => 7,

            /*
             * After the "keep_all_backups_for_days" period is over, the most recent backup
             * of that day will be kept. Older backups within the same day will be removed.
             * If you create backups only once a day, no backups will be removed yet.
             */
            'keep_daily_backups_for_days' => 16,

            /*
             * After the "keep_daily_backups_for_days" period is over, the most recent backup
             * of that week will be kept. Older backups within the same week will be removed.
             * If you create backups only once a week, no backups will be removed yet.
             */
            'keep_weekly_backups_for_weeks' => 8,

            /*
             * After the "keep_weekly_backups_for_weeks" period is over, the most recent backup
             * of that month will be kept. Older backups within the same month will be removed.
             */
            'keep_monthly_backups_for_months' => 4,

            /*
             * After the "keep_monthly_backups_for_months" period is over, the most recent backup
             * of that year will be kept. Older backups within the same year will be removed.
             */
            'keep_yearly_backups_for_years' => 2,

            /*
             * After cleaning up the backups remove the oldest backup until
             * this amount of megabytes has been reached.
             * Set null for unlimited size.
             */
            'delete_oldest_backups_when_using_more_megabytes_than' => 5000,
        ],

        /*
         * The number of attempts, in case the cleanup command encounters an exception
         */
        'tries' => 1,

        /*
         * The number of seconds to wait before attempting a new cleanup if the previous try failed
         * Set to `0` for none
         */
        'retry_delay' => 0,
    ],

];

```

## File: config/broadcasting.php
```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | This option controls the default broadcaster that will be used by the
    | framework when an event needs to be broadcast. You may set this to
    | any of the connections defined in the "connections" array below.
    |
    | Supported: "reverb", "pusher", "ably", "redis", "log", "null"
    |
    */

    'default' => env('BROADCAST_CONNECTION', 'null'),

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the broadcast connections that will be used
    | to broadcast events to other systems or over WebSockets. Samples of
    | each available type of connection are provided inside this array.
    |
    */

    'connections' => [

        'reverb' => [
            'driver' => 'reverb',
            'key' => env('REVERB_APP_KEY'),
            'secret' => env('REVERB_APP_SECRET'),
            'app_id' => env('REVERB_APP_ID'),
            'options' => [
                'host' => env('REVERB_HOST'),
                'port' => env('REVERB_PORT', 443),
                'scheme' => env('REVERB_SCHEME', 'https'),
                'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
            ],
            'client_options' => [
                // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
            ],
        ],

        'pusher' => [
            'driver' => 'pusher',
            'key' => env('PUSHER_APP_KEY'),
            'secret' => env('PUSHER_APP_SECRET'),
            'app_id' => env('PUSHER_APP_ID'),
            'options' => [
                'cluster' => env('PUSHER_APP_CLUSTER'),
                'host' => env('PUSHER_HOST') ?: 'api-'.env('PUSHER_APP_CLUSTER', 'mt1').'.pusher.com',
                'port' => env('PUSHER_PORT', 443),
                'scheme' => env('PUSHER_SCHEME', 'https'),
                'encrypted' => true,
                'useTLS' => env('PUSHER_SCHEME', 'https') === 'https',
            ],
            'client_options' => [
                // Guzzle client options: https://docs.guzzlephp.org/en/stable/request-options.html
            ],
        ],

        'ably' => [
            'driver' => 'ably',
            'key' => env('ABLY_KEY'),
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];

```

## File: config/cache.php
```php
<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Cache Store
    |--------------------------------------------------------------------------
    |
    | This option controls the default cache store that will be used by the
    | framework. This connection is utilized if another isn't explicitly
    | specified when running a cache operation inside the application.
    |
    */

    'default' => env('CACHE_STORE', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Cache Stores
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the cache "stores" for your application as
    | well as their drivers. You may even define multiple stores for the
    | same cache driver to group types of items stored in your caches.
    |
    | Supported drivers: "array", "database", "file", "memcached",
    |                    "redis", "dynamodb", "octane",
    |                    "failover", "null"
    |
    */

    'stores' => [

        'array' => [
            'driver' => 'array',
            'serialize' => false,
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_CACHE_CONNECTION'),
            'table' => env('DB_CACHE_TABLE', 'cache'),
            'lock_connection' => env('DB_CACHE_LOCK_CONNECTION'),
            'lock_table' => env('DB_CACHE_LOCK_TABLE'),
        ],

        'file' => [
            'driver' => 'file',
            'path' => storage_path('framework/cache/data'),
            'lock_path' => storage_path('framework/cache/data'),
        ],

        'memcached' => [
            'driver' => 'memcached',
            'persistent_id' => env('MEMCACHED_PERSISTENT_ID'),
            'sasl' => [
                env('MEMCACHED_USERNAME'),
                env('MEMCACHED_PASSWORD'),
            ],
            'options' => [
                // Memcached::OPT_CONNECT_TIMEOUT => 2000,
            ],
            'servers' => [
                [
                    'host' => env('MEMCACHED_HOST', '127.0.0.1'),
                    'port' => env('MEMCACHED_PORT', 11211),
                    'weight' => 100,
                ],
            ],
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_CACHE_CONNECTION', 'cache'),
            'lock_connection' => env('REDIS_CACHE_LOCK_CONNECTION', 'default'),
        ],

        'dynamodb' => [
            'driver' => 'dynamodb',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'table' => env('DYNAMODB_CACHE_TABLE', 'cache'),
            'endpoint' => env('DYNAMODB_ENDPOINT'),
        ],

        'octane' => [
            'driver' => 'octane',
        ],

        'digibase' => [
            'driver' => env('DIGIBASE_CACHE_DRIVER', 'file'),
            'path' => storage_path('framework/cache/digibase'),
            'lock_path' => storage_path('framework/cache/digibase'),
        ],

        'failover' => [
            'driver' => 'failover',
            'stores' => [
                'database',
                'array',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | When utilizing the APC, database, memcached, Redis, and DynamoDB cache
    | stores, there might be other applications using the same cache. For
    | that reason, you may prefix every cache key to avoid collisions.
    |
    */

    'prefix' => env('CACHE_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-cache-'),

];

```

## File: config/cors.php
```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['*'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];

```

## File: config/database.php
```php
<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection Name
    |--------------------------------------------------------------------------
    |
    | Here you may specify which of the database connections below you wish
    | to use as your default connection for database operations. This is
    | the connection which will be utilized unless another connection
    | is explicitly specified when you execute a query / statement.
    |
    */

    'default' => env('DB_CONNECTION', 'sqlite'),

    /*
    |--------------------------------------------------------------------------
    | Database Connections
    |--------------------------------------------------------------------------
    |
    | Below are all of the database connections defined for your application.
    | An example configuration is provided for each database system which
    | is supported by Laravel. You're free to add / remove connections.
    |
    */

    'connections' => [

        'sqlite' => [
            'driver' => 'sqlite',
            'url' => env('DB_URL'),
            'database' => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix' => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
            'busy_timeout' => env('DB_BUSY_TIMEOUT', 5000),
            'journal_mode' => env('DB_JOURNAL_MODE', 'WAL'),
            'synchronous' => env('DB_SYNCHRONOUS', 'NORMAL'),
            'transaction_mode' => 'IMMEDIATE',
        ],

        'mysql' => [
            'driver' => 'mysql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                (PHP_VERSION_ID >= 80500 ? \Pdo\Mysql::ATTR_SSL_CA : \PDO::MYSQL_ATTR_SSL_CA) => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'mariadb' => [
            'driver' => 'mariadb',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '3306'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'unix_socket' => env('DB_SOCKET', ''),
            'charset' => env('DB_CHARSET', 'utf8mb4'),
            'collation' => env('DB_COLLATION', 'utf8mb4_unicode_ci'),
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
            'options' => extension_loaded('pdo_mysql') ? array_filter([
                (PHP_VERSION_ID >= 80500 ? \Pdo\Mysql::ATTR_SSL_CA : \PDO::MYSQL_ATTR_SSL_CA) => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        'pgsql' => [
            'driver' => 'pgsql',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', '127.0.0.1'),
            'port' => env('DB_PORT', '5432'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            'search_path' => 'public',
            'sslmode' => env('DB_SSLMODE', 'prefer'),
        ],

        'sqlsrv' => [
            'driver' => 'sqlsrv',
            'url' => env('DB_URL'),
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '1433'),
            'database' => env('DB_DATABASE', 'laravel'),
            'username' => env('DB_USERNAME', 'root'),
            'password' => env('DB_PASSWORD', ''),
            'charset' => env('DB_CHARSET', 'utf8'),
            'prefix' => '',
            'prefix_indexes' => true,
            // 'encrypt' => env('DB_ENCRYPT', 'yes'),
            // 'trust_server_certificate' => env('DB_TRUST_SERVER_CERTIFICATE', 'false'),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    |
    | This table keeps track of all the migrations that have already run for
    | your application. Using this information, we can determine which of
    | the migrations on disk haven't actually been run on the database.
    |
    */

    'migrations' => [
        'table' => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    |
    | Redis is an open source, fast, and advanced key-value store that also
    | provides a richer body of commands than a typical key-value system
    | such as Memcached. You may define your connection settings here.
    |
    */

    'redis' => [

        'client' => env('REDIS_CLIENT', 'phpredis'),

        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
            'persistent' => env('REDIS_PERSISTENT', false),
        ],

        'default' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

        'cache' => [
            'url' => env('REDIS_URL'),
            'host' => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port' => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
            'max_retries' => env('REDIS_MAX_RETRIES', 3),
            'backoff_algorithm' => env('REDIS_BACKOFF_ALGORITHM', 'decorrelated_jitter'),
            'backoff_base' => env('REDIS_BACKOFF_BASE', 100),
            'backoff_cap' => env('REDIS_BACKOFF_CAP', 1000),
        ],

    ],

];

```

## File: config/filament-shield.php
```php
<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Shield Resource
    |--------------------------------------------------------------------------
    |
    | Here you may configure the built-in role management resource. You can
    | customize the URL, choose whether to show model paths, group it under
    | a cluster, and decide which permission tabs to display.
    |
    */

    'shield_resource' => [
        'slug' => 'shield/roles',
        'show_model_path' => true,
        'cluster' => null,
        'tabs' => [
            'pages' => true,
            'widgets' => true,
            'resources' => true,
            'custom_permissions' => false,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Multi-Tenancy
    |--------------------------------------------------------------------------
    |
    | When your application supports teams, Shield will automatically detect
    | and configure the tenant model during setup. This enables tenant-scoped
    | roles and permissions throughout your application.
    |
    */

    'tenant_model' => null,

    /*
    |--------------------------------------------------------------------------
    | User Model
    |--------------------------------------------------------------------------
    |
    | This value contains the class name of your user model. This model will
    | be used for role assignments and must implement the HasRoles trait
    | provided by the Spatie\Permission package.
    |
    */

    'auth_provider_model' => 'App\\Models\\User',

    /*
    |--------------------------------------------------------------------------
    | Super Admin
    |--------------------------------------------------------------------------
    |
    | Here you may define a super admin that has unrestricted access to your
    | application. You can choose to implement this via Laravel's gate system
    | or as a traditional role with all permissions explicitly assigned.
    |
    */

    'super_admin' => [
        'enabled' => true,
        'name' => 'super_admin',
        'define_via_gate' => false,
        'intercept_gate' => 'before',
    ],

    /*
    |--------------------------------------------------------------------------
    | Panel User
    |--------------------------------------------------------------------------
    |
    | When enabled, Shield will create a basic panel user role that can be
    | assigned to users who should have access to your Filament panels but
    | don't need any specific permissions beyond basic authentication.
    |
    */

    'panel_user' => [
        'enabled' => true,
        'name' => 'panel_user',
    ],

    /*
    |--------------------------------------------------------------------------
    | Permission Builder
    |--------------------------------------------------------------------------
    |
    | You can customize how permission keys are generated to match your
    | preferred naming convention and organizational standards. Shield uses
    | these settings when creating permission names from your resources.
    |
    | Supported formats: snake, kebab, pascal, camel, upper_snake, lower_snake
    |
    */

    'permissions' => [
        'separator' => ':',
        'case' => 'pascal',
        'generate' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Policies
    |--------------------------------------------------------------------------
    |
    | Shield can automatically generate Laravel policies for your resources.
    | When merge is enabled, the methods below will be combined with any
    | resource-specific methods you define in the resources section.
    |
    */

    'policies' => [
        'path' => app_path('Policies'),
        'merge' => true,
        'generate' => true,
        'methods' => [
            'viewAny', 'view', 'create', 'update', 'delete', 'restore',
            'forceDelete', 'forceDeleteAny', 'restoreAny', 'replicate', 'reorder',
        ],
        'single_parameter_methods' => [
            'viewAny',
            'create',
            'deleteAny',
            'forceDeleteAny',
            'restoreAny',
            'reorder',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Localization
    |--------------------------------------------------------------------------
    |
    | Shield supports multiple languages out of the box. When enabled, you
    | can provide translated labels for permissions to create a more
    | localized experience for your international users.
    |
    */

    'localization' => [
        'enabled' => false,
        'key' => 'filament-shield::filament-shield.resource_permission_prefixes_labels',
    ],

    /*
    |--------------------------------------------------------------------------
    | Resources
    |--------------------------------------------------------------------------
    |
    | Here you can fine-tune permissions for specific Filament resources.
    | Use the 'manage' array to override the default policy methods for
    | individual resources, giving you granular control over permissions.
    |
    */

    'resources' => [
        'subject' => 'model',
        'manage' => [
            \BezhanSalleh\FilamentShield\Resources\Roles\RoleResource::class => [
                'viewAny',
                'view',
                'create',
                'update',
                'delete',
            ],
        ],
        'exclude' => [
            //
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pages
    |--------------------------------------------------------------------------
    |
    | Most Filament pages only require view permissions. Pages listed in the
    | exclude array will be skipped during permission generation and won't
    | appear in your role management interface.
    |
    */

    'pages' => [
        'subject' => 'class',
        'prefix' => 'view',
        'exclude' => [
            \Filament\Pages\Dashboard::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Widgets
    |--------------------------------------------------------------------------
    |
    | Like pages, widgets typically only need view permissions. Add widgets
    | to the exclude array if you don't want them to appear in your role
    | management interface.
    |
    */

    'widgets' => [
        'subject' => 'class',
        'prefix' => 'view',
        'exclude' => [
            \Filament\Widgets\AccountWidget::class,
            \Filament\Widgets\FilamentInfoWidget::class,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Permissions
    |--------------------------------------------------------------------------
    |
    | Sometimes you need permissions that don't map to resources, pages, or
    | widgets. Define any custom permissions here and they'll be available
    | when editing roles in your application.
    |
    */

    'custom_permissions' => [],

    /*
    |--------------------------------------------------------------------------
    | Entity Discovery
    |--------------------------------------------------------------------------
    |
    | By default, Shield only looks for entities in your default Filament
    | panel. Enable these options if you're using multiple panels and want
    | Shield to discover entities across all of them.
    |
    */

    'discovery' => [
        'discover_all_resources' => false,
        'discover_all_widgets' => false,
        'discover_all_pages' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Role Policy
    |--------------------------------------------------------------------------
    |
    | Shield can automatically register a policy for role management itself.
    | This lets you control who can manage roles using Laravel's built-in
    | authorization system. Requires a RolePolicy class in your app.
    |
    */

    'register_role_policy' => true,

];

```

## File: config/filemanager.php
```php
<?php

return [
    /*
    |--------------------------------------------------------------------------
    | File Manager Mode
    |--------------------------------------------------------------------------
    |
    | The file manager supports two modes:
    |
    | - 'database': Files and folders are tracked in a database table.
    |   Metadata, hierarchy, and relationships are stored in the database.
    |   File contents are stored on the configured disk. Best for applications
    |   that need to attach metadata, tags, or relationships to files.
    |
    | - 'storage': Files and folders are read directly from a storage disk.
    |   No database is used. The file manager shows the actual file system
    |   structure. Renaming and moving actually rename/move files on the disk.
    |   Best for managing cloud storage (S3, etc.) or local file systems.
    |
    */
    'mode' => 'storage', // 'database' or 'storage'

    /*
    |--------------------------------------------------------------------------
    | Storage Mode Settings
    |--------------------------------------------------------------------------
    |
    | These settings only apply when mode is set to 'storage'.
    |
    | - disk: The Laravel filesystem disk to use (e.g., 'local', 's3', 'public')
    | - root: The root path within the disk (empty string for disk root)
    | - show_hidden: Whether to show hidden files (starting with .)
    |
    */
    'storage_mode' => [
        'disk' => env('FILEMANAGER_DISK', env('FILESYSTEM_DISK', 'public')),
        'root' => env('FILEMANAGER_ROOT', ''),
        'show_hidden' => env('FILEMANAGER_SHOW_HIDDEN', false),
        // For S3/MinIO: URL expiration time in minutes for signed URLs
        'url_expiration' => env('FILEMANAGER_URL_EXPIRATION', 60),
    ],

    /*
    |--------------------------------------------------------------------------
    | File Streaming Settings
    |--------------------------------------------------------------------------
    |
    | Configure how files are served for preview and download.
    |
    | The file manager uses different URL strategies based on the disk:
    | - S3-compatible disks: Uses temporaryUrl() for pre-signed URLs
    | - Public disk: Uses direct Storage::url() (works via symlink)
    | - Local/other disks: Uses signed routes to a streaming controller
    |
    */
    'streaming' => [
        // URL generation strategy:
        // - 'auto': Automatically detect best strategy per disk (recommended)
        // - 'signed_route': Always use signed routes to streaming controller
        // - 'direct': Always use Storage::url() (only works for public disk)
        'url_strategy' => env('FILEMANAGER_URL_STRATEGY', 'auto'),

        // URL expiration in minutes (for signed URLs and S3 temporary URLs)
        'url_expiration' => env('FILEMANAGER_URL_EXPIRATION', 60),

        // Route prefix for streaming endpoints
        'route_prefix' => env('FILEMANAGER_ROUTE_PREFIX', 'filemanager'),

        // Middleware applied to streaming routes
        'middleware' => ['web'],

        // Disks that should always use signed routes (even if public)
        // Useful if you want extra security for certain disks
        'force_signed_disks' => [],

        // Disks that are publicly accessible via URL (override auto-detection)
        // Files on these disks can be accessed directly without streaming
        'public_disks' => ['public'],

        // Disks that don't require authentication for streaming access
        // Use with caution - files on these disks can be accessed without login
        // Note: Signed URLs are still required, this just skips the auth check
        'public_access_disks' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | File System Item Model (Database Mode)
    |--------------------------------------------------------------------------
    |
    | This is the model that represents files and folders in your application.
    | Only used when mode is 'database'.
    | It must implement the MWGuerra\FileManager\Contracts\FileSystemItemInterface.
    |
    | The package provides a default model. You can extend it or create your own:
    |
    | Option 1: Use the package model directly (default)
    | 'model' => \MWGuerra\FileManager\Models\FileSystemItem::class,
    |
    | Option 2: Extend the package model in your app
    | 'model' => \App\Models\FileSystemItem::class,
    | // where App\Models\FileSystemItem extends MWGuerra\FileManager\Models\FileSystemItem
    |
    | Option 3: Create your own model implementing FileSystemItemInterface
    | 'model' => \App\Models\CustomFileModel::class,
    |
    */
    'model' => \MWGuerra\FileManager\Models\FileSystemItem::class,

    /*
    |--------------------------------------------------------------------------
    | File Manager Page (Database Mode)
    |--------------------------------------------------------------------------
    |
    | Configure the File Manager page which uses database mode to track
    | files with metadata, hierarchy, and relationships.
    |
    */
    'file_manager' => [
        'enabled' => true,
        'navigation' => [
            'icon' => 'heroicon-o-folder',
            'label' => 'File Manager',
            'sort' => 1,
            'group' => 'FileManager',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | File System Page (Storage Mode)
    |--------------------------------------------------------------------------
    |
    | Configure the File System page which shows files directly from the
    | storage disk without using the database.
    |
    */
    'file_system' => [
        'enabled' => true,
        'navigation' => [
            'icon' => 'heroicon-o-server-stack',
            'label' => 'File System',
            'sort' => 2,
            'group' => 'FileManager',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Schema Example Page
    |--------------------------------------------------------------------------
    |
    | Enable or disable the Schema Example page which demonstrates
    | how to embed the file manager components into Filament forms.
    |
    */
    'schema_example' => [
        'enabled' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Upload Settings
    |--------------------------------------------------------------------------
    |
    | Configure upload settings for the file manager.
    |
    | Note: You may also need to adjust PHP settings in php.ini:
    |   - upload_max_filesize (default: 2M)
    |   - post_max_size (default: 8M)
    |   - max_execution_time (default: 30)
    |
    | For Livewire temporary uploads, also check config/livewire.php:
    |   - temporary_file_upload.rules (default: max:12288 = 12MB)
    |
    */
    'upload' => [
        'disk' => env('FILEMANAGER_DISK', env('FILESYSTEM_DISK', 'public')),
        'directory' => env('FILEMANAGER_UPLOAD_DIR', 'uploads'),
        'max_file_size' => 100 * 1024, // 100 MB in kilobytes
        'allowed_mimes' => [
            // Videos
            'video/mp4', 'video/webm', 'video/ogg', 'video/quicktime', 'video/x-msvideo',
            // Images (SVG excluded by default - can contain scripts)
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            // Documents
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain',
            // Audio
            'audio/mpeg', 'audio/wav', 'audio/ogg', 'audio/webm', 'audio/flac',
            // Archives
            'application/zip', 'application/x-rar-compressed', 'application/x-7z-compressed',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Settings
    |--------------------------------------------------------------------------
    |
    | Configure security settings to prevent malicious file uploads and access.
    |
    */
    'security' => [
        // Dangerous extensions that should NEVER be uploaded (executable files)
        'blocked_extensions' => [
            // Server-side scripts
            'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar',
            'pl', 'py', 'pyc', 'pyo', 'rb', 'sh', 'bash', 'zsh', 'cgi',
            'asp', 'aspx', 'jsp', 'jspx', 'cfm', 'cfc',
            // Executables
            'exe', 'msi', 'dll', 'com', 'bat', 'cmd', 'vbs', 'vbe',
            'js', 'jse', 'ws', 'wsf', 'wsc', 'wsh', 'ps1', 'psm1',
            // Other dangerous
            'htaccess', 'htpasswd', 'ini', 'log', 'sql', 'env',
            'pem', 'key', 'crt', 'cer',
        ],

        // Files that can contain embedded scripts (XSS risk when served inline)
        'sanitize_extensions' => ['svg', 'html', 'htm', 'xml'],

        // Validate MIME type matches extension (prevents spoofing)
        'validate_mime' => true,

        // Rename files to prevent execution (adds random prefix)
        'rename_uploads' => false,

        // Strip potentially dangerous characters from filenames
        'sanitize_filenames' => true,

        // Maximum filename length
        'max_filename_length' => 255,

        // Patterns blocked in filenames (regex)
        'blocked_filename_patterns' => [
            '/\.{2,}/',           // Multiple dots (path traversal)
            '/^\./',              // Hidden files
            '/[\x00-\x1f]/',      // Control characters
            '/[<>:"|?*]/',        // Windows reserved characters
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Authorization Settings
    |--------------------------------------------------------------------------
    |
    | Configure authorization for file manager operations.
    |
    | When enabled, the package will check permissions before allowing operations.
    | You can specify permission names that will be checked via the user's can() method.
    |
    | To customize authorization logic, extend FileSystemItemPolicy and register
    | your custom policy in your application's AuthServiceProvider.
    |
    */
    'authorization' => [
        // Enable/disable authorization checks (set to false during development)
        'enabled' => env('FILEMANAGER_AUTH_ENABLED', true),

        // Permission names to check (uses user->can() method)
        // Set to null to skip permission check and just require authentication
        'permissions' => [
            'view_any' => null,    // Access file manager page
            'view' => null,        // View/preview files
            'create' => null,      // Upload files, create folders
            'update' => null,      // Rename, move items
            'delete' => null,      // Delete items
            'delete_any' => null,  // Bulk delete
            'download' => null,    // Download files
        ],

        // The policy class to use (can be overridden with custom implementation)
        'policy' => \MWGuerra\FileManager\Policies\FileSystemItemPolicy::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Panel Sidebar Settings
    |--------------------------------------------------------------------------
    |
    | Configure the file manager folder tree that can be rendered in the
    | Filament panel sidebar using render hooks.
    |
    | - enabled: Enable/disable the sidebar folder tree
    | - root_label: Label for the root folder (e.g., "Root", "/", "Home")
    | - heading: Heading text shown above the folder tree
    | - show_in_file_manager: Show the sidebar within the file manager page
    |
    */
    'sidebar' => [
        'enabled' => true,
        'root_label' => env('FILEMANAGER_SIDEBAR_ROOT_LABEL', 'Root'),
        'heading' => env('FILEMANAGER_SIDEBAR_HEADING', 'Folders'),
        'show_in_file_manager' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | File Types
    |--------------------------------------------------------------------------
    |
    | Configure which file types are enabled and register custom file types.
    |
    | Built-in types can be disabled by setting their value to false.
    | Custom types can be added by listing their fully-qualified class names.
    |
    | Each custom type class must implement FileTypeContract or extend
    | AbstractFileType from MWGuerra\FileManager\FileTypes.
    |
    | Example of registering custom types:
    |
    | 'custom' => [
    |     \App\FileTypes\ThreeDModelFileType::class,
    |     \App\FileTypes\EbookFileType::class,
    | ],
    |
    */
    'file_types' => [
        // Built-in types (set to false to disable)
        'video' => true,
        'image' => true,
        'audio' => true,
        'pdf' => true,
        'text' => true,
        'document' => true,
        'archive' => true,

        // Custom file types (fully-qualified class names)
        'custom' => [
            // \App\FileTypes\ThreeDModelFileType::class,
        ],
    ],
];

```

## File: config/filesystems.php
```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => storage_path('app/public'),
            'url' => rtrim(env('APP_URL', 'http://localhost'), '/').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];

```

## File: config/health.php
```php
<?php

return [
    /*
     * A result store is responsible for saving the results of the checks. The
     * `EloquentHealthResultStore` will save results in the database. You
     * can use multiple stores at the same time.
     */
    'result_stores' => [
        Spatie\Health\ResultStores\EloquentHealthResultStore::class => [
            'connection' => env('HEALTH_DB_CONNECTION', env('DB_CONNECTION')),
            'model' => Spatie\Health\Models\HealthCheckResultHistoryItem::class,
            'keep_history_for_days' => 5,
        ],

        /*
        Spatie\Health\ResultStores\CacheHealthResultStore::class => [
            'store' => 'file',
        ],

        Spatie\Health\ResultStores\JsonFileHealthResultStore::class => [
            'disk' => 's3',
            'path' => 'health.json',
        ],

        Spatie\Health\ResultStores\InMemoryHealthResultStore::class,
        */
    ],

    /*
     * You can get notified when specific events occur. Out of the box you can use 'mail' and 'slack'.
     * For Slack you need to install laravel/slack-notification-channel.
     */
    'notifications' => [
        /*
         * Notifications will only get sent if this option is set to `true`.
         */
        'enabled' => true,

        'notifications' => [
            Spatie\Health\Notifications\CheckFailedNotification::class => ['mail'],
        ],

        /*
         * Here you can specify the notifiable to which the notifications should be sent. The default
         * notifiable will use the variables specified in this config file.
         */
        'notifiable' => Spatie\Health\Notifications\Notifiable::class,

        /*
         * When checks start failing, you could potentially end up getting
         * a notification every minute.
         *
         * With this setting, notifications are throttled. By default, you'll
         * only get one notification per hour.
         */
        'throttle_notifications_for_minutes' => 60,
        'throttle_notifications_key' => 'health:latestNotificationSentAt:',

        /*
         * When set to true, notifications will only be sent when at least one
         * check has a 'failed' status. Warnings will be ignored.
         */
        'only_on_failure' => false,

        'mail' => [
            'to' => 'your@example.com',

            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                'name' => env('MAIL_FROM_NAME', 'Example'),
            ],
        ],

        'slack' => [
            'webhook_url' => env('HEALTH_SLACK_WEBHOOK_URL', ''),

            /*
             * If this is set to null the default channel of the webhook will be used.
             */
            'channel' => null,

            'username' => null,

            'icon' => null,
        ],
    ],

    /*
     * You can let Oh Dear monitor the results of all health checks. This way, you'll
     * get notified of any problems even if your application goes totally down. Via
     * Oh Dear, you can also have access to more advanced notification options.
     */
    'oh_dear_endpoint' => [
        'enabled' => false,

        /*
         * When this option is enabled, the checks will run before sending a response.
         * Otherwise, we'll send the results from the last time the checks have run.
         */
        'always_send_fresh_results' => true,

        /*
         * The secret that is displayed at the Application Health settings at Oh Dear.
         */
        'secret' => env('OH_DEAR_HEALTH_CHECK_SECRET'),

        /*
         * The URL that should be configured in the Application health settings at Oh Dear.
         */
        'url' => '/oh-dear-health-check-results',
    ],

    /*
     * You can specify a heartbeat URL for the Horizon check.
     * This URL will be pinged if the Horizon check is successful.
     * This way you can get notified if Horizon goes down.
     */
    'horizon' => [
        'heartbeat_url' => env('HORIZON_HEARTBEAT_URL'),
    ],

    /*
     * You can specify a heartbeat URL for the Schedule check.
     * This URL will be pinged if the Schedule check is successful.
     * This way you can get notified if the schedule fails to run.
     */
    'schedule' => [
        'heartbeat_url' => env('SCHEDULE_HEARTBEAT_URL'),
    ],

    /*
     * You can set a theme for the local results page
     *
     * - light: light mode
     * - dark: dark mode
     */
    'theme' => 'light',

    /*
     * When enabled, completed `HealthQueueJob`s will be displayed
     * in Horizon's silenced jobs screen.
     */
    'silence_health_queue_job' => true,

    /*
     * The response code to use for HealthCheckJsonResultsController when a health
     * check has failed
     */
    'json_results_failure_status' => 200,

    /*
     * You can specify a secret token that needs to be sent in the X-Secret-Token for secured access.
     */
    'secret_token' => env('HEALTH_SECRET_TOKEN'),

/**
 * By default, conditionally skipped health checks are treated as failures.
 * You can override this behavior by uncommenting the configuration below.
 *
 * @link https://spatie.be/docs/laravel-health/v1/basic-usage/conditionally-running-or-modifying-checks
 */
    // 'treat_skipped_as_failure' => false
];

```

## File: config/livewire.php
```php
<?php

return [

    /*
    |---------------------------------------------------------------------------
    | Class Namespace
    |---------------------------------------------------------------------------
    |
    | This value sets the root class namespace for Livewire component classes in
    | your application. This value will change where component auto-discovery
    | finds components. It's also referenced by the file creation commands.
    |
    */

    'class_namespace' => 'App\\Livewire',

    /*
    |---------------------------------------------------------------------------
    | View Path
    |---------------------------------------------------------------------------
    |
    | This value is used to specify where Livewire component Blade templates are
    | stored when running file creation commands like `artisan make:livewire`.
    | It is also used if you choose to omit a component's render() method.
    |
    */

    'view_path' => resource_path('views/livewire'),

    /*
    |---------------------------------------------------------------------------
    | Layout
    |---------------------------------------------------------------------------
    | The view that will be used as the layout when rendering a single component
    | as an entire page via `Route::get('/post/create', CreatePost::class);`.
    | In this case, the view returned by CreatePost will render into $slot.
    |
    */

    'layout' => 'components.layouts.app',

    /*
    |---------------------------------------------------------------------------
    | Lazy Loading Placeholder
    |---------------------------------------------------------------------------
    | Livewire allows you to lazy load components that would otherwise slow down
    | the initial page load. Every component can have a custom placeholder or
    | you can define the default placeholder view for all components below.
    |
    */

    'lazy_placeholder' => null,

    /*
    |---------------------------------------------------------------------------
    | Temporary File Uploads
    |---------------------------------------------------------------------------
    |
    | Livewire handles file uploads by storing uploads in a temporary directory
    | before the file is stored permanently. All file uploads are directed to
    | a global endpoint for temporary storage. You may configure this below:
    |
    */

    'temporary_file_upload' => [
        'disk' => 'local',        // Example: 'local', 's3'              | Default: 'default'
        'rules' => null,       // Example: ['file', 'mimes:png,jpg']  | Default: ['required', 'file', 'max:12288'] (12MB)
        'directory' => null,   // Example: 'tmp'                      | Default: 'livewire-tmp'
        'middleware' => null,  // Example: 'throttle:5,1'             | Default: 'throttle:60,1'
        'preview_mimes' => [   // Supported file types for temporary pre-signed file URLs...
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
        'max_upload_time' => 5, // Max duration (in minutes) before an upload is invalidated...
        'cleanup' => true, // Should cleanup temporary uploads older than 24 hrs...
    ],

    /*
    |---------------------------------------------------------------------------
    | Render On Redirect
    |---------------------------------------------------------------------------
    |
    | This value determines if Livewire will run a component's `render()` method
    | after a redirect has been triggered using something like `redirect(...)`
    | Setting this to true will render the view once more before redirecting
    |
    */

    'render_on_redirect' => false,

    /*
    |---------------------------------------------------------------------------
    | Eloquent Model Binding
    |---------------------------------------------------------------------------
    |
    | Previous versions of Livewire supported binding directly to eloquent model
    | properties using wire:model by default. However, this behavior has been
    | deemed too "magical" and has therefore been put under a feature flag.
    |
    */

    'legacy_model_binding' => false,

    /*
    |---------------------------------------------------------------------------
    | Auto-inject Frontend Assets
    |---------------------------------------------------------------------------
    |
    | By default, Livewire automatically injects its JavaScript and CSS into the
    | <head> and <body> of pages containing Livewire components. By disabling
    | this behavior, you need to use @livewireStyles and @livewireScripts.
    |
    */

    'inject_assets' => true,

    /*
    |---------------------------------------------------------------------------
    | Navigate (SPA mode)
    |---------------------------------------------------------------------------
    |
    | By adding `wire:navigate` to links in your Livewire application, Livewire
    | will prevent the default link handling and instead request those pages
    | via AJAX, creating an SPA-like effect. Configure this behavior here.
    |
    */

    'navigate' => [
        'show_progress_bar' => true,
        'progress_bar_color' => '#2299dd',
    ],

    /*
    |---------------------------------------------------------------------------
    | HTML Morph Markers
    |---------------------------------------------------------------------------
    |
    | Livewire intelligently "morphs" existing HTML into the newly rendered HTML
    | after each update. To make this process more reliable, Livewire injects
    | "markers" into the rendered Blade surrounding @if, @class & @foreach.
    |
    */

    'inject_morph_markers' => true,

    /*
    |---------------------------------------------------------------------------
    | Smart Wire Keys
    |---------------------------------------------------------------------------
    |
    | Livewire uses loops and keys used within loops to generate smart keys that
    | are applied to nested components that don't have them. This makes using
    | nested components more reliable by ensuring that they all have keys.
    |
    */

    'smart_wire_keys' => false,

    /*
    |---------------------------------------------------------------------------
    | Pagination Theme
    |---------------------------------------------------------------------------
    |
    | When enabling Livewire's pagination feature by using the `WithPagination`
    | trait, Livewire will use Tailwind templates to render pagination views
    | on the page. If you want Bootstrap CSS, you can specify: "bootstrap"
    |
    */

    'pagination_theme' => 'tailwind',

    /*
    |---------------------------------------------------------------------------
    | Release Token
    |---------------------------------------------------------------------------
    |
    | This token is stored client-side and sent along with each request to check
    | a users session to see if a new release has invalidated it. If there is
    | a mismatch it will throw an error and prompt for a browser refresh.
    |
    */

    'release_token' => 'a',
];

```

## File: config/logging.php
```php
<?php

use Monolog\Handler\NullHandler;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SyslogUdpHandler;
use Monolog\Processor\PsrLogMessageProcessor;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that is utilized to write
    | messages to your logs. The value provided here should match one of
    | the channels present in the list of "channels" configured below.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Deprecations Log Channel
    |--------------------------------------------------------------------------
    |
    | This option controls the log channel that should be used to log warnings
    | regarding deprecated PHP and library features. This allows you to get
    | your application ready for upcoming major versions of dependencies.
    |
    */

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Laravel
    | utilizes the Monolog PHP logging library, which includes a variety
    | of powerful log handlers and formatters that you're free to use.
    |
    | Available drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog", "custom", "stack"
    |
    */

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => explode(',', (string) env('LOG_STACK', 'single')),
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'debug'),
            'days' => env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
        ],

        'slack' => [
            'driver' => 'slack',
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => env('LOG_SLACK_USERNAME', 'Laravel Log'),
            'emoji' => env('LOG_SLACK_EMOJI', ':boom:'),
            'level' => env('LOG_LEVEL', 'critical'),
            'replace_placeholders' => true,
        ],

        'papertrail' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => env('LOG_PAPERTRAIL_HANDLER', SyslogUdpHandler::class),
            'handler_with' => [
                'host' => env('PAPERTRAIL_URL'),
                'port' => env('PAPERTRAIL_PORT'),
                'connectionString' => 'tls://'.env('PAPERTRAIL_URL').':'.env('PAPERTRAIL_PORT'),
            ],
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'stderr' => [
            'driver' => 'monolog',
            'level' => env('LOG_LEVEL', 'debug'),
            'handler' => StreamHandler::class,
            'handler_with' => [
                'stream' => 'php://stderr',
            ],
            'formatter' => env('LOG_STDERR_FORMATTER'),
            'processors' => [PsrLogMessageProcessor::class],
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => env('LOG_LEVEL', 'debug'),
            'facility' => env('LOG_SYSLOG_FACILITY', LOG_USER),
            'replace_placeholders' => true,
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => env('LOG_LEVEL', 'debug'),
            'replace_placeholders' => true,
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

        'emergency' => [
            'path' => storage_path('logs/laravel.log'),
        ],

    ],

];

```

## File: config/mail.php
```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],

];

```

## File: config/media-library.php
```php
<?php

return [

    /*
     * The disk on which to store added files and derived images by default. Choose
     * one or more of the disks you've configured in config/filesystems.php.
     */
    'disk_name' => env('MEDIA_DISK', 'public'),

    /*
     * The maximum file size of an item in bytes.
     * Adding a larger file will result in an exception.
     */
    'max_file_size' => 1024 * 1024 * 10, // 10MB

    /*
     * This queue connection will be used to generate derived and responsive images.
     * Leave empty to use the default queue connection.
     */
    'queue_connection_name' => env('QUEUE_CONNECTION', 'sync'),

    /*
     * This queue will be used to generate derived and responsive images.
     * Leave empty to use the default queue.
     */
    'queue_name' => env('MEDIA_QUEUE', ''),

    /*
     * By default all conversions will be performed on a queue.
     */
    'queue_conversions_by_default' => env('QUEUE_CONVERSIONS_BY_DEFAULT', true),

    /*
     * Should database transactions be run after database commits?
     */
    'queue_conversions_after_database_commit' => env('QUEUE_CONVERSIONS_AFTER_DB_COMMIT', true),

    /*
     * The fully qualified class name of the media model.
     */
    'media_model' => Spatie\MediaLibrary\MediaCollections\Models\Media::class,

    /*
     * The fully qualified class name of the media observer.
     */
    'media_observer' => Spatie\MediaLibrary\MediaCollections\Models\Observers\MediaObserver::class,

    /*
     * When enabled, media collections will be serialised using the default
     * laravel model serialization behaviour.
     *
     * Keep this option disabled if using Media Library Pro components (https://medialibrary.pro)
     */
    'use_default_collection_serialization' => false,

    /*
     * The fully qualified class name of the model used for temporary uploads.
     *
     * This model is only used in Media Library Pro (https://medialibrary.pro)
     */
    'temporary_upload_model' => Spatie\MediaLibraryPro\Models\TemporaryUpload::class,

    /*
     * When enabled, Media Library Pro will only process temporary uploads that were uploaded
     * in the same session. You can opt to disable this for stateless usage of
     * the pro components.
     */
    'enable_temporary_uploads_session_affinity' => true,

    /*
     * When enabled, Media Library pro will generate thumbnails for uploaded file.
     */
    'generate_thumbnails_for_temporary_uploads' => true,

    /*
     * This is the class that is responsible for naming generated files.
     */
    'file_namer' => Spatie\MediaLibrary\Support\FileNamer\DefaultFileNamer::class,

    /*
     * The class that contains the strategy for determining a media file's path.
     */
    'path_generator' => Spatie\MediaLibrary\Support\PathGenerator\DefaultPathGenerator::class,

    /*
     * The class that contains the strategy for determining how to remove files.
     */
    'file_remover_class' => Spatie\MediaLibrary\Support\FileRemover\DefaultFileRemover::class,

    /*
     * Here you can specify which path generator should be used for the given class.
     */
    'custom_path_generators' => [
        // Model::class => PathGenerator::class
        // or
        // 'model_morph_alias' => PathGenerator::class
    ],

    /*
     * When urls to files get generated, this class will be called. Use the default
     * if your files are stored locally above the site root or on s3.
     */
    'url_generator' => Spatie\MediaLibrary\Support\UrlGenerator\DefaultUrlGenerator::class,

    /*
     * Moves media on updating to keep path consistent. Enable it only with a custom
     * PathGenerator that uses, for example, the media UUID.
     */
    'moves_media_on_update' => false,

    /*
     * Whether to activate versioning when urls to files get generated.
     * When activated, this attaches a ?v=xx query string to the URL.
     */
    'version_urls' => false,

    /*
     * The media library will try to optimize all converted images by removing
     * metadata and applying a little bit of compression. These are
     * the optimizers that will be used by default.
     */
    'image_optimizers' => [
        Spatie\ImageOptimizer\Optimizers\Jpegoptim::class => [
            '-m85', // set maximum quality to 85%
            '--force', // ensure that progressive generation is always done also if a little bigger
            '--strip-all', // this strips out all text information such as comments and EXIF data
            '--all-progressive', // this will make sure the resulting image is a progressive one
        ],
        Spatie\ImageOptimizer\Optimizers\Pngquant::class => [
            '--force', // required parameter for this package
        ],
        Spatie\ImageOptimizer\Optimizers\Optipng::class => [
            '-i0', // this will result in a non-interlaced, progressive scanned image
            '-o2', // this set the optimization level to two (multiple IDAT compression trials)
            '-quiet', // required parameter for this package
        ],
        Spatie\ImageOptimizer\Optimizers\Svgo::class => [
            '--disable=cleanupIDs', // disabling because it is known to cause troubles
        ],
        Spatie\ImageOptimizer\Optimizers\Gifsicle::class => [
            '-b', // required parameter for this package
            '-O3', // this produces the slowest but best results
        ],
        Spatie\ImageOptimizer\Optimizers\Cwebp::class => [
            '-m 6', // for the slowest compression method in order to get the best compression.
            '-pass 10', // for maximizing the amount of analysis pass.
            '-mt', // multithreading for some speed improvements.
            '-q 90', // quality factor that brings the least noticeable changes.
        ],
        Spatie\ImageOptimizer\Optimizers\Avifenc::class => [
            '-a cq-level=23', // constant quality level, lower values mean better quality and greater file size (0-63).
            '-j all', // number of jobs (worker threads, "all" uses all available cores).
            '--min 0', // min quantizer for color (0-63).
            '--max 63', // max quantizer for color (0-63).
            '--minalpha 0', // min quantizer for alpha (0-63).
            '--maxalpha 63', // max quantizer for alpha (0-63).
            '-a end-usage=q', // rate control mode set to Constant Quality mode.
            '-a tune=ssim', // SSIM as tune the encoder for distortion metric.
        ],
    ],

    /*
     * These generators will be used to create an image of media files.
     */
    'image_generators' => [
        Spatie\MediaLibrary\Conversions\ImageGenerators\Image::class,
        Spatie\MediaLibrary\Conversions\ImageGenerators\Webp::class,
        Spatie\MediaLibrary\Conversions\ImageGenerators\Avif::class,
        Spatie\MediaLibrary\Conversions\ImageGenerators\Pdf::class,
        Spatie\MediaLibrary\Conversions\ImageGenerators\Svg::class,
        Spatie\MediaLibrary\Conversions\ImageGenerators\Video::class,
    ],

    /*
     * The path where to store temporary files while performing image conversions.
     * If set to null, storage_path('media-library/temp') will be used.
     */
    'temporary_directory_path' => null,

    /*
     * The engine that should perform the image conversions.
     * Should be either `gd`, `imagick` or `vips`.
     */
    'image_driver' => env('IMAGE_DRIVER', 'gd'),

    /*
     * FFMPEG & FFProbe binaries paths, only used if you try to generate video
     * thumbnails and have installed the php-ffmpeg/php-ffmpeg composer
     * dependency.
     */
    'ffmpeg_path' => env('FFMPEG_PATH', '/usr/bin/ffmpeg'),
    'ffprobe_path' => env('FFPROBE_PATH', '/usr/bin/ffprobe'),

    /*
     * The timeout (in seconds) that will be used when generating video
     * thumbnails via FFMPEG.
     */
    'ffmpeg_timeout' => env('FFMPEG_TIMEOUT', 900),

    /*
     * The number of threads that FFMPEG should use. 0 means that FFMPEG
     * may decide itself.
     */
    'ffmpeg_threads' => env('FFMPEG_THREADS', 0),

    /*
     * Here you can override the class names of the jobs used by this package. Make sure
     * your custom jobs extend the ones provided by the package.
     */
    'jobs' => [
        'perform_conversions' => Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob::class,
        'generate_responsive_images' => Spatie\MediaLibrary\ResponsiveImages\Jobs\GenerateResponsiveImagesJob::class,
    ],

    /*
     * When using the addMediaFromUrl method you may want to replace the default downloader.
     * This is particularly useful when the url of the image is behind a firewall and
     * need to add additional flags, possibly using curl.
     */
    'media_downloader' => Spatie\MediaLibrary\Downloaders\DefaultDownloader::class,

    /*
     * When using the addMediaFromUrl method the SSL is verified by default.
     * This is option disables SSL verification when downloading remote media.
     * Please note that this is a security risk and should only be false in a local environment.
     */
    'media_downloader_ssl' => env('MEDIA_DOWNLOADER_SSL', true),

    /*
     * The default lifetime in minutes for temporary urls.
     * This is used when you call the `getLastTemporaryUrl` or `getLastTemporaryUrl` method on a media item.
     */
    'temporary_url_default_lifetime' => env('MEDIA_TEMPORARY_URL_DEFAULT_LIFETIME', 5),

    'remote' => [
        /*
         * Any extra headers that should be included when uploading media to
         * a remote disk. Even though supported headers may vary between
         * different drivers, a sensible default has been provided.
         *
         * Supported by S3: CacheControl, Expires, StorageClass,
         * ServerSideEncryption, Metadata, ACL, ContentEncoding
         */
        'extra_headers' => [
            'CacheControl' => 'max-age=604800',
        ],
    ],

    'responsive_images' => [
        /*
         * This class is responsible for calculating the target widths of the responsive
         * images. By default we optimize for filesize and create variations that each are 30%
         * smaller than the previous one. More info in the documentation.
         *
         * https://docs.spatie.be/laravel-medialibrary/v9/advanced-usage/generating-responsive-images
         */
        'width_calculator' => Spatie\MediaLibrary\ResponsiveImages\WidthCalculator\FileSizeOptimizedWidthCalculator::class,

        /*
         * By default rendering media to a responsive image will add some javascript and a tiny placeholder.
         * This ensures that the browser can already determine the correct layout.
         * When disabled, no tiny placeholder is generated.
         */
        'use_tiny_placeholders' => true,

        /*
         * This class will generate the tiny placeholder used for progressive image loading. By default
         * the media library will use a tiny blurred jpg image.
         */
        'tiny_placeholder_generator' => Spatie\MediaLibrary\ResponsiveImages\TinyPlaceholderGenerator\Blurred::class,
    ],

    /*
     * When enabling this option, a route will be registered that will enable
     * the Media Library Pro Vue and React components to move uploaded files
     * in a S3 bucket to their right place.
     */
    'enable_vapor_uploads' => env('ENABLE_MEDIA_LIBRARY_VAPOR_UPLOADS', false),

    /*
     * When converting Media instances to response the media library will add
     * a `loading` attribute to the `img` tag. Here you can set the default
     * value of that attribute.
     *
     * Possible values: 'lazy', 'eager', 'auto' or null if you don't want to set any loading instruction.
     *
     * More info: https://css-tricks.com/native-lazy-loading/
     */
    'default_loading_attribute_value' => null,

    /*
     * You can specify a prefix for that is used for storing all media.
     * If you set this to `/my-subdir`, all your media will be stored in a `/my-subdir` directory.
     */
    'prefix' => env('MEDIA_PREFIX', ''),

    /*
     * When forcing lazy loading, media will be loaded even if you don't eager load media and you have
     * disabled lazy loading globally in the service provider.
     */
    'force_lazy_loading' => env('FORCE_MEDIA_LIBRARY_LAZY_LOADING', true),
];

```

## File: config/permission.php
```php
<?php

return [

    'models' => [

        /*
         * When using the "HasPermissions" trait from this package, we need to know which
         * Eloquent model should be used to retrieve your permissions. Of course, it
         * is often just the "Permission" model but you may use whatever you like.
         *
         * The model you want to use as a Permission model needs to implement the
         * `Spatie\Permission\Contracts\Permission` contract.
         */

        'permission' => Spatie\Permission\Models\Permission::class,

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * Eloquent model should be used to retrieve your roles. Of course, it
         * is often just the "Role" model but you may use whatever you like.
         *
         * The model you want to use as a Role model needs to implement the
         * `Spatie\Permission\Contracts\Role` contract.
         */

        'role' => Spatie\Permission\Models\Role::class,

    ],

    'table_names' => [

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * table should be used to retrieve your roles. We have chosen a basic
         * default value but you may easily change it to any table you like.
         */

        'roles' => 'roles',

        /*
         * When using the "HasPermissions" trait from this package, we need to know which
         * table should be used to retrieve your permissions. We have chosen a basic
         * default value but you may easily change it to any table you like.
         */

        'permissions' => 'permissions',

        /*
         * When using the "HasPermissions" trait from this package, we need to know which
         * table should be used to retrieve your models permissions. We have chosen a
         * basic default value but you may easily change it to any table you like.
         */

        'model_has_permissions' => 'model_has_permissions',

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * table should be used to retrieve your models roles. We have chosen a
         * basic default value but you may easily change it to any table you like.
         */

        'model_has_roles' => 'model_has_roles',

        /*
         * When using the "HasRoles" trait from this package, we need to know which
         * table should be used to retrieve your roles permissions. We have chosen a
         * basic default value but you may easily change it to any table you like.
         */

        'role_has_permissions' => 'role_has_permissions',
    ],

    'column_names' => [
        /*
         * Change this if you want to name the related pivots other than defaults
         */
        'role_pivot_key' => null, // default 'role_id',
        'permission_pivot_key' => null, // default 'permission_id',

        /*
         * Change this if you want to name the related model primary key other than
         * `model_id`.
         *
         * For example, this would be nice if your primary keys are all UUIDs. In
         * that case, name this `model_uuid`.
         */

        'model_morph_key' => 'model_id',

        /*
         * Change this if you want to use the teams feature and your related model's
         * foreign key is other than `team_id`.
         */

        'team_foreign_key' => 'team_id',
    ],

    /*
     * When set to true, the method for checking permissions will be registered on the gate.
     * Set this to false if you want to implement custom logic for checking permissions.
     */

    'register_permission_check_method' => true,

    /*
     * When set to true, Laravel\Octane\Events\OperationTerminated event listener will be registered
     * this will refresh permissions on every TickTerminated, TaskTerminated and RequestTerminated
     * NOTE: This should not be needed in most cases, but an Octane/Vapor combination benefited from it.
     */
    'register_octane_reset_listener' => false,

    /*
     * Events will fire when a role or permission is assigned/unassigned:
     * \Spatie\Permission\Events\RoleAttached
     * \Spatie\Permission\Events\RoleDetached
     * \Spatie\Permission\Events\PermissionAttached
     * \Spatie\Permission\Events\PermissionDetached
     *
     * To enable, set to true, and then create listeners to watch these events.
     */
    'events_enabled' => false,

    /*
     * Teams Feature.
     * When set to true the package implements teams using the 'team_foreign_key'.
     * If you want the migrations to register the 'team_foreign_key', you must
     * set this to true before doing the migration.
     * If you already did the migration then you must make a new migration to also
     * add 'team_foreign_key' to 'roles', 'model_has_roles', and 'model_has_permissions'
     * (view the latest version of this package's migration file)
     */

    'teams' => false,

    /*
     * The class to use to resolve the permissions team id
     */
    'team_resolver' => \Spatie\Permission\DefaultTeamResolver::class,

    /*
     * Passport Client Credentials Grant
     * When set to true the package will use Passports Client to check permissions
     */

    'use_passport_client_credentials' => false,

    /*
     * When set to true, the required permission names are added to exception messages.
     * This could be considered an information leak in some contexts, so the default
     * setting is false here for optimum safety.
     */

    'display_permission_in_exception' => false,

    /*
     * When set to true, the required role names are added to exception messages.
     * This could be considered an information leak in some contexts, so the default
     * setting is false here for optimum safety.
     */

    'display_role_in_exception' => false,

    /*
     * By default wildcard permission lookups are disabled.
     * See documentation to understand supported syntax.
     */

    'enable_wildcard_permission' => false,

    /*
     * The class to use for interpreting wildcard permissions.
     * If you need to modify delimiters, override the class and specify its name here.
     */
    // 'wildcard_permission' => Spatie\Permission\WildcardPermission::class,

    /* Cache-specific settings */

    'cache' => [

        /*
         * By default all permissions are cached for 24 hours to speed up performance.
         * When permissions or roles are updated the cache is flushed automatically.
         */

        'expiration_time' => \DateInterval::createFromDateString('24 hours'),

        /*
         * The cache key used to store all permissions.
         */

        'key' => 'spatie.permission.cache',

        /*
         * You may optionally indicate a specific cache driver to use for permission and
         * role caching using any of the `store` drivers listed in the cache.php config
         * file. Using 'default' here means to use the `default` set in cache.php.
         */

        'store' => 'default',
    ],
];

```

## File: config/pulse.php
```php
<?php

use Laravel\Pulse\Http\Middleware\Authorize;
use Laravel\Pulse\Pulse;
use Laravel\Pulse\Recorders;

return [

    /*
    |--------------------------------------------------------------------------
    | Pulse Domain
    |--------------------------------------------------------------------------
    |
    | This is the subdomain which the Pulse dashboard will be accessible from.
    | When set to null, the dashboard will reside under the same domain as
    | the application. Remember to configure your DNS entries correctly.
    |
    */

    'domain' => env('PULSE_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | Pulse Path
    |--------------------------------------------------------------------------
    |
    | This is the path which the Pulse dashboard will be accessible from. Feel
    | free to change this path to anything you'd like. Note that this won't
    | affect the path of the internal API that is never exposed to users.
    |
    */

    'path' => env('PULSE_PATH', 'pulse'),

    /*
    |--------------------------------------------------------------------------
    | Pulse Master Switch
    |--------------------------------------------------------------------------
    |
    | This configuration option may be used to completely disable all Pulse
    | data recorders regardless of their individual configurations. This
    | provides a single option to quickly disable all Pulse recording.
    |
    */

    'enabled' => env('PULSE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Pulse Storage Driver
    |--------------------------------------------------------------------------
    |
    | This configuration option determines which storage driver will be used
    | while storing entries from Pulse's recorders. In addition, you also
    | may provide any options to configure the selected storage driver.
    |
    */

    'storage' => [
        'driver' => env('PULSE_STORAGE_DRIVER', 'database'),

        'trim' => [
            'keep' => env('PULSE_STORAGE_KEEP', '7 days'),
        ],

        'database' => [
            'connection' => env('PULSE_DB_CONNECTION'),
            'chunk' => 1000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pulse Ingest Driver
    |--------------------------------------------------------------------------
    |
    | This configuration options determines the ingest driver that will be used
    | to capture entries from Pulse's recorders. Ingest drivers are great to
    | free up your request workers quickly by offloading the data storage.
    |
    */

    'ingest' => [
        'driver' => env('PULSE_INGEST_DRIVER', 'storage'),

        'buffer' => env('PULSE_INGEST_BUFFER', 5_000),

        'trim' => [
            'lottery' => [1, 1_000],
            'keep' => env('PULSE_INGEST_KEEP', '7 days'),
        ],

        'redis' => [
            'connection' => env('PULSE_REDIS_CONNECTION'),
            'chunk' => 1000,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pulse Cache Driver
    |--------------------------------------------------------------------------
    |
    | This configuration option determines the cache driver that will be used
    | for various tasks, including caching dashboard results, establishing
    | locks for events that should only occur on one server and signals.
    |
    */

    'cache' => env('PULSE_CACHE_DRIVER'),

    /*
    |--------------------------------------------------------------------------
    | Pulse Route Middleware
    |--------------------------------------------------------------------------
    |
    | These middleware will be assigned to every Pulse route, giving you the
    | chance to add your own middleware to this list or change any of the
    | existing middleware. Of course, reasonable defaults are provided.
    |
    */

    'middleware' => [
        'web',
        Authorize::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Pulse Recorders
    |--------------------------------------------------------------------------
    |
    | The following array lists the "recorders" that will be registered with
    | Pulse, along with their configuration. Recorders gather application
    | event data from requests and tasks to pass to your ingest driver.
    |
    */

    'recorders' => [
        Recorders\CacheInteractions::class => [
            'enabled' => env('PULSE_CACHE_INTERACTIONS_ENABLED', true),
            'sample_rate' => env('PULSE_CACHE_INTERACTIONS_SAMPLE_RATE', 1),
            'ignore' => [
                ...Pulse::defaultVendorCacheKeys(),
            ],
            'groups' => [
                '/^job-exceptions:.*/' => 'job-exceptions:*',
                // '/:\d+/' => ':*',
            ],
        ],

        Recorders\Exceptions::class => [
            'enabled' => env('PULSE_EXCEPTIONS_ENABLED', true),
            'sample_rate' => env('PULSE_EXCEPTIONS_SAMPLE_RATE', 1),
            'location' => env('PULSE_EXCEPTIONS_LOCATION', true),
            'ignore' => [
                // '/^Package\\\\Exceptions\\\\/',
            ],
        ],

        Recorders\Queues::class => [
            'enabled' => env('PULSE_QUEUES_ENABLED', true),
            'sample_rate' => env('PULSE_QUEUES_SAMPLE_RATE', 1),
            'ignore' => [
                // '/^Package\\\\Jobs\\\\/',
            ],
        ],

        Recorders\Servers::class => [
            'server_name' => env('PULSE_SERVER_NAME', gethostname()),
            'directories' => explode(':', env('PULSE_SERVER_DIRECTORIES', '/')),
        ],

        Recorders\SlowJobs::class => [
            'enabled' => env('PULSE_SLOW_JOBS_ENABLED', true),
            'sample_rate' => env('PULSE_SLOW_JOBS_SAMPLE_RATE', 1),
            'threshold' => env('PULSE_SLOW_JOBS_THRESHOLD', 1000),
            'ignore' => [
                // '/^Package\\\\Jobs\\\\/',
            ],
        ],

        Recorders\SlowOutgoingRequests::class => [
            'enabled' => env('PULSE_SLOW_OUTGOING_REQUESTS_ENABLED', true),
            'sample_rate' => env('PULSE_SLOW_OUTGOING_REQUESTS_SAMPLE_RATE', 1),
            'threshold' => env('PULSE_SLOW_OUTGOING_REQUESTS_THRESHOLD', 1000),
            'ignore' => [
                // '#^http://127\.0\.0\.1:13714#', // Inertia SSR...
            ],
            'groups' => [
                // '#^https://api\.github\.com/repos/.*$#' => 'api.github.com/repos/*',
                // '#^https?://([^/]*).*$#' => '\1',
                // '#/\d+#' => '/*',
            ],
        ],

        Recorders\SlowQueries::class => [
            'enabled' => env('PULSE_SLOW_QUERIES_ENABLED', true),
            'sample_rate' => env('PULSE_SLOW_QUERIES_SAMPLE_RATE', 1),
            'threshold' => env('PULSE_SLOW_QUERIES_THRESHOLD', 1000),
            'location' => env('PULSE_SLOW_QUERIES_LOCATION', true),
            'max_query_length' => env('PULSE_SLOW_QUERIES_MAX_QUERY_LENGTH'),
            'ignore' => [
                '/(["`])pulse_[\w]+?\1/', // Pulse tables...
                '/(["`])telescope_[\w]+?\1/', // Telescope tables...
            ],
        ],

        Recorders\SlowRequests::class => [
            'enabled' => env('PULSE_SLOW_REQUESTS_ENABLED', true),
            'sample_rate' => env('PULSE_SLOW_REQUESTS_SAMPLE_RATE', 1),
            'threshold' => env('PULSE_SLOW_REQUESTS_THRESHOLD', 1000),
            'ignore' => [
                '#^/'.env('PULSE_PATH', 'pulse').'$#', // Pulse dashboard...
                '#^/telescope#', // Telescope dashboard...
            ],
        ],

        Recorders\UserJobs::class => [
            'enabled' => env('PULSE_USER_JOBS_ENABLED', true),
            'sample_rate' => env('PULSE_USER_JOBS_SAMPLE_RATE', 1),
            'ignore' => [
                // '/^Package\\\\Jobs\\\\/',
            ],
        ],

        Recorders\UserRequests::class => [
            'enabled' => env('PULSE_USER_REQUESTS_ENABLED', true),
            'sample_rate' => env('PULSE_USER_REQUESTS_SAMPLE_RATE', 1),
            'ignore' => [
                '#^/'.env('PULSE_PATH', 'pulse').'$#', // Pulse dashboard...
                '#^/telescope#', // Telescope dashboard...
            ],
        ],
    ],
];

```

## File: config/queue.php
```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Queue Connection Name
    |--------------------------------------------------------------------------
    |
    | Laravel's queue supports a variety of backends via a single, unified
    | API, giving you convenient access to each backend using identical
    | syntax for each. The default queue connection is defined below.
    |
    */

    'default' => env('QUEUE_CONNECTION', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Queue Connections
    |--------------------------------------------------------------------------
    |
    | Here you may configure the connection options for every queue backend
    | used by your application. An example configuration is provided for
    | each backend supported by Laravel. You're also free to add more.
    |
    | Drivers: "sync", "database", "beanstalkd", "sqs", "redis",
    |          "deferred", "background", "failover", "null"
    |
    */

    'connections' => [

        'sync' => [
            'driver' => 'sync',
        ],

        'database' => [
            'driver' => 'database',
            'connection' => env('DB_QUEUE_CONNECTION'),
            'table' => env('DB_QUEUE_TABLE', 'jobs'),
            'queue' => env('DB_QUEUE', 'default'),
            'retry_after' => (int) env('DB_QUEUE_RETRY_AFTER', 90),
            'after_commit' => false,
        ],

        'beanstalkd' => [
            'driver' => 'beanstalkd',
            'host' => env('BEANSTALKD_QUEUE_HOST', 'localhost'),
            'queue' => env('BEANSTALKD_QUEUE', 'default'),
            'retry_after' => (int) env('BEANSTALKD_QUEUE_RETRY_AFTER', 90),
            'block_for' => 0,
            'after_commit' => false,
        ],

        'sqs' => [
            'driver' => 'sqs',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'prefix' => env('SQS_PREFIX', 'https://sqs.us-east-1.amazonaws.com/your-account-id'),
            'queue' => env('SQS_QUEUE', 'default'),
            'suffix' => env('SQS_SUFFIX'),
            'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
            'after_commit' => false,
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => env('REDIS_QUEUE_CONNECTION', 'default'),
            'queue' => env('REDIS_QUEUE', 'default'),
            'retry_after' => (int) env('REDIS_QUEUE_RETRY_AFTER', 90),
            'block_for' => null,
            'after_commit' => false,
        ],

        'deferred' => [
            'driver' => 'deferred',
        ],

        'background' => [
            'driver' => 'background',
        ],

        'failover' => [
            'driver' => 'failover',
            'connections' => [
                'database',
                'deferred',
            ],
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Job Batching
    |--------------------------------------------------------------------------
    |
    | The following options configure the database and table that store job
    | batching information. These options can be updated to any database
    | connection and table which has been defined by your application.
    |
    */

    'batching' => [
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'job_batches',
    ],

    /*
    |--------------------------------------------------------------------------
    | Failed Queue Jobs
    |--------------------------------------------------------------------------
    |
    | These options configure the behavior of failed queue job logging so you
    | can control how and where failed jobs are stored. Laravel ships with
    | support for storing failed jobs in a simple file or in a database.
    |
    | Supported drivers: "database-uuids", "dynamodb", "file", "null"
    |
    */

    'failed' => [
        'driver' => env('QUEUE_FAILED_DRIVER', 'database-uuids'),
        'database' => env('DB_CONNECTION', 'sqlite'),
        'table' => 'failed_jobs',
    ],

];

```

## File: config/reverb.php
```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Reverb Server
    |--------------------------------------------------------------------------
    |
    | This option controls the default server used by Reverb to handle
    | incoming messages as well as broadcasting message to all your
    | connected clients. At this time only "reverb" is supported.
    |
    */

    'default' => env('REVERB_SERVER', 'reverb'),

    /*
    |--------------------------------------------------------------------------
    | Reverb Servers
    |--------------------------------------------------------------------------
    |
    | Here you may define details for each of the supported Reverb servers.
    | Each server has its own configuration options that are defined in
    | the array below. You should ensure all the options are present.
    |
    */

    'servers' => [

        'reverb' => [
            'host' => env('REVERB_SERVER_HOST', '0.0.0.0'),
            'port' => env('REVERB_SERVER_PORT', 8080),
            'path' => env('REVERB_SERVER_PATH', ''),
            'hostname' => env('REVERB_HOST'),
            'options' => [
                'tls' => [],
            ],
            'max_request_size' => env('REVERB_MAX_REQUEST_SIZE', 10_000),
            'scaling' => [
                'enabled' => env('REVERB_SCALING_ENABLED', false),
                'channel' => env('REVERB_SCALING_CHANNEL', 'reverb'),
                'server' => [
                    'url' => env('REDIS_URL'),
                    'host' => env('REDIS_HOST', '127.0.0.1'),
                    'port' => env('REDIS_PORT', '6379'),
                    'username' => env('REDIS_USERNAME'),
                    'password' => env('REDIS_PASSWORD'),
                    'database' => env('REDIS_DB', '0'),
                    'timeout' => env('REDIS_TIMEOUT', 60),
                ],
            ],
            'pulse_ingest_interval' => env('REVERB_PULSE_INGEST_INTERVAL', 15),
            'telescope_ingest_interval' => env('REVERB_TELESCOPE_INGEST_INTERVAL', 15),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Reverb Applications
    |--------------------------------------------------------------------------
    |
    | Here you may define how Reverb applications are managed. If you choose
    | to use the "config" provider, you may define an array of apps which
    | your server will support, including their connection credentials.
    |
    */

    'apps' => [

        'provider' => 'config',

        'apps' => [
            [
                'key' => env('REVERB_APP_KEY'),
                'secret' => env('REVERB_APP_SECRET'),
                'app_id' => env('REVERB_APP_ID'),
                'options' => [
                    'host' => env('REVERB_HOST'),
                    'port' => env('REVERB_PORT', 443),
                    'scheme' => env('REVERB_SCHEME', 'https'),
                    'useTLS' => env('REVERB_SCHEME', 'https') === 'https',
                ],
                'allowed_origins' => ['*'],
                'ping_interval' => env('REVERB_APP_PING_INTERVAL', 60),
                'activity_timeout' => env('REVERB_APP_ACTIVITY_TIMEOUT', 30),
                'max_connections' => env('REVERB_APP_MAX_CONNECTIONS'),
                'max_message_size' => env('REVERB_APP_MAX_MESSAGE_SIZE', 10_000),
            ],
        ],

    ],

];

```

## File: config/sanctum.php
```php
<?php

use Laravel\Sanctum\Sanctum;

return [

    /*
    |--------------------------------------------------------------------------
    | Stateful Domains
    |--------------------------------------------------------------------------
    |
    | Requests from the following domains / hosts will receive stateful API
    | authentication cookies. Typically, these should include your local
    | and production domains which access your API via a frontend SPA.
    |
    */

    'stateful' => explode(',', env('SANCTUM_STATEFUL_DOMAINS', sprintf(
        '%s%s',
        'localhost,localhost:3000,127.0.0.1,127.0.0.1:8000,::1',
        Sanctum::currentApplicationUrlWithPort(),
        // Sanctum::currentRequestHost(),
    ))),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Guards
    |--------------------------------------------------------------------------
    |
    | This array contains the authentication guards that will be checked when
    | Sanctum is trying to authenticate a request. If none of these guards
    | are able to authenticate the request, Sanctum will use the bearer
    | token that's present on an incoming request for authentication.
    |
    */

    'guard' => ['web'],

    /*
    |--------------------------------------------------------------------------
    | Expiration Minutes
    |--------------------------------------------------------------------------
    |
    | This value controls the number of minutes until an issued token will be
    | considered expired. This will override any values set in the token's
    | "expires_at" attribute, but first-party sessions are not affected.
    |
    */

    'expiration' => null,

    /*
    |--------------------------------------------------------------------------
    | Token Prefix
    |--------------------------------------------------------------------------
    |
    | Sanctum can prefix new tokens in order to take advantage of numerous
    | security scanning initiatives maintained by open source platforms
    | that notify developers if they commit tokens into repositories.
    |
    | See: https://docs.github.com/en/code-security/secret-scanning/about-secret-scanning
    |
    */

    'token_prefix' => env('SANCTUM_TOKEN_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Sanctum Middleware
    |--------------------------------------------------------------------------
    |
    | When authenticating your first-party SPA with Sanctum you may need to
    | customize some of the middleware Sanctum uses while processing the
    | request. You may change the middleware listed below as required.
    |
    */

    'middleware' => [
        'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
        'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
        'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
    ],

];

```

## File: config/scramble.php
```php
<?php

use Dedoc\Scramble\Http\Middleware\RestrictedDocsAccess;

return [
    /*
     * Your API path. By default, all routes starting with this path will be added to the docs.
     */
    'api_path' => 'api',

    /*
     * Your API domain. By default, app domain is used.
     */
    'api_domain' => null,

    /*
     * The path where your OpenAPI specification will be exported.
     */
    'export_path' => 'api.json',

    'info' => [
        /*
         * API version.
         */
        'version' => '1.0.0',

        /*
         * Description rendered on the docs page. Supports Markdown.
         */
        'description' => 'Digibase BaaS Platform — Auto-generated API Documentation',
    ],

    /*
     * Customize Stoplight Elements UI
     */
    'ui' => [
        /*
         * Define the title of the documentation's website.
         */
        'title' => 'Digibase API Docs',

        /*
         * Define the theme of the documentation. Available: "light", "dark".
         */
        'theme' => 'dark',

        /*
         * Hide the "Try It" feature. Enabled by default.
         */
        'hide_try_it' => false,

        /*
         * URL to an image that will be used as a logo in the top left corner.
         */
        'logo' => '',
    ],

    /*
     * The list of servers of the API. By default, when the list is empty, the current host will be used.
     */
    'servers' => [],

    'middleware' => [
        'web',
        // RestrictedDocsAccess::class,  // Commented out = public access
    ],
];

```

## File: config/services.php
```php
<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];

```

## File: config/session.php
```php
<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Session Driver
    |--------------------------------------------------------------------------
    |
    | This option determines the default session driver that is utilized for
    | incoming requests. Laravel supports a variety of storage options to
    | persist session data. Database storage is a great default choice.
    |
    | Supported: "file", "cookie", "database", "memcached",
    |            "redis", "dynamodb", "array"
    |
    */

    'driver' => env('SESSION_DRIVER', 'database'),

    /*
    |--------------------------------------------------------------------------
    | Session Lifetime
    |--------------------------------------------------------------------------
    |
    | Here you may specify the number of minutes that you wish the session
    | to be allowed to remain idle before it expires. If you want them
    | to expire immediately when the browser is closed then you may
    | indicate that via the expire_on_close configuration option.
    |
    */

    'lifetime' => (int) env('SESSION_LIFETIME', 120),

    'expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE', false),

    /*
    |--------------------------------------------------------------------------
    | Session Encryption
    |--------------------------------------------------------------------------
    |
    | This option allows you to easily specify that all of your session data
    | should be encrypted before it's stored. All encryption is performed
    | automatically by Laravel and you may use the session like normal.
    |
    */

    'encrypt' => env('SESSION_ENCRYPT', false),

    /*
    |--------------------------------------------------------------------------
    | Session File Location
    |--------------------------------------------------------------------------
    |
    | When utilizing the "file" session driver, the session files are placed
    | on disk. The default storage location is defined here; however, you
    | are free to provide another location where they should be stored.
    |
    */

    'files' => storage_path('framework/sessions'),

    /*
    |--------------------------------------------------------------------------
    | Session Database Connection
    |--------------------------------------------------------------------------
    |
    | When using the "database" or "redis" session drivers, you may specify a
    | connection that should be used to manage these sessions. This should
    | correspond to a connection in your database configuration options.
    |
    */

    'connection' => env('SESSION_CONNECTION'),

    /*
    |--------------------------------------------------------------------------
    | Session Database Table
    |--------------------------------------------------------------------------
    |
    | When using the "database" session driver, you may specify the table to
    | be used to store sessions. Of course, a sensible default is defined
    | for you; however, you're welcome to change this to another table.
    |
    */

    'table' => env('SESSION_TABLE', 'sessions'),

    /*
    |--------------------------------------------------------------------------
    | Session Cache Store
    |--------------------------------------------------------------------------
    |
    | When using one of the framework's cache driven session backends, you may
    | define the cache store which should be used to store the session data
    | between requests. This must match one of your defined cache stores.
    |
    | Affects: "dynamodb", "memcached", "redis"
    |
    */

    'store' => env('SESSION_STORE'),

    /*
    |--------------------------------------------------------------------------
    | Session Sweeping Lottery
    |--------------------------------------------------------------------------
    |
    | Some session drivers must manually sweep their storage location to get
    | rid of old sessions from storage. Here are the chances that it will
    | happen on a given request. By default, the odds are 2 out of 100.
    |
    */

    'lottery' => [2, 100],

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Name
    |--------------------------------------------------------------------------
    |
    | Here you may change the name of the session cookie that is created by
    | the framework. Typically, you should not need to change this value
    | since doing so does not grant a meaningful security improvement.
    |
    */

    'cookie' => env(
        'SESSION_COOKIE',
        Str::slug((string) env('APP_NAME', 'laravel')).'-session'
    ),

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Path
    |--------------------------------------------------------------------------
    |
    | The session cookie path determines the path for which the cookie will
    | be regarded as available. Typically, this will be the root path of
    | your application, but you're free to change this when necessary.
    |
    */

    'path' => env('SESSION_PATH', '/'),

    /*
    |--------------------------------------------------------------------------
    | Session Cookie Domain
    |--------------------------------------------------------------------------
    |
    | This value determines the domain and subdomains the session cookie is
    | available to. By default, the cookie will be available to the root
    | domain without subdomains. Typically, this shouldn't be changed.
    |
    */

    'domain' => env('SESSION_DOMAIN'),

    /*
    |--------------------------------------------------------------------------
    | HTTPS Only Cookies
    |--------------------------------------------------------------------------
    |
    | By setting this option to true, session cookies will only be sent back
    | to the server if the browser has a HTTPS connection. This will keep
    | the cookie from being sent to you when it can't be done securely.
    |
    */

    'secure' => env('SESSION_SECURE_COOKIE'),

    /*
    |--------------------------------------------------------------------------
    | HTTP Access Only
    |--------------------------------------------------------------------------
    |
    | Setting this value to true will prevent JavaScript from accessing the
    | value of the cookie and the cookie will only be accessible through
    | the HTTP protocol. It's unlikely you should disable this option.
    |
    */

    'http_only' => env('SESSION_HTTP_ONLY', true),

    /*
    |--------------------------------------------------------------------------
    | Same-Site Cookies
    |--------------------------------------------------------------------------
    |
    | This option determines how your cookies behave when cross-site requests
    | take place, and can be used to mitigate CSRF attacks. By default, we
    | will set this value to "lax" to permit secure cross-site requests.
    |
    | See: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Set-Cookie#samesitesamesite-value
    |
    | Supported: "lax", "strict", "none", null
    |
    */

    'same_site' => env('SESSION_SAME_SITE', 'lax'),

    /*
    |--------------------------------------------------------------------------
    | Partitioned Cookies
    |--------------------------------------------------------------------------
    |
    | Setting this value to true will tie the cookie to the top-level site for
    | a cross-site context. Partitioned cookies are accepted by the browser
    | when flagged "secure" and the Same-Site attribute is set to "none".
    |
    */

    'partitioned' => env('SESSION_PARTITIONED_COOKIE', false),

];

```

## File: config/settings.php
```php
<?php

return [

    /*
     * Each settings class used in your application must be registered, you can
     * put them (manually) here.
     */
    'settings' => [

    ],

    /*
     * The path where the settings classes will be created.
     */
    'setting_class_path' => app_path('Settings'),

    /*
     * In these directories settings migrations will be stored and ran when migrating. A settings
     * migration created via the make:settings-migration command will be stored in the first path or
     * a custom defined path when running the command.
     */
    'migrations_paths' => [
        database_path('settings'),
    ],

    /*
     * When no repository was set for a settings class the following repository
     * will be used for loading and saving settings.
     */
    'default_repository' => 'database',

    /*
     * Settings will be stored and loaded from these repositories.
     */
    'repositories' => [
        'database' => [
            'type' => Spatie\LaravelSettings\SettingsRepositories\DatabaseSettingsRepository::class,
            'model' => null,
            'table' => 'spatie_settings',
            'connection' => null,
        ],
        'redis' => [
            'type' => Spatie\LaravelSettings\SettingsRepositories\RedisSettingsRepository::class,
            'connection' => null,
            'prefix' => null,
        ],
    ],

    /*
     * The encoder and decoder will determine how settings are stored and
     * retrieved in the database. By default, `json_encode` and `json_decode`
     * are used.
     */
    'encoder' => null,
    'decoder' => null,

    /*
     * The contents of settings classes can be cached through your application,
     * settings will be stored within a provided Laravel store and can have an
     * additional prefix.
     */
    'cache' => [
        'enabled' => env('SETTINGS_CACHE_ENABLED', false),
        'store' => null,
        'prefix' => null,
        'ttl' => null,

        /*
         * When enabled, uses Laravel's memoized cache driver (requires Laravel 12.9+)
         * to keep resolved values in memory during a single request.
         */
        'memo' => env('SETTINGS_CACHE_MEMO', false),
    ],

    /*
     * These global casts will be automatically used whenever a property within
     * your settings class isn't a default PHP type.
     */
    'global_casts' => [
        DateTimeInterface::class => Spatie\LaravelSettings\SettingsCasts\DateTimeInterfaceCast::class,
        DateTimeZone::class => Spatie\LaravelSettings\SettingsCasts\DateTimeZoneCast::class,
//        Spatie\DataTransferObject\DataTransferObject::class => Spatie\LaravelSettings\SettingsCasts\DtoCast::class,
        Spatie\LaravelData\Data::class => Spatie\LaravelSettings\SettingsCasts\DataCast::class,
    ],

    /*
     * The package will look for settings in these paths and automatically
     * register them.
     */
    'auto_discover_settings' => [
        app_path('Settings'),
    ],

    /*
     * Automatically discovered settings classes can be cached, so they don't
     * need to be searched each time the application boots up.
     */
    'discovered_settings_cache_path' => base_path('bootstrap/cache'),
];

```

## File: config/webhook-server.php
```php
<?php

return [

    /*
     *  The default queue that should be used to send webhook requests.
     */
    'queue' => 'default',

    /*
     *  The default queue connection that should be used to send webhook requests.
     */
    'connection' => null,

    /*
     * The default http verb to use.
     */
    'http_verb' => 'post',

    /*
     * Proxies to use for request.
     *
     * See https://docs.guzzlephp.org/en/stable/request-options.html#proxy
     */
    'proxy' => null,

    /*
     * This class is responsible for calculating the signature that will be added to
     * the headers of the webhook request. A webhook client can use the signature
     * to verify the request hasn't been tampered with.
     */
    'signer' => \Spatie\WebhookServer\Signer\DefaultSigner::class,

    /*
     * This is the name of the header where the signature will be added.
     */
    'signature_header_name' => 'Signature',

    /*
     * This is the name of the header where the timestamp will be added.
     */
    'timestamp_header_name' => 'Timestamp',

    /*
     * These are the headers that will be added to all webhook requests.
     */
    'headers' => [
        'Content-Type' => 'application/json',
    ],

    /*
     * If a call to a webhook takes longer this amount of seconds
     * the attempt will be considered failed.
     */
    'timeout_in_seconds' => 3,

    /*
     * The amount of times the webhook should be called before we give up.
     */
    'tries' => 3,

    /*
     * This class determines how many seconds there should be between attempts.
     */
    'backoff_strategy' => \Spatie\WebhookServer\BackoffStrategy\ExponentialBackoffStrategy::class,

    /*
     * This class is used to dispatch webhooks onto the queue.
     */
    'webhook_job' => \Spatie\WebhookServer\CallWebhookJob::class,

    /*
     * By default we will verify that the ssl certificate of the destination
     * of the webhook is valid.
     */
    'verify_ssl' => true,

    /*
     * When set to true, an exception will be thrown when the last attempt fails
     */
    'throw_exception_on_failure' => false,

    /*
     * When using Laravel Horizon you can specify tags that should be used on the
     * underlying job that performs the webhook request.
     */
    'tags' => [],
];

```

## File: database/migrations/0001_01_01_000000_create_users_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};

```

## File: database/migrations/0001_01_01_000001_create_cache_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration')->index();
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cache');
        Schema::dropIfExists('cache_locks');
    }
};

```

## File: database/migrations/0001_01_01_000002_create_jobs_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->string('name');
            $table->integer('total_jobs');
            $table->integer('pending_jobs');
            $table->integer('failed_jobs');
            $table->longText('failed_job_ids');
            $table->mediumText('options')->nullable();
            $table->integer('cancelled_at')->nullable();
            $table->integer('created_at');
            $table->integer('finished_at')->nullable();
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('failed_jobs');
    }
};

```

## File: database/migrations/2022_12_14_083707_create_settings_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();

            $table->string('group');
            $table->string('name');
            $table->boolean('locked')->default(false);
            $table->json('payload');

            $table->timestamps();

            $table->unique(['group', 'name']);
        });
    }
};

```

## File: database/migrations/2026_01_26_112115_create_permission_tables.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $teams = config('permission.teams');
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');
        $pivotRole = $columnNames['role_pivot_key'] ?? 'role_id';
        $pivotPermission = $columnNames['permission_pivot_key'] ?? 'permission_id';

        throw_if(empty($tableNames), Exception::class, 'Error: config/permission.php not loaded. Run [php artisan config:clear] and try again.');
        throw_if($teams && empty($columnNames['team_foreign_key'] ?? null), Exception::class, 'Error: team_foreign_key on config/permission.php not loaded. Run [php artisan config:clear] and try again.');

        Schema::create($tableNames['permissions'], static function (Blueprint $table) {
            // $table->engine('InnoDB');
            $table->bigIncrements('id'); // permission id
            $table->string('name');       // For MyISAM use string('name', 225); // (or 166 for InnoDB with Redundant/Compact row format)
            $table->string('guard_name'); // For MyISAM use string('guard_name', 25);
            $table->timestamps();

            $table->unique(['name', 'guard_name']);
        });

        Schema::create($tableNames['roles'], static function (Blueprint $table) use ($teams, $columnNames) {
            // $table->engine('InnoDB');
            $table->bigIncrements('id'); // role id
            if ($teams || config('permission.testing')) { // permission.testing is a fix for sqlite testing
                $table->unsignedBigInteger($columnNames['team_foreign_key'])->nullable();
                $table->index($columnNames['team_foreign_key'], 'roles_team_foreign_key_index');
            }
            $table->string('name');       // For MyISAM use string('name', 225); // (or 166 for InnoDB with Redundant/Compact row format)
            $table->string('guard_name'); // For MyISAM use string('guard_name', 25);
            $table->timestamps();
            if ($teams || config('permission.testing')) {
                $table->unique([$columnNames['team_foreign_key'], 'name', 'guard_name']);
            } else {
                $table->unique(['name', 'guard_name']);
            }
        });

        Schema::create($tableNames['model_has_permissions'], static function (Blueprint $table) use ($tableNames, $columnNames, $pivotPermission, $teams) {
            $table->unsignedBigInteger($pivotPermission);

            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_permissions_model_id_model_type_index');

            $table->foreign($pivotPermission)
                ->references('id') // permission id
                ->on($tableNames['permissions'])
                ->onDelete('cascade');
            if ($teams) {
                $table->unsignedBigInteger($columnNames['team_foreign_key']);
                $table->index($columnNames['team_foreign_key'], 'model_has_permissions_team_foreign_key_index');

                $table->primary([$columnNames['team_foreign_key'], $pivotPermission, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_permissions_permission_model_type_primary');
            } else {
                $table->primary([$pivotPermission, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_permissions_permission_model_type_primary');
            }

        });

        Schema::create($tableNames['model_has_roles'], static function (Blueprint $table) use ($tableNames, $columnNames, $pivotRole, $teams) {
            $table->unsignedBigInteger($pivotRole);

            $table->string('model_type');
            $table->unsignedBigInteger($columnNames['model_morph_key']);
            $table->index([$columnNames['model_morph_key'], 'model_type'], 'model_has_roles_model_id_model_type_index');

            $table->foreign($pivotRole)
                ->references('id') // role id
                ->on($tableNames['roles'])
                ->onDelete('cascade');
            if ($teams) {
                $table->unsignedBigInteger($columnNames['team_foreign_key']);
                $table->index($columnNames['team_foreign_key'], 'model_has_roles_team_foreign_key_index');

                $table->primary([$columnNames['team_foreign_key'], $pivotRole, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_roles_role_model_type_primary');
            } else {
                $table->primary([$pivotRole, $columnNames['model_morph_key'], 'model_type'],
                    'model_has_roles_role_model_type_primary');
            }
        });

        Schema::create($tableNames['role_has_permissions'], static function (Blueprint $table) use ($tableNames, $pivotRole, $pivotPermission) {
            $table->unsignedBigInteger($pivotPermission);
            $table->unsignedBigInteger($pivotRole);

            $table->foreign($pivotPermission)
                ->references('id') // permission id
                ->on($tableNames['permissions'])
                ->onDelete('cascade');

            $table->foreign($pivotRole)
                ->references('id') // role id
                ->on($tableNames['roles'])
                ->onDelete('cascade');

            $table->primary([$pivotPermission, $pivotRole], 'role_has_permissions_permission_id_role_id_primary');
        });

        app('cache')
            ->store(config('permission.cache.store') != 'default' ? config('permission.cache.store') : null)
            ->forget(config('permission.cache.key'));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableNames = config('permission.table_names');

        throw_if(empty($tableNames), Exception::class, 'Error: config/permission.php not found and defaults could not be merged. Please publish the package configuration before proceeding, or drop the tables manually.');

        Schema::drop($tableNames['role_has_permissions']);
        Schema::drop($tableNames['model_has_roles']);
        Schema::drop($tableNames['model_has_permissions']);
        Schema::drop($tableNames['roles']);
        Schema::drop($tableNames['permissions']);
    }
};

```

## File: database/migrations/2026_01_26_112116_create_personal_access_tokens_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();
            $table->morphs('tokenable');
            $table->text('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};

```

## File: database/migrations/2026_01_26_120000_create_dynamic_models_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dynamic_models', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('table_name')->unique();
            $table->string('display_name');
            $table->text('description')->nullable();
            $table->string('icon')->default('table');
            $table->boolean('is_active')->default(true);
            $table->boolean('has_timestamps')->default(true);
            $table->boolean('has_soft_deletes')->default(false);
            $table->boolean('generate_api')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dynamic_models');
    }
};

```

## File: database/migrations/2026_01_26_120001_create_dynamic_fields_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dynamic_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dynamic_model_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('display_name');
            $table->string('type'); // string, text, integer, float, boolean, date, datetime, json, etc.
            $table->text('description')->nullable();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_unique')->default(false);
            $table->boolean('is_indexed')->default(false);
            $table->boolean('is_searchable')->default(true);
            $table->boolean('is_filterable')->default(true);
            $table->boolean('is_sortable')->default(true);
            $table->boolean('show_in_list')->default(true);
            $table->boolean('show_in_detail')->default(true);
            $table->string('default_value')->nullable();
            $table->json('validation_rules')->nullable();
            $table->json('options')->nullable(); // For enum/select fields
            $table->integer('order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dynamic_fields');
    }
};

```

## File: database/migrations/2026_01_26_120002_create_dynamic_relationships_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dynamic_relationships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dynamic_model_id')->constrained()->onDelete('cascade');
            $table->foreignId('related_model_id')->constrained('dynamic_models')->onDelete('cascade');
            $table->string('name');
            $table->string('type'); // hasOne, hasMany, belongsTo, belongsToMany
            $table->string('foreign_key')->nullable();
            $table->string('local_key')->nullable();
            $table->string('pivot_table')->nullable(); // For many-to-many
            $table->json('settings')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dynamic_relationships');
    }
};

```

## File: database/migrations/2026_01_27_062424_create_names_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('names', function (Blueprint $table) {
            $table->id();
            $table->text('name')->nullable();
            $table->text('last_name')->nullable();
            $table->timestamps();
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('names');
    }
};
```

## File: database/migrations/2026_01_27_094920_add_columns_to_names_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SKIPPING: Causing test failures due to duplicate column
        /*
        Schema::table('names', function (Blueprint $table) {
            $table->string('test');
        });
        */
    }

    public function down(): void
    {
    }
};
```

## File: database/migrations/2026_01_27_094922_add_columns_to_names_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SKIPPING: Causing test failures due to duplicate column
        /*
        if (!Schema::hasColumn('names', 'test')) {
            Schema::table('names', function (Blueprint $table) {
                $table->string('test');
            });
        }
        */
    }

    public function down(): void
    {
        /*
        Schema::table('names', function (Blueprint $table) {
            // Rollback not fully supported for dynamic additions yet
        });
        */
    }
};
```

## File: database/migrations/2026_01_27_094954_add_columns_to_names_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // SKIPPING: Third duplicate causing test failures
        /*
        Schema::table('names', function (Blueprint $table) {
            $table->string('test');
        });
        */
    }

    public function down(): void
    {
    }
};
```

## File: database/migrations/2026_01_27_134108_create_posts_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->text('title')->nullable();
            $table->text('description')->nullable();
            $table->text('content')->nullable();
            
            
            $table->timestamps();
            
            
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
```

## File: database/migrations/2026_02_08_070836_add_plain_text_token_to_personal_access_tokens_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->string('plain_text_token')->nullable()->after('token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('personal_access_tokens', function (Blueprint $table) {
            $table->dropColumn('plain_text_token');
        });
    }
};

```

## File: database/migrations/2026_02_08_133323_add_rules_to_dynamic_models_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dynamic_models', function (Blueprint $table) {
            // Row Level Security (RLS) Rules
            // null = Admin Only (default), 'true' = Public, or expression like 'auth.id == user_id'
            $table->string('list_rule')->nullable()->after('settings')
                ->comment('Who can view the collection. Examples: true, auth.id != null, auth.id == user_id');
            $table->string('view_rule')->nullable()->after('list_rule')
                ->comment('Who can view a single record');
            $table->string('create_rule')->nullable()->after('view_rule')
                ->comment('Who can create new records');
            $table->string('update_rule')->nullable()->after('create_rule')
                ->comment('Who can update records');
            $table->string('delete_rule')->nullable()->after('update_rule')
                ->comment('Who can delete records');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dynamic_models', function (Blueprint $table) {
            $table->dropColumn([
                'list_rule',
                'view_rule',
                'create_rule',
                'update_rule',
                'delete_rule',
            ]);
        });
    }
};

```

## File: database/migrations/2026_02_08_134645_create_webhooks_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('webhooks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dynamic_model_id')->constrained('dynamic_models')->onDelete('cascade');
            $table->string('name')->nullable(); // Optional friendly name
            $table->string('url'); // Target webhook URL
            $table->string('secret')->nullable(); // For HMAC signature verification
            $table->json('events')->default('["created","updated","deleted"]'); // Events to trigger
            $table->json('headers')->nullable(); // Custom headers to send
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_triggered_at')->nullable();
            $table->integer('failure_count')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhooks');
    }
};

```

## File: database/migrations/2026_02_08_163949_add_is_hidden_to_dynamic_fields_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('dynamic_fields', function (Blueprint $table) {
            $table->boolean('is_hidden')->default(false)->after('is_searchable');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('dynamic_fields', function (Blueprint $table) {
            $table->dropColumn('is_hidden');
        });
    }
};

```

## File: database/migrations/2026_02_08_174254_create_db_config_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        $tableName = config('db-config.table_name', 'db_config');

        Schema::create($tableName, function (Blueprint $table) {
            $table->id();

            $table->string('group');

            $table->string('key');

            $table->json('settings')->nullable();

            $table->unique(['group', 'key']);

            $table->timestamps();
        });
    }
};

```

## File: database/migrations/2026_02_09_000001_create_api_keys_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');                          // Friendly name: "Production Key"
            $table->string('key', 64)->unique();             // pk_xxx or sk_xxx
            $table->enum('type', ['public', 'secret'])       // public = read-only, secret = full access
                  ->default('public');
            $table->json('scopes')->nullable();              // ['read', 'write', 'delete'] or ['*']
            $table->integer('rate_limit')->default(60);      // Requests per minute
            $table->boolean('is_active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            // Indexes for fast lookup
            $table->index('key');
            $table->index(['user_id', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('api_keys');
    }
};

```

## File: database/migrations/2026_02_09_100000_add_allowed_tables_to_api_keys_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->json('allowed_tables')->nullable()->after('scopes');
        });
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropColumn('allowed_tables');
        });
    }
};

```

## File: database/migrations/2026_02_10_000001_create_api_analytics_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('api_analytics', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('table_name', 100)->index();
            $table->string('method', 7); // GET, POST, PUT, DELETE, PATCH, OPTIONS, HEAD
            $table->unsignedSmallInteger('status_code');
            $table->unsignedInteger('duration_ms');
            $table->string('ip_address', 45); // IPv6 max length
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('api_analytics');
    }
};

```

## File: database/migrations/2026_02_10_000002_add_key_hash_to_api_keys_table.php
```php
<?php

use App\Models\ApiKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->string('key_hash', 64)->nullable()->after('key')->index();
        });

        // Backfill existing keys with their SHA-256 hash
        foreach (ApiKey::all() as $apiKey) {
            $apiKey->update(['key_hash' => hash('sha256', $apiKey->key)]);
        }
    }

    public function down(): void
    {
        Schema::table('api_keys', function (Blueprint $table) {
            $table->dropColumn('key_hash');
        });
    }
};

```

## File: database/migrations/2026_02_10_075753_create_system_settings_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('system_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique()->index();
            $table->text('value')->nullable();
            $table->string('group')->default('system')->index();
            $table->text('description')->nullable();
            $table->boolean('is_encrypted')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('system_settings');
    }
};

```

## File: database/migrations/2026_02_10_121609_create_media_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media', function (Blueprint $table) {
            $table->id();

            $table->morphs('model');
            $table->uuid()->nullable()->unique();
            $table->string('collection_name');
            $table->string('name');
            $table->string('file_name');
            $table->string('mime_type')->nullable();
            $table->string('disk');
            $table->string('conversions_disk')->nullable();
            $table->unsignedBigInteger('size');
            $table->json('manipulations');
            $table->json('custom_properties');
            $table->json('generated_conversions');
            $table->json('responsive_images');
            $table->unsignedInteger('order_column')->nullable()->index();

            $table->nullableTimestamps();
        });
    }
};

```

## File: database/migrations/2026_02_10_141542_clean_orphan_permissions.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        //
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

```

## File: database/migrations/2026_02_10_141754_clean_orphan_permissions.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Helper to allow running raw SQL
        \Illuminate\Support\Facades\DB::statement("DELETE FROM permissions WHERE name LIKE '%storage_file%'");
        \Illuminate\Support\Facades\DB::statement("DELETE FROM permissions WHERE name LIKE '%file_system_item%'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

```

## File: database/migrations/2026_02_10_142247_clean_orphan_permissions.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Helper to allow running raw SQL
        \Illuminate\Support\Facades\DB::statement("DELETE FROM permissions WHERE name LIKE '%storage_file%'");
        \Illuminate\Support\Facades\DB::statement("DELETE FROM permissions WHERE name LIKE '%file_system_item%'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

```

## File: database/migrations/2026_02_10_145924_clean_orphan_permissions_v2.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        \Illuminate\Support\Facades\DB::table('permissions')->where('name', 'like', '%storage_file%')->delete();
        \Illuminate\Support\Facades\DB::table('permissions')->where('name', 'like', '%file_system_item%')->delete();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};

```

## File: database/migrations/2026_02_10_161409_create_pulse_tables.php
```php
<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Laravel\Pulse\Support\PulseMigration;

return new class extends PulseMigration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! $this->shouldRun()) {
            return;
        }

        Schema::create('pulse_values', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('timestamp');
            $table->string('type');
            $table->mediumText('key');
            match ($this->driver()) {
                'mariadb', 'mysql' => $table->char('key_hash', 16)->charset('binary')->virtualAs('unhex(md5(`key`))'),
                'pgsql' => $table->uuid('key_hash')->storedAs('md5("key")::uuid'),
                'sqlite' => $table->string('key_hash'),
            };
            $table->mediumText('value');

            $table->index('timestamp'); // For trimming...
            $table->index('type'); // For fast lookups and purging...
            $table->unique(['type', 'key_hash']); // For data integrity and upserts...
        });

        Schema::create('pulse_entries', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('timestamp');
            $table->string('type');
            $table->mediumText('key');
            match ($this->driver()) {
                'mariadb', 'mysql' => $table->char('key_hash', 16)->charset('binary')->virtualAs('unhex(md5(`key`))'),
                'pgsql' => $table->uuid('key_hash')->storedAs('md5("key")::uuid'),
                'sqlite' => $table->string('key_hash'),
            };
            $table->bigInteger('value')->nullable();

            $table->index('timestamp'); // For trimming...
            $table->index('type'); // For purging...
            $table->index('key_hash'); // For mapping...
            $table->index(['timestamp', 'type', 'key_hash', 'value']); // For aggregate queries...
        });

        Schema::create('pulse_aggregates', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('bucket');
            $table->unsignedMediumInteger('period');
            $table->string('type');
            $table->mediumText('key');
            match ($this->driver()) {
                'mariadb', 'mysql' => $table->char('key_hash', 16)->charset('binary')->virtualAs('unhex(md5(`key`))'),
                'pgsql' => $table->uuid('key_hash')->storedAs('md5("key")::uuid'),
                'sqlite' => $table->string('key_hash'),
            };
            $table->string('aggregate');
            $table->decimal('value', 20, 2);
            $table->unsignedInteger('count')->nullable();

            $table->unique(['bucket', 'period', 'type', 'aggregate', 'key_hash']); // Force "on duplicate update"...
            $table->index(['period', 'bucket']); // For trimming...
            $table->index('type'); // For purging...
            $table->index(['period', 'type', 'aggregate', 'bucket']); // For aggregate queries...
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pulse_values');
        Schema::dropIfExists('pulse_entries');
        Schema::dropIfExists('pulse_aggregates');
    }
};

```

## File: database/migrations/2026_02_10_163455_create_notifications_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};

```

## File: database/migrations/2026_02_10_163500_create_breezy_sessions_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('breezy_sessions', function (Blueprint $table) {
            $table->id();
            $table->morphs('authenticatable');
            $table->string('panel_id')->nullable();
            $table->string('guard')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->timestamps();
        });

    }

    public function down()
    {
        Schema::dropIfExists('breezy_sessions');
    }
};

```

## File: database/migrations/2026_02_10_163501_alter_breezy_sessions_table.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('breezy_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'guard',
                'ip_address',
                'user_agent',
                'expires_at',
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('breezy_sessions', function (Blueprint $table) {
            $table->after('panel_id', function (BluePrint $table) {
                $table->string('guard')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->text('user_agent')->nullable();
                $table->timestamp('expires_at')->nullable();
            });
        });
    }
};

```

## File: database/migrations/2026_02_10_170102_create_health_tables.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Health\Models\HealthCheckResultHistoryItem;
use Spatie\Health\ResultStores\EloquentHealthResultStore;

return new class extends Migration
{
    public function up()
    {
        $connection = (new HealthCheckResultHistoryItem)->getConnectionName();
        $tableName = EloquentHealthResultStore::getHistoryItemInstance()->getTable();
    
        Schema::connection($connection)->create($tableName, function (Blueprint $table) {
            $table->id();

            $table->string('check_name');
            $table->string('check_label');
            $table->string('status');
            $table->text('notification_message')->nullable();
            $table->string('short_summary')->nullable();
            $table->json('meta');
            $table->timestamp('ended_at');
            $table->uuid('batch');

            $table->timestamps();
        });
        
        Schema::connection($connection)->table($tableName, function (Blueprint $table) {
            $table->index('created_at');
            $table->index('batch');
        });
    }
};

```

## File: database/migrations/2026_02_10_171641_create_activity_log_table.php
```php
<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateActivityLogTable extends Migration
{
    public function up()
    {
        Schema::connection(config('activitylog.database_connection'))->create(config('activitylog.table_name'), function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->nullableMorphs('causer', 'causer');
            $table->json('properties')->nullable();
            $table->timestamps();
            $table->index('log_name');
        });
    }

    public function down()
    {
        Schema::connection(config('activitylog.database_connection'))->dropIfExists(config('activitylog.table_name'));
    }
}

```

## File: database/migrations/2026_02_10_171642_add_event_column_to_activity_log_table.php
```php
<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddEventColumnToActivityLogTable extends Migration
{
    public function up()
    {
        Schema::connection(config('activitylog.database_connection'))->table(config('activitylog.table_name'), function (Blueprint $table) {
            $table->string('event')->nullable()->after('subject_type');
        });
    }

    public function down()
    {
        Schema::connection(config('activitylog.database_connection'))->table(config('activitylog.table_name'), function (Blueprint $table) {
            $table->dropColumn('event');
        });
    }
}

```

## File: database/migrations/2026_02_10_171643_add_batch_uuid_column_to_activity_log_table.php
```php
<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class AddBatchUuidColumnToActivityLogTable extends Migration
{
    public function up()
    {
        Schema::connection(config('activitylog.database_connection'))->table(config('activitylog.table_name'), function (Blueprint $table) {
            $table->uuid('batch_uuid')->nullable()->after('properties');
        });
    }

    public function down()
    {
        Schema::connection(config('activitylog.database_connection'))->table(config('activitylog.table_name'), function (Blueprint $table) {
            $table->dropColumn('batch_uuid');
        });
    }
}

```

## File: database/migrations/2026_02_10_171644_create_spatie_settings.php
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('spatie_settings', function (Blueprint $table): void {
            $table->id();

            $table->string('group');
            $table->string('name');
            $table->boolean('locked')->default(false);
            $table->json('payload');

            $table->timestamps();

            $table->unique(['group', 'name']);
        });
    }
};

```

## File: resources/views/filament/components/json-preview.blade.php
```php
<div class="space-y-4">
    <div class="flex items-center justify-between mb-4">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Complete schema definition for this table
        </p>
        <button 
            onclick="navigator.clipboard.writeText(document.getElementById('json-content').textContent); 
                     const btn = this; 
                     const original = btn.innerHTML; 
                     btn.innerHTML = '✓ Copied!'; 
                     setTimeout(() => btn.innerHTML = original, 2000);"
            class="px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 rounded-lg transition">
            📋 Copy JSON
        </button>
    </div>
    
    <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto max-h-[600px] overflow-y-auto">
        <pre id="json-content" class="text-sm text-gray-100"><code>{{ $json }}</code></pre>
    </div>
    
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div class="flex-1">
                <h4 class="font-semibold text-blue-900 dark:text-blue-100 mb-1">Schema Information</h4>
                <p class="text-sm text-blue-800 dark:text-blue-200">
                    This JSON represents the complete structure of your table including fields, validation rules, and security policies. 
                    You can use this for documentation, backup, or importing into other systems.
                </p>
            </div>
        </div>
    </div>
</div>

```

## File: resources/views/filament/pages/api-documentation.blade.php
```php
<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Model Selector -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            {{ $this->schema }}
        </div>

        @if($documentation)
            <!-- Overview -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h2 class="text-2xl font-bold mb-2">{{ $documentation['model']['display_name'] }}</h2>
                        @if($documentation['model']['description'])
                            <p class="text-gray-600 dark:text-gray-400 mb-4">{{ $documentation['model']['description'] }}</p>
                        @endif
                        <div class="flex items-center gap-4 text-sm">
                            <span class="px-3 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded-full">
                                Table: {{ $documentation['model']['table_name'] }}
                            </span>
                        </div>
                    </div>
                    <div>
                        <button wire:click="downloadOpenApiSpec" 
                                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg flex items-center gap-2">
                            <x-heroicon-o-arrow-down-tray class="w-5 h-5" />
                            Download OpenAPI Spec
                        </button>
                    </div>
                </div>
            </div>

            <!-- Authentication -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                    <x-heroicon-o-shield-check class="w-6 h-6 text-green-500" />
                    Authentication
                </h3>
                <p class="text-gray-600 dark:text-gray-400 mb-4">{{ $documentation['authentication']['description'] }}</p>
                
                <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 mb-4">
                    <code class="text-sm">{{ $documentation['authentication']['example'] }}</code>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($documentation['authentication']['key_types'] as $prefix => $desc)
                        <div class="border dark:border-gray-700 rounded-lg p-4">
                            <code class="text-sm font-mono text-blue-600 dark:text-blue-400">{{ $prefix }}</code>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $desc }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Endpoints -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                    <x-heroicon-o-globe-alt class="w-6 h-6 text-purple-500" />
                    API Endpoints
                </h3>

                <div class="space-y-6">
                    @foreach($documentation['endpoints'] as $endpoint)
                        <div class="border dark:border-gray-700 rounded-lg p-4">
                            <div class="flex items-center gap-3 mb-3">
                                <span class="px-3 py-1 rounded font-mono text-sm font-bold
                                    {{ $endpoint['method'] === 'GET' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : '' }}
                                    {{ $endpoint['method'] === 'POST' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : '' }}
                                    {{ $endpoint['method'] === 'PUT' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : '' }}
                                    {{ $endpoint['method'] === 'DELETE' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : '' }}
                                ">
                                    {{ $endpoint['method'] }}
                                </span>
                                <code class="text-sm font-mono">{{ $endpoint['path'] }}</code>
                            </div>
                            <p class="text-gray-600 dark:text-gray-400 mb-3">{{ $endpoint['description'] }}</p>

                            @if(!empty($endpoint['parameters']))
                                <div class="mb-3">
                                    <h4 class="font-semibold text-sm mb-2">Parameters:</h4>
                                    <div class="space-y-2">
                                        @foreach($endpoint['parameters'] as $param)
                                            <div class="text-sm">
                                                <code class="text-blue-600 dark:text-blue-400">{{ $param['name'] }}</code>
                                                <span class="text-gray-500">({{ $param['type'] }})</span>
                                                @if($param['required'])
                                                    <span class="text-red-500">*</span>
                                                @endif
                                                - {{ $param['description'] }}
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if(!empty($endpoint['body']))
                                <div>
                                    <h4 class="font-semibold text-sm mb-2">Request Body:</h4>
                                    <div class="bg-gray-50 dark:bg-gray-900 rounded p-3 space-y-2">
                                        @foreach($endpoint['body'] as $field)
                                            <div class="text-sm">
                                                <code class="text-blue-600 dark:text-blue-400">{{ $field['name'] }}</code>
                                                <span class="text-gray-500">({{ $field['type'] }})</span>
                                                @if($field['required'])
                                                    <span class="text-red-500">*</span>
                                                @endif
                                                - {{ $field['description'] }}
                                                @if(!empty($field['validation']))
                                                    <div class="ml-4 text-xs text-gray-500 mt-1">
                                                        Validation: {{ implode(', ', $field['validation']) }}
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Try It Out (Interactive Testing) -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                    <x-heroicon-o-play class="w-6 h-6 text-green-500" />
                    Try It Out
                </h3>
                
                <div x-data="{ activeTestOp: 'list' }" class="space-y-4">
                    <!-- Operation Selector -->
                    <div class="flex gap-2 flex-wrap">
                        <button @click="activeTestOp = 'list'" 
                                :class="activeTestOp === 'list' ? 'bg-blue-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                                class="px-3 py-1 rounded text-sm font-semibold">
                            GET List
                        </button>
                        <button @click="activeTestOp = 'create'" 
                                :class="activeTestOp === 'create' ? 'bg-green-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                                class="px-3 py-1 rounded text-sm font-semibold">
                            POST Create
                        </button>
                    </div>

                    <!-- API Key Input -->
                    <div>
                        <label class="block text-sm font-semibold mb-2">API Key (sk_ for write access)</label>
                        <input type="text" 
                               wire:model="testApiKey" 
                               placeholder="sk_your_secret_key_here"
                               class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100">
                    </div>

                    <!-- Request Body (for POST/PUT) -->
                    <div x-show="activeTestOp === 'create'">
                        <label class="block text-sm font-semibold mb-2">Request Body (JSON)</label>
                        <textarea wire:model="testRequestBody" 
                                  rows="8"
                                  class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg bg-gray-900 text-gray-100 font-mono text-sm"></textarea>
                    </div>

                    <!-- Test Button -->
                    <div class="flex gap-2">
                        <button x-show="activeTestOp === 'list'"
                                wire:click="testEndpoint('GET', '/api/data/{{ $documentation['model']['table_name'] }}')"
                                wire:loading.attr="disabled"
                                class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold disabled:opacity-50">
                            <span wire:loading.remove wire:target="testEndpoint">Send Request</span>
                            <span wire:loading wire:target="testEndpoint">Loading...</span>
                        </button>
                        <button x-show="activeTestOp === 'create'"
                                wire:click="testEndpoint('POST', '/api/data/{{ $documentation['model']['table_name'] }}')"
                                wire:loading.attr="disabled"
                                class="px-6 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold disabled:opacity-50">
                            <span wire:loading.remove wire:target="testEndpoint">Send Request</span>
                            <span wire:loading wire:target="testEndpoint">Loading...</span>
                        </button>
                    </div>

                    <!-- Response Display -->
                    @if($testResponse !== null)
                        <div class="border-t dark:border-gray-700 pt-4">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="font-semibold">Response:</span>
                                <span class="px-2 py-1 rounded text-sm font-mono
                                    {{ $testStatusCode >= 200 && $testStatusCode < 300 ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : '' }}
                                    {{ $testStatusCode >= 400 ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : '' }}
                                ">
                                    {{ $testStatusCode }}
                                </span>
                            </div>
                            <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                                <pre class="text-sm text-gray-100"><code>{{ json_encode($testResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Response Examples -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                    <x-heroicon-o-check-circle class="w-6 h-6 text-emerald-500" />
                    Response Examples
                </h3>

                <div class="space-y-6">
                    <!-- Success Responses -->
                    <div>
                        <h4 class="font-semibold text-lg mb-3 text-green-600 dark:text-green-400">Success Responses</h4>
                        <div class="space-y-4">
                            @foreach($documentation['responses']['success'] as $code => $response)
                                <div class="border dark:border-gray-700 rounded-lg p-4">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="px-3 py-1 bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded font-mono text-sm font-bold">
                                            {{ $code }}
                                        </span>
                                        <span class="text-sm text-gray-600 dark:text-gray-400">{{ $response['description'] }}</span>
                                    </div>
                                    <div class="bg-gray-900 rounded p-3 overflow-x-auto">
                                        <pre class="text-sm text-gray-100"><code>{{ json_encode($response['example'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Error Responses -->
                    <div>
                        <h4 class="font-semibold text-lg mb-3 text-red-600 dark:text-red-400">Error Responses</h4>
                        <div class="space-y-4">
                            @foreach($documentation['responses']['error'] as $code => $response)
                                <div class="border dark:border-gray-700 rounded-lg p-4">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="px-3 py-1 bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 rounded font-mono text-sm font-bold">
                                            {{ $code }}
                                        </span>
                                        <span class="text-sm text-gray-600 dark:text-gray-400">{{ $response['description'] }}</span>
                                    </div>
                                    <div class="bg-gray-900 rounded p-3 overflow-x-auto">
                                        <pre class="text-sm text-gray-100"><code>{{ json_encode($response['example'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- Webhook Documentation -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                    <x-heroicon-o-bolt class="w-6 h-6 text-yellow-500" />
                    Webhooks
                </h3>

                <p class="text-gray-600 dark:text-gray-400 mb-6">{{ $documentation['webhooks']['description'] }}</p>

                <!-- Webhook Events -->
                <div class="space-y-4 mb-6">
                    <h4 class="font-semibold text-lg">Events</h4>
                    @foreach($documentation['webhooks']['events'] as $event => $details)
                        <div class="border dark:border-gray-700 rounded-lg p-4">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="px-3 py-1 bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200 rounded font-mono text-sm font-bold">
                                    {{ $event }}
                                </span>
                                <span class="text-sm text-gray-600 dark:text-gray-400">{{ $details['description'] }}</span>
                            </div>
                            <div class="bg-gray-900 rounded p-3 overflow-x-auto">
                                <pre class="text-sm text-gray-100"><code>{{ json_encode($details['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Webhook Headers -->
                <div class="mb-6">
                    <h4 class="font-semibold text-lg mb-3">Headers</h4>
                    <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                        @foreach($documentation['webhooks']['headers'] as $header => $value)
                            <div class="flex items-start gap-2 mb-2">
                                <code class="text-sm text-blue-600 dark:text-blue-400 font-semibold">{{ $header }}:</code>
                                <code class="text-sm text-gray-700 dark:text-gray-300">{{ $value }}</code>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Signature Verification -->
                <div>
                    <h4 class="font-semibold text-lg mb-3">Signature Verification</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">{{ $documentation['webhooks']['signature_verification']['description'] }}</p>
                    
                    <div x-data="{ activeLang: 'php' }">
                        <div class="flex gap-2 mb-3">
                            <button @click="activeLang = 'php'" 
                                    :class="activeLang === 'php' ? 'bg-blue-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                                    class="px-3 py-1 rounded text-sm">PHP</button>
                            <button @click="activeLang = 'javascript'" 
                                    :class="activeLang === 'javascript' ? 'bg-blue-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                                    class="px-3 py-1 rounded text-sm">JavaScript</button>
                            <button @click="activeLang = 'python'" 
                                    :class="activeLang === 'python' ? 'bg-blue-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                                    class="px-3 py-1 rounded text-sm">Python</button>
                        </div>

                        <div x-show="activeLang === 'php'" class="bg-gray-900 rounded-lg p-4">
                            <pre class="text-sm text-gray-100"><code>{{ $documentation['webhooks']['signature_verification']['example_code']['php'] }}</code></pre>
                        </div>
                        <div x-show="activeLang === 'javascript'" class="bg-gray-900 rounded-lg p-4">
                            <pre class="text-sm text-gray-100"><code>{{ $documentation['webhooks']['signature_verification']['example_code']['javascript'] }}</code></pre>
                        </div>
                        <div x-show="activeLang === 'python'" class="bg-gray-900 rounded-lg p-4">
                            <pre class="text-sm text-gray-100"><code>{{ $documentation['webhooks']['signature_verification']['example_code']['python'] }}</code></pre>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Code Examples -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                    <x-heroicon-o-code-bracket class="w-6 h-6 text-orange-500" />
                    Code Examples
                </h3>

                <div x-data="{ activeTab: 'curl', activeOperation: 'list' }">
                    <!-- Language Tabs -->
                    <div class="flex gap-2 mb-4 border-b dark:border-gray-700">
                        <button @click="activeTab = 'curl'" 
                                :class="activeTab === 'curl' ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-400'"
                                class="px-4 py-2 font-semibold">
                            cURL
                        </button>
                        <button @click="activeTab = 'javascript'" 
                                :class="activeTab === 'javascript' ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-400'"
                                class="px-4 py-2 font-semibold">
                            JavaScript
                        </button>
                        <button @click="activeTab = 'python'" 
                                :class="activeTab === 'python' ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-400'"
                                class="px-4 py-2 font-semibold">
                            Python
                        </button>
                    </div>

                    <!-- Operation Tabs -->
                    <div class="flex gap-2 mb-4 flex-wrap">
                        <button @click="activeOperation = 'list'" 
                                :class="activeOperation === 'list' ? 'bg-blue-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                                class="px-3 py-1 rounded text-sm">
                            List All
                        </button>
                        <button @click="activeOperation = 'get'" 
                                :class="activeOperation === 'get' ? 'bg-blue-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                                class="px-3 py-1 rounded text-sm">
                            Get One
                        </button>
                        <button @click="activeOperation = 'create'" 
                                :class="activeOperation === 'create' ? 'bg-green-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                                class="px-3 py-1 rounded text-sm">
                            Create
                        </button>
                        <button @click="activeOperation = 'update'" 
                                :class="activeOperation === 'update' ? 'bg-yellow-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                                class="px-3 py-1 rounded text-sm">
                            Update
                        </button>
                        <button @click="activeOperation = 'delete'" 
                                :class="activeOperation === 'delete' ? 'bg-red-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                                class="px-3 py-1 rounded text-sm">
                            Delete
                        </button>
                    </div>

                    <!-- Code Display -->
                    @foreach(['curl', 'javascript', 'python'] as $lang)
                        <div x-show="activeTab === '{{ $lang }}'" class="space-y-4">
                            @foreach(['list', 'get', 'create', 'update', 'delete'] as $op)
                                <div x-show="activeOperation === '{{ $op }}'">
                                    <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                                        <pre class="text-sm text-gray-100"><code>{{ $documentation['examples'][$lang][$op] }}</code></pre>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- JSON Schema -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                    <x-heroicon-o-document-text class="w-6 h-6 text-indigo-500" />
                    JSON Schema
                </h3>
                <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                    <pre class="text-sm text-gray-100"><code>{{ json_encode($documentation['schema'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                </div>
            </div>
        @else
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-12 text-center">
                <x-heroicon-o-book-open class="w-16 h-16 text-gray-400 mx-auto mb-4" />
                <h3 class="text-xl font-semibold text-gray-600 dark:text-gray-400 mb-2">
                    Select a table to view API documentation
                </h3>
                <p class="text-gray-500 dark:text-gray-500">
                    Choose a table from the dropdown above to see its API endpoints, examples, and schema.
                </p>
            </div>
        @endif
    </div>
</x-filament-panels::page>

```

## File: resources/views/filament/pages/branding-settings.blade.php
```php
<x-filament-panels::page>
    <form wire:submit="save" class="fi-page-content">
        {{ $this->form }}
        <div class="mt-6 flex items-end justify-between">
            <x-filament::button type="submit">
                {{ __('db-config::db-config.save') }}
            </x-filament::button>
            <small class="text-success">
                {{ __('db-config::db-config.last_updated') }}:
                {{ $this->lastUpdatedAt(timezone: 'UTC', format: 'F j, Y, H:i:s') . ' UTC' ?? 'Never' }}
            </small>
        </div>
    </form>
</x-filament-panels::page>
```

## File: resources/views/filament/pages/code-generator.blade.php
```php
<x-filament-panels::page>
    {{-- Input Form --}}
    <form wire:submit="generate">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button type="submit" icon="heroicon-o-bolt">
                Generate Code
            </x-filament::button>
        </div>
    </form>

    {{-- Output Section --}}
    @if(count($this->generatedFiles) > 0)
        <div class="mt-6" x-data="{ activeTab: @entangle('activeTab') }">
            {{-- Tab Headers --}}
            <div class="flex gap-1 border-b border-gray-200 dark:border-gray-700">
                @foreach($this->generatedFiles as $index => $file)
                    <button
                        type="button"
                        wire:click="setTab({{ $index }})"
                        class="px-4 py-2.5 text-sm font-medium rounded-t-lg transition-colors
                            {{ $activeTab === $index
                                ? 'bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400 border-b-2 border-primary-600 dark:border-primary-400'
                                : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800' }}"
                    >
                        <span class="flex items-center gap-2">
                            <x-heroicon-m-document-text class="w-4 h-4" />
                            {{ $file['name'] }}
                        </span>
                    </button>
                @endforeach
            </div>

            {{-- Tab Content --}}
            @foreach($this->generatedFiles as $index => $file)
                <div x-show="activeTab === {{ $index }}" x-cloak>
                    {{-- File Info Bar --}}
                    <div class="flex items-center justify-between px-4 py-2 bg-gray-100 dark:bg-gray-800 border-x border-gray-200 dark:border-gray-700">
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $file['description'] ?? $file['name'] }}
                        </span>
                        <x-filament::button
                            size="xs"
                            color="gray"
                            icon="heroicon-m-clipboard-document"
                            wire:click="copyCode({{ $index }})"
                        >
                            Copy
                        </x-filament::button>
                    </div>

                    {{-- Code Block --}}
                    <div class="relative border border-t-0 border-gray-200 dark:border-gray-700 rounded-b-lg overflow-hidden">
                        <pre class="p-4 overflow-x-auto bg-gray-950 text-gray-100 text-sm leading-relaxed" style="max-height: 600px;"><code>{{ $file['code'] }}</code></pre>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Clipboard JS --}}
    @script
    <script>
        $wire.on('copy-to-clipboard', ({ code }) => {
            navigator.clipboard.writeText(code).catch(() => {
                // Fallback for older browsers
                const textarea = document.createElement('textarea');
                textarea.value = code;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
            });
        });
    </script>
    @endscript
</x-filament-panels::page>

```

## File: resources/views/filament/pages/data-explorer.blade.php
```php
<x-filament-panels::page>
    @if(!$tableId)
        <div class="flex flex-col items-center justify-center p-12 text-center border-2 border-dashed border-gray-300 rounded-xl dark:border-gray-700">
            <h2 class="text-xl font-bold">No Table Selected</h2>
            <p class="text-gray-500">Go to "Table Builder" and click "View Data" on a table.</p>
        </div>
    @else
        @if($isSpreadsheet)
            @php
                $dynamicModel = \App\Models\DynamicModel::find($tableId);
            @endphp
            @if($dynamicModel)
                @livewire(\App\Filament\Widgets\UniverSheetWidget::class, ['tableId' => $tableId])
            @endif
        @else
            {{ $this->table }}
        @endif
    @endif
</x-filament-panels::page>

```

## File: resources/views/filament/pages/social-settings.blade.php
```php
<x-filament-panels::page>
    <form wire:submit="save" class="fi-page-content">
        {{ $this->form }}
        <div class="mt-6 flex items-end justify-between">
            <x-filament::button type="submit">
                {{ __('db-config::db-config.save') }}
            </x-filament::button>
            <small class="text-success">
                {{ __('db-config::db-config.last_updated') }}:
                {{ $this->lastUpdatedAt(timezone: 'UTC', format: 'F j, Y, H:i:s') . ' UTC' ?? 'Never' }}
            </small>
        </div>
    </form>
</x-filament-panels::page>
```

## File: resources/views/filament/pages/sql-playground.blade.php
```php
<x-filament-panels::page>
    <form wire:submit="runQuery">
        {{ $this->form }}
    </form>

    @if($message)
        <div class="mt-4 p-4 bg-success-500/10 text-success-600 rounded-lg border border-success-500/20">
            {{ $message }}
        </div>
    @endif

    @if($results !== null && count($results) > 0)
        <x-filament::section>
            <x-slot name="heading">
                Query Results ({{ count($results) }} rows)
            </x-slot>

            @if(count($results) > 0)
                <div class="overflow-x-auto">
                    <table class="w-full text-sm text-left rtl:text-right text-gray-500 dark:text-gray-400">
                        <thead class="text-xs text-gray-700 uppercase bg-gray-50 dark:bg-gray-700 dark:text-gray-400">
                            <tr>
                                @foreach(array_keys((array)$results[0]) as $column)
                                    <th scope="col" class="px-6 py-3">
                                        {{ $column }}
                                    </th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($results as $row)
                                <tr class="bg-white border-b dark:bg-gray-800 dark:border-gray-700">
                                    @foreach((array)$row as $value)
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            @if(is_array($value) || is_object($value))
                                                <pre class="text-xs">{{ json_encode($value) }}</pre>
                                            @else
                                                {{ $value }}
                                            @endif
                                        </td>
                                    @endforeach
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @else
                <div class="p-4 text-center text-gray-500">
                    No results found or query triggered no dataset.
                </div>
            @endif
        </x-filament::section>
    @endif
</x-filament-panels::page>

```

## File: resources/views/filament/pages/storage-settings.blade.php
```php
<x-filament-panels::page>
    <form wire:submit="submit">
        {{ $this->form }}

        <div class="mt-6">
            <x-filament::button type="submit">
                {{ __('Save Configuration') }}
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>

```

## File: resources/views/filament/widgets/univer-sheet.blade.php
```php
@php
    $cssFile = null;
    try {
        $manifestPath = public_path('build/manifest.json');
        if (file_exists($manifestPath)) {
            $manifest = json_decode(file_get_contents($manifestPath), true);
            $cssFile = $manifest['resources/js/univer-adapter.js']['css'][0] ?? null;
        }
    } catch (\Exception $e) { }
@endphp

<x-filament-widgets::widget>
    @if($hasData)
        {{-- Force load the CSS --}}
        @if($cssFile)
            <link rel="stylesheet" href="{{ asset('build/' . $cssFile) }}" data-univer-styles>
        @endif

        <div 
            id="univer-widget-outer-container-{{ $tableId }}"
            x-data="{ 
                tableName: '{{ $tableName }}',
                saveUrl: '{{ $saveUrl }}',
                csrfToken: '{{ $csrfToken }}',
                apiToken: '{{ $apiToken }}',
                schema: {{ json_encode($schema) }},
                tableData: {{ json_encode($tableData) }},
                initialized: false,
                adapterReady: false,
                checkCount: 0,
                
                checkAndInit() {
                    this.checkCount++;
                    const isReady = (typeof window.initUniverInstance === 'function');
                    this.adapterReady = isReady;
                    
                    if (isReady && !this.initialized) {
                        console.log('[Univer] 🚀 Engine found! Booting...');
                        const containerId = 'univer-container-{{ $tableId }}';
                        
                        try {
                            window.initUniverInstance(
                                containerId,
                                this.tableData,
                                this.schema,
                                this.saveUrl,
                                this.csrfToken,
                                this.apiToken
                            );
                            this.initialized = true;
                        } catch (e) {
                            console.error('[Univer] 💥 BOOT ERROR:', e);
                        }
                    }
                },

                injectScript() {
                    const scriptId = 'univer-engine-loader';
                    if (document.getElementById(scriptId)) return;

                    console.log('[Univer] 📥 Injecting engine script...');
                    const s = document.createElement('script');
                    s.id = scriptId;
                    s.type = 'module';
                    s.src = '{{ Vite::asset("resources/js/univer-adapter.js") }}';
                    document.head.appendChild(s);
                }
            }"
            x-init="
                injectScript();
                const timer = setInterval(() => {
                    if ($data.initialized || $data.checkCount > 150) {
                        clearInterval(timer);
                        return;
                    }
                    $data.checkAndInit();
                }, 1000);

                document.addEventListener('univer-ready', () => $data.checkAndInit());
            "
        >
            <x-filament::section>
                <div id="univer-container-root-{{ $tableId }}" wire:ignore>
                    <div id="univer-container-{{ $tableId }}" style="height: 80vh; width: 100%; position: relative;" class="border rounded-lg shadow-inner bg-gray-50 overflow-hidden">
                        
                        {{-- Loading Overlay --}}
                        <div x-show="!initialized" class="flex flex-col items-center justify-center h-full p-8 text-center bg-white/90 backdrop-blur-md z-50 absolute inset-0 rounded-lg">
                            <x-filament::loading-indicator class="h-16 w-16 mx-auto mb-6 text-primary-600" />
                            
                            <h3 class="text-xl font-black text-slate-800 mb-2">Univer Intelligence</h3>
                            <p class="text-slate-500 font-medium mb-8">Assembling spreadsheet engine modules...</p>
                            
                            <div class="p-6 bg-slate-50 border border-slate-200 rounded-2xl text-left text-sm font-mono inline-block min-w-[320px] shadow-lg">
                                <div class="flex items-center justify-between mb-3 text-xs uppercase tracking-tighter">
                                    <span class="text-slate-400">Engine Source</span>
                                    <span :class="adapterReady ? 'text-green-600 font-bold' : 'text-amber-500 font-bold'" x-text="adapterReady ? '✅ ATTACHED' : '⏳ LOADING...'"></span>
                                </div>
                                <div class="flex items-center justify-between mb-5 text-xs uppercase tracking-tighter">
                                    <span class="text-slate-400">Boot Duration</span>
                                    <span class="text-primary-600 font-bold"><span x-text="checkCount"></span>s</span>
                                </div>
                                
                                <div class="h-1.5 w-full bg-slate-200 rounded-full overflow-hidden mb-4">
                                    <div class="h-full bg-primary-600 transition-all duration-1000" :style="'width: ' + Math.min((checkCount/30)*100, 100) + '%'"></div>
                                </div>
                            </div>

                            <div class="mt-8 flex gap-4">
                                <button @click="location.reload()" class="px-6 py-2.5 bg-slate-800 text-white rounded-xl text-sm font-bold hover:bg-slate-900 transition-all shadow-md">Full Restart</button>
                            </div>
                        </div>
                    </div>
                </div>
            </x-filament::section>
        </div>
    @else
        <div class="p-10 text-center text-gray-500">
            <p>Please select a table to begin using Spreadsheet View.</p>
        </div>
    @endif
</x-filament-widgets::widget>

```

## File: bootstrap/app.php
```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \App\Http\Middleware\LogApiActivity::class,
        ]);
        $middleware->alias([
            'api.key' => \App\Http\Middleware\VerifyApiKey::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

```

## File: composer.json
```json
{
    "$schema": "https://getcomposer.org/schema.json",
    "name": "laravel/laravel",
    "type": "project",
    "description": "The skeleton application for the Laravel framework.",
    "keywords": [
        "laravel",
        "framework"
    ],
    "license": "MIT",
    "require": {
        "php": "^8.2",
        "bezhansalleh/filament-shield": "^4.1",
        "dedoc/scramble": "^0.13.10",
        "filament/filament": "^4.0",
        "filament/spatie-laravel-media-library-plugin": "^3.2",
        "hugomyb/filament-media-action": "^5.0",
        "inerba/filament-db-config": "^1.3",
        "jeffgreco13/filament-breezy": "^3.1",
        "laravel/framework": "^12.0",
        "laravel/pulse": "^1.5",
        "laravel/reverb": "^1.7",
        "laravel/sanctum": "^4.2",
        "laravel/tinker": "^2.10.1",
        "mwguerra/filemanager": "^0.1.8",
        "opcodesio/log-viewer": "^3.0",
        "pxlrbt/filament-excel": "^3.4",
        "pxlrbt/filament-spotlight": "^2.1",
        "shuvroroy/filament-spatie-laravel-backup": "^3.3",
        "shuvroroy/filament-spatie-laravel-health": "^3.2",
        "spatie/laravel-activitylog": "^4.11",
        "spatie/laravel-backup": "^8.0",
        "spatie/laravel-health": "^1.36",
        "spatie/laravel-medialibrary": "^10.0 || ^11.0",
        "spatie/laravel-permission": "^6.24",
        "spatie/laravel-query-builder": "^6.4",
        "spatie/laravel-settings": "^3.7",
        "spatie/laravel-webhook-server": "^3.9",
        "symfony/html-sanitizer": "^7.0",
        "tailflow/laravel-orion": "^2.23"
    },
    "require-dev": {
        "fakerphp/faker": "^1.23",
        "laravel/pail": "^1.2.2",
        "laravel/pint": "^1.24",
        "laravel/sail": "^1.41",
        "mockery/mockery": "^1.6",
        "nunomaduro/collision": "^8.6",
        "phpunit/phpunit": "^11.5.3"
    },
    "autoload": {
        "psr-4": {
            "App\\": "app/",
            "Database\\Factories\\": "database/factories/",
            "Database\\Seeders\\": "database/seeders/"
        }
    },
    "autoload-dev": {
        "psr-4": {
            "Tests\\": "tests/"
        }
    },
    "scripts": {
        "setup": [
            "composer install",
            "@php -r \"file_exists('.env') || copy('.env.example', '.env');\"",
            "@php artisan key:generate",
            "@php artisan migrate --force",
            "npm install",
            "npm run build"
        ],
        "dev": [
            "Composer\\Config::disableProcessTimeout",
            "npx concurrently -c \"#93c5fd,#c4b5fd,#fb7185,#fdba74\" \"php artisan serve\" \"php artisan queue:listen --tries=1 --timeout=0\" \"php artisan pail --timeout=0\" \"npm run dev\" --names=server,queue,logs,vite --kill-others"
        ],
        "test": [
            "@php artisan config:clear --ansi",
            "@php artisan test"
        ],
        "post-autoload-dump": [
            "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
            "@php artisan package:discover --ansi",
            "@php artisan filament:upgrade"
        ],
        "post-update-cmd": [
            "@php artisan vendor:publish --tag=laravel-assets --ansi --force"
        ],
        "post-root-package-install": [
            "@php -r \"file_exists('.env') || copy('.env.example', '.env');\""
        ],
        "post-create-project-cmd": [
            "@php artisan key:generate --ansi",
            "@php -r \"file_exists('database/database.sqlite') || touch('database/database.sqlite');\"",
            "@php artisan migrate --graceful --ansi"
        ],
        "pre-package-uninstall": [
            "Illuminate\\Foundation\\ComposerScripts::prePackageUninstall"
        ]
    },
    "extra": {
        "laravel": {
            "dont-discover": []
        }
    },
    "config": {
        "optimize-autoloader": true,
        "preferred-install": "dist",
        "sort-packages": true,
        "allow-plugins": {
            "pestphp/pest-plugin": true,
            "php-http/discovery": true
        }
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

## File: PHASE2_SUMMARY.md
```markdown
# 🚀 Phase 2 Complete: API Documentation Evolution

## What Was Built

I've successfully evolved your API Documentation Engine with **4 major power features**:

### ⚡ 1. Try It Out (Interactive Testing)
- **Live API testing** directly in the docs
- Test GET and POST operations with real API calls
- Enter your API key and request body
- See live responses with status codes
- Color-coded success (green) and errors (red)

### 📄 2. OpenAPI 3.0 Export
- **Download button** generates complete OpenAPI spec
- Import into Postman, Insomnia, or Swagger UI
- Includes all endpoints, schemas, and security definitions
- Industry-standard format for API documentation

### 📊 3. Response Examples
- **Success responses**: 200 OK, 201 Created
- **Error responses**: 400, 401, 403, 404, 422
- Real sample data based on your field types
- Shows validation error examples

### 🪝 4. Webhook Documentation
- Complete guide to webhook events (created, updated, deleted)
- Event payload examples
- Headers sent with each webhook
- **Signature verification** code in PHP, JavaScript, and Python
- HMAC SHA256 security examples

---

## Files Modified

✅ `app/Services/ApiDocumentationService.php` - Added OpenAPI generation, response examples, webhook docs  
✅ `app/Filament/Pages/ApiDocumentation.php` - Added testing methods, download functionality  
✅ `resources/views/filament/pages/api-documentation.blade.php` - Added interactive UI sections  

---

## How to Use

### Test an Endpoint:
1. Go to **Developer → API Documentation**
2. Select a table
3. Scroll to "Try It Out" section
4. Enter your API key (use `sk_` for write operations)
5. Click "Send Request"
6. View the live response!

### Download OpenAPI Spec:
1. Click "Download OpenAPI Spec" button at the top
2. Import the JSON file into Postman or Insomnia
3. Start making API calls from your favorite tool!

### View Response Examples:
- Scroll to "Response Examples" section
- See what successful responses look like
- Understand error formats and validation messages

### Learn About Webhooks:
- Scroll to "Webhooks" section
- See event payloads for created/updated/deleted
- Copy signature verification code for your language
- Secure your webhook endpoints

---

## Integration with Digibase Core

✅ **Iron Dome** - Respects API key permissions  
✅ **Turbo Cache** - Tests show real API behavior  
✅ **Schema Doctor** - Validation rules documented  
✅ **Live Wire** - Webhook events explained  

---

## What Makes This Special

🎯 **Swagger-like experience** without external tools  
🎯 **Always up-to-date** with your schema  
🎯 **PocketBase-inspired** clean design  
🎯 **Dark mode** support  
🎯 **Real API calls** not mocked responses  
🎯 **Security-first** with Iron Dome integration  

---

## Next Steps

Your API Documentation is now **production-ready** and **developer-friendly**! 

Try it out:
1. Navigate to `/admin/api-documentation`
2. Select a dynamic table
3. Test the interactive features
4. Download the OpenAPI spec
5. Share with your team!

---

**Phase 2 Evolution: Complete! 🎉**

```

## File: CORE_API_ARCHITECTURE.md
```markdown
# 🏗️ Core API Engine Architecture

## System Overview

```
┌─────────────────────────────────────────────────────────────────────┐
│                         CLIENT APPLICATIONS                          │
│  (digibase.js SDK, Mobile Apps, Web Apps, Third-party Services)    │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             │ HTTP Requests
                             │ (Authorization: Bearer sk_xxx)
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                          API GATEWAY                                 │
│                      Laravel Router (api.php)                        │
├─────────────────────────────────────────────────────────────────────┤
│  Legacy API: /api/data/{table}        (Backward Compatible)         │
│  New v1 API: /api/v1/data/{table}     (Recommended)                 │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             │ Middleware Pipeline
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      MIDDLEWARE LAYER                                │
├─────────────────────────────────────────────────────────────────────┤
│  1. VerifyApiKey (Iron Dome)                                        │
│     ├─ Validate API key (pk_/sk_)                                   │
│     ├─ Check scopes (read, write, delete)                           │
│     ├─ Verify table access (allowed_tables)                         │
│     └─ Record usage tracking                                        │
│                                                                      │
│  2. ApiRateLimiter                                                  │
│     ├─ Read rate_limit from api_keys table                          │
│     ├─ Enforce per-key limits                                       │
│     ├─ Add rate limit headers                                       │
│     └─ Return 429 if exceeded                                       │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             │ Authorized Request
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                    CORE DATA CONTROLLER                              │
│              (app/Http/Controllers/Api/CoreDataController.php)       │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌────────────────────────────────────────────────────────────┐   │
│  │  REQUEST PROCESSING                                         │   │
│  ├────────────────────────────────────────────────────────────┤   │
│  │  1. Get DynamicModel by table name                         │   │
│  │  2. Validate RLS rules (list_rule, create_rule, etc.)      │   │
│  │  3. Build validation rules (Schema Doctor)                 │   │
│  │  4. Execute operation in transaction                        │   │
│  └────────────────────────────────────────────────────────────┘   │
│                                                                      │
│  ┌────────────────────────────────────────────────────────────┐   │
│  │  CRUD OPERATIONS                                            │   │
│  ├────────────────────────────────────────────────────────────┤   │
│  │  • index()   - List records (with cache)                   │   │
│  │  • show()    - Get single record                           │   │
│  │  • store()   - Create record (transaction + type-safe)     │   │
│  │  • update()  - Update record (transaction + type-safe)     │   │
│  │  • destroy() - Delete record (transaction)                 │   │
│  │  • schema()  - Get model schema                            │   │
│  └────────────────────────────────────────────────────────────┘   │
│                                                                      │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             │ Integrations
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      CORE SYSTEMS                                    │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  🛡️ IRON DOME (Security)                                           │
│  ├─ API Key validation                                              │
│  ├─ Scope-based permissions                                         │
│  ├─ Table-level access control                                      │
│  └─ Usage tracking                                                  │
│                                                                      │
│  🩺 SCHEMA DOCTOR (Validation)                                      │
│  ├─ Dynamic validation rules                                        │
│  ├─ Type-based validation                                           │
│  ├─ Required field enforcement                                      │
│  └─ Unique constraint handling                                      │
│                                                                      │
│  ⚡ TURBO CACHE (Performance)                                       │
│  ├─ Automatic caching for GET                                       │
│  ├─ Cache invalidation on mutations                                 │
│  ├─ Request-aware cache keys                                        │
│  └─ Configurable TTL                                                │
│                                                                      │
│  📡 LIVE WIRE (Real-time)                                           │
│  ├─ Event broadcasting                                              │
│  ├─ Webhook triggering                                              │
│  ├─ ModelActivity events                                            │
│  └─ Private channel support                                         │
│                                                                      │
│  🔒 TRANSACTION WRAPPER (Stability)                                 │
│  ├─ All mutations in DB transactions                                │
│  ├─ Automatic rollback on errors                                    │
│  ├─ Prevents partial updates                                        │
│  └─ Error logging with context                                      │
│                                                                      │
│  🎯 TYPE-SAFE CASTING (Data Integrity)                              │
│  ├─ Integer/bigint → (int)                                          │
│  ├─ Float/decimal/money → (float)                                   │
│  ├─ Boolean/checkbox → filter_var()                                 │
│  ├─ JSON/array → json_encode()                                      │
│  └─ Date/time → date formatting                                     │
│                                                                      │
└────────────────────────────┬────────────────────────────────────────┘
                             │
                             │ Database Operations
                             ▼
┌─────────────────────────────────────────────────────────────────────┐
│                      DATABASE LAYER                                  │
├─────────────────────────────────────────────────────────────────────┤
│  SQLite Database (WAL Mode)                                         │
│  ├─ Dynamic tables (created by users)                               │
│  ├─ System tables (users, api_keys, etc.)                           │
│  ├─ Optimized with indexes                                          │
│  └─ Transaction support                                             │
└─────────────────────────────────────────────────────────────────────┘
```

---

## Request Flow Diagram

### GET Request (List Records)

```
Client Request
    │
    ├─> VerifyApiKey Middleware
    │   ├─ Validate API key
    │   ├─ Check 'read' scope
    │   └─ Verify table access
    │
    ├─> ApiRateLimiter Middleware
    │   ├─ Check rate limit
    │   └─ Add rate limit headers
    │
    ├─> CoreDataController::index()
    │   ├─ Get DynamicModel
    │   ├─ Validate list_rule (RLS)
    │   ├─ Check Turbo Cache
    │   │   ├─ Cache HIT → Return cached data
    │   │   └─ Cache MISS → Continue
    │   ├─ Build query with filters
    │   ├─ Execute query
    │   ├─ Hide hidden fields
    │   ├─ Store in cache
    │   └─ Return JSON response
    │
    └─> Response with rate limit headers
```

### POST Request (Create Record)

```
Client Request
    │
    ├─> VerifyApiKey Middleware
    │   ├─ Validate API key
    │   ├─ Check 'write' scope
    │   └─ Verify table access
    │
    ├─> ApiRateLimiter Middleware
    │   ├─ Check rate limit
    │   └─ Add rate limit headers
    │
    ├─> CoreDataController::store()
    │   ├─ Get DynamicModel
    │   ├─ Validate create_rule (RLS)
    │   ├─ Build validation rules (Schema Doctor)
    │   ├─ Validate request data
    │   │   └─ Return 422 if invalid
    │   ├─> START TRANSACTION
    │   │   ├─ Cast values to correct types
    │   │   ├─ Create record
    │   │   ├─ Save to database
    │   │   └─ COMMIT
    │   ├─ Invalidate Turbo Cache
    │   ├─ Dispatch ModelActivity event
    │   ├─ Trigger webhooks (async)
    │   └─ Return 201 with record data
    │
    └─> Response with rate limit headers
```

### PUT Request (Update Record)

```
Client Request
    │
    ├─> VerifyApiKey Middleware
    │   ├─ Validate API key
    │   ├─ Check 'write' scope
    │   └─ Verify table access
    │
    ├─> ApiRateLimiter Middleware
    │   ├─ Check rate limit
    │   └─ Add rate limit headers
    │
    ├─> CoreDataController::update()
    │   ├─ Get DynamicModel
    │   ├─ Find existing record
    │   │   └─ Return 404 if not found
    │   ├─ Validate update_rule (RLS)
    │   │   └─ Return 403 if denied
    │   ├─ Build validation rules (Schema Doctor)
    │   ├─ Validate request data
    │   │   └─ Return 422 if invalid
    │   ├─> START TRANSACTION
    │   │   ├─ Cast values to correct types
    │   │   ├─ Update record
    │   │   ├─ Save to database
    │   │   └─ COMMIT
    │   ├─ Invalidate Turbo Cache
    │   ├─ Dispatch ModelActivity event
    │   ├─ Trigger webhooks (async)
    │   └─ Return 200 with updated record
    │
    └─> Response with rate limit headers
```

### DELETE Request (Delete Record)

```
Client Request
    │
    ├─> VerifyApiKey Middleware
    │   ├─ Validate API key
    │   ├─ Check 'delete' scope
    │   └─ Verify table access
    │
    ├─> ApiRateLimiter Middleware
    │   ├─ Check rate limit
    │   └─ Add rate limit headers
    │
    ├─> CoreDataController::destroy()
    │   ├─ Get DynamicModel
    │   ├─ Find existing record
    │   │   └─ Return 404 if not found
    │   ├─ Validate delete_rule (RLS)
    │   │   └─ Return 403 if denied
    │   ├─> START TRANSACTION
    │   │   ├─ Soft delete or hard delete
    │   │   └─ COMMIT
    │   ├─ Invalidate Turbo Cache
    │   ├─ Dispatch ModelActivity event
    │   ├─ Trigger webhooks (async)
    │   └─ Return 200 with success message
    │
    └─> Response with rate limit headers
```

---

## Error Handling Flow

```
┌─────────────────────────────────────────────────────────────────┐
│                      ERROR SCENARIOS                             │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  Missing API Key                                                │
│  └─> 401 Unauthorized                                           │
│      └─> { error_code: "MISSING_API_KEY" }                      │
│                                                                  │
│  Invalid API Key                                                │
│  └─> 401 Unauthorized                                           │
│      └─> { error_code: "INVALID_API_KEY" }                      │
│                                                                  │
│  Insufficient Scope                                             │
│  └─> 403 Forbidden                                              │
│      └─> { error_code: "INSUFFICIENT_SCOPE" }                   │
│                                                                  │
│  Table Access Denied                                            │
│  └─> 403 Forbidden                                              │
│      └─> { error_code: "TABLE_ACCESS_DENIED" }                  │
│                                                                  │
│  RLS Rule Denied                                                │
│  └─> 403 Forbidden                                              │
│      └─> { message: "Access denied by security rules" }         │
│                                                                  │
│  Model Not Found                                                │
│  └─> 404 Not Found                                              │
│      └─> { message: "Model not found" }                         │
│                                                                  │
│  Record Not Found                                               │
│  └─> 404 Not Found                                              │
│      └─> { message: "Record not found" }                        │
│                                                                  │
│  Validation Failed                                              │
│  └─> 422 Unprocessable Entity                                   │
│      └─> { message: "Validation failed", errors: {...} }        │
│                                                                  │
│  Rate Limit Exceeded                                            │
│  └─> 429 Too Many Requests                                      │
│      └─> { message: "Too many requests", retry_after: 60 }      │
│                                                                  │
│  Server Error                                                   │
│  └─> 500 Internal Server Error                                  │
│      ├─> Log error with context                                 │
│      ├─> Rollback transaction                                   │
│      └─> { message: "Failed to create record" }                 │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

---

## Data Flow: Type-Safe Casting

```
Client Request Data
    │
    ├─ { "name": "Product", "price": "29.99", "quantity": "10", "is_active": "1" }
    │
    ▼
Schema Doctor Validation
    │
    ├─ name: string ✓
    ├─ price: numeric ✓
    ├─ quantity: integer ✓
    └─ is_active: boolean ✓
    │
    ▼
Type-Safe Casting
    │
    ├─ name: "Product" (string)
    ├─ price: 29.99 (float)
    ├─ quantity: 10 (int)
    └─ is_active: true (boolean)
    │
    ▼
Database Write
    │
    └─ SQLite stores with correct types
```

---

## Cache Flow

```
GET Request
    │
    ├─> Check Turbo Cache
    │   ├─ Key: "table:products:params:hash"
    │   │
    │   ├─ Cache HIT
    │   │   └─> Return cached data (fast!)
    │   │
    │   └─ Cache MISS
    │       ├─> Execute database query
    │       ├─> Store result in cache
    │       └─> Return data
    │
    └─> Response

POST/PUT/DELETE Request
    │
    ├─> Execute mutation
    │
    └─> Invalidate cache
        └─> Clear all cache keys for this table
```

---

## Rate Limiting Flow

```
Request
    │
    ├─> ApiRateLimiter Middleware
    │   │
    │   ├─ Get API key from request
    │   ├─ Read rate_limit from api_keys table
    │   │   └─ Default: 60 req/min
    │   │
    │   ├─ Check RateLimiter
    │   │   ├─ Key: "api:{api_key_id}"
    │   │   ├─ Max: {rate_limit}
    │   │   └─ Decay: 1 minute
    │   │
    │   ├─ Too Many Attempts?
    │   │   ├─ YES → Return 429
    │   │   │   └─ Headers: X-RateLimit-*, Retry-After
    │   │   │
    │   │   └─ NO → Continue
    │   │       ├─ Hit rate limiter
    │   │       └─ Add rate limit headers
    │   │
    │   └─> Next middleware
    │
    └─> Controller
```

---

## Webhook Flow

```
CRUD Operation Complete
    │
    ├─> triggerWebhooks()
    │   │
    │   ├─ Find active webhooks for this model
    │   │
    │   ├─ For each webhook:
    │   │   ├─ Check if event matches (created, updated, deleted)
    │   │   ├─ Validate URL (SSRF protection)
    │   │   ├─ Remove sensitive fields
    │   │   ├─ Build payload
    │   │   ├─ Generate signature
    │   │   ├─ Send HTTP POST (async)
    │   │   │   ├─ Success → recordSuccess()
    │   │   │   └─ Failure → recordFailure()
    │   │   └─ Log result
    │   │
    │   └─> Continue (non-blocking)
    │
    └─> Return response to client
```

---

## Transaction Flow

```
Mutation Request (POST/PUT/DELETE)
    │
    ├─> executeInTransaction()
    │   │
    │   ├─> BEGIN TRANSACTION
    │   │   │
    │   │   ├─ Cast values to correct types
    │   │   ├─ Create/Update/Delete record
    │   │   ├─ Save to database
    │   │   │
    │   │   ├─ Success?
    │   │   │   ├─ YES → COMMIT
    │   │   │   │   └─> Return record
    │   │   │   │
    │   │   │   └─ NO → ROLLBACK
    │   │   │       ├─> Log error
    │   │   │       └─> Throw exception
    │   │   │
    │   │   └─> END TRANSACTION
    │   │
    │   └─> Return result
    │
    └─> Continue with events/webhooks
```

---

## System Integration Map

```
┌─────────────────────────────────────────────────────────────────┐
│                    CORE API ENGINE                               │
│                  (CoreDataController)                            │
└───────────────────────┬─────────────────────────────────────────┘
                        │
        ┌───────────────┼───────────────┐
        │               │               │
        ▼               ▼               ▼
┌──────────────┐ ┌──────────────┐ ┌──────────────┐
│  Iron Dome   │ │Schema Doctor │ │ Turbo Cache  │
│  (Security)  │ │ (Validation) │ │(Performance) │
└──────────────┘ └──────────────┘ └──────────────┘
        │               │               │
        └───────────────┼───────────────┘
                        │
        ┌───────────────┼───────────────┐
        │               │               │
        ▼               ▼               ▼
┌──────────────┐ ┌──────────────┐ ┌──────────────┐
│  Live Wire   │ │ Transaction  │ │  Type-Safe   │
│ (Real-time)  │ │   Wrapper    │ │   Casting    │
└──────────────┘ └──────────────┘ └──────────────┘
        │               │               │
        └───────────────┼───────────────┘
                        │
                        ▼
                ┌──────────────┐
                │   Database   │
                │   (SQLite)   │
                └──────────────┘
```

---

**The Core API Engine is a unified, production-ready system that seamlessly integrates all Digibase core features! 🚀**

```

## File: README.md
```markdown
<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework. You can also check out [Laravel Learn](https://laravel.com/learn), where you will be guided through building a modern Laravel application.

If you don't feel like reading, [Laracasts](https://laracasts.com) can help. Laracasts contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

## Laravel Sponsors

We would like to extend our thanks to the following sponsors for funding Laravel development. If you are interested in becoming a sponsor, please visit the [Laravel Partners program](https://partners.laravel.com).

### Premium Partners

- **[Vehikl](https://vehikl.com)**
- **[Tighten Co.](https://tighten.co)**
- **[Kirschbaum Development Group](https://kirschbaumdevelopment.com)**
- **[64 Robots](https://64robots.com)**
- **[Curotec](https://www.curotec.com/services/technologies/laravel)**
- **[DevSquad](https://devsquad.com/hire-laravel-developers)**
- **[Redberry](https://redberry.international/laravel-development)**
- **[Active Logic](https://activelogic.com)**

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

```

