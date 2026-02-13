<x-filament-panels::page>
    <div class="sb-studio" x-data="{ search: '' }">
        
        {{-- SIDEBAR --}}
        <aside class="sb-sidebar">
            <div class="sb-sidebar-header">
                <div class="sb-schema-info">
                    <span class="sb-schema-label">schema</span>
                    <span class="sb-schema-badge">public</span>
                </div>
                <a href="{{ \App\Filament\Resources\DynamicModelResource::getUrl('create') }}" class="sb-new-table-btn">
                    <svg class="sb-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    New table
                </a>
            </div>

            <div class="sb-search-wrapper">
                <svg class="sb-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input x-model="search" type="text" placeholder="Search tables..." class="sb-search-input" />
            </div>

            <nav class="sb-nav">
                @foreach($tables as $table)
                    <button
                        wire:click="switchTable('{{ $table->table_name }}')"
                        x-show="!search || '{{ strtolower($table->display_name ?? $table->table_name) }}'.includes(search.toLowerCase())"
                        @class(['sb-nav-item', 'active' => $activeTable === $table->table_name])
                    >
                        <svg class="sb-table-icon" viewBox="0 0 16 16" fill="currentColor"><path d="M1 3a1 1 0 011-1h12a1 1 0 011 1v1H1V3zm0 2h14v2H1V5zm0 3h6v2H1V8zm7 0h7v2H8V8zm-7 3h6v2H1v-2zm7 0h7v2H8v-2z"/></svg>
                        <span class="sb-nav-text">{{ $table->display_name ?? $table->table_name }}</span>
                        @if(!$table->is_active)
                            <span class="sb-status-off">OFF</span>
                        @else
                            <svg class="sb-lock-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        @endif
                    </button>
                @endforeach
            </nav>

            <div class="sb-sidebar-footer">
                <span>{{ $tables->count() }} tables</span>
                <span class="sb-connection-status">
                    <span class="sb-status-dot online"></span>
                    connected
                </span>
            </div>
        </aside>

        {{-- MAIN CONTENT --}}
        <div class="sb-main">
            @if($activeTable)
                {{-- Tab Bar --}}
                <div class="sb-tab-bar">
                    <div class="sb-tabs">
                        @foreach($tables as $table)
                            @if($activeTable === $table->table_name)
                                <div class="sb-tab active">
                                    <svg class="sb-icon-xs" viewBox="0 0 16 16" fill="currentColor"><path d="M1 3a1 1 0 011-1h12a1 1 0 011 1v1H1V3zm0 2h14v2H1V5zm0 3h6v2H1V8zm7 0h7v2H8V8zm-7 3h6v2H1v-2zm7 0h7v2H8v-2z"/></svg>
                                    <span>public.{{ $table->table_name }}</span>
                                    <button wire:click="switchTable('')" class="sb-tab-close" title="Close">×</button>
                                </div>
                            @endif
                        @endforeach
                        <button class="sb-tab-add" title="Open table">
                            <svg class="sb-icon-xs" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                        </button>
                    </div>
                </div>

                {{-- Toolbar --}}
                <div class="sb-toolbar">
                    <div class="sb-toolbar-left">
                        <button class="sb-tool-btn">
                            <svg class="sb-icon-xs" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg>
                            Filter
                        </button>
                        <button class="sb-tool-btn">
                            <svg class="sb-icon-xs" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4h13M3 8h9m-9 4h6m4 0l4-4m0 0l4 4m-4-4v12"/></svg>
                            Sort
                        </button>
                        <button wire:click="syncDatabase" class="sb-tool-btn sb-sync-btn">
                            <svg class="sb-icon-xs" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                            Sync DB
                        </button>
                    </div>
                    <div class="sb-toolbar-right">
                        <div class="sb-mode-switch">
                            <button wire:click="switchMode('data')" @class(['sb-mode-btn', 'active' => $viewMode === 'data'])>
                                <svg class="sb-icon-xs" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                                Data
                            </button>
                            <button wire:click="switchMode('definition')" @class(['sb-mode-btn', 'active' => $viewMode === 'definition'])>
                                <svg class="sb-icon-xs" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                Definition
                            </button>
                        </div>
                    </div>
                </div>

                {{-- Workspace --}}
                <div class="sb-workspace">
                    @if($viewMode === 'data')
                        @livewire('data-nexus.grid', ['table' => $activeTable], key('grid-' . $activeTable))
                    @else
                        <div class="sb-schema-panel">
                            @livewire('data-nexus.schema-editor', ['modelId' => $this->getActiveModel()->id], key('schema-' . $activeTable))
                        </div>
                    @endif
                </div>
            @else
                {{-- Empty State / Dashboard --}}
                <div class="sb-workspace sb-empty-state">
                    <div class="sb-empty-content">
                        <svg class="sb-empty-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"/></svg>
                        <h3 class="sb-empty-title">Select a table to get started</h3>
                        <p class="sb-empty-text">Choose a table from the sidebar to view and manage your data</p>
                    </div>
                </div>
            @endif
        </div>
    </div>

    <style>
        /* Reset Filament defaults */
        .fi-page-content-ctn { max-width: none !important; padding: 0 !important; }
        .fi-page > .fi-page-content-ctn > div { padding: 0 !important; }
        .fi-page-header { display: none !important; }

        /* ============================================
           SUPABASE STUDIO LAYOUT
           ============================================ */
        .sb-studio {
            display: flex;
            height: calc(100vh - 48px);
            margin: -24px;
            overflow: hidden;
            background: #0f0f0f;
        }

        /* ============================================
           SIDEBAR
           ============================================ */
        .sb-sidebar {
            width: 260px;
            min-width: 260px;
            display: flex;
            flex-direction: column;
            background: #0f0f0f;
            border-right: 1px solid #2e2e2e;
            z-index: 30;
        }

        .sb-sidebar-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            border-bottom: 1px solid #2e2e2e;
        }

        .sb-schema-info {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .sb-schema-label {
            font-size: 11px;
            color: #6b6b6b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 500;
        }

        .sb-schema-badge {
            font-size: 11px;
            font-weight: 600;
            color: #a0a0a0;
            background: #232323;
            padding: 2px 8px;
            border-radius: 4px;
        }

        .sb-new-table-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            font-weight: 500;
            color: #3ecf8e;
            padding: 6px 12px;
            border-radius: 6px;
            background: rgba(62, 207, 142, 0.1);
            transition: all 0.15s ease;
            text-decoration: none;
        }

        .sb-new-table-btn:hover {
            background: rgba(62, 207, 142, 0.2);
        }

        .sb-search-wrapper {
            position: relative;
            padding: 12px 16px;
            border-bottom: 1px solid #2e2e2e;
        }

        .sb-search-icon {
            position: absolute;
            left: 24px;
            top: 50%;
            transform: translateY(-50%);
            width: 14px;
            height: 14px;
            color: #6b6b6b;
            pointer-events: none;
        }

        .sb-search-input {
            width: 100%;
            padding: 8px 12px 8px 32px;
            font-size: 13px;
            background: #1c1c1c;
            border: 1px solid #2e2e2e;
            border-radius: 6px;
            color: #ededed;
            outline: none;
            transition: all 0.15s ease;
        }

        .sb-search-input::placeholder {
            color: #6b6b6b;
        }

        .sb-search-input:focus {
            border-color: #3ecf8e;
            box-shadow: 0 0 0 2px rgba(62, 207, 142, 0.2);
        }

        .sb-nav {
            flex: 1;
            overflow-y: auto;
            padding: 8px 12px;
        }

        .sb-nav::-webkit-scrollbar {
            width: 4px;
        }

        .sb-nav::-webkit-scrollbar-thumb {
            background: #383838;
            border-radius: 10px;
        }

        .sb-nav-item {
            display: flex;
            align-items: center;
            width: 100%;
            padding: 8px 12px;
            margin: 2px 0;
            font-size: 13px;
            color: #a0a0a0;
            border-radius: 6px;
            border: none;
            background: none;
            cursor: pointer;
            text-align: left;
            transition: all 0.15s ease;
            position: relative;
        }

        .sb-nav-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 25%;
            bottom: 25%;
            width: 3px;
            background: #3ecf8e;
            border-radius: 0 4px 4px 0;
            opacity: 0;
            transition: opacity 0.2s ease;
        }

        .sb-nav-item:hover {
            background: #1c1c1c;
            color: #ededed;
        }

        .sb-nav-item.active {
            background: rgba(62, 207, 142, 0.1);
            color: #3ecf8e;
        }

        .sb-nav-item.active::before {
            opacity: 1;
        }

        .sb-table-icon {
            width: 14px;
            height: 14px;
            margin-right: 10px;
            flex-shrink: 0;
            color: #6b6b6b;
        }

        .sb-nav-item.active .sb-table-icon {
            color: #3ecf8e;
        }

        .sb-nav-text {
            flex: 1;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .sb-lock-icon {
            width: 12px;
            height: 12px;
            margin-left: auto;
            color: #4a4a4a;
            opacity: 0;
            transition: opacity 0.15s ease;
        }

        .sb-nav-item:hover .sb-lock-icon {
            opacity: 1;
        }

        .sb-status-off {
            margin-left: auto;
            font-size: 9px;
            font-weight: 700;
            color: #ffa726;
            background: rgba(255, 167, 38, 0.1);
            padding: 2px 6px;
            border-radius: 4px;
        }

        .sb-sidebar-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            border-top: 1px solid #2e2e2e;
            font-size: 11px;
            color: #6b6b6b;
        }

        .sb-connection-status {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .sb-status-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }

        .sb-status-dot.online {
            background: #3ecf8e;
            box-shadow: 0 0 8px #3ecf8e;
            animation: sb-pulse 2s ease-in-out infinite;
        }

        @keyframes sb-pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        /* ============================================
           MAIN CONTENT
           ============================================ */
        .sb-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            min-width: 0;
            background: #0f0f0f;
        }

        /* Tab Bar */
        .sb-tab-bar {
            display: flex;
            align-items: stretch;
            background: #161616;
            border-bottom: 1px solid #2e2e2e;
            min-height: 40px;
        }

        .sb-tabs {
            display: flex;
            align-items: stretch;
        }

        .sb-tab {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0 16px;
            font-size: 13px;
            font-weight: 500;
            min-height: 40px;
            white-space: nowrap;
            color: #a0a0a0;
            background: transparent;
            border-right: 1px solid #2e2e2e;
            position: relative;
        }

        .sb-tab.active {
            background: #1c1c1c;
            color: #ededed;
            border-bottom: 2px solid #3ecf8e;
            margin-bottom: -1px;
        }

        .sb-tab-close {
            margin-left: 8px;
            font-size: 18px;
            color: #6b6b6b;
            cursor: pointer;
            line-height: 1;
            background: none;
            border: none;
            padding: 0 4px;
            transition: color 0.15s ease;
        }

        .sb-tab-close:hover {
            color: #ff6b6b;
        }

        .sb-tab-add {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            min-height: 40px;
            cursor: pointer;
            background: none;
            border: none;
            color: #6b6b6b;
            border-right: 1px solid #2e2e2e;
            transition: all 0.15s ease;
        }

        .sb-tab-add:hover {
            color: #3ecf8e;
            background: #1c1c1c;
        }

        /* Toolbar */
        .sb-toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            gap: 12px;
            background: #1c1c1c;
            border-bottom: 1px solid #2e2e2e;
        }

        .sb-toolbar-left, .sb-toolbar-right {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .sb-tool-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            font-size: 13px;
            font-weight: 400;
            color: #a0a0a0;
            background: #232323;
            border: 1px solid #383838;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .sb-tool-btn:hover {
            background: #2e2e2e;
            border-color: #4a4a4a;
            color: #ededed;
        }

        .sb-sync-btn {
            background: rgba(62, 207, 142, 0.1);
            border-color: rgba(62, 207, 142, 0.3);
            color: #3ecf8e;
            font-weight: 500;
        }

        .sb-sync-btn:hover {
            background: rgba(62, 207, 142, 0.2);
            border-color: rgba(62, 207, 142, 0.5);
        }

        .sb-mode-switch {
            display: flex;
            background: #232323;
            border-radius: 6px;
            padding: 3px;
            gap: 2px;
        }

        .sb-mode-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 5px 12px;
            font-size: 12px;
            font-weight: 500;
            color: #a0a0a0;
            border-radius: 4px;
            border: none;
            background: none;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .sb-mode-btn:hover {
            color: #ededed;
        }

        .sb-mode-btn.active {
            background: #1c1c1c;
            color: #3ecf8e;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
        }

        /* Workspace */
        .sb-workspace {
            flex: 1;
            overflow: auto;
            background: #0f0f0f;
        }

        .sb-workspace::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .sb-workspace::-webkit-scrollbar-track {
            background: #161616;
        }

        .sb-workspace::-webkit-scrollbar-thumb {
            background: #383838;
            border-radius: 4px;
        }

        .sb-workspace::-webkit-scrollbar-thumb:hover {
            background: #4a4a4a;
        }

        /* Schema Panel */
        .sb-schema-panel {
            padding: 24px;
            max-width: 1200px;
        }

        /* Empty State */
        .sb-empty-state {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
        }

        .sb-empty-content {
            text-align: center;
            padding: 40px;
        }

        .sb-empty-icon {
            width: 64px;
            height: 64px;
            color: #383838;
            margin-bottom: 24px;
        }

        .sb-empty-title {
            font-size: 18px;
            font-weight: 600;
            color: #ededed;
            margin-bottom: 8px;
        }

        .sb-empty-text {
            font-size: 14px;
            color: #6b6b6b;
        }

        /* Icons */
        .sb-icon-xs {
            width: 14px;
            height: 14px;
        }

        .sb-icon-sm {
            width: 16px;
            height: 16px;
        }

        /* Remove Filament table styling inside workspace */
        .sb-workspace .fi-ta {
            border: none !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            background: transparent !important;
        }

        .sb-workspace .fi-ta-content {
            border-radius: 0 !important;
        }

        .sb-workspace .fi-ta-header-ctn {
            border-radius: 0 !important;
            background: #161616 !important;
            border-bottom: 1px solid #2e2e2e !important;
        }

        .sb-workspace .fi-ta-header-cell {
            font-size: 12px !important;
            font-weight: 600 !important;
            color: #a0a0a0 !important;
            text-transform: uppercase !important;
            letter-spacing: 0.05em !important;
            padding: 12px 16px !important;
            border-bottom: none !important;
        }

        .sb-workspace .fi-ta-row {
            background: #0f0f0f !important;
            border-bottom: 1px solid #1c1c1c !important;
            transition: background-color 0.15s ease !important;
        }

        .sb-workspace .fi-ta-row:hover {
            background: #161616 !important;
        }

        .sb-workspace .fi-ta-row:nth-child(even) {
            background: #0f0f0f !important;
        }

        .sb-workspace .fi-ta-cell {
            font-size: 13px !important;
            color: #ededed !important;
            padding: 12px 16px !important;
        }

        .sb-workspace .fi-section {
            border: none !important;
            border-radius: 0 !important;
            box-shadow: none !important;
            background: transparent !important;
        }

        .sb-workspace .fi-section-content-ctn {
            padding: 0 !important;
        }
    </style>
</x-filament-panels::page>