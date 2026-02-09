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
