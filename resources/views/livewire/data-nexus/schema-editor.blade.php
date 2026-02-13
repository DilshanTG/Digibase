<div class="space-y-6 animate-in fade-in slide-in-from-top-4 duration-500">
    <div class="bg-white dark:bg-gray-800 rounded-2xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        {{ $this->form }}

        <div class="mt-6 flex justify-end">
            <x-filament::button 
                wire:click="save"
                color="emerald"
                icon="heroicon-o-check-circle"
                class="shadow-lg shadow-emerald-500/20 px-8"
            >
                Apply Schema Changes
            </x-filament::button>
        </div>
    </div>

    <!-- Live Preview of physical sync status -->
    <div class="bg-gray-50/50 dark:bg-gray-900/50 backdrop-blur-sm rounded-2xl border border-dashed border-gray-300 dark:border-gray-700 p-8 text-center">
        <x-filament::icon icon="heroicon-o-arrow-path" class="w-12 h-12 text-gray-400 mx-auto mb-3" />
        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">Studio Sync Engine</h3>
        <p class="text-sm text-gray-500 dark:text-gray-400 max-w-md mx-auto mt-1">Changes made in the definition mode will be applied to the database schema upon clicking "Apply".</p>
    </div>
</div>
