<?php

namespace App\Console\Commands;

use App\Models\DynamicModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class SystemHealthCheck extends Command
{
    protected $signature = 'digibase:health {--fix : Automatically fix issues}';
    protected $description = 'Comprehensive system health check - catches Laravel errors proactively';

    protected array $errors = [];
    protected array $warnings = [];
    protected array $passed = [];

    public function handle()
    {
        $this->info('🏥 Digibase System Health Check');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->newLine();

        // Run all checks
        $this->checkDatabase();
        $this->checkCache();
        $this->checkBroadcasting();
        $this->checkStorage();
        $this->checkDynamicTables();
        $this->checkMigrations();
        $this->checkEnvironment();

        // Display results
        $this->displayResults();

        return empty($this->errors) ? 0 : 1;
    }

    protected function checkDatabase(): void
    {
        $this->line('🔍 Checking Database...');

        try {
            DB::connection()->getPdo();
            $this->passed[] = 'Database connection successful';

            // Check if migrations table exists
            if (Schema::hasTable('migrations')) {
                $this->passed[] = 'Migrations table exists';
            } else {
                $this->errors[] = 'Migrations table not found - run: php artisan migrate';
            }
        } catch (\Exception $e) {
            $this->errors[] = "Database connection failed: {$e->getMessage()}";
        }
    }

    protected function checkCache(): void
    {
        $this->line('🔍 Checking Cache...');

        try {
            Cache::put('health_check', 'test', 1);
            $value = Cache::get('health_check');
            Cache::forget('health_check');

            if ($value === 'test') {
                $this->passed[] = 'Cache working correctly';
            } else {
                $this->warnings[] = 'Cache may not be working properly';
            }
        } catch (\Exception $e) {
            $this->warnings[] = "Cache error: {$e->getMessage()}";
        }
    }

    protected function checkBroadcasting(): void
    {
        $this->line('🔍 Checking Broadcasting...');

        $driver = config('broadcasting.default');

        if ($driver === 'reverb') {
            try {
                $host = config('broadcasting.connections.reverb.host', '127.0.0.1');
                $port = config('broadcasting.connections.reverb.port', 8080);

                $connection = @fsockopen($host, $port, $errno, $errstr, 1);

                if ($connection) {
                    $this->passed[] = 'Reverb server is running';
                    fclose($connection);
                } else {
                    $this->warnings[] = "Reverb server not running on {$host}:{$port}";
                    $this->line("   ℹ️  Start with: php artisan reverb:start");
                    $this->line("   ℹ️  Broadcasting will fail silently (non-critical)");
                }
            } catch (\Exception $e) {
                $this->warnings[] = "Broadcasting check failed: {$e->getMessage()}";
            }
        } else {
            $this->passed[] = "Broadcasting driver: {$driver}";
        }
    }

    protected function checkStorage(): void
    {
        $this->line('🔍 Checking Storage...');

        $directories = [
            storage_path('app'),
            storage_path('framework/cache'),
            storage_path('framework/sessions'),
            storage_path('framework/views'),
            storage_path('logs'),
        ];

        foreach ($directories as $dir) {
            if (is_writable($dir)) {
                $this->passed[] = "Writable: " . basename($dir);
            } else {
                $this->errors[] = "Not writable: {$dir}";
                if ($this->option('fix')) {
                    @chmod($dir, 0775);
                }
            }
        }
    }

    protected function checkDynamicTables(): void
    {
        $this->line('🔍 Checking Dynamic Tables...');

        try {
            $models = DynamicModel::all();

            foreach ($models as $model) {
                $tableName = $model->table_name;

                // Check table exists
                if (!Schema::hasTable($tableName)) {
                    $this->warnings[] = "Table '{$tableName}' does not exist (Model ID: {$model->id})";
                    continue;
                }

                // Check soft deletes
                if ($model->has_soft_deletes && !Schema::hasColumn($tableName, 'deleted_at')) {
                    $this->warnings[] = "Missing deleted_at column in '{$tableName}'";

                    if ($this->option('fix')) {
                        try {
                            Schema::table($tableName, function ($table) {
                                $table->softDeletes();
                            });
                            $this->info("   ✅ Fixed: Added deleted_at to '{$tableName}'");
                        } catch (\Exception $e) {
                            $this->errors[] = "Failed to add deleted_at to '{$tableName}': {$e->getMessage()}";
                        }
                    }
                }

                // Check timestamps
                if ($model->has_timestamps) {
                    $missingTimestamps = [];

                    if (!Schema::hasColumn($tableName, 'created_at')) {
                        $missingTimestamps[] = 'created_at';
                    }
                    if (!Schema::hasColumn($tableName, 'updated_at')) {
                        $missingTimestamps[] = 'updated_at';
                    }

                    if (!empty($missingTimestamps)) {
                        $columns = implode(', ', $missingTimestamps);
                        $this->warnings[] = "Missing timestamp columns in '{$tableName}': {$columns}";

                        if ($this->option('fix')) {
                            try {
                                Schema::table($tableName, function ($table) use ($missingTimestamps) {
                                    if (in_array('created_at', $missingTimestamps)) {
                                        $table->timestamp('created_at')->nullable();
                                    }
                                    if (in_array('updated_at', $missingTimestamps)) {
                                        $table->timestamp('updated_at')->nullable();
                                    }
                                });
                                $this->info("   ✅ Fixed: Added {$columns} to '{$tableName}'");
                            } catch (\Exception $e) {
                                $this->errors[] = "Failed to add timestamps to '{$tableName}': {$e->getMessage()}";
                            }
                        }
                    }
                }
            }

            if ($models->isEmpty()) {
                $this->passed[] = 'No dynamic tables to check';
            } else {
                $this->passed[] = "Checked {$models->count()} dynamic table(s)";
            }
        } catch (\Exception $e) {
            $this->errors[] = "Dynamic table check failed: {$e->getMessage()}";
        }
    }

    protected function checkMigrations(): void
    {
        $this->line('🔍 Checking Migrations...');

        try {
            $pending = 0;
            $migrationFiles = glob(database_path('migrations/*.php'));
            $ranMigrations = DB::table('migrations')->pluck('migration')->toArray();

            foreach ($migrationFiles as $file) {
                $filename = pathinfo($file, PATHINFO_FILENAME);
                if (!in_array($filename, $ranMigrations)) {
                    $pending++;
                }
            }

            if ($pending > 0) {
                $this->warnings[] = "{$pending} pending migration(s) - run: php artisan migrate";
            } else {
                $this->passed[] = 'All migrations up to date';
            }
        } catch (\Exception $e) {
            $this->errors[] = "Migration check failed: {$e->getMessage()}";
        }
    }

    protected function checkEnvironment(): void
    {
        $this->line('🔍 Checking Environment...');

        // Check critical .env variables
        $required = ['APP_KEY', 'DB_CONNECTION'];

        foreach ($required as $var) {
            if (empty(env($var))) {
                $this->errors[] = "Missing required environment variable: {$var}";
            } else {
                $this->passed[] = "{$var} is set";
            }
        }

        // Check APP_ENV
        $env = config('app.env');
        if ($env === 'production') {
            $this->line("   ⚠️  Running in PRODUCTION mode");

            if (config('app.debug') === true) {
                $this->warnings[] = 'APP_DEBUG is enabled in production!';
            }
        } else {
            $this->passed[] = "Environment: {$env}";
        }
    }

    protected function displayResults(): void
    {
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════════');
        $this->info('📊 HEALTH CHECK RESULTS');
        $this->info('═══════════════════════════════════════════════════════════');
        $this->newLine();

        // Display errors
        if (!empty($this->errors)) {
            $this->error('❌ ERRORS (' . count($this->errors) . ')');
            foreach ($this->errors as $error) {
                $this->line("   • {$error}");
            }
            $this->newLine();
        }

        // Display warnings
        if (!empty($this->warnings)) {
            $this->warn('⚠️  WARNINGS (' . count($this->warnings) . ')');
            foreach ($this->warnings as $warning) {
                $this->line("   • {$warning}");
            }
            $this->newLine();
        }

        // Display passed
        if (!empty($this->passed)) {
            $this->info('✅ PASSED (' . count($this->passed) . ')');
            foreach ($this->passed as $pass) {
                $this->line("   • {$pass}");
            }
            $this->newLine();
        }

        // Summary
        $total = count($this->errors) + count($this->warnings) + count($this->passed);
        $this->info("Summary: {$total} checks completed");

        if (empty($this->errors) && empty($this->warnings)) {
            $this->info('🎉 All systems operational!');
        } elseif (empty($this->errors)) {
            $this->warn('⚠️  System has warnings but no critical errors');
        } else {
            $this->error('❌ System has critical errors that need attention');
        }

        if (!$this->option('fix') && !empty($this->warnings)) {
            $this->newLine();
            $this->line('💡 Tip: Run with --fix flag to auto-fix some issues');
            $this->line('   php artisan digibase:health --fix');
        }
    }
}
