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
