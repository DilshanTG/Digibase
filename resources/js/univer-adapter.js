console.log("📍 Univer Adapter Module starting execution...");

import {
    Univer,
    LocaleType,
    UniverInstanceType,
    ICommandService,
    Tools
} from "@univerjs/core";
import { UniverRenderEnginePlugin } from "@univerjs/engine-render";
import { UniverFormulaEnginePlugin } from "@univerjs/engine-formula";
import { UniverUIPlugin } from "@univerjs/ui";
import { UniverSheetsPlugin } from "@univerjs/sheets";
import { UniverSheetsUIPlugin } from "@univerjs/sheets-ui";
import { UniverSheetsFormulaPlugin } from "@univerjs/sheets-formula";
import { UniverDocsPlugin } from "@univerjs/docs";
import { UniverDocsUIPlugin } from "@univerjs/docs-ui";

// Import Styles
import "@univerjs/design/lib/index.css";
import "@univerjs/ui/lib/index.css";
import "@univerjs/docs-ui/lib/index.css";
import "@univerjs/sheets-ui/lib/index.css";

// Import Locales (Fixed: Removed invalid engine-formula locale path)
import enUS from "@univerjs/design/lib/locale/en-US";
import uiEnUS from "@univerjs/ui/lib/locale/en-US";
import docsUIEnUS from "@univerjs/docs-ui/lib/locale/en-US";
import sheetsEnUS from "@univerjs/sheets/lib/locale/en-US";
import sheetsUIEnUS from "@univerjs/sheets-ui/lib/locale/en-US";

/**
 * Global Initialization Function
 */
window.initUniverInstance = function (containerId, options = {}) {
    console.log("🚀 window.initUniverInstance START", { containerId });

    const { tableName, apiToken, schema, records } = options;

    try {
        // 1. Initialize Univer with Locales
        const univer = new Univer({
            locale: LocaleType.EN_US,
            locales: {
                [LocaleType.EN_US]: Tools.deepMerge(
                    enUS,
                    uiEnUS,
                    docsUIEnUS,
                    sheetsEnUS,
                    sheetsUIEnUS
                ),
            },
        });

        // 2. Register Plugins
        univer.registerPlugin(UniverRenderEnginePlugin);
        univer.registerPlugin(UniverFormulaEnginePlugin);

        // UI Plugin - Order matters!
        univer.registerPlugin(UniverUIPlugin, {
            container: containerId,
            header: true,
            toolbar: true,
            footer: true,
        });

        // Core Docs (Required for rich text & cell editing)
        univer.registerPlugin(UniverDocsPlugin);
        univer.registerPlugin(UniverDocsUIPlugin);

        // Sheets Engine & UI
        univer.registerPlugin(UniverSheetsPlugin);
        univer.registerPlugin(UniverSheetsUIPlugin);
        univer.registerPlugin(UniverSheetsFormulaPlugin);

        // 3. Prepare Data
        const cellData = convertLaravelToUniver(schema, records);

        // 4. Create Workbook
        const workbook = univer.createUnit(UniverInstanceType.UNIVER_SHEET, {
            id: 'digibase-sheet-' + tableName,
            name: tableName.toUpperCase(),
            sheets: {
                'sheet-01': {
                    id: 'sheet-01',
                    name: 'Data',
                    cellData: cellData,
                    rowCount: Math.max(records.length + 50, 100),
                    columnCount: Math.max(schema.length + 10, 26),
                    columnData: schema.reduce((acc, col, i) => {
                        acc[i] = { w: 180 }; // Better default width
                        return acc;
                    }, {})
                }
            }
        });

        // 5. Setup Auto-Save via CommandService
        const commandService = univer.__getInjector().get(ICommandService);

        let isSaving = false;
        commandService.onCommandExecuted((command) => {
            if (command.id === 'sheet.command.set-range-values') {
                handleUpdate(command.params, schema, records, tableName, apiToken, () => isSaving, (val) => isSaving = val);
            }
        });

        console.log("✅ Univer Intelligence ONLINE for " + tableName);
        return { univer, workbook };
    } catch (err) {
        console.error("🔥 Univer Engine Exception:", err);
    }
};

/**
 * Mapping Helper
 */
function convertLaravelToUniver(schema, records) {
    const cellData = {};
    const columns = schema.map(f => ({ key: f.name, title: f.label || f.name }));

    // Header Row (Row 0)
    cellData[0] = {};
    columns.forEach((col, i) => {
        cellData[0][i] = {
            v: col.title,
            s: {
                bl: 1,
                fs: 11,
                cl: { rgb: '#FFFFFF' },
                bg: { rgb: '#4f46e5' }, // Modern Indigo
                ht: 2, // Center align
                vt: 2  // Middle align
            }
        };
    });

    // Content Rows
    records.forEach((record, rowIndex) => {
        const r = rowIndex + 1;
        cellData[r] = {};
        columns.forEach((col, colIndex) => {
            const val = record[col.key];
            cellData[r][colIndex] = {
                v: val !== null && val !== undefined ? String(val) : '',
                s: {
                    fs: 10,
                    ht: 1, // Left align
                    vt: 2  // Middle align
                }
            };
        });
    });

    return cellData;
}

/**
 * Save Handler
 */
async function handleUpdate(params, schema, records, tableName, apiToken, getIsSaving, setIsSaving) {
    const { cellValue } = params;
    const columns = schema.map(f => ({ key: f.name }));

    for (const r in cellValue) {
        const rowIndex = parseInt(r);
        if (rowIndex === 0) continue; // Don't save header changes

        const record = records[rowIndex - 1];
        if (!record || !record.id) continue;

        const rowChanges = cellValue[r];
        const updates = {};

        for (const c in rowChanges) {
            const colIndex = parseInt(c);
            if (!columns[colIndex]) continue;

            const colKey = columns[colIndex].key;
            updates[colKey] = rowChanges[colIndex].v;
        }

        if (Object.keys(updates).length > 0 && !getIsSaving()) {
            setIsSaving(true);
            try {
                await fetch(`/api/data/${tableName}/${record.id}`, {
                    method: 'PUT',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-API-KEY': apiToken,
                        'Accept': 'application/json'
                    },
                    body: JSON.stringify(updates)
                });
                console.log(`✅ Saved record ${record.id}`);
            } catch (e) {
                console.error(`❌ Save failed for ${record.id}:`, e);
            } finally {
                setIsSaving(false);
            }
        }
    }
}

// Signal readiness
console.log("🏁 Univer Adapter Module signaling readiness...");
document.dispatchEvent(new CustomEvent('univer-ready'));
window.UniverAdapterReady = true;
