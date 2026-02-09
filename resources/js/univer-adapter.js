console.log("📍 Univer Adapter Module starting execution...");

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

// Global instance keeper to prevent duplicates
let univerInstance = null;

/**
 * 🔄 Data Converter
 */
function convertToUniverData(dbData, schema) {
    console.log("🔄 Translating Data:", { records: dbData.length, fields: schema.length });

    const cellData = {};

    // Row 0: Headers
    if (!cellData[0]) cellData[0] = {};
    schema.forEach((field, colIndex) => {
        cellData[0][colIndex] = {
            v: field.name,
            t: 1,
            s: {
                bl: 1,
                bg: { rgb: '#f1f5f9' },
                ht: 2, // Center
                vt: 2  // Middle
            }
        };
    });

    // Row 1+: Data
    dbData.forEach((record, dataIndex) => {
        const rowIndex = dataIndex + 1;
        if (!cellData[rowIndex]) cellData[rowIndex] = {};

        schema.forEach((field, colIndex) => {
            let val = record[field.name];
            if (val === null || val === undefined) val = "";

            cellData[rowIndex][colIndex] = {
                v: String(val),
                m: String(val),
                t: 1
            };
        });
    });

    return {
        cellData,
        rowCount: Math.max(dbData.length + 50, 100),
        columnCount: Math.max(schema.length + 5, 26)
    };
}

/**
 * 🚀 Initialization Function
 */
window.initUniverInstance = function (containerId, tableData, schema, saveUrl, csrfToken) {
    console.log(`🚀 Booting Univer for #${containerId}`);

    // 1. Cleanup old instance
    if (univerInstance) {
        console.log("🧹 Cleaning up old instance...");
        try {
            univerInstance.dispose();
        } catch (e) {
            console.warn("Dispose error (can be ignored):", e);
        }
        univerInstance = null;
    }

    const container = document.getElementById(containerId);
    if (container) container.innerHTML = '';

    // 2. Prepare Data
    const { cellData, rowCount, columnCount } = convertToUniverData(tableData, schema);

    // 3. Init Core with Locales
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
    univerInstance = univer;

    // 4. Register Plugins
    univer.registerPlugin(UniverRenderEnginePlugin);
    univer.registerPlugin(UniverFormulaEnginePlugin);

    univer.registerPlugin(UniverUIPlugin, {
        container: containerId,
        header: true,
        toolbar: true,
        footer: true
    });

    univer.registerPlugin(UniverDocsPlugin);
    univer.registerPlugin(UniverDocsUIPlugin);
    univer.registerPlugin(UniverSheetsPlugin);
    univer.registerPlugin(UniverSheetsUIPlugin);
    univer.registerPlugin(UniverSheetsFormulaPlugin);
    univer.registerPlugin(UniverSheetsNumfmtPlugin);

    // 5. Create Workbook
    const workbook = univer.createUnit(UniverInstanceType.UNIVER_SHEET, {
        id: 'digibase-workbook',
        sheets: {
            'sheet-1': {
                name: 'Data',
                id: 'sheet-1',
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

    console.log("✅ Univer Intelligence ONLINE");

    // 6. 📡 ACTIVATE WRITE-BACK ENGINE
    setupWriteBack(univer, tableData, schema, saveUrl, csrfToken);
};

/**
 * 💾 Write-Back Logic
 */
function setupWriteBack(univer, tableData, schema, saveUrl, csrfToken) {
    const commandService = univer.__getInjector().get(ICommandService);

    commandService.onCommandExecuted((command) => {
        if (command.id === 'sheet.command.set-range-values') {
            const params = command.params;
            if (params && params.range && params.value) {
                handleEdit(params, tableData, schema, saveUrl, csrfToken);
            }
        }
    });
}

async function handleEdit(params, tableData, schema, saveUrl, csrfToken) {
    const { startRow, startColumn } = params.range;

    // Ignore Header Row (Row 0)
    if (startRow === 0) return;

    // Identify Record
    const dataIndex = startRow - 1;
    const record = tableData[dataIndex];

    if (!record || !record.id) {
        console.warn("⚠️ No ID found for row " + startRow);
        return;
    }

    // Identify Field
    const field = schema[startColumn];
    if (!field) return;

    // Get New Value
    const cellUpdate = params.value && params.value[startRow] && params.value[startRow][startColumn];
    if (!cellUpdate) return;

    const newValue = cellUpdate.v;

    console.log(`📝 Syncing Edit: ID ${record.id} | ${field.name} = ${newValue}`);

    // 🔥 Send API Request (Using CSRF and Session)
    try {
        const response = await fetch(`${saveUrl}/${record.id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                [field.name]: newValue
            })
        });

        if (!response.ok) throw new Error(`Server status: ${response.status}`);
        console.log("✅ Database Updated!");
    } catch (error) {
        console.error("❌ Sync Failed:", error);
    }
}

// Signal readiness
console.log("🏁 Univer Adapter Module signaling readiness...");
document.dispatchEvent(new CustomEvent('univer-ready'));
window.UniverAdapterReady = true;