<x-filament-widgets::widget>
    <div 
        x-data="{ 
            tableName: '{{ $tableName }}',
            apiToken: '{{ $apiToken }}',
            schema: {{ json_encode($schema) }},
            data: {{ json_encode($records) }},
            initialized: false,
            
            initUniver() {
                if (this.initialized) return;
                if (!window.UniverAdapter) return;
                
                console.log('Initializing Univer for table:', this.tableName);
                
                try {
                    const adapter = new window.UniverAdapter('univer-container', {
                        tableName: this.tableName,
                        apiToken: this.apiToken
                    });
                    
                    adapter.init().loadData(this.schema, this.data);
                    this.initialized = true;
                    console.log('Univer initialized successfully');
                } catch (e) {
                    console.error('Univer init failed:', e);
                }
            }
        }"
        x-init="
            $nextTick(() => {
                if (window.UniverAdapter) {
                    initUniver();
                } else {
                    // 1. Listen for the custom event
                    document.addEventListener('univer-ready', () => initUniver());
                    
                    // 2. Fallback polling
                    let checkCount = 0;
                    const interval = setInterval(() => {
                        checkCount++;
                        if (window.UniverAdapter) {
                            initUniver();
                            clearInterval(interval);
                        }
                        if (checkCount > 40) clearInterval(interval); // Stop after 20s
                    }, 500);
                }
            })
        "
    >
        <x-filament::section>
            <div id="univer-container" style="height: 700px; width: 100%; position: relative;" class="border rounded-lg shadow-inner bg-gray-50 overflow-hidden">
                <div x-show="!initialized" class="flex items-center justify-center h-full">
                    <div class="text-center">
                        <x-filament::loading-indicator class="h-10 w-10 mx-auto mb-4 text-primary-600" />
                        <p class="text-gray-500 font-medium">Booting Univer Intelligence...</p>
                        <p class="text-xs text-gray-400 mt-2">Connecting to spreadsheet engine...</p>
                    </div>
                </div>
            </div>
        </x-filament::section>

        {{-- Loading scripts directly in the component so Livewire partial refresh executes them --}}
        @vite('resources/js/univer-adapter.js')
    </div>
</x-filament-widgets::widget>
