<x-filament-panels::page>
    {{-- Form with auto-generation on model selection --}}
    <form wire:submit.prevent>
        {{ $this->form }}
    </form>

    {{-- Clipboard JS --}}
    @script
    <script>
        $wire.on('copy-to-clipboard', ({ code }) => {
            // Remove markdown code fence if present
            const cleanCode = code.replace(/^```[a-z]*\n/, '').replace(/\n```$/, '');

            navigator.clipboard.writeText(cleanCode).then(() => {
                // Success - notification already shown by Filament
            }).catch(() => {
                // Fallback for older browsers
                const textarea = document.createElement('textarea');
                textarea.value = cleanCode;
                textarea.style.position = 'fixed';
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand('copy');
                document.body.removeChild(textarea);
            });
        });
    </script>
    @endscript
</x-filament-panels::page>
