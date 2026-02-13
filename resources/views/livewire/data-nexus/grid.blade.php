<div class="sb-grid-container" x-data="gridEditor()">
    @if(count($columns) === 0)
        <div class="sb-grid-empty">
            <svg class="sb-empty-icon-lg" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 7v10c0 1.105 4.477 2 10 2s10-.895 10-2V7M4 7c0 1.105 4.477 2 10 2s10-.895 10-2M4 7c0-1.105 4.477-2 10-2s10 .895 10 2m0 10c0 1.105-4.477 2-10 2s-10-.895-10-2"/>
            </svg>
            <p class="sb-empty-message">No table selected or schema not found.</p>
        </div>
    @else
        <div class="sb-grid-wrapper" x-ref="scrollContainer">
            <table class="sb-data-grid">
                {{-- Header --}}
                <thead>
                    <tr>
                        <th class="sb-th sb-th-row">#</th>
                        <th class="sb-th sb-th-id">
                            <span class="sb-col-name">id</span>
                            <span class="sb-col-type">int8</span>
                        </th>
                        @foreach($columns as $col)
                            <th class="sb-th">
                                <span class="sb-col-name">{{ $col['name'] }}</span>
                                <span class="sb-col-type">{{ $col['type'] }}</span>
                            </th>
                        @endforeach
                        <th class="sb-th sb-th-actions"></th>
                    </tr>
                </thead>

                {{-- Body --}}
                <tbody>
                    @forelse($rows as $i => $row)
                        <tr class="sb-row" wire:key="row-{{ $row->id }}">
                            <td class="sb-td sb-td-row">{{ ($page - 1) * $perPage + $i + 1 }}</td>
                            <td class="sb-td sb-td-id">{{ $row->id }}</td>

                            @foreach($columns as $j => $col)
                                <td 
                                    class="sb-td sb-cell"
                                    :class="{ 
                                        'sb-editing': isEditing({{ $row->id }}, '{{ $col['name'] }}'),
                                        'sb-selected': isSelected({{ $i }}, {{ $j }})
                                    }"
                                    @click="selectCell({{ $i }}, {{ $j }}, {{ $row->id }}, '{{ $col['name'] }}')"
                                    @dblclick="startEdit({{ $row->id }}, '{{ $col['name'] }}')"
                                >
                                    {{-- Display Mode --}}
                                    <div x-show="!isEditing({{ $row->id }}, '{{ $col['name'] }}')" class="sb-cell-display">
                                        @if(in_array($col['type'], ['file', 'image']))
                                            @php $mediaUrls = $row->_media[$col['name']] ?? []; @endphp
                                            @if(count($mediaUrls) > 0)
                                                <div class="sb-media-cell">
                                                    @foreach($mediaUrls as $url)
                                                        @if($col['type'] === 'image')
                                                            <a href="{{ $url }}" target="_blank" class="sb-media-thumb" @click.stop>
                                                                <img src="{{ $url }}" alt="" />
                                                            </a>
                                                        @else
                                                            <a href="{{ $url }}" target="_blank" class="sb-media-link" @click.stop>
                                                                <svg class="sb-icon-xs" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                                                {{ \Illuminate\Support\Str::limit(basename($url), 20) }}
                                                            </a>
                                                        @endif
                                                    @endforeach
                                                </div>
                                            @else
                                                <span class="sb-null">NULL</span>
                                            @endif
                                        @elseif($col['type'] === 'boolean')
                                            @if($row->{$col['name']})
                                                <span class="sb-bool sb-bool-true">true</span>
                                            @else
                                                <span class="sb-bool sb-bool-false">false</span>
                                            @endif
                                        @elseif($row->{$col['name']} === null || $row->{$col['name']} === '')
                                            <span class="sb-null">NULL</span>
                                        @else
                                            {{ \Illuminate\Support\Str::limit($row->{$col['name']}, 100) }}
                                        @endif
                                    </div>

                                    {{-- Edit Mode --}}
                                    <div x-show="isEditing({{ $row->id }}, '{{ $col['name'] }}')" x-cloak class="sb-cell-edit">
                                        @if($col['type'] === 'boolean')
                                            <select
                                                class="sb-edit-input"
                                                x-ref="input_{{ $row->id }}_{{ $col['name'] }}"
                                                @change="saveCell({{ $row->id }}, '{{ $col['name'] }}', $event.target.value)"
                                                @blur="cancelEdit()"
                                                @keydown.escape.prevent="cancelEdit()"
                                            >
                                                <option value="1" @if($row->{$col['name']}) selected @endif>true</option>
                                                <option value="0" @if(!$row->{$col['name']}) selected @endif>false</option>
                                            </select>
                                        @elseif($col['type'] === 'text')
                                            <textarea
                                                class="sb-edit-input sb-edit-textarea"
                                                x-ref="input_{{ $row->id }}_{{ $col['name'] }}"
                                                @blur="saveCell({{ $row->id }}, '{{ $col['name'] }}', $event.target.value)"
                                                @keydown.escape.prevent="cancelEdit()"
                                                x-init="$nextTick(() => { $el.focus(); $el.select(); })"
                                            >{{ $row->{$col['name']} }}</textarea>
                                        @else
                                            <input
                                                type="{{ $col['type'] === 'integer' ? 'number' : ($col['type'] === 'date' ? 'date' : ($col['type'] === 'datetime' ? 'datetime-local' : 'text')) }}"
                                                class="sb-edit-input"
                                                value="{{ $row->{$col['name']} }}"
                                                x-ref="input_{{ $row->id }}_{{ $col['name'] }}"
                                                @blur="saveCell({{ $row->id }}, '{{ $col['name'] }}', $event.target.value)"
                                                @keydown.enter.prevent="saveAndMove({{ $row->id }}, '{{ $col['name'] }}', $event.target.value)"
                                                @keydown.escape.prevent="cancelEdit()"
                                                x-init="$nextTick(() => { $el.focus(); $el.select(); })"
                                            />
                                        @endif
                                    </div>
                                </td>
                            @endforeach

                            <td class="sb-td sb-td-actions">
                                <button class="sb-delete-btn" wire:click="deleteRow({{ $row->id }})" wire:confirm="Delete this row?" title="Delete">
                                    <svg class="sb-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($columns) + 3 }}" class="sb-td sb-td-empty">
                                No records found. Click <strong>Insert row</strong> to add one.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        {{-- Footer --}}
        <div class="sb-grid-footer">
            <div class="sb-footer-left">
                <button class="sb-insert-btn" wire:click="insertRow">
                    <svg class="sb-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                    Insert row
                </button>
                <div class="sb-divider"></div>
                <span class="sb-row-count">{{ $totalRows }} ROWS</span>
            </div>
            <div class="sb-footer-right">
                <span class="sb-page-info">Page {{ $page }} of {{ max(1, ceil($totalRows / $perPage)) }}</span>
                <div class="sb-pagination">
                    <button class="sb-page-btn" wire:click="prevPage" @if($page <= 1) disabled @endif>
                        <svg class="sb-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                    </button>
                    <button class="sb-page-btn" wire:click="nextPage" @if($page * $perPage >= $totalRows) disabled @endif>
                        <svg class="sb-icon-sm" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                    </button>
                </div>
            </div>
        </div>
    @endif

    <script>
        function gridEditor() {
            return {
                editingId: null,
                editingCol: null,
                selectedRow: null,
                selectedCol: null,
                
                isEditing(id, col) {
                    return this.editingId === id && this.editingCol === col;
                },
                
                isSelected(row, col) {
                    return this.selectedRow === row && this.selectedCol === col;
                },
                
                selectCell(row, col, id, field) {
                    this.selectedRow = row;
                    this.selectedCol = col;
                },
                
                startEdit(id, col) {
                    this.editingId = id;
                    this.editingCol = col;
                },
                
                saveCell(id, col, value) {
                    this.$wire.updateCell(id, col, value);
                    this.editingId = null;
                    this.editingCol = null;
                },
                
                saveAndMove(id, col, value) {
                    this.$wire.updateCell(id, col, value);
                    this.editingId = null;
                    this.editingCol = null;
                    // Move to next cell down
                    if (this.selectedRow !== null) {
                        this.selectedRow++;
                    }
                },
                
                cancelEdit() {
                    this.editingId = null;
                    this.editingCol = null;
                }
            }
        }
    </script>

    <style>
        .sb-grid-container {
            display: flex;
            flex-direction: column;
            height: 100%;
            overflow: hidden;
            background: #0f0f0f;
        }

        .sb-grid-empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            gap: 16px;
        }

        .sb-empty-icon-lg {
            width: 48px;
            height: 48px;
            color: #383838;
        }

        .sb-empty-message {
            font-size: 14px;
            color: #6b6b6b;
        }

        .sb-grid-wrapper {
            flex: 1;
            overflow: auto;
        }

        .sb-grid-wrapper::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .sb-grid-wrapper::-webkit-scrollbar-track {
            background: #161616;
        }

        .sb-grid-wrapper::-webkit-scrollbar-thumb {
            background: #383838;
            border-radius: 4px;
        }

        .sb-grid-wrapper::-webkit-scrollbar-thumb:hover {
            background: #4a4a4a;
        }

        .sb-data-grid {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
            font-family: 'JetBrains Mono', 'Fira Code', monospace;
            table-layout: fixed;
        }

        /* Header */
        .sb-th {
            position: sticky;
            top: 0;
            z-index: 10;
            padding: 12px 16px;
            text-align: left;
            background: #161616;
            border-bottom: 1px solid #2e2e2e;
            border-right: 1px solid #232323;
            font-weight: 600;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #6b6b6b;
            white-space: nowrap;
        }

        .sb-th:last-child {
            border-right: none;
        }

        .sb-th-row {
            width: 50px;
            min-width: 50px;
            text-align: center;
        }

        .sb-th-id {
            width: 80px;
            min-width: 80px;
        }

        .sb-th-actions {
            width: 50px;
            min-width: 50px;
        }

        .sb-col-name {
            display: block;
            color: #a0a0a0;
            font-weight: 600;
        }

        .sb-col-type {
            display: block;
            font-size: 10px;
            color: #6b6b6b;
            font-weight: 500;
            margin-top: 2px;
        }

        /* Body */
        .sb-row {
            transition: background-color 0.15s ease;
        }

        .sb-row:hover {
            background: #161616;
        }

        .sb-td {
            padding: 0;
            border-bottom: 1px solid #1c1c1c;
            border-right: 1px solid #1c1c1c;
            color: #ededed;
            vertical-align: middle;
            height: 40px;
            position: relative;
        }

        .sb-td:last-child {
            border-right: none;
        }

        .sb-td-row {
            text-align: center;
            color: #4a4a4a;
            font-size: 11px;
            font-weight: 600;
            width: 50px;
            background: #0f0f0f;
            padding: 10px;
        }

        .sb-td-id {
            color: #6b6b6b;
            font-weight: 600;
            width: 80px;
            padding: 10px 16px;
        }

        .sb-td-actions {
            width: 50px;
            text-align: center;
            padding: 10px;
        }

        .sb-td-empty {
            text-align: center;
            padding: 48px;
            color: #6b6b6b;
        }

        /* Cell States */
        .sb-cell {
            cursor: cell;
            user-select: none;
        }

        .sb-cell.sb-selected {
            outline: 2px solid #3ecf8e;
            outline-offset: -2px;
            z-index: 2;
        }

        .sb-cell.sb-editing {
            padding: 0;
            z-index: 3;
        }

        .sb-cell-display {
            padding: 10px 16px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            min-height: 40px;
            line-height: 20px;
        }

        .sb-cell-edit {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            z-index: 10;
        }

        /* Edit Inputs */
        .sb-edit-input {
            width: 100%;
            height: 100%;
            min-height: 40px;
            padding: 10px 16px;
            border: 2px solid #3ecf8e;
            font-size: 13px;
            font-family: inherit;
            outline: none;
            background: #0f0f0f;
            color: #ededed;
            box-sizing: border-box;
        }

        .sb-edit-input:focus {
            box-shadow: 0 0 0 2px rgba(62, 207, 142, 0.2);
        }

        .sb-edit-textarea {
            min-height: 80px;
            resize: vertical;
        }

        /* Special cells */
        .sb-null {
            color: #4a4a4a;
            font-style: italic;
            font-size: 11px;
        }

        .sb-bool {
            font-size: 11px;
            font-weight: 600;
            padding: 3px 8px;
            border-radius: 4px;
            text-transform: lowercase;
        }

        .sb-bool-true {
            color: #3ecf8e;
            background: rgba(62, 207, 142, 0.1);
        }

        .sb-bool-false {
            color: #6b6b6b;
            background: #232323;
        }

        /* Media cells */
        .sb-media-cell {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 16px;
        }

        .sb-media-thumb img {
            width: 28px;
            height: 28px;
            object-fit: cover;
            border-radius: 4px;
            border: 1px solid #383838;
        }

        .sb-media-link {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 12px;
            color: #3ecf8e;
            text-decoration: none;
        }

        .sb-media-link:hover {
            text-decoration: underline;
        }

        /* Delete button */
        .sb-delete-btn {
            opacity: 0;
            padding: 6px;
            color: #ff6b6b;
            background: none;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .sb-row:hover .sb-delete-btn {
            opacity: 1;
        }

        .sb-delete-btn:hover {
            background: rgba(255, 107, 107, 0.1);
        }

        /* Footer */
        .sb-grid-footer {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 12px 16px;
            background: #161616;
            border-top: 1px solid #2e2e2e;
            flex-shrink: 0;
        }

        .sb-footer-left, .sb-footer-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .sb-insert-btn {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 500;
            color: #3ecf8e;
            background: rgba(62, 207, 142, 0.1);
            border: 1px solid rgba(62, 207, 142, 0.3);
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .sb-insert-btn:hover {
            background: rgba(62, 207, 142, 0.2);
            border-color: rgba(62, 207, 142, 0.5);
        }

        .sb-divider {
            width: 1px;
            height: 16px;
            background: #2e2e2e;
        }

        .sb-row-count {
            font-size: 11px;
            font-weight: 600;
            color: #6b6b6b;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .sb-page-info {
            font-size: 12px;
            color: #a0a0a0;
        }

        .sb-pagination {
            display: flex;
            gap: 4px;
        }

        .sb-page-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            background: #232323;
            border: 1px solid #383838;
            border-radius: 6px;
            color: #a0a0a0;
            cursor: pointer;
            transition: all 0.15s ease;
        }

        .sb-page-btn:hover:not([disabled]) {
            background: #2e2e2e;
            border-color: #4a4a4a;
            color: #ededed;
        }

        .sb-page-btn[disabled] {
            opacity: 0.3;
            cursor: not-allowed;
        }

        /* Icons */
        .sb-icon-xs {
            width: 12px;
            height: 12px;
        }

        .sb-icon-sm {
            width: 16px;
            height: 16px;
        }

        [x-cloak] {
            display: none !important;
        }
    </style>
</div>