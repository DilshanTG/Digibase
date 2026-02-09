<x-filament-panels::page>
    <div class="space-y-6">
        <!-- Model Selector -->
        <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
            {{ $this->schema }}
        </div>

        @if($documentation)
            <!-- Overview -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <div class="flex items-start justify-between">
                    <div class="flex-1">
                        <h2 class="text-2xl font-bold mb-2">{{ $documentation['model']['display_name'] }}</h2>
                        @if($documentation['model']['description'])
                            <p class="text-gray-600 dark:text-gray-400 mb-4">{{ $documentation['model']['description'] }}</p>
                        @endif
                        <div class="flex items-center gap-4 text-sm">
                            <span class="px-3 py-1 bg-blue-100 dark:bg-blue-900 text-blue-800 dark:text-blue-200 rounded-full">
                                Table: {{ $documentation['model']['table_name'] }}
                            </span>
                        </div>
                    </div>
                    <div>
                        <button wire:click="downloadOpenApiSpec" 
                                class="px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-lg flex items-center gap-2">
                            <x-heroicon-o-arrow-down-tray class="w-5 h-5" />
                            Download OpenAPI Spec
                        </button>
                    </div>
                </div>
            </div>

            <!-- Authentication -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                    <x-heroicon-o-shield-check class="w-6 h-6 text-green-500" />
                    Authentication
                </h3>
                <p class="text-gray-600 dark:text-gray-400 mb-4">{{ $documentation['authentication']['description'] }}</p>
                
                <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4 mb-4">
                    <code class="text-sm">{{ $documentation['authentication']['example'] }}</code>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach($documentation['authentication']['key_types'] as $prefix => $desc)
                        <div class="border dark:border-gray-700 rounded-lg p-4">
                            <code class="text-sm font-mono text-blue-600 dark:text-blue-400">{{ $prefix }}</code>
                            <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">{{ $desc }}</p>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Endpoints -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                    <x-heroicon-o-globe-alt class="w-6 h-6 text-purple-500" />
                    API Endpoints
                </h3>

                <div class="space-y-6">
                    @foreach($documentation['endpoints'] as $endpoint)
                        <div class="border dark:border-gray-700 rounded-lg p-4">
                            <div class="flex items-center gap-3 mb-3">
                                <span class="px-3 py-1 rounded font-mono text-sm font-bold
                                    {{ $endpoint['method'] === 'GET' ? 'bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200' : '' }}
                                    {{ $endpoint['method'] === 'POST' ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : '' }}
                                    {{ $endpoint['method'] === 'PUT' ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-900 dark:text-yellow-200' : '' }}
                                    {{ $endpoint['method'] === 'DELETE' ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : '' }}
                                ">
                                    {{ $endpoint['method'] }}
                                </span>
                                <code class="text-sm font-mono">{{ $endpoint['path'] }}</code>
                            </div>
                            <p class="text-gray-600 dark:text-gray-400 mb-3">{{ $endpoint['description'] }}</p>

                            @if(!empty($endpoint['parameters']))
                                <div class="mb-3">
                                    <h4 class="font-semibold text-sm mb-2">Parameters:</h4>
                                    <div class="space-y-2">
                                        @foreach($endpoint['parameters'] as $param)
                                            <div class="text-sm">
                                                <code class="text-blue-600 dark:text-blue-400">{{ $param['name'] }}</code>
                                                <span class="text-gray-500">({{ $param['type'] }})</span>
                                                @if($param['required'])
                                                    <span class="text-red-500">*</span>
                                                @endif
                                                - {{ $param['description'] }}
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            @if(!empty($endpoint['body']))
                                <div>
                                    <h4 class="font-semibold text-sm mb-2">Request Body:</h4>
                                    <div class="bg-gray-50 dark:bg-gray-900 rounded p-3 space-y-2">
                                        @foreach($endpoint['body'] as $field)
                                            <div class="text-sm">
                                                <code class="text-blue-600 dark:text-blue-400">{{ $field['name'] }}</code>
                                                <span class="text-gray-500">({{ $field['type'] }})</span>
                                                @if($field['required'])
                                                    <span class="text-red-500">*</span>
                                                @endif
                                                - {{ $field['description'] }}
                                                @if(!empty($field['validation']))
                                                    <div class="ml-4 text-xs text-gray-500 mt-1">
                                                        Validation: {{ implode(', ', $field['validation']) }}
                                                    </div>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Try It Out (Interactive Testing) -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                    <x-heroicon-o-play class="w-6 h-6 text-green-500" />
                    Try It Out
                </h3>
                
                <div x-data="{ activeTestOp: 'list' }" class="space-y-4">
                    <!-- Operation Selector -->
                    <div class="flex gap-2 flex-wrap">
                        <button @click="activeTestOp = 'list'" 
                                :class="activeTestOp === 'list' ? 'bg-blue-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                                class="px-3 py-1 rounded text-sm font-semibold">
                            GET List
                        </button>
                        <button @click="activeTestOp = 'create'" 
                                :class="activeTestOp === 'create' ? 'bg-green-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                                class="px-3 py-1 rounded text-sm font-semibold">
                            POST Create
                        </button>
                    </div>

                    <!-- API Key Input -->
                    <div>
                        <label class="block text-sm font-semibold mb-2">API Key (sk_ for write access)</label>
                        <input type="text" 
                               wire:model="testApiKey" 
                               placeholder="sk_your_secret_key_here"
                               class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg bg-white dark:bg-gray-900 text-gray-900 dark:text-gray-100">
                    </div>

                    <!-- Request Body (for POST/PUT) -->
                    <div x-show="activeTestOp === 'create'">
                        <label class="block text-sm font-semibold mb-2">Request Body (JSON)</label>
                        <textarea wire:model="testRequestBody" 
                                  rows="8"
                                  class="w-full px-4 py-2 border dark:border-gray-700 rounded-lg bg-gray-900 text-gray-100 font-mono text-sm"></textarea>
                    </div>

                    <!-- Test Button -->
                    <div class="flex gap-2">
                        <button x-show="activeTestOp === 'list'"
                                wire:click="testEndpoint('GET', '/api/data/{{ $documentation['model']['table_name'] }}')"
                                wire:loading.attr="disabled"
                                class="px-6 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-lg font-semibold disabled:opacity-50">
                            <span wire:loading.remove wire:target="testEndpoint">Send Request</span>
                            <span wire:loading wire:target="testEndpoint">Loading...</span>
                        </button>
                        <button x-show="activeTestOp === 'create'"
                                wire:click="testEndpoint('POST', '/api/data/{{ $documentation['model']['table_name'] }}')"
                                wire:loading.attr="disabled"
                                class="px-6 py-2 bg-green-600 hover:bg-green-700 text-white rounded-lg font-semibold disabled:opacity-50">
                            <span wire:loading.remove wire:target="testEndpoint">Send Request</span>
                            <span wire:loading wire:target="testEndpoint">Loading...</span>
                        </button>
                    </div>

                    <!-- Response Display -->
                    @if($testResponse !== null)
                        <div class="border-t dark:border-gray-700 pt-4">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="font-semibold">Response:</span>
                                <span class="px-2 py-1 rounded text-sm font-mono
                                    {{ $testStatusCode >= 200 && $testStatusCode < 300 ? 'bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200' : '' }}
                                    {{ $testStatusCode >= 400 ? 'bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200' : '' }}
                                ">
                                    {{ $testStatusCode }}
                                </span>
                            </div>
                            <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                                <pre class="text-sm text-gray-100"><code>{{ json_encode($testResponse, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                            </div>
                        </div>
                    @endif
                </div>
            </div>

            <!-- Response Examples -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                    <x-heroicon-o-check-circle class="w-6 h-6 text-emerald-500" />
                    Response Examples
                </h3>

                <div class="space-y-6">
                    <!-- Success Responses -->
                    <div>
                        <h4 class="font-semibold text-lg mb-3 text-green-600 dark:text-green-400">Success Responses</h4>
                        <div class="space-y-4">
                            @foreach($documentation['responses']['success'] as $code => $response)
                                <div class="border dark:border-gray-700 rounded-lg p-4">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="px-3 py-1 bg-green-100 text-green-800 dark:bg-green-900 dark:text-green-200 rounded font-mono text-sm font-bold">
                                            {{ $code }}
                                        </span>
                                        <span class="text-sm text-gray-600 dark:text-gray-400">{{ $response['description'] }}</span>
                                    </div>
                                    <div class="bg-gray-900 rounded p-3 overflow-x-auto">
                                        <pre class="text-sm text-gray-100"><code>{{ json_encode($response['example'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <!-- Error Responses -->
                    <div>
                        <h4 class="font-semibold text-lg mb-3 text-red-600 dark:text-red-400">Error Responses</h4>
                        <div class="space-y-4">
                            @foreach($documentation['responses']['error'] as $code => $response)
                                <div class="border dark:border-gray-700 rounded-lg p-4">
                                    <div class="flex items-center gap-2 mb-2">
                                        <span class="px-3 py-1 bg-red-100 text-red-800 dark:bg-red-900 dark:text-red-200 rounded font-mono text-sm font-bold">
                                            {{ $code }}
                                        </span>
                                        <span class="text-sm text-gray-600 dark:text-gray-400">{{ $response['description'] }}</span>
                                    </div>
                                    <div class="bg-gray-900 rounded p-3 overflow-x-auto">
                                        <pre class="text-sm text-gray-100"><code>{{ json_encode($response['example'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <!-- Webhook Documentation -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                    <x-heroicon-o-bolt class="w-6 h-6 text-yellow-500" />
                    Webhooks
                </h3>

                <p class="text-gray-600 dark:text-gray-400 mb-6">{{ $documentation['webhooks']['description'] }}</p>

                <!-- Webhook Events -->
                <div class="space-y-4 mb-6">
                    <h4 class="font-semibold text-lg">Events</h4>
                    @foreach($documentation['webhooks']['events'] as $event => $details)
                        <div class="border dark:border-gray-700 rounded-lg p-4">
                            <div class="flex items-center gap-2 mb-2">
                                <span class="px-3 py-1 bg-purple-100 text-purple-800 dark:bg-purple-900 dark:text-purple-200 rounded font-mono text-sm font-bold">
                                    {{ $event }}
                                </span>
                                <span class="text-sm text-gray-600 dark:text-gray-400">{{ $details['description'] }}</span>
                            </div>
                            <div class="bg-gray-900 rounded p-3 overflow-x-auto">
                                <pre class="text-sm text-gray-100"><code>{{ json_encode($details['payload'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Webhook Headers -->
                <div class="mb-6">
                    <h4 class="font-semibold text-lg mb-3">Headers</h4>
                    <div class="bg-gray-50 dark:bg-gray-900 rounded-lg p-4">
                        @foreach($documentation['webhooks']['headers'] as $header => $value)
                            <div class="flex items-start gap-2 mb-2">
                                <code class="text-sm text-blue-600 dark:text-blue-400 font-semibold">{{ $header }}:</code>
                                <code class="text-sm text-gray-700 dark:text-gray-300">{{ $value }}</code>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Signature Verification -->
                <div>
                    <h4 class="font-semibold text-lg mb-3">Signature Verification</h4>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">{{ $documentation['webhooks']['signature_verification']['description'] }}</p>
                    
                    <div x-data="{ activeLang: 'php' }">
                        <div class="flex gap-2 mb-3">
                            <button @click="activeLang = 'php'" 
                                    :class="activeLang === 'php' ? 'bg-blue-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                                    class="px-3 py-1 rounded text-sm">PHP</button>
                            <button @click="activeLang = 'javascript'" 
                                    :class="activeLang === 'javascript' ? 'bg-blue-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                                    class="px-3 py-1 rounded text-sm">JavaScript</button>
                            <button @click="activeLang = 'python'" 
                                    :class="activeLang === 'python' ? 'bg-blue-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                                    class="px-3 py-1 rounded text-sm">Python</button>
                        </div>

                        <div x-show="activeLang === 'php'" class="bg-gray-900 rounded-lg p-4">
                            <pre class="text-sm text-gray-100"><code>{{ $documentation['webhooks']['signature_verification']['example_code']['php'] }}</code></pre>
                        </div>
                        <div x-show="activeLang === 'javascript'" class="bg-gray-900 rounded-lg p-4">
                            <pre class="text-sm text-gray-100"><code>{{ $documentation['webhooks']['signature_verification']['example_code']['javascript'] }}</code></pre>
                        </div>
                        <div x-show="activeLang === 'python'" class="bg-gray-900 rounded-lg p-4">
                            <pre class="text-sm text-gray-100"><code>{{ $documentation['webhooks']['signature_verification']['example_code']['python'] }}</code></pre>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Code Examples -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                    <x-heroicon-o-code-bracket class="w-6 h-6 text-orange-500" />
                    Code Examples
                </h3>

                <div x-data="{ activeTab: 'curl', activeOperation: 'list' }">
                    <!-- Language Tabs -->
                    <div class="flex gap-2 mb-4 border-b dark:border-gray-700">
                        <button @click="activeTab = 'curl'" 
                                :class="activeTab === 'curl' ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-400'"
                                class="px-4 py-2 font-semibold">
                            cURL
                        </button>
                        <button @click="activeTab = 'javascript'" 
                                :class="activeTab === 'javascript' ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-400'"
                                class="px-4 py-2 font-semibold">
                            JavaScript
                        </button>
                        <button @click="activeTab = 'python'" 
                                :class="activeTab === 'python' ? 'border-b-2 border-blue-500 text-blue-600 dark:text-blue-400' : 'text-gray-600 dark:text-gray-400'"
                                class="px-4 py-2 font-semibold">
                            Python
                        </button>
                    </div>

                    <!-- Operation Tabs -->
                    <div class="flex gap-2 mb-4 flex-wrap">
                        <button @click="activeOperation = 'list'" 
                                :class="activeOperation === 'list' ? 'bg-blue-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                                class="px-3 py-1 rounded text-sm">
                            List All
                        </button>
                        <button @click="activeOperation = 'get'" 
                                :class="activeOperation === 'get' ? 'bg-blue-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                                class="px-3 py-1 rounded text-sm">
                            Get One
                        </button>
                        <button @click="activeOperation = 'create'" 
                                :class="activeOperation === 'create' ? 'bg-green-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                                class="px-3 py-1 rounded text-sm">
                            Create
                        </button>
                        <button @click="activeOperation = 'update'" 
                                :class="activeOperation === 'update' ? 'bg-yellow-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                                class="px-3 py-1 rounded text-sm">
                            Update
                        </button>
                        <button @click="activeOperation = 'delete'" 
                                :class="activeOperation === 'delete' ? 'bg-red-500 text-white' : 'bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300'"
                                class="px-3 py-1 rounded text-sm">
                            Delete
                        </button>
                    </div>

                    <!-- Code Display -->
                    @foreach(['curl', 'javascript', 'python'] as $lang)
                        <div x-show="activeTab === '{{ $lang }}'" class="space-y-4">
                            @foreach(['list', 'get', 'create', 'update', 'delete'] as $op)
                                <div x-show="activeOperation === '{{ $op }}'">
                                    <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                                        <pre class="text-sm text-gray-100"><code>{{ $documentation['examples'][$lang][$op] }}</code></pre>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- JSON Schema -->
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-6">
                <h3 class="text-xl font-bold mb-4 flex items-center gap-2">
                    <x-heroicon-o-document-text class="w-6 h-6 text-indigo-500" />
                    JSON Schema
                </h3>
                <div class="bg-gray-900 rounded-lg p-4 overflow-x-auto">
                    <pre class="text-sm text-gray-100"><code>{{ json_encode($documentation['schema'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</code></pre>
                </div>
            </div>
        @else
            <div class="bg-white dark:bg-gray-800 rounded-lg shadow p-12 text-center">
                <x-heroicon-o-book-open class="w-16 h-16 text-gray-400 mx-auto mb-4" />
                <h3 class="text-xl font-semibold text-gray-600 dark:text-gray-400 mb-2">
                    Select a table to view API documentation
                </h3>
                <p class="text-gray-500 dark:text-gray-500">
                    Choose a table from the dropdown above to see its API endpoints, examples, and schema.
                </p>
            </div>
        @endif
    </div>
</x-filament-panels::page>
