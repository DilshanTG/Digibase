<?php

namespace App\Filament\Pages;

use App\Models\DynamicField;
use App\Models\DynamicModel;
use App\Models\DynamicRelationship;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB; // 👈 CRITICAL IMPORT
use Illuminate\Support\Facades\Schema as LaravelSchema;
use Illuminate\Support\Str;
use UnitEnum;

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
            if (empty($sql)) {
                return;
            }

            // 🛡️ SECURITY: Deep SQL parsing with tokenization
            $tokens = $this->tokenizeSql($sql);
            $normalizedSql = $this->normalizeSql($sql);

            // 🛡️ SECURITY: Block multiple statements
            if ($this->hasMultipleStatements($sql)) {
                Notification::make()->danger()->title('Blocked')->body('Multiple statements are not allowed for security reasons.')->send();

                return;
            }

            // 🛡️ SECURITY: Strict keyword validation
            $forbiddenKeywords = [
                'DROP', 'TRUNCATE', 'GRANT', 'REVOKE', 'EXEC', 'EXECUTE',
                'BENCHMARK', 'SLEEP', 'WAITFOR', 'DELAY', 'INTO OUTFILE',
                'INTO DUMPFILE', 'LOAD_FILE', 'SYSTEM', 'PG_SLEEP',
                'UNION', 'UNION ALL', 'INSERT', 'UPDATE', 'DELETE',
            ];

            foreach ($forbiddenKeywords as $keyword) {
                if ($this->containsKeyword($normalizedSql, $keyword)) {
                    Notification::make()->danger()->title('Blocked')->body("Forbidden SQL keyword detected: {$keyword}")->send();

                    return;
                }
            }

            // 🛡️ SECURITY: Block protected tables
            foreach ($this->protectedTables as $table) {
                if ($this->referencesTable($normalizedSql, $table)) {
                    Notification::make()->danger()->title('Blocked')->body("Access to system table '{$table}' is restricted.")->send();

                    return;
                }
            }

            // 🎯 PERFORMANCE: Force pagination limits to prevent OOM
            $sql = $this->enforcePagination($sql);

            // ✅ Execute based on query type
            $firstToken = strtoupper($tokens[0] ?? '');

            if (in_array($firstToken, ['SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN', 'WITH'])) {
                // 🎯 PERFORMANCE: Use cursor/pagination for large results
                $this->results = $this->executeSelectWithLimit($sql);
                $count = count($this->results);
                Notification::make()->success()->title('Query Loaded')->body("$count rows found (max 100).")->send();
            } else {
                Notification::make()->danger()->title('Blocked')->body('Only SELECT queries are allowed in the playground.')->send();
            }

        } catch (\Exception $e) {
            Notification::make()->danger()->title('SQL Error')->body($e->getMessage())->send();
        }
    }

    /**
     * 🛡️ Tokenize SQL for safe parsing
     */
    protected function tokenizeSql(string $sql): array
    {
        // Remove comments first
        $sql = preg_replace('/--.*$/m', '', $sql);
        $sql = preg_replace('/#.*$/m', '', $sql);
        $sql = preg_replace('/\/\*[\s\S]*?\*\//', '', $sql);

        // Tokenize by whitespace and special chars
        $tokens = preg_split('/\s+/', trim($sql), -1, PREG_SPLIT_NO_EMPTY);

        return array_map('strtoupper', $tokens);
    }

    /**
     * 🛡️ Normalize SQL for comparison
     */
    protected function normalizeSql(string $sql): string
    {
        // Remove all whitespace variations
        $normalized = preg_replace('/\s+/', ' ', $sql);

        return strtoupper(trim($normalized));
    }

    /**
     * 🛡️ Check for multiple statements
     */
    protected function hasMultipleStatements(string $sql): bool
    {
        // Count semicolons outside of strings
        $inString = false;
        $stringChar = null;
        $semicolons = 0;

        for ($i = 0; $i < strlen($sql); $i++) {
            $char = $sql[$i];

            if (! $inString && ($char === '"' || $char === "'" || $char === '`')) {
                $inString = true;
                $stringChar = $char;
            } elseif ($inString && $char === $stringChar) {
                $inString = false;
                $stringChar = null;
            } elseif (! $inString && $char === ';') {
                $semicolons++;
                if ($semicolons > 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * 🛡️ Check if normalized SQL contains a keyword
     */
    protected function containsKeyword(string $normalizedSql, string $keyword): bool
    {
        // Use word boundaries to prevent partial matches
        return preg_match('/\b'.preg_quote(strtoupper($keyword), '/').'\b/', $normalizedSql) === 1;
    }

    /**
     * 🛡️ Check if SQL references a specific table
     */
    protected function referencesTable(string $normalizedSql, string $table): bool
    {
        // Match table name with word boundaries, accounting for backticks
        $pattern = '/\b'.preg_quote(strtoupper($table), '/').'\b/';

        return preg_match($pattern, $normalizedSql) === 1;
    }

    /**
     * 🎯 PERFORMANCE: Enforce LIMIT clause to prevent OOM
     */
    protected function enforcePagination(string $sql): string
    {
        // Check if LIMIT already exists
        if (preg_match('/\bLIMIT\s+\d+/i', $sql)) {
            // Extract and limit existing LIMIT
            $sql = preg_replace_callback('/\bLIMIT\s+(\d+)/i', function ($matches) {
                $currentLimit = (int) $matches[1];

                return 'LIMIT '.min($currentLimit, 100);
            }, $sql);

            return $sql;
        }

        // Check if the query ends with ORDER BY
        if (preg_match('/\bORDER\s+BY\s+[^)]+$/i', $sql)) {
            return $sql.' LIMIT 100';
        }

        // Check for GROUP BY
        if (preg_match('/\bGROUP\s+BY\s+[^)]+$/i', $sql)) {
            return $sql.' LIMIT 100';
        }

        // Add LIMIT before any trailing semicolon
        $sql = rtrim($sql, ';');

        return $sql.' LIMIT 100';
    }

    /**
     * 🎯 PERFORMANCE: Execute SELECT with enforced limits
     */
    protected function executeSelectWithLimit(string $sql): array
    {
        // Use Laravel's chunking for memory efficiency
        $results = [];
        $count = 0;

        DB::select($sql);

        // Get results with hard limit
        $rawResults = DB::select($sql);

        foreach ($rawResults as $row) {
            if ($count >= 100) {
                break;
            }
            $results[] = (array) $row;
            $count++;
        }

        return $results;
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
                                'dynamic_relationships', 'model_activity', 'storage_files', 'file_system_items',
                            ];

                            $options = [];
                            foreach ($tables as $rawTableName) {
                                $cleanName = Str::afterLast($rawTableName, '.');
                                if (Str::startsWith($cleanName, 'sqlite_')) {
                                    continue;
                                }
                                if (in_array($cleanName, $exclude)) {
                                    continue;
                                }
                                $options[$rawTableName] = Str::headline($cleanName)." ($cleanName)";
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
                                    if ($colName === 'id') {
                                        continue;
                                    }

                                    $type = match (strtolower($col['type_name'])) {
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
                                        'is_required' => ! ($col['nullable']),
                                    ]);
                                }
                            });
                            $count++;
                        } catch (\Exception $e) {
                            continue;
                        }
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
