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
