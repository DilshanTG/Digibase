<x-filament-widgets::widget>
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
                
                // Check if the global initialization function exists
                const isReady = (typeof window.initUniverInstance === 'function');
                this.adapterReady = isReady;
                
                if (isReady && !this.initialized) {
                    console.log('[Univer] 🚀 Engine discovered! Booting for ' + this.tableName);
                    try {
                        window.initUniverInstance('univer-container', {
                            tableName: this.tableName,
                            apiToken: this.apiToken,
                            schema: this.schema,
                            records: this.data
                        });
                        this.initialized = true;
                    } catch (e) {
                        console.error('[Univer] 💥 CRITICAL BOOT ERROR:', e);
                    }
                }
            },

            injectScript() {
                if (this.injectStarted) return;
                this.injectStarted = true;
                
                const scriptId = 'univer-engine-loader';
                if (document.getElementById(scriptId)) {
                    console.log('[Univer] Script tag already in head, waiting for execution...');
                    return;
                }

                console.log('[Univer] 📥 Injecting engine script into head...');
                const s = document.createElement('script');
                s.id = scriptId;
                s.type = 'module';
                s.src = '{{ Vite::asset("resources/js/univer-adapter.js") }}';
                s.onload = () => console.log('[Univer] 📜 Script tag HTTP Load Success');
                s.onerror = (e) => console.error('[Univer] ❌ Script tag HTTP Load FAILED', e);
                document.head.appendChild(s);
            }
        }"
        x-init="
            console.log('[Univer] Widget x-init started');
            
            // 1. Start injection immediately
            injectScript();

            // 2. Poll for the engine to finish parsing that huge 9.7MB blob
            const timer = setInterval(() => {
                if ($data.initialized || $data.checkCount > 150) {
                    if ($data.checkCount > 150) console.warn('[Univer] 🏁 Boot timeout reached.');
                    clearInterval(timer);
                    return;
                }
                $data.checkAndInit();
            }, 1000);

            // 3. Keep an ear out for the signal
            document.addEventListener('univer-ready', () => {
                console.log('[Univer] 🔔 Global readiness signal heard');
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
                        <p class="text-slate-500 font-medium mb-8">Authorizing Spreadsheet Modules...</p>
                        
                        <div class="p-6 bg-slate-50 border border-slate-200 rounded-2xl text-left text-sm font-mono inline-block min-w-[320px] shadow-lg">
                            <div class="flex items-center justify-between mb-3 text-xs uppercase tracking-tighter">
                                <span class="text-slate-400">Engine Source</span>
                                <span :class="adapterReady ? 'text-green-600 font-bold' : 'text-amber-500 font-bold'" x-text="adapterReady ? '✅ ATTACHED' : '⏳ DOWNLOADING...'"></span>
                            </div>
                            <div class="flex items-center justify-between mb-5 text-xs uppercase tracking-tighter">
                                <span class="text-slate-400">Boot Duration</span>
                                <span class="text-primary-600 font-bold"><span x-text="checkCount"></span>s</span>
                            </div>
                            
                            <div class="h-1.5 w-full bg-slate-200 rounded-full overflow-hidden mb-4">
                                <div class="h-full bg-primary-600 transition-all duration-1000" :style="'width: ' + Math.min((checkCount/30)*100, 100) + '%'"></div>
                            </div>
                            
                            <div class="pt-4 border-t border-slate-200 border-dashed">
                                <p class="text-[10px] text-slate-400 leading-relaxed uppercase">
                                    Payload Size: ~9.76 MB<br>
                                    Instance Target: [{{ $tableName }}]<br>
                                    Security: API Token Verified
                                </p>
                            </div>
                        </div>

                        <div class="mt-8 flex gap-4">
                            <button @click="location.reload()" class="px-6 py-2.5 bg-slate-800 text-white rounded-xl text-sm font-bold hover:bg-slate-900 transition-all shadow-md">Full Restart</button>
                            <button x-show="!adapterReady && checkCount > 10" @click="injectScript(); checkAndInit()" class="px-6 py-2.5 bg-primary-600 text-white rounded-xl text-sm font-bold hover:bg-primary-700 shadow-xl shadow-primary-200 transition-all underline decoration-2 underline-offset-4">Retry Engine Load</button>
                        </div>
                    </div>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-widgets::widget>
