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

// Global instance keeper
let univerInstance = null;

/**
 * 🛠️ CSS Patch for Editing
 * Forces the Univer Editor popup to appear above Filament's UI
 */
function injectEditorStyles() {
    const styleId = 'univer-editor-fix';
    if (!document.getElementById(styleId)) {
        const style = document.createElement('style');
        style.id = styleId;
        style.innerHTML = `
            /* Ensure editor popups are visible */
            .univer-editor-container, .univer-popup-container, .univer-float-dom-container {
                z-index: 9999 !important;
            }
            /* Ensure the grid receives pointer events */
            .univer-render-canvas {
                pointer-events: auto !important;
            }
        `;
        document.head.appendChild(style);
    }
}

/**
 * 🔄 Data Converter (STRICT MODE)
 */
function convertToUniverData(dbData, schema) {
    console.log("🔄 Translating Data (Strict Mode):", { records: dbData.length, fields: schema.length });

    const cellData = {};

    // Row 0: Headers
    if (!cellData[0]) cellData[0] = {};
    schema.forEach((field, colIndex) => {
        cellData[0][colIndex] = {
            v: field.name,
            t: 1,
            s: {
                bl: 1,
                bg: { rgb: '#e2e8f0' }, // Filament Slate-200
                ht: 2, // Center
                vt: 2, // Middle
                fw: 600 // Semi-bold
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
        // STRICT LIMITS: Prevent users from scrolling into infinity
        rowCount: dbData.length + 10, // Only allow 10 empty rows at bottom
        columnCount: schema.length // EXACT column count. No ghost columns.
    };
}

/**
 * 🚀 Initialization Function
 */
window.initUniverInstance = function (containerId, tableData, schema, saveUrl, csrfToken, apiKey) {
    injectEditorStyles(); // Apply CSS patch
    console.log(`🚀 Booting Univer Strict Mode for #${containerId}`);

    // 1. Cleanup
    if (univerInstance) {
        try { univerInstance.dispose(); } catch (e) { }
        univerInstance = null;
    }
    const container = document.getElementById(containerId);
    if (container) container.innerHTML = '';

    // 2. Prepare Data
    const { cellData, rowCount, columnCount } = convertToUniverData(tableData, schema);

    // 3. Init Core
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

    // 4. Register Plugins (Order matters for dependency injection)
    univer.registerPlugin(UniverRenderEnginePlugin);
    univer.registerPlugin(UniverFormulaEnginePlugin);

    // UI Plugin with Full Features (Register early as other UI plugins depend on its services)
    univer.registerPlugin(UniverUIPlugin, {
        container: containerId,
        header: true,
        toolbar: true,
        footer: true, // Shows sheet tabs
    });

    univer.registerPlugin(UniverDocsPlugin);
    univer.registerPlugin(UniverDocsUIPlugin); // Crucial for cell editing
    univer.registerPlugin(UniverSheetsPlugin);
    univer.registerPlugin(UniverSheetsUIPlugin);
    univer.registerPlugin(UniverSheetsFormulaPlugin);
    univer.registerPlugin(UniverSheetsNumfmtPlugin);

    // 5. Create Workbook (The "Prison" for Data)
    univer.createUnit(UniverInstanceType.UNIVER_SHEET, {
        id: 'digibase-workbook',
        sheets: {
            'sheet-1': {
                name: 'Database',
                id: 'sheet-1',
                cellData: cellData,
                rowCount: rowCount,
                columnCount: columnCount, // Strict limit
                // Auto-width columns
                columnData: schema.reduce((acc, col, i) => {
                    acc[i] = { w: 150 };
                    return acc;
                }, {}),
                // Freeze the Header Row
                freeze: {
                    xSplit: 0,
                    ySplit: 1, // Freeze Row 1 (Header)
                    startRow: 0,
                    startColumn: 0,
                }
            }
        }
    });

    console.log("✅ Univer Intelligence ONLINE");

    // 6. Activate Write-Back
    setupWriteBack(univer, tableData, schema, saveUrl, csrfToken, apiKey);
};

/**
 * 💾 Write-Back Logic (with debounce and error revert)
 */
const pendingEdits = new Map(); // key: "row:col" -> timeout ID

function setupWriteBack(univer, tableData, schema, saveUrl, csrfToken, apiKey) {
    const commandService = univer.__getInjector().get(ICommandService);

    commandService.onCommandExecuted((command) => {
        // Listen for standard cell edits
        if (command.id === 'sheet.command.set-range-values') {
            const params = command.params;
            if (params && params.range && params.value) {
                debouncedEdit(params, tableData, schema, saveUrl, csrfToken, apiKey);
            }
        }
    });
}

function debouncedEdit(params, tableData, schema, saveUrl, csrfToken, apiKey) {
    const { startRow, startColumn } = params.range;
    const editKey = `${startRow}:${startColumn}`;

    // Clear previous pending edit for this cell
    if (pendingEdits.has(editKey)) {
        clearTimeout(pendingEdits.get(editKey));
    }

    // Debounce: wait 400ms before sending to avoid flooding during rapid typing
    const timeoutId = setTimeout(() => {
        pendingEdits.delete(editKey);
        handleEdit(params, tableData, schema, saveUrl, csrfToken, apiKey);
    }, 400);

    pendingEdits.set(editKey, timeoutId);
}

async function handleEdit(params, tableData, schema, saveUrl, csrfToken, apiKey) {
    const { startRow, startColumn } = params.range;

    if (startRow === 0) {
        console.warn("Header row is read-only");
        return;
    }

    const dataIndex = startRow - 1;
    const record = tableData[dataIndex];

    if (!record || !record.id) {
        console.warn("Cannot edit empty row. Please use 'Create' button to add records.");
        return;
    }

    const field = schema[startColumn];
    if (!field) return;

    // Extract value safely
    const cellUpdate = params.value && params.value[startRow] && params.value[startRow][startColumn];

    // Check if cell was cleared or updated
    let newValue = null;
    if (cellUpdate) {
        newValue = cellUpdate.v !== undefined ? cellUpdate.v : null;
    }

    // Store old value for revert on failure
    const oldValue = record[field.name];

    console.log(`Syncing Edit: ID ${record.id} | ${field.name} = ${newValue}`);

    try {
        const response = await fetch(`${saveUrl}/${record.id}`, {
            method: 'PUT',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
                'Accept': 'application/json',
                'x-api-key': apiKey
            },
            body: JSON.stringify({
                [field.name]: newValue
            })
        });

        if (!response.ok) {
            const errorBody = await response.text().catch(() => '');
            console.error(`Save Failed: ${response.status}`, errorBody);

            // Revert the local data model so future edits reference correct old value
            record[field.name] = oldValue;

            // Visual feedback: briefly flash the cell red (best-effort)
            alert(`Save failed for "${field.name}" (HTTP ${response.status}). The cell value has been reverted.`);
        } else {
            // Update local data model with new value so subsequent edits are correct
            record[field.name] = newValue;
            console.log("Database Updated!");
        }
    } catch (error) {
        console.error("Network Error:", error);
        record[field.name] = oldValue;
        alert(`Network error saving "${field.name}". The cell value has been reverted.`);
    }
}

// Signal readiness
console.log("🏁 Univer Adapter Module signaling readiness...");
document.dispatchEvent(new CustomEvent('univer-ready'));
window.UniverAdapterReady = true;