console.log("� Univer Adapter Module starting execution...");

/**
 * 🎨 UNIVERSAL STYLES (Crucial for the Grid)
 */
import "@univerjs/design/lib/index.css";
import "@univerjs/ui/lib/index.css";
import "@univerjs/docs-ui/lib/index.css";
import "@univerjs/sheets-ui/lib/index.css";

import {
    Univer,
    LocaleType,
    UniverInstanceType,
    ICommandService,
    Tools
} from "@univerjs/core";
import { defaultTheme } from "@univerjs/design";
import { UniverRenderEnginePlugin } from "@univerjs/engine-render";
import { UniverFormulaEnginePlugin } from "@univerjs/engine-formula";
import { UniverUIPlugin } from "@univerjs/ui";
import { UniverSheetsPlugin } from "@univerjs/sheets";
import { UniverSheetsUIPlugin } from "@univerjs/sheets-ui";
import { UniverSheetsFormulaPlugin } from "@univerjs/sheets-formula";
import { UniverSheetsNumfmtPlugin } from "@univerjs/sheets-numfmt";
import { UniverDocsPlugin } from "@univerjs/docs";
import { UniverDocsUIPlugin } from "@univerjs/docs-ui";

// Import Locales
import enUS from "@univerjs/design/lib/locale/en-US";
import uiEnUS from "@univerjs/ui/lib/locale/en-US";
import docsUIEnUS from "@univerjs/docs-ui/lib/locale/en-US";
import sheetsEnUS from "@univerjs/sheets/lib/locale/en-US";
import sheetsUIEnUS from "@univerjs/sheets-ui/lib/locale/en-US";

/**
 * 🔄 The Translator: Converts Laravel JSON -> Univer Grid Data
 * Improved: Added high-precision mapping and display values (m).
 */
function convertToUniverData(dbData, schema) {
    console.log("🔄 Translating Data:", { records: dbData.length, fields: schema.length });

    const cellData = {};

    // 1. Create Headers (Row 0)
    if (!cellData[0]) cellData[0] = {};
    schema.forEach((field, colIndex) => {
        cellData[0][colIndex] = {
            v: field.name.toUpperCase(),
            t: 1, // String
            s: {
                bl: 1, // Bold
                bg: { rgb: '#f1f5f9' }, // Gray Background
                ht: 2, // Center
                vt: 2  // Middle
            }
        };
    });

    // 2. Map Data Rows (Row 1 to N)
    dbData.forEach((record, dataIndex) => {
        const rowIndex = dataIndex + 1;
        if (!cellData[rowIndex]) cellData[rowIndex] = {};

        schema.forEach((field, colIndex) => {
            let val = record[field.name];

            // Safety check for nulls
            if (val === null || val === undefined) val = "";

            cellData[rowIndex][colIndex] = {
                v: String(val), // Raw Value
                m: String(val), // Display Value
                t: 1 // Type: String
            };
        });
    });

    console.log("✅ Translation Complete. Row 0 (Header):", cellData[0]);
    if (cellData[1]) console.log("✅ Row 1 Data:", cellData[1]);

    return {
        cellData,
        rowCount: Math.max(dbData.length + 50, 100),
        columnCount: Math.max(schema.length + 5, 26)
    };
}

/**
 * Global Initialization Function
 */
window.initUniverInstance = function (containerId, options = {}) {
    console.log(`🚀 Booting Univer for container: #${containerId}`);

    const { tableName, apiToken, schema, records } = options;

    // Debug: Check if data is actually arriving
    if (!records || records.length === 0) {
        console.warn("⚠️ WARNING: records is empty! The grid will be blank.");
    }

    try {
        // 1. Cleanup previous instance if exists (prevent memory leaks)
        const container = document.getElementById(containerId);
        if (container) container.innerHTML = '';

        // 2. Initialize Univer with Locales & Theme
        const univer = new Univer({
            theme: defaultTheme,
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

        // 3. Register Plugins
        univer.registerPlugin(UniverRenderEnginePlugin);
        univer.registerPlugin(UniverFormulaEnginePlugin);

        univer.registerPlugin(UniverUIPlugin, {
            container: containerId,
            header: true,
            toolbar: true,
            footer: true,
        });

        univer.registerPlugin(UniverDocsPlugin);
        univer.registerPlugin(UniverDocsUIPlugin);
        univer.registerPlugin(UniverSheetsPlugin);
        univer.registerPlugin(UniverSheetsUIPlugin);
        univer.registerPlugin(UniverSheetsFormulaPlugin);
        univer.registerPlugin(UniverSheetsNumfmtPlugin);

        // 4. Prepare Data using the Translator
        const { cellData, rowCount, columnCount } = convertToUniverData(records, schema);

        // 5. Create Workbook
        console.log("📝 Creating Workbook with Rows:", rowCount);
        const workbook = univer.createUnit(UniverInstanceType.UNIVER_SHEET, {
            id: 'digibase-sheet-' + tableName,
            name: tableName.toUpperCase(),
            sheets: {
                'sheet-01': {
                    id: 'sheet-01',
                    name: 'Data',
                    cellData: cellData,
                    rowCount: rowCount,
                    columnCount: columnCount,
                    columnData: schema.reduce((acc, col, i) => {
                        acc[i] = { w: 180 };
                        return acc;
                    }, {})
                }
            }
        });

        // 6. Setup Auto-Save
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
 * Save Handler
 */
async function handleUpdate(params, schema, records, tableName, apiToken, getIsSaving, setIsSaving) {
    const { cellValue } = params;
    const columns = schema.map(f => ({ key: f.name }));

    for (const r in cellValue) {
        const rowIndex = parseInt(r);
        if (rowIndex === 0) continue;

        const record = records[rowIndex - 1];
        if (!record || !record.id) continue;

        const rowChanges = cellValue[r];
        const updates = {};

        for (const c in rowChanges) {
            const colIndex = parseInt(c);
            if (!columns[colIndex]) continue;

            const colKey = columns[colIndex].key;
            updates[colKey] = rowChanges[c].v;
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