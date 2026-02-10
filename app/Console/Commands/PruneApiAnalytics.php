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
