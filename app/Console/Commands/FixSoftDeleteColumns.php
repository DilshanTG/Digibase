<?php

namespace App\Console\Commands;

use App\Models\DynamicModel;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FixSoftDeleteColumns extends Command
{
    protected $signature = 'digibase:fix-soft-deletes';
    protected $description = 'Add deleted_at column to all tables with soft deletes enabled';

    public function handle()
    {
        $this->info('🔍 Scanning for tables with missing soft delete columns...');

        $models = DynamicModel::where('has_soft_deletes', true)->get();

        if ($models->isEmpty()) {
            $this->info('✅ No models with soft deletes found.');
            return 0;
        }

        $fixed = 0;
        $skipped = 0;
        $errors = 0;

        foreach ($models as $model) {
            $tableName = $model->table_name;

            // Check if table exists
            if (!Schema::hasTable($tableName)) {
                $this->warn("⚠️  Table '{$tableName}' does not exist. Skipping...");
                $skipped++;
                continue;
            }

            // Check if deleted_at column already exists
            if (Schema::hasColumn($tableName, 'deleted_at')) {
                $this->line("✓ Table '{$tableName}' already has deleted_at column.");
                $skipped++;
                continue;
            }

            // Add deleted_at column
            try {
                Schema::table($tableName, function ($table) {
                    $table->softDeletes();
                });
                $this->info("✅ Added deleted_at column to '{$tableName}'");
                $fixed++;
            } catch (\Exception $e) {
                $this->error("❌ Failed to add deleted_at to '{$tableName}': {$e->getMessage()}");
                $errors++;
            }
        }

        $this->newLine();
        $this->info("📊 Summary:");
        $this->table(
            ['Status', 'Count'],
            [
                ['✅ Fixed', $fixed],
                ['✓ Already OK', $skipped],
                ['❌ Errors', $errors],
            ]
        );

        return $errors > 0 ? 1 : 0;
    }
}
