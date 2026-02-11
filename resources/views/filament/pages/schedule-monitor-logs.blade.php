<div class="space-y-2 p-4">
    @forelse($logs as $log)
        <div class="flex items-start gap-3 p-3 rounded-lg {{ $log->type === 'failed' ? 'bg-danger-50 dark:bg-danger-950/30' : 'bg-gray-50 dark:bg-gray-800' }}">
            @if($log->type === 'starting')
                <x-heroicon-m-play class="w-4 h-4 text-primary-500 mt-0.5 shrink-0" />
            @elseif($log->type === 'finished')
                <x-heroicon-m-check class="w-4 h-4 text-success-500 mt-0.5 shrink-0" />
            @elseif($log->type === 'failed')
                <x-heroicon-m-x-mark class="w-4 h-4 text-danger-500 mt-0.5 shrink-0" />
            @elseif($log->type === 'skipped')
                <x-heroicon-m-forward class="w-4 h-4 text-warning-500 mt-0.5 shrink-0" />
            @else
                <x-heroicon-m-minus class="w-4 h-4 text-gray-400 mt-0.5 shrink-0" />
            @endif
            <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-2">
                    <span class="text-sm font-medium capitalize {{ $log->type === 'failed' ? 'text-danger-700 dark:text-danger-300' : 'text-gray-700 dark:text-gray-300' }}">
                        {{ $log->type }}
                    </span>
                    <span class="text-xs text-gray-500 dark:text-gray-400 shrink-0">
                        {{ $log->created_at->diffForHumans() }}
                    </span>
                </div>
                @if($log->meta)
                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1 truncate">
                        {{ is_array($log->meta) ? json_encode($log->meta) : $log->meta }}
                    </p>
                @endif
            </div>
        </div>
    @empty
        <div class="text-center py-8">
            <x-heroicon-o-inbox class="w-8 h-8 text-gray-400 mx-auto mb-2" />
            <p class="text-sm text-gray-500 dark:text-gray-400">No logs recorded yet.</p>
        </div>
    @endforelse
</div>
