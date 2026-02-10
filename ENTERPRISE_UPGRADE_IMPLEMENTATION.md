# 🔧 ENTERPRISE UPGRADE - IMPLEMENTATION GUIDE

## 📦 STEP 1: Install Packages

Copy and paste this entire block into your terminal:

```bash
# Install Core Packages
composer require spatie/laravel-medialibrary:"^11.0"
composer require filament/spatie-laravel-media-library-plugin:"^3.2"
composer require opcodesio/log-viewer:"^3.0"
composer require shuvroroy/filament-spatie-laravel-backup:"^2.0"

# Publish Configurations
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-config"
php artisan vendor:publish --provider="Spatie\Backup\BackupServiceProvider" --tag="backup-config"

# Run Migrations
php artisan migrate

# Create Storage Links (if not already done)
php artisan storage:link

# Clear Cache
php artisan config:clear
php artisan cache:clear
```

---

## 📝 STEP 2: Update DynamicRecord Model

**File:** `app/Models/DynamicRecord.php`

Replace the entire file with this:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

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
     * Register media collections for this model.
     * Supports multiple file types with automatic optimization.
     */
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('files')
            ->useDisk('digibase_storage') // Use your unified storage disk
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

        // Separate collection for images with automatic optimization
        $this->addMediaCollection('images')
            ->useDisk('digibase_storage')
            ->acceptsMimeTypes(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])
            ->registerMediaConversions(function () {
                $this->addMediaConversion('thumb')
                    ->width(150)
                    ->height(150)
                    ->sharpen(10)
                    ->nonQueued(); // Generate immediately

                $this->addMediaConversion('preview')
                    ->width(800)
                    ->height(600)
                    ->sharpen(10)
                    ->nonQueued();
            });
    }
}
```

---

## 📝 STEP 3: Update DataExplorer Page

**File:** `app/Filament/Pages/DataExplorer.php`

Replace the `getDynamicForm` method with this enhanced version:

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

---

## 📝 STEP 4: Update AdminPanelProvider

**File:** `app/Providers/Filament/AdminPanelProvider.php`

Add these imports at the top:

```php
use Filament\SpatieLaravelMediaLibraryPlugin;
```

Then update the `panel()` method to include the Media Library plugin:

```php
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
            'Database',
            'Storage',
            'Integrations',
            'Settings',
            'System', // New group for logs and backups
        ])
        
        // 📦 EXISTING PLUGINS
        ->plugin(
            FileManagerPlugin::make()
                ->withoutSchemaExample()
        )
        ->plugin(
            FilamentSpatieLaravelBackupPlugin::make()
                ->usingPolingInterval('10s')
                ->noTimeout()
        )
        ->plugin(
            DbConfigPlugin::make()
        )
        
        // 🎯 NEW: Spatie Media Library Plugin
        ->plugin(
            \Filament\SpatieLaravelMediaLibraryPlugin::make()
        )
        
        ->navigationItems([
            NavigationItem::make('API Docs')
                ->url('/docs/api')
                ->icon('heroicon-o-book-open')
                ->group('Integrations')
                ->sort(99)
                ->openUrlInNewTab(),
            
            // 🎯 NEW: Log Viewer Navigation Item
            NavigationItem::make('Log Viewer')
                ->url('/admin/log-viewer', shouldOpenInNewTab: false)
                ->icon('heroicon-o-bug-ant')
                ->group('System')
                ->sort(100)
                ->visible(fn () => auth()->check() && (auth()->id() === 1 || auth()->user()->is_admin ?? false)),
        ])
        
        // ... rest of your configuration
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
```

---

## 📝 STEP 5: Add Log Viewer Security Gate

**File:** `app/Providers/AppServiceProvider.php`

Add this import at the top:

```php
use Illuminate\Support\Facades\Gate;
```

Then add this method to the `boot()` function:

```php
public function boot(): void
{
    // 🧠 CENTRAL NERVOUS SYSTEM: Register the Observer
    DynamicRecord::observe(DynamicRecordObserver::class);

    // 🔒 SECURITY: Log Viewer Access Control
    $this->configureLogViewerSecurity();

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
```

---

## 📝 STEP 6: Configure Backup Settings

**File:** `config/backup.php`

Update the backup configuration to use your database:

```php
<?php

return [
    'backup' => [
        'name' => env('APP_NAME', 'digibase'),

        'source' => [
            'files' => [
                'include' => [
                    base_path(),
                ],
                'exclude' => [
                    base_path('vendor'),
                    base_path('node_modules'),
                    base_path('storage/framework'),
                    base_path('storage/logs'),
                ],
                'follow_links' => false,
                'ignore_unreadable_directories' => false,
                'relative_path' => null,
            ],

            'databases' => [
                'sqlite', // Your SQLite database
            ],
        ],

        'destination' => [
            'filename_prefix' => '',
            'disks' => [
                'local', // Store backups locally
                // Add 's3' or other disks for cloud backups
            ],
        ],

        'temporary_directory' => storage_path('app/backup-temp'),

        'password' => env('BACKUP_ARCHIVE_PASSWORD'),

        'encryption' => 'default',
    ],

    'notifications' => [
        'notifications' => [
            \Spatie\Backup\Notifications\Notifications\BackupHasFailedNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\UnhealthyBackupWasFoundNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\CleanupHasFailedNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\BackupWasSuccessfulNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\HealthyBackupWasFoundNotification::class => ['mail'],
            \Spatie\Backup\Notifications\Notifications\CleanupWasSuccessfulNotification::class => ['mail'],
        ],

        'notifiable' => \Spatie\Backup\Notifications\Notifiable::class,

        'mail' => [
            'to' => env('BACKUP_MAIL_TO', 'admin@example.com'),
            'from' => [
                'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
                'name' => env('MAIL_FROM_NAME', 'Digibase'),
            ],
        ],

        'slack' => [
            'webhook_url' => '',
            'channel' => null,
            'username' => null,
            'icon' => null,
        ],

        'discord' => [
            'webhook_url' => '',
            'username' => '',
            'avatar_url' => '',
        ],
    ],

    'monitor_backups' => [
        [
            'name' => env('APP_NAME', 'digibase'),
            'disks' => ['local'],
            'health_checks' => [
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumAgeInDays::class => 1,
                \Spatie\Backup\Tasks\Monitor\HealthChecks\MaximumStorageInMegabytes::class => 5000,
            ],
        ],
    ],

    'cleanup' => [
        'strategy' => \Spatie\Backup\Tasks\Cleanup\Strategies\DefaultStrategy::class,

        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => 5000,
        ],
    ],
];
```

---

## ✅ STEP 7: Test the Upgrade

### Test File Uploads
1. Go to `/admin/data-explorer`
2. Select a table with file/image fields
3. Create or edit a record
4. Upload files - they should now use Spatie Media Library
5. Verify files are stored in `storage/app/public/` or your S3 bucket

### Test Log Viewer
1. Go to `/admin/log-viewer` (or click "Log Viewer" in sidebar)
2. You should see all Laravel logs
3. Search, filter, and download logs
4. Only admins (User ID 1 or is_admin) can access

### Test Backups
1. Go to `/admin/backups`
2. Click "Create Backup"
3. Wait for backup to complete
4. Download backup file
5. Verify database is included

---

## 🎯 What Changed

### File Handling
**Before:** Custom `StorageController` with manual file management  
**After:** Spatie Media Library with automatic optimization, conversions, and cloud storage support

### Logging
**Before:** SSH required to view logs  
**After:** In-panel log viewer with search and filtering

### Backups
**Before:** Manual database backups  
**After:** Automated backup system with scheduling and cloud storage

---

## 🚀 Next Steps

1. Run all commands in STEP 1
2. Update all files in STEPS 2-6
3. Test everything in STEP 7
4. Follow `ENTERPRISE_CLEANUP_GUIDE.md` to remove old code
5. Configure backup schedule in `app/Console/Kernel.php`

---

**Implementation Status: Ready to Deploy** ✅
