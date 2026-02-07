<x-filament-panels::page>
    {{-- Input Form --}}
    <form wire:submit="generate">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button type="submit" icon="heroicon-o-bolt">
                Generate Code
            </x-filament::button>
        </div>
    </form>

    {{-- Output Section --}}
    @if(count($this->generatedFiles) > 0)
        <div class="mt-6" x-data="{ activeTab: @entangle('activeTab') }">
            {{-- Tab Headers --}}
            <div class="flex gap-1 border-b border-gray-200 dark:border-gray-700">
                @foreach($this->generatedFiles as $index => $file)
                    <button
                        type="button"
                        wire:click="setTab({{ $index }})"
                        class="px-4 py-2.5 text-sm font-medium rounded-t-lg transition-colors
                            {{ $activeTab === $index
                                ? 'bg-primary-50 dark:bg-primary-500/10 text-primary-600 dark:text-primary-400 border-b-2 border-primary-600 dark:border-primary-400'
                                : 'text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-800' }}"
                    >
                        <span class="flex items-center gap-2">
                            <x-heroicon-m-document-text class="w-4 h-4" />
                            {{ $file['name'] }}
                        </span>
                    </button>
                @endforeach
            </div>

            {{-- Tab Content --}}
            @foreach($this->generatedFiles as $index => $file)
                <div x-show="activeTab === {{ $index }}" x-cloak>
                    {{-- File Info Bar --}}
                    <div class="flex items-center justify-between px-4 py-2 bg-gray-100 dark:bg-gray-800 border-x border-gray-200 dark:border-gray-700">
                        <span class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $file['description'] ?? $file['name'] }}
                        </span>
                        <x-filament::button
                            size="xs"
                            color="gray"
                            icon="heroicon-m-clipboard-document"
                            wire:click="copyCode({{ $index }})"
                        >
                            Copy
                        </x-filament::button>
                    </div>

                    {{-- Code Block --}}
                    <div class="relative border border-t-0 border-gray-200 dark:border-gray-700 rounded-b-lg overflow-hidden">
                        <pre class="p-4 overflow-x-auto bg-gray-950 text-gray-100 text-sm leading-relaxed" style="max-height: 600px;"><code>{{ $file['code'] }}</code></pre>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    {{-- Clipboard JS --}}
    @script
    <script>
        $wire.on('copy-to-clipboard', ({ code }) => {
            navigator.clipboard.writeText(code).catch(() => {
                // Fallback for older browsers
                const textarea = document.createElement('textarea');
                textarea.value = code;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
            });
        });
    </script>
    @endscript
</x-filament-panels::page>
