<x-filament-panels::page>
    <div class="space-y-4">
        <div class="flex items-center gap-3 p-4 rounded-xl bg-primary-50 dark:bg-primary-950/30 border border-primary-200 dark:border-primary-800">
            <x-heroicon-o-information-circle class="w-5 h-5 text-primary-500 shrink-0" />
            <p class="text-sm text-primary-700 dark:text-primary-300">
                Monitors your scheduled tasks (cron jobs). Tasks are synced automatically when you run
                <code class="px-1.5 py-0.5 rounded bg-primary-100 dark:bg-primary-900 font-mono text-xs">php artisan schedule-monitor:sync</code>
            </p>
        </div>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
