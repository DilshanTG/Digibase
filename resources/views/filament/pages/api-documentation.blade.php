<x-filament-panels::page>
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="col-span-1 space-y-2">
            <div class="p-4 bg-white rounded-lg shadow dark:bg-gray-900">
                <h3 class="font-bold mb-2 text-primary-500">Getting Started</h3>
                <ul class="space-y-1 text-sm">
                    <li><a href="#intro" class="hover:text-primary-500">Introduction</a></li>
                    <li><a href="#auth" class="hover:text-primary-500">Authentication</a></li>
                    <li><a href="#sdk" class="hover:text-primary-500">JS SDK Setup</a></li>
                </ul>

                <h3 class="font-bold mt-4 mb-2 text-primary-500">Resources</h3>
                <ul class="space-y-1 text-sm">
                    @foreach($models as $model)
                        <li>
                            <a href="#model-{{ $model->id }}" class="hover:text-primary-500">
                                {{ $model->display_name }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>

        <div class="col-span-3 space-y-6">
            <x-filament::section id="intro">
                <x-slot name="heading">Introduction</x-slot>
                <p>Welcome to the Digibase API. This API allows you to access all your dynamic data securely.</p>
                <p class="mt-2">Base URL: <code class="bg-gray-100 dark:bg-gray-800 px-1 py-0.5 rounded">{{ $baseUrl }}</code></p>
            </x-filament::section>

            <x-filament::section id="auth">
                <x-slot name="heading">Authentication</x-slot>
                <p class="mb-3">All API requests require authentication using an API key in the header:</p>
                <pre class="bg-gray-900 text-gray-100 p-3 rounded text-sm overflow-x-auto">X-API-Key: your_api_key_here</pre>
                <p class="mt-3 text-sm text-gray-500">Generate API keys in the "API Keys" section of the admin panel.</p>
            </x-filament::section>

            <x-filament::section id="sdk">
                <x-slot name="heading">JS SDK Installation</x-slot>
                <pre class="bg-gray-900 text-gray-100 p-3 rounded text-sm overflow-x-auto">npm install digibase-js</pre>
                <p class="mt-2 text-sm text-gray-500">Or copy the `sdk/` folder to your project.</p>

                <div class="mt-4">
                    <h4 class="text-sm font-bold mb-2">Quick Start</h4>
                    <pre class="bg-gray-900 text-gray-100 p-3 rounded text-sm overflow-x-auto">import { createClient } from '@digibase/sdk';

const digibase = createClient(
  'https://your-api-domain.com',
  'pk_your_api_key_here'
);

// Fetch data
const { data } = await digibase.from('products').get();</pre>
                </div>
            </x-filament::section>

            @foreach($models as $model)
                <x-filament::section id="model-{{ $model->id }}">
                    <x-slot name="heading">{{ $model->display_name }} API</x-slot>

                    <div class="space-y-4">
                        <div>
                            <span class="px-2 py-1 bg-green-100 text-green-700 rounded text-xs font-bold">GET</span>
                            <code class="text-sm ml-2">/data/{{ $model->table_name }}</code>
                            <p class="text-xs text-gray-500 mt-1">List all records.</p>
                        </div>

                        <div>
                            <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs font-bold">GET</span>
                            <code class="text-sm ml-2">/data/{{ $model->table_name }}/{'{id}'}</code>
                            <p class="text-xs text-gray-500 mt-1">Get a single record by ID.</p>
                        </div>

                        <div>
                            <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-xs font-bold">POST</span>
                            <code class="text-sm ml-2">/data/{{ $model->table_name }}</code>
                            <p class="text-xs text-gray-500 mt-1">Create a new record.</p>
                        </div>

                        <div>
                            <span class="px-2 py-1 bg-yellow-100 text-yellow-700 rounded text-xs font-bold">PUT</span>
                            <code class="text-sm ml-2">/data/{{ $model->table_name }}/{'{id}'}</code>
                            <p class="text-xs text-gray-500 mt-1">Update an existing record.</p>
                        </div>

                        <div>
                            <span class="px-2 py-1 bg-red-100 text-red-700 rounded text-xs font-bold">DELETE</span>
                            <code class="text-sm ml-2">/data/{{ $model->table_name }}/{'{id}'}</code>
                            <p class="text-xs text-gray-500 mt-1">Delete a record.</p>
                        </div>

                        <div class="mt-4">
                            <h4 class="text-sm font-bold">SDK Example</h4>
                            <pre class="bg-gray-900 text-gray-100 p-3 rounded text-sm mt-2 overflow-x-auto">// List all {{ $model->display_name }}
const { data, meta } = await digibase.from('{{ $model->table_name }}').get();

// Get single record
const { data } = await digibase.from('{{ $model->table_name }}').find(1);

// Create record
const { data } = await digibase.from('{{ $model->table_name }}').insert({
  // your fields here
});

// Update record
const { data } = await digibase.from('{{ $model->table_name }}').update(1, {
  // updated fields here
});

// Delete record
const { data } = await digibase.from('{{ $model->table_name }}').delete(1);</pre>
                        </div>

                        <div class="mt-4">
                            <h4 class="text-sm font-bold">cURL Example</h4>
                            <pre class="bg-gray-900 text-gray-100 p-3 rounded text-sm mt-2 overflow-x-auto">curl -X GET "{{ $baseUrl }}/data/{{ $model->table_name }}" \
  -H "X-API-Key: your_api_key_here" \
  -H "Accept: application/json"</pre>
                        </div>
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
