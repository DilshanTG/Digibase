<div class="space-y-4">
    <div class="flex items-center justify-between mb-4">
        <p class="text-sm text-gray-600 dark:text-gray-400">
            Complete schema definition for this table
        </p>
        <button 
            onclick="navigator.clipboard.writeText(document.getElementById('json-content').textContent); 
                     const btn = this; 
                     const original = btn.innerHTML; 
                     btn.innerHTML = '✓ Copied!'; 
                     setTimeout(() => btn.innerHTML = original, 2000);"
            class="px-3 py-1 text-sm bg-gray-100 hover:bg-gray-200 dark:bg-gray-700 dark:hover:bg-gray-600 rounded-lg transition">
            📋 Copy JSON
        </button>
    </div>
    
    <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto max-h-[600px] overflow-y-auto">
        <pre id="json-content" class="text-sm text-gray-100"><code>{{ $json }}</code></pre>
    </div>
    
    <div class="bg-blue-50 dark:bg-blue-900/20 border border-blue-200 dark:border-blue-800 rounded-lg p-4">
        <div class="flex items-start gap-3">
            <svg class="w-5 h-5 text-blue-600 dark:text-blue-400 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div class="flex-1">
                <h4 class="font-semibold text-blue-900 dark:text-blue-100 mb-1">Schema Information</h4>
                <p class="text-sm text-blue-800 dark:text-blue-200">
                    This JSON represents the complete structure of your table including fields, validation rules, and security policies. 
                    You can use this for documentation, backup, or importing into other systems.
                </p>
            </div>
        </div>
    </div>
</div>
