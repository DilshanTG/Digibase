@php
    $cssFile = null;
    try {
        $manifestPath = public_path('build/manifest.json');
        if (file_exists($manifestPath)) {
            $manifest = json_decode(file_get_contents($manifestPath), true);
            $cssFile = $manifest['resources/js/univer-adapter.js']['css'][0] ?? null;
        }
    } catch (\Exception $e) {
        // Fallback or ignore
    }
@endphp

<x-filament-widgets::widget>
    {{-- Force load the CSS if found in manifest --}}
    @if($cssFile)
        <link rel="stylesheet" href="{{ asset('build/' . $cssFile) }}" data-univer-styles>
    @endif

    <div 
        id="univer-widget-outer-container"
        x-data="{ 
            tableName: '{{ $tableName }}',
            apiToken: '{{ $apiToken }}',
            schema: {{ json_encode($schema) }},
            data: {{ json_encode($records) }},
            initialized: false,
            adapterReady: false,
            checkCount: 0,
            injectStarted: false,
            
            checkAndInit() {
                this.checkCount++;
                const isReady = (typeof window.initUniverInstance === 'function');
                this.adapterReady = isReady;
                
                if (isReady && !this.initialized) {
                    console.log('[Univer] 🚀 Engine found! Booting...');
                    try {
                        window.initUniverInstance('univer-container', {
                            tableName: this.tableName,
                            apiToken: this.apiToken,
                            schema: this.schema,
                            records: this.data
                        });
                        this.initialized = true;
                    } catch (e) {
                        console.error('[Univer] 💥 BOOT ERROR:', e);
                    }
                }
            },

            injectScript() {
                if (this.injectStarted) return;
                this.injectStarted = true;
                
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

            document.addEventListener('univer-ready', () => {
                $data.checkAndInit();
            });
        "
    >
        <x-filament::section>
            <div id="univer-container-root" wire:ignore>
                <div id="univer-container" style="height: 750px; width: 100%; position: relative;" class="border rounded-lg shadow-inner bg-gray-50 overflow-hidden">
                    
                    {{-- Loading Overlay --}}
                    <div x-show="!initialized" class="flex flex-col items-center justify-center h-full p-8 text-center bg-white/90 backdrop-blur-md z-50 absolute inset-0">
                        <x-filament::loading-indicator class="h-16 w-16 mx-auto mb-6 text-primary-600" />
                        
                        <h3 class="text-xl font-black text-slate-800 mb-2">Univer Workspace</h3>
                        <p class="text-slate-500 font-medium mb-8">Loading Styles & Engine...</p>
                        
                        <div class="p-6 bg-slate-50 border border-slate-200 rounded-2xl text-left text-sm font-mono inline-block min-w-[320px] shadow-lg">
                            <div class="flex items-center justify-between mb-3 text-xs uppercase tracking-tighter">
                                <span class="text-slate-400">Styles Status</span>
                                <span class="{{ $cssFile ? 'text-green-600' : 'text-amber-500' }} font-bold">{{ $cssFile ? '✅ LINKED' : '⏳ SEARCHING...' }}</span>
                            </div>
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

                        <div class="mt-8">
                            <button @click="location.reload()" class="px-6 py-2.5 bg-slate-800 text-white rounded-xl text-sm font-bold hover:bg-slate-900 transition-all shadow-md">Refresh Assets</button>
                        </div>
                    </div>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-widgets::widget>
