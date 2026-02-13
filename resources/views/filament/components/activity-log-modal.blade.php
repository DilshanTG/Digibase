@php
    use Illuminate\Support\Str;
    
    $properties = $log->properties ?? collect();
    $old = $properties['old'] ?? [];
    $attributes = $properties['attributes'] ?? [];
@endphp

<div class="sb-activity-modal" x-data="{ activeTab: 'details' }">
    {{-- Header Info --}}
    <div class="mb-6 p-4 bg-slate-900/50 rounded-lg border border-emerald-500/20 backdrop-blur-xl">
        <div class="flex items-center justify-between mb-4">
            <div class="flex items-center space-x-3">
                <div class="w-12 h-12 rounded-lg flex items-center justify-center
                    @if(in_array($log->event, ['created', 'create'])) bg-emerald-500
                    @elseif(in_array($log->event, ['updated', 'update'])) bg-blue-500
                    @elseif(in_array($log->event, ['deleted', 'delete'])) bg-red-500
                    @elseif(in_array($log->event, ['restored', 'restore'])) bg-amber-500
                    @else bg-gray-500 @endif">
                    <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        @if(in_array($log->event, ['created', 'create']))
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                        @elseif(in_array($log->event, ['updated', 'update']))
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                        @elseif(in_array($log->event, ['deleted', 'delete']))
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                        @elseif(in_array($log->event, ['restored', 'restore']))
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"></path>
                        @else
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        @endif
                    </svg>
                </div>
                <div>
                    <h3 class="text-xl font-bold text-slate-100">{{ ucfirst($log->event) }}</h3>
                    <p class="text-sm text-slate-400">{{ $log->log_name }}</p>
                </div>
            </div>
            
            <div class="text-right">
                <div class="text-sm text-slate-400">Log #{{ $log->id }}</div>
                <div class="text-xs text-slate-500">{{ $log->created_at?->format('M j, Y H:i:s') }}</div>
            </div>
        </div>
        
        <p class="text-slate-300">{{ $log->description }}</p>
    </div>

    {{-- Navigation Tabs --}}
    <div class="flex space-x-1 mb-6 p-1 bg-slate-800 rounded-lg">
        <button 
            @click="activeTab = 'details'"
            :class="activeTab === 'details' ? 'bg-emerald-600 text-white' : 'text-slate-400 hover:text-slate-300'"
            class="flex-1 px-4 py-2 rounded-md font-medium transition-colors duration-200">
            General Details
        </button>
        @if(!empty($old) || !empty($attributes))
            <button 
                @click="activeTab = 'changes'"
                :class="activeTab === 'changes' ? 'bg-emerald-600 text-white' : 'text-slate-400 hover:text-slate-300'"
                class="flex-1 px-4 py-2 rounded-md font-medium transition-colors duration-200">
                Changes
            </button>
        @endif
        @if(!empty($properties) && count($properties) > 0)
            <button 
                @click="activeTab = 'raw'"
                :class="activeTab === 'raw' ? 'bg-emerald-600 text-white' : 'text-slate-400 hover:text-slate-300'"
                class="flex-1 px-4 py-2 rounded-md font-medium transition-colors duration-200">
                Raw Data
            </button>
        @endif
    </div>

    {{-- Tab Content --}}
    <div class="space-y-6">
        {{-- General Details Tab --}}
        <div x-show="activeTab === 'details'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                {{-- Subject Info --}}
                <div class="p-4 bg-slate-800/50 rounded-lg border border-slate-700/50">
                    <h4 class="text-sm font-semibold text-slate-400 mb-3 uppercase tracking-wider">Subject</h4>
                    @php
                        $subjectType = $log->subject_type;
                        $isValidClass = $subjectType && class_exists($subjectType);
                        $subject = $isValidClass ? $log->subject : null;
                    @endphp
                    @if($subject)
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-slate-500">Type:</span>
                                <span class="text-slate-300">{{ Str::afterLast($subjectType, '\\') }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">ID:</span>
                                <span class="text-slate-300 font-mono">#{{ $log->subject_id }}</span>
                            </div>
                            @if($subject->name ?? $subject->title ?? null)
                                <div class="flex justify-between">
                                    <span class="text-slate-500">Name:</span>
                                    <span class="text-slate-300">{{ $subject->name ?? $subject->title }}</span>
                                </div>
                            @endif
                        </div>
                    @elseif($subjectType)
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-slate-500">Type:</span>
                                <span class="text-slate-300">{{ Str::studly($subjectType) }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">ID:</span>
                                <span class="text-slate-300 font-mono">#{{ $log->subject_id }}</span>
                            </div>
                        </div>
                    @else
                        <p class="text-slate-500 italic">No subject information available</p>
                    @endif
                </div>

                {{-- User Info --}}
                <div class="p-4 bg-slate-800/50 rounded-lg border border-slate-700/50">
                    <h4 class="text-sm font-semibold text-slate-400 mb-3 uppercase tracking-wider">User</h4>
                    @if($log->causer)
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-slate-500">Name:</span>
                                <span class="text-slate-300">{{ $log->causer->name }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">Email:</span>
                                <span class="text-slate-300">{{ $log->causer->email }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-slate-500">ID:</span>
                                <span class="text-slate-300 font-mono">#{{ $log->causer->id }}</span>
                            </div>
                        </div>
                    @else
                        <p class="text-slate-500 italic">System / No user</p>
                    @endif
                </div>

                {{-- Additional Info --}}
                <div class="p-4 bg-slate-800/50 rounded-lg border border-slate-700/50">
                    <h4 class="text-sm font-semibold text-slate-400 mb-3 uppercase tracking-wider">Request Info</h4>
                    <div class="space-y-2">
                        @if($log->ip_address)
                            <div class="flex justify-between">
                                <span class="text-slate-500">IP Address:</span>
                                <span class="text-slate-300 font-mono text-xs">{{ $log->ip_address }}</span>
                            </div>
                        @endif
                        <div class="flex justify-between">
                            <span class="text-slate-500">Event:</span>
                            <span class="text-emerald-400 font-medium">{{ $log->event }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Log Name:</span>
                            <span class="text-slate-300">{{ $log->log_name }}</span>
                        </div>
                    </div>
                </div>

                {{-- Timestamp Info --}}
                <div class="p-4 bg-slate-800/50 rounded-lg border border-slate-700/50">
                    <h4 class="text-sm font-semibold text-slate-400 mb-3 uppercase tracking-wider">Timestamp</h4>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-slate-500">Date:</span>
                            <span class="text-slate-300">{{ $log->created_at?->format('F j, Y') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Time:</span>
                            <span class="text-slate-300">{{ $log->created_at?->format('H:i:s') }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Relative:</span>
                            <span class="text-slate-300">{{ $log->created_at?->diffForHumans() }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Changes Tab --}}
        @if(!empty($old) || !empty($attributes))
            <div x-show="activeTab === 'changes'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="hidden">
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead>
                            <tr class="border-b border-slate-700/50">
                                <th class="text-left py-3 px-4 text-xs font-semibold text-slate-400 uppercase tracking-wider">Field</th>
                                <th class="text-left py-3 px-4 text-xs font-semibold text-slate-400 uppercase tracking-wider">Old Value</th>
                                <th class="text-left py-3 px-4 text-xs font-semibold text-slate-400 uppercase tracking-wider">New Value</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/30">
                            @php
                                $allKeys = array_unique(array_merge(array_keys($old), array_keys($attributes)));
                            @endphp
                            @foreach($allKeys as $key)
                                @php
                                    $oldValue = $old[$key] ?? null;
                                    $newValue = $attributes[$key] ?? null;
                                    $isChanged = $oldValue !== $newValue;
                                @endphp
                                <tr class="hover:bg-slate-800/50 transition-colors duration-150">
                                    <td class="py-3 px-4">
                                        <span class="text-sm font-medium text-slate-300">{{ $key }}</span>
                                    </td>
                                    <td class="py-3 px-4">
                                        @if($oldValue !== null)
                                            <span class="text-sm text-red-400 bg-red-400/10 px-2 py-1 rounded">
                                                {{ is_array($oldValue) ? json_encode($oldValue) : Str::limit($oldValue, 50) }}
                                            </span>
                                        @else
                                            <span class="text-sm text-slate-500 italic">null</span>
                                        @endif
                                    </td>
                                    <td class="py-3 px-4">
                                        @if($newValue !== null)
                                            <span class="text-sm text-emerald-400 bg-emerald-400/10 px-2 py-1 rounded">
                                                {{ is_array($newValue) ? json_encode($newValue) : Str::limit($newValue, 50) }}
                                            </span>
                                        @else
                                            <span class="text-sm text-slate-500 italic">null</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        {{-- Raw Data Tab --}}
        @if(!empty($properties) && count($properties) > 0)
            <div x-show="activeTab === 'raw'" x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100" class="hidden">
                <div class="bg-slate-900 rounded-lg border border-slate-700 p-4 overflow-x-auto">
                    <pre class="text-sm text-slate-300 font-mono">{{ json_encode($properties->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </div>
            </div>
        @endif
    </div>
</div>

<style>
    .sb-activity-modal [x-show] {
        display: none;
    }
    .sb-activity-modal [x-show]:not(.hidden) {
        display: block;
    }
</style>