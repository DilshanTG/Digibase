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

    /**
     * Dangerous SQL patterns that should be blocked even in the admin panel.
     * These target system-critical tables that could break the application.
     */
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

            // 1. Remove comments to correctly check if it's a SELECT query
            // This fixes the bug where "-- Select all users" was treated as a Write operation
            $cleanSql = preg_replace('/--.*$/m', '', $sql);
            $cleanSql = trim($cleanSql);

            // 2. Check if it's a Read Operation (SELECT, SHOW, DESCRIBE, WITH)
            if (stripos($cleanSql, 'SELECT') === 0 || stripos($cleanSql, 'SHOW') === 0 || stripos($cleanSql, 'DESCRIBE') === 0 || stripos($cleanSql, 'WITH') === 0) {
                
                // Use json_decode trick to convert stdClass objects to arrays for Blade compatibility
                $this->results = json_decode(json_encode(DB::select($sql)), true);
                
                $count = count($this->results);
                Notification::make()->success()->title('Query Loaded')->body("$count rows found.")->send();
            
            } else {
                // 3. Write Operation (CREATE, INSERT, UPDATE, DELETE, DROP)
                // Use 'unprepared' to support multiple statements (e.g., CREATE TABLE x; INSERT INTO x...)
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
                            $cleanTableName = Str::afterLast($rawTableName, '.');
                            
                            DB::transaction(function () use ($rawTableName, $cleanTableName, &$importedIds) {
                                // 1. Create/Find Model
                                $model = DynamicModel::firstOrCreate(
                                    ['table_name' => $rawTableName],
                                    [
                                        'name' => $cleanTableName,
                                        'display_name' => Str::headline($cleanTableName),
                                        'is_active' => true,
                                        'generate_api' => true,
                                        'user_id' => auth()->id(),
                                    ]
                                );
                                $importedIds[$rawTableName] = $model->id;

                                // 2. Sync Columns (Delete old fields to be safe, then recreate)
                                $model->fields()->delete();
                                $columns = LaravelSchema::getColumns($rawTableName);
                                
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

                    // PHASE 2: Auto-Detect Relationships (The Magic 🪄)
                    // We loop again because now all models exist!
                    foreach ($selectedTables as $rawTableName) {
                        $currentModelId = $importedIds[$rawTableName] ?? null;
                        if (!$currentModelId) continue;

                        // Get Foreign Keys from SQLite
                        $fks = DB::select("PRAGMA foreign_key_list('$rawTableName')");

                        foreach ($fks as $fk) {
                            $parentTableName = $fk->table; // e.g. 'authors'
                            $localCol = $fk->from;         // e.g. 'author_id'
                            
                            // Check if Parent is in our system (it might have a prefix)
                            // We search broadly for the table name
                            $parentModel = DynamicModel::where('table_name', 'LIKE', "%$parentTableName")->first();

                            if ($parentModel) {
                                // A. Create "BelongsTo" (Child -> Parent)
                                // "Book belongs to Author"
                                \App\Models\DynamicRelationship::firstOrCreate([
                                    'dynamic_model_id' => $currentModelId,
                                    'related_model_id' => $parentModel->id,
                                    'type' => 'belongsTo', 
                                ], [
                                    'foreign_key' => $localCol,
                                    'method_name' => Str::camel(Str::singular($parentModel->name)), // e.g. 'author'
                                ]);

                                // B. Create "HasMany" (Parent -> Child)
                                // "Author has many Books"
                                \App\Models\DynamicRelationship::firstOrCreate([
                                    'dynamic_model_id' => $parentModel->id,
                                    'related_model_id' => $currentModelId,
                                    'type' => 'hasMany',
                                ], [
                                    'foreign_key' => $localCol,
                                    'method_name' => Str::camel(Str::plural(Str::afterLast($rawTableName, '.'))), // e.g. 'books'
                                ]);
                            }
                        }
                    }

                    Notification::make()->success()->title("$count Tables Imported & Linked!")->send();
                }),
        ];
    }
}
