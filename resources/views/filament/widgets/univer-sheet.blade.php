@php
    $cssFile = null;
    try {
        $manifestPath = public_path('build/manifest.json');
        if (file_exists($manifestPath)) {
            $manifest = json_decode(file_get_contents($manifestPath), true);
            $cssFile = $manifest['resources/js/univer-adapter.js']['css'][0] ?? null;
        }
    } catch (\Exception $e) { }
@endphp

<x-filament-widgets::widget>
    @if($hasData)
        {{-- Force load the CSS --}}
        @if($cssFile)
            <link rel="stylesheet" href="{{ asset('build/' . $cssFile) }}" data-univer-styles>
        @endif

        <div 
            id="univer-widget-outer-container-{{ $tableId }}"
            x-data="{ 
                tableName: '{{ $tableName }}',
                saveUrl: '{{ $saveUrl }}',
                csrfToken: '{{ $csrfToken }}',
                apiToken: '{{ $apiToken }}',
                schema: {{ json_encode($schema) }},
                tableData: {{ json_encode($tableData) }},
                initialized: false,
                adapterReady: false,
                checkCount: 0,
                
                checkAndInit() {
                    this.checkCount++;
                    const isReady = (typeof window.initUniverInstance === 'function');
                    this.adapterReady = isReady;
                    
                    if (isReady && !this.initialized) {
                        console.log('[Univer] 🚀 Engine found! Booting...');
                        const containerId = 'univer-container-{{ $tableId }}';
                        
                        try {
                            window.initUniverInstance(
                                containerId,
                                this.tableData,
                                this.schema,
                                this.saveUrl,
                                this.csrfToken,
                                this.apiToken
                            );
                            this.initialized = true;
                        } catch (e) {
                            console.error('[Univer] 💥 BOOT ERROR:', e);
                        }
                    }
                },

                injectScript() {
                    const scriptId = 'univer-engine-loader';
                    if (document.getElementById(scriptId)) return;

                    console.log('[Univer] 📥 Injecting engine script...');
                    const s = document.createElement('script');
                    s.id = scriptId;
                    s.type = 'module';
                    s.src = '{{ Vite::asset("resources/js/univer-adapter.js") }}';
                    document.head.appendChild(s);
                }
            }"
            x-init="
                injectScript();
                const timer = setInterval(() => {
                    if ($data.initialized || $data.checkCount > 150) {
                        clearInterval(timer);
                        return;
                    }
                    $data.checkAndInit();
                }, 1000);

                document.addEventListener('univer-ready', () => $data.checkAndInit());
            "
        >
            <x-filament::section>
                <div id="univer-container-root-{{ $tableId }}" wire:ignore>
                    <div id="univer-container-{{ $tableId }}" style="height: 80vh; width: 100%; position: relative;" class="border rounded-lg shadow-inner bg-gray-50 overflow-hidden">
                        
                        {{-- Loading Overlay --}}
                        <div x-show="!initialized" class="flex flex-col items-center justify-center h-full p-8 text-center bg-white/90 backdrop-blur-md z-50 absolute inset-0 rounded-lg">
                            <x-filament::loading-indicator class="h-16 w-16 mx-auto mb-6 text-primary-600" />
                            
                            <h3 class="text-xl font-black text-slate-800 mb-2">Univer Intelligence</h3>
                            <p class="text-slate-500 font-medium mb-8">Assembling spreadsheet engine modules...</p>
                            
                            <div class="p-6 bg-slate-50 border border-slate-200 rounded-2xl text-left text-sm font-mono inline-block min-w-[320px] shadow-lg">
                                <div class="flex items-center justify-between mb-3 text-xs uppercase tracking-tighter">
                                    <span class="text-slate-400">Engine Source</span>
                                    <span :class="adapterReady ? 'text-green-600 font-bold' : 'text-amber-500 font-bold'" x-text="adapterReady ? '✅ ATTACHED' : '⏳ LOADING...'"></span>
                                </div>
                                <div class="flex items-center justify-between mb-5 text-xs uppercase tracking-tighter">
                                    <span class="text-slate-400">Boot Duration</span>
                                    <span class="text-primary-600 font-bold"><span x-text="checkCount"></span>s</span>
                                </div>
                                
                                <div class="h-1.5 w-full bg-slate-200 rounded-full overflow-hidden mb-4">
                                    <div class="h-full bg-primary-600 transition-all duration-1000" :style="'width: ' + Math.min((checkCount/30)*100, 100) + '%'"></div>
                                </div>
                            </div>

                            <div class="mt-8 flex gap-4">
                                <button @click="location.reload()" class="px-6 py-2.5 bg-slate-800 text-white rounded-xl text-sm font-bold hover:bg-slate-900 transition-all shadow-md">Full Restart</button>
                            </div>
                        </div>
                    </div>
                </div>
            </x-filament::section>
        </div>
    @else
        <div class="p-10 text-center text-gray-500">
            <p>Please select a table to begin using Spreadsheet View.</p>
        </div>
    @endif
</x-filament-widgets::widget>
