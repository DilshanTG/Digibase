import { Univer, LocaleType, UniverInstanceType } from "@univerjs/core";
import { UniverRenderEnginePlugin } from "@univerjs/engine-render";
import { UniverFormulaEnginePlugin } from "@univerjs/engine-formula";
import { UniverUIPlugin } from "@univerjs/ui";
import { UniverSheetsPlugin } from "@univerjs/sheets";
import { UniverSheetsUIPlugin } from "@univerjs/sheets-ui";
import { UniverSheetsFormulaPlugin } from "@univerjs/sheets-formula";

// Import styles
import "@univerjs/design/lib/index.css";
import "@univerjs/ui/lib/index.css";
import "@univerjs/sheets-ui/lib/index.css";

/**
 * Digibase Univer Adapter
 * Translates Digibase API data to Univer format and handles auto-save.
 */
class UniverAdapter {
    constructor(containerId, options = {}) {
        this.containerId = containerId;
        this.tableName = options.tableName;
        this.apiToken = options.apiToken;
        this.univer = null;
        this.workbook = null;
        this.isSaving = false;
    }

    /**
     * Initialize Univer instance
     */
    init() {
        this.univer = new Univer({
            locale: LocaleType.EN_US,
        });

        // Register core plugins
        this.univer.registerPlugin(UniverRenderEnginePlugin);
        this.univer.registerPlugin(UniverFormulaEnginePlugin);
        this.univer.registerPlugin(UniverUIPlugin, {
            container: this.containerId,
            header: true,
            footer: true,
        });

        // Register Sheets plugins
        this.univer.registerPlugin(UniverSheetsPlugin);
        this.univer.registerPlugin(UniverSheetsUIPlugin);
        this.univer.registerPlugin(UniverSheetsFormulaPlugin);

        return this;
    }

    /**
     * Load Digibase data into a new workbook
     */
    loadData(schema, records) {
        const columns = schema.map(field => ({
            title: field.label || field.name,
            key: field.name
        }));

        // Convert Laravel records to Univer snapshot format
        const rowCount = Math.max(records.length + 10, 50); // Extra empty rows
        const columnCount = columns.length;

        const cellData = {};

        // 1. Add Header Row
        columns.forEach((col, index) => {
            cellData[0] = cellData[0] || {};
            cellData[0][index] = {
                v: col.title,
                s: { bl: 1, fs: 12, cl: { rgb: '#FFFFFF' }, bg: { rgb: '#6366f1' } } // PocketBase Blue Header
            };
        });

        // 2. Add Data Rows
        records.forEach((record, rowIndex) => {
            const r = rowIndex + 1; // row 0 is header
            cellData[r] = cellData[r] || {};

            columns.forEach((col, colIndex) => {
                const value = record[col.key];
                cellData[r][colIndex] = {
                    v: value !== null && value !== undefined ? String(value) : '',
                };
            });
        });

        // Create the workbook
        this.workbook = this.univer.createUnit(UniverInstanceType.UNIVER_SHEET, {
            id: 'digibase-sheet-' + this.tableName,
            name: this.tableName.toUpperCase(),
            styles: {},
            sheets: {
                'sheet-01': {
                    id: 'sheet-01',
                    name: 'Data',
                    cellData: cellData,
                    rowCount: rowCount,
                    columnCount: columnCount,
                    columnData: columns.reduce((acc, col, i) => {
                        acc[i] = { hd: false, w: 150 };
                        return acc;
                    }, {})
                }
            }
        });

        this.setupAutoSave(columns, records);

        return this;
    }

    /**
     * Listen for changes and push to API
     */
    setupAutoSave(columns, records) {
        // Univer 0.x uses a command listener for changes
        this.univer.onCommandExecuted((command) => {
            // Check if it's a cell edit command
            if (command.id === 'sheet.command.set-range-values') {
                const { params } = command;
                console.log('Cell Edited:', params);
                this.handleCellChange(params, columns, records);
            }
        });
    }

    async handleCellChange(params, columns, records) {
        const cellValue = params.cellValue;

        for (const r in cellValue) {
            const rowIndex = parseInt(r);
            if (rowIndex === 0) continue; // Skip header

            const recordIndex = rowIndex - 1;
            const record = records[recordIndex];
            if (!record || !record.id) continue;

            const rowChanges = cellValue[r];
            const updates = {};

            for (const c in rowChanges) {
                const colIndex = parseInt(c);
                const colKey = columns[colIndex].key;
                const newValue = rowChanges[c].v;

                updates[colKey] = newValue;
            }

            if (Object.keys(updates).length > 0) {
                await this.saveRecord(record.id, updates);
            }
        }
    }

    async saveRecord(id, data) {
        if (this.isSaving) return;
        this.isSaving = true;

        try {
            const response = await fetch(`/api/data/${this.tableName}/${id}`, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-API-KEY': this.apiToken,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(data)
            });

            if (!response.ok) throw new Error('Failed to save');

            console.log(`Record ${id} saved successfully`);
        } catch (error) {
            console.error('Save error:', error);
        } finally {
            this.isSaving = false;
        }
    }
}

// Export for use in Blade
window.UniverAdapter = UniverAdapter;

// Signal that the script is fully loaded and initialized
console.log('Univer Adapter script loaded and registered.');
document.dispatchEvent(new CustomEvent('univer-ready'));

