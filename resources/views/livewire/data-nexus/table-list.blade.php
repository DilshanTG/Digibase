<div class="sb-table-list">
    {{-- Header --}}
    <div class="sb-list-header">
        <div class="sb-header-content">
            <h2 class="sb-header-title">Database Tables</h2>
            <p class="sb-header-subtitle">Manage and explore your application database structure.</p>
        </div>
        
        <div class="sb-header-actions">
            <div class="sb-search-box">
                <svg class="sb-search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <input wire:model.live.debounce.250ms="search" type="text" placeholder="Search tables..." class="sb-search-input" />
            </div>
            
            <a href="{{ \App\Filament\Resources\DynamicModelResource::getUrl('create') }}" class="sb-new-btn">
                <svg class="sb-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                New table
            </a>
        </div>
    </div>

    {{-- Table Grid --}}
    <div class="sb-list-content">
        <div class="sb-table-container">
            <table class="sb-data-table">
                <thead class="sb-table-head">
                    <tr>
                        <th class="sb-th sb-th-icon"></th>
                        <th class="sb-th">NAME</th>
                        <th class="sb-th">DESCRIPTION</th>
                        <th class="sb-th sb-th-right">ROWS</th>
                        <th class="sb-th sb-th-right">SIZE</th>
                        <th class="sb-th sb-th-center">STATUS</th>
                        <th class="sb-th sb-th-actions"></th>
                    </tr>
                </thead>
                <tbody class="sb-table-body">
                    @forelse($tables as $table)
                        <tr wire:key="table-{{ $table->id }}" class="sb-table-row" onclick="window.NexusDashboard.switchTable('{{ $table->table_name }}')">
                            <td class="sb-td sb-td-icon">
                                <div class="sb-table-icon">
                                    <svg class="sb-icon-sm" viewBox="0 0 16 16" fill="currentColor"><path d="M1 3a1 1 0 011-1h12a1 1 0 011 1v1H1V3zm0 2h14v2H1V5zm0 3h6v2H1V8zm7 0h7v2H8V8zm-7 3h6v2H1v-2zm7 0h7v2H8v-2z"/></svg>
                                </div>
                            </td>
                            <td class="sb-td">
                                <div class="sb-table-name-wrapper">
                                    <span class="sb-table-name">{{ $table->table_name }}</span>
                                    <span class="sb-table-schema">public.{{ $table->table_name }}</span>
                                </div>
                            </td>
                            <td class="sb-td">
                                <span class="sb-table-desc">{{ $table->description ?? 'No description provided' }}</span>
                            </td>
                            <td class="sb-td sb-td-right">
                                <span class="sb-table-stat">{{ number_format($table->rows_count) }}</span>
                            </td>
                            <td class="sb-td sb-td-right">
                                <span class="sb-table-stat">{{ $table->size_estimated }}</span>
                            </td>
                            <td class="sb-td sb-td-center">
                                <div class="sb-status-wrapper">
                                    <span class="sb-status-dot online"></span>
                                    <span class="sb-status-text">Active</span>
                                </div>
                            </td>
                            <td class="sb-td sb-td-actions">
                                <div class="sb-row-actions">
                                    <a href="{{ \App\Filament\Resources\DynamicModelResource::getUrl('edit', ['record' => $table->id]) }}" 
                                       class="sb-action-btn sb-action-edit"
                                       onclick="event.stopPropagation()">
                                        <svg class="sb-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                                    </a>
                                    <button wire:click="deleteTable({{ $table->id }})" 
                                            wire:confirm="Are you sure you want to delete table '{{ $table->table_name }}'? This cannot be undone."
                                            class="sb-action-btn sb-action-delete"
                                            onclick="event.stopPropagation()">
                                        <svg class="sb-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="sb-td sb-td-empty">
                                <div class="sb-empty-state">
                                    <svg class="sb-empty-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 6h16M4 10h16M4 14h16M4 18h16"/></svg>
                                    <div class="sb-empty-content">
                                        <span class="sb-empty-title">No tables found</span>
                                        <span class="sb-empty-desc">Try searching for something else or create a new table.</span>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        <div class="sb-pagination-wrapper">
            {{ $tables->links() }}
        </div>
    </div>

    <script>
        window.NexusDashboard = {
            switchTable(tableName) {
                window.Livewire.dispatch('table-switched', { tableName: tableName });
            }
        }
    </script>

    <style>
        .sb-table-list {
            padding: 48px;
            max-width: 1400px;
            margin: 0 auto;
            width: 100%;
        }

        .sb-list-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
        }

        .sb-header-title {
            font-size: 24px;
            font-weight: 600;
            color: #ededed;
            margin-bottom: 4px;
        }

        .sb-header-subtitle {
            font-size: 14px;
            color: #6b6b6b;
        }

        .sb-header-actions {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .sb-search-box {
            position: relative;
            width: 280px;
        }

        .sb-search-icon {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            width: 16px;
            height: 16px;
            color: #6b6b6b;
            pointer-events: none;
        }

        .sb-search-input {
            width: 100%;
            padding: 10px 12px 10px 36px;
            font-size: 14px;
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

        .sb-new-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 16px;
            font-size: 14px;
            font-weight: 500;
            color: #0f0f0f;
            background: #3ecf8e;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.15s ease;
        }

        .sb-new-btn:hover {
            background: #2ea87a;
        }

        .sb-table-container {
            background: #1c1c1c;
            border: 1px solid #2e2e2e;
            border-radius: 8px;
            overflow: hidden;
        }

        .sb-data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        .sb-table-head {
            background: #161616;
            border-bottom: 1px solid #2e2e2e;
        }

        .sb-th {
            padding: 14px 16px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b6b6b;
            text-align: left;
        }

        .sb-th-icon {
            width: 60px;
            text-align: center;
        }

        .sb-th-right {
            text-align: right;
        }

        .sb-th-center {
            text-align: center;
        }

        .sb-th-actions {
            width: 100px;
        }

        .sb-table-body {
            background: #1c1c1c;
        }

        .sb-table-row {
            border-bottom: 1px solid #232323;
            transition: background-color 0.15s ease;
            cursor: pointer;
        }

        .sb-table-row:hover {
            background: #232323;
        }

        .sb-table-row:last-child {
            border-bottom: none;
        }

        .sb-td {
            padding: 16px;
            color: #ededed;
            vertical-align: middle;
        }

        .sb-td-icon {
            text-align: center;
        }

        .sb-td-right {
            text-align: right;
        }

        .sb-td-center {
            text-align: center;
        }

        .sb-td-actions {
            text-align: right;
        }

        .sb-table-icon {
            width: 36px;
            height: 36px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #232323;
            border-radius: 6px;
            color: #6b6b6b;
            margin: 0 auto;
        }

        .sb-table-row:hover .sb-table-icon {
            color: #3ecf8e;
        }

        .sb-table-name-wrapper {
            display: flex;
            flex-direction: column;
        }

        .sb-table-name {
            font-weight: 600;
            color: #ededed;
            margin-bottom: 2px;
        }

        .sb-table-schema {
            font-size: 11px;
            color: #6b6b6b;
            font-family: 'JetBrains Mono', monospace;
        }

        .sb-table-desc {
            color: #a0a0a0;
            font-size: 13px;
        }

        .sb-table-stat {
            font-family: 'JetBrains Mono', monospace;
            font-size: 13px;
            color: #a0a0a0;
        }

        .sb-status-wrapper {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .sb-status-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
        }

        .sb-status-dot.online {
            background: #3ecf8e;
            box-shadow: 0 0 8px #3ecf8e;
        }

        .sb-status-text {
            font-size: 11px;
            font-weight: 600;
            color: #3ecf8e;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .sb-row-actions {
            display: flex;
            justify-content: flex-end;
            gap: 4px;
            opacity: 0;
            transition: opacity 0.15s ease;
        }

        .sb-table-row:hover .sb-row-actions {
            opacity: 1;
        }

        .sb-action-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            color: #6b6b6b;
            background: none;
            border: none;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .sb-action-btn:hover {
            background: #2e2e2e;
        }

        .sb-action-edit:hover {
            color: #3ecf8e;
        }

        .sb-action-delete:hover {
            color: #ff6b6b;
            background: rgba(255, 107, 107, 0.1);
        }

        .sb-td-empty {
            text-align: center;
            padding: 64px;
        }

        .sb-empty-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 16px;
        }

        .sb-empty-icon {
            width: 48px;
            height: 48px;
            color: #383838;
        }

        .sb-empty-content {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .sb-empty-title {
            font-size: 16px;
            font-weight: 600;
            color: #ededed;
        }

        .sb-empty-desc {
            font-size: 14px;
            color: #6b6b6b;
        }

        .sb-pagination-wrapper {
            margin-top: 24px;
        }

        /* Icons */
        .sb-icon-sm {
            width: 16px;
            height: 16px;
        }
    </style>
</div>