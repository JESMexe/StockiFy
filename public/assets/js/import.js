
import * as api from './api.js';
import { pop_ups } from "./notifications/pop-up.js";

let modalElement, closeModalBtn, importCancelBtn, dropZone, fileInput, importStatus;
let step1, step2, mappingForm, validatePrepareBtn;
let uploadedFile = null;
let currentStockifyColumns = [];
let detectedDelimiter = ','; // Variable para guardar el delimitador detectado

window.setStockifyColumns = function (columns) {
    currentStockifyColumns = columns || [];
};

export function openImportModal() {
    modalElement = document.getElementById('import-modal');
    if (!modalElement) return;

    modalElement.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    importStatus = document.getElementById('import-status');
    fileInput = document.getElementById('csv-file-input');
    dropZone = document.getElementById('import-drop-zone');
    step1 = document.getElementById('import-step-1');
    step2 = document.getElementById('import-step-2');
    validatePrepareBtn = document.getElementById('validate-prepare-btn');
    mappingForm = document.getElementById('mapping-form');

    showStep(1);
    if (importStatus) importStatus.textContent = '';
    uploadedFile = null;
    detectedDelimiter = ',';
    if (fileInput) fileInput.value = '';

    setupEventListeners();
}

function closeImportModal() {
    if (modalElement) {
        modalElement.classList.add('hidden');
        document.body.style.overflow = '';
    }
}

function showStep(stepNumber) {
    if (!step1 || !step2) return;
    step1.classList.toggle('hidden', stepNumber !== 1);
    step2.classList.toggle('hidden', stepNumber !== 2);

    if (validatePrepareBtn) validatePrepareBtn.classList.toggle('hidden', stepNumber !== 2);
    if (mappingForm) mappingForm.classList.toggle('hidden', stepNumber !== 2);
}

function setupEventListeners() {
    closeModalBtn = document.getElementById('close-modal-btn');
    importCancelBtn = document.getElementById('import-cancel-btn');

    if (closeModalBtn) {
        const newBtn = closeModalBtn.cloneNode(true);
        closeModalBtn.parentNode.replaceChild(newBtn, closeModalBtn);
        closeModalBtn = newBtn;
        closeModalBtn.addEventListener('click', closeImportModal);
    }

    if (importCancelBtn) {
        const newCancel = importCancelBtn.cloneNode(true);
        importCancelBtn.parentNode.replaceChild(newCancel, importCancelBtn);
        importCancelBtn = newCancel;
        importCancelBtn.addEventListener('click', closeImportModal);
    }

    if (validatePrepareBtn) {
        const newValidate = validatePrepareBtn.cloneNode(true);
        validatePrepareBtn.parentNode.replaceChild(newValidate, validatePrepareBtn);
        validatePrepareBtn = newValidate;
        validatePrepareBtn.addEventListener('click', handlePrepareImport);
    }

    if (dropZone) {
        const newZone = dropZone.cloneNode(true);
        dropZone.parentNode.replaceChild(newZone, dropZone);
        dropZone = newZone;

        dropZone.addEventListener('click', () => fileInput && fileInput.click());
        dropZone.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('drag-over'); });
        dropZone.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('drag-over');
            if (e.dataTransfer.files.length > 0) handleFileSelect(e.dataTransfer.files[0]);
        });
    }

    if (fileInput) {
        const newInput = fileInput.cloneNode(true);
        fileInput.parentNode.replaceChild(newInput, fileInput);
        fileInput = newInput;
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) handleFileSelect(e.target.files[0]);
        });
    }
}

async function handleFileSelect(file) {
    if (!file.name.toLowerCase().endsWith('.csv') && file.type !== 'text/csv' && file.type !== 'application/vnd.ms-excel') {
        pop_ups.error("Por favor selecciona un archivo CSV válido.", "Archivo Incorrecto");
        return;
    }

    uploadedFile = file;
    if (importStatus) importStatus.innerHTML = `<i class="ph ph-spinner ph-spin"></i> Analizando <b>${file.name}</b>...`;

    const formData = new FormData();
    formData.append('csv_file', file);

    try {
        const response = await api.getCsvHeaders(formData);

        if (response.success) {
            detectedDelimiter = response.delimiter || ',';
            console.log("Delimitador detectado:", detectedDelimiter);

            generateMappingTable(response.headers, response.ui_headers || response.headers, response.ui_headers || response.headers);
            showStep(2);
            if (importStatus) importStatus.textContent = '';
        } else {
            throw new Error(response.message || 'Error al leer cabeceras.');
        }
    } catch (error) {
        console.error(error);
        if (importStatus) importStatus.textContent = '';
        pop_ups.error(error.message, "Error de Lectura");
    }
}

// csvHeaders = sanitized names (used for auto-match logic)
// uiHeaders  = original CSV header text (used as option VALUES so prepare-csv.php can do exact lookup)
// rawHeaders = same as uiHeaders (original text from CSV)
function generateMappingTable(csvHeaders, uiHeaders, rawHeaders) {
    rawHeaders = rawHeaders || uiHeaders;
    if (!mappingForm) return;
    mappingForm.innerHTML = '';

    const gridContainer = document.createElement('div');
    gridContainer.className = 'mapping-grid';
    gridContainer.style.display = 'grid';
    gridContainer.style.gridTemplateColumns = 'repeat(auto-fill, minmax(300px, 1fr))';
    gridContainer.style.gap = '15px';

    const aliasMap = {
        'min_stock': ['stockminimo', 'stockmnimo', 'minimo'],
        'sale_price': ['preciodeventa', 'precioventa', 'venta'],
        'receipt_price': ['preciodecompra', 'preciocompra', 'compra', 'costo'],
        'hard_gain': ['ganancia', 'gananciafija'],
        'percentage_gain': ['porcentaje', 'gananciaporcentaje']
    };

    const usedCsvHeaders = new Set();

    currentStockifyColumns.forEach(sysCol => {
        if (['id', 'created_at', 'updated_at'].includes(sysCol.toLowerCase())) return;

        const card = document.createElement('div');
        card.className = 'mapping-card input-group';
        card.style.background = 'var(--bg-secondary, #f8f9fa)';
        card.style.padding = '10px';
        card.style.borderRadius = '8px';
        card.style.border = '1px solid var(--border-color, #eee)';

        const label = document.createElement('label');
        label.className = 'form-label';
        label.style.display = 'block';
        label.style.marginBottom = '5px';
        label.style.fontWeight = '600';
        label.textContent = formatColumnName(sysCol);

        const select = document.createElement('select');
        select.name = `map[${sysCol}]`;
        select.className = 'form-select mapping-select';
        select.style.width = '100%';
        select.style.padding = '8px';
        select.style.borderRadius = '4px';
        select.style.border = '1px solid #ccc';

        const defaultOption = document.createElement('option');
        defaultOption.value = '';
        defaultOption.textContent = '-- Ignorar esta columna --';
        defaultOption.style.color = '#999';
        select.appendChild(defaultOption);

        let mapped = false;

        csvHeaders.forEach((headerVal, index) => {
            const option = document.createElement('option');
            // VALUE = original CSV header text so prepare-csv.php can do an exact lookup
            option.value = rawHeaders[index];
            option.textContent = uiHeaders[index];

            const cleanSys = sysCol.toLowerCase().replace(/[^a-z0-9]/g, '');
            const cleanCsv = headerVal.toLowerCase().replace(/[^a-z0-9]/g, '');

            const isAliasMatch = aliasMap[sysCol] && aliasMap[sysCol].some(alias => cleanCsv.includes(alias) || alias.includes(cleanCsv));

            if (!mapped && (cleanSys === cleanCsv || cleanCsv.includes(cleanSys) || cleanSys.includes(cleanCsv) || isAliasMatch)) {
                option.selected = true;
                select.style.borderColor = 'var(--primary-color, #4CAF50)';
                select.style.backgroundColor = '#f0fff4';
                usedCsvHeaders.add(rawHeaders[index]); // track by original header
                mapped = true;
            }
            select.appendChild(option);
        });

        // Event listener para actualizar dinámicamente las columnas usadas (opcional pero lo dejamos simple capturando al validar)
        select.addEventListener('change', function () {
            if (this.value) {
                this.style.borderColor = 'var(--primary-color, #4CAF50)';
                this.style.backgroundColor = '#f0fff4';
            } else {
                this.style.border = '1px solid #ccc';
                this.style.backgroundColor = '';
            }
        });

        card.appendChild(label);
        card.appendChild(select);
        gridContainer.appendChild(card);
    });

    mappingForm.appendChild(gridContainer);

    setTimeout(() => {
        const currentSelects = mappingForm.querySelectorAll('select');
        const actuallyUsed = new Set();
        currentSelects.forEach(sel => { if (sel.value) actuallyUsed.add(sel.value); });

        // Compare against rawHeaders (original text) since that's what we use as option values
        const unmappedCsvHeaders = rawHeaders.filter((h, i) => !actuallyUsed.has(h) && h.trim() !== '');

        if (unmappedCsvHeaders.length > 0) {
            const extraSection = document.createElement('div');
            extraSection.style.marginTop = '30px';
            extraSection.innerHTML = `
                <hr style="border: 1px dashed var(--border-color, #ccc); margin-bottom: 20px;">
                <h5 style="color: var(--text-color, #555); font-size: 15px; font-weight: 600; margin-bottom: 15px;">
                    Otras columnas detectadas en tu archivo:
                    <span style="display: block; font-size: 12px; font-weight: 400; color: #888; margin-top: 5px;">
                        Seleccionalas para crear la columna e importar sus datos al vuelo.
                    </span>
                </h5>
                <div id="dynamic-columns-container" style="display: flex; flex-wrap: wrap; gap: 10px;"></div>
            `;
            mappingForm.appendChild(extraSection);

            const dynContainer = document.getElementById('dynamic-columns-container');
            unmappedCsvHeaders.forEach(col => {
                const wrap = document.createElement('label');
                wrap.style.display = 'flex';
                wrap.style.alignItems = 'center';
                wrap.style.gap = '8px';
                wrap.style.padding = '8px 14px';
                wrap.style.background = 'var(--bg-secondary, #f8f9fa)';
                wrap.style.border = '1px solid var(--border-color, #eaeaea)';
                wrap.style.borderRadius = '20px';
                wrap.style.cursor = 'pointer';
                wrap.style.fontSize = '13px';
                wrap.style.transition = 'all 0.2s';

                wrap.onmouseover = () => wrap.style.borderColor = 'var(--accent-color, var(--primary-color, #4CAF50))';
                wrap.onmouseout = () => { if (!check.checked) wrap.style.borderColor = 'var(--border-color, #eaeaea)'; }

                const check = document.createElement('input');
                check.type = 'checkbox';
                check.className = 'dynamic-column-checkbox';
                // Store the original CSV header text as value
                check.value = col;

                check.addEventListener('change', () => {
                    if (check.checked) {
                        wrap.style.background = 'var(--accent-color-quat-opacity, #e8f5e9)';
                        wrap.style.borderColor = 'var(--accent-color, #4CAF50)';
                    } else {
                        wrap.style.background = 'var(--bg-secondary, #f8f9fa)';
                        wrap.style.borderColor = 'var(--border-color, #eaeaea)';
                    }
                });

                wrap.appendChild(check);
                wrap.appendChild(document.createTextNode(col));
                dynContainer.appendChild(wrap);
            });
        }
    }, 100);
}

function formatColumnName(name) {
    return name.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

async function handlePrepareImport() {
    if (!uploadedFile) return;

    const mappingData = {};
    const selects = mappingForm.querySelectorAll('select');
    let hasMapping = false;

    selects.forEach(select => {
        if (select.value) {
            const sysCol = select.name.match(/\[(.*?)\]/)[1];
            mappingData[sysCol] = select.value;
            hasMapping = true;
        }
    });

    const dynamicCheckboxes = mappingForm.querySelectorAll('.dynamic-column-checkbox:checked');
    if (dynamicCheckboxes.length > 0) {
        if (validatePrepareBtn) {
            validatePrepareBtn.disabled = true;
            validatePrepareBtn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Preparando DB...';
        }
        for (const cb of dynamicCheckboxes) {
            // cb.value = original CSV header text (e.g. "Código de Barras")
            const originalCsvHeader = cb.value;
            try {
                const addReq = await fetch('/api/table/manage-column.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'add_column',
                        inventoryId: typeof activeInventoryId !== 'undefined' ? activeInventoryId : window.activeInventoryId,
                        columnName: originalCsvHeader  // server will sanitize it
                    })
                });
                const addRes = await addReq.json();
                if (addRes.success) {
                    // Key = column name as created in DB (server sanitizes), value = original CSV header
                    // We use the original CSV header as both key and value; prepare-csv.php
                    // will resolve the DB column name via normKey and the CSV index via exact match
                    mappingData[originalCsvHeader] = originalCsvHeader;
                    hasMapping = true;
                } else {
                    console.error("Error backend al añadir columna: " + originalCsvHeader, addRes.message);
                }
            } catch (e) {
                console.error("No se pudo crear red: " + originalCsvHeader, e);
            }
        }
    }

    if (!hasMapping) {
        pop_ups.alert("Debes mapear al menos una columna para continuar.", "Atención");
        return;
    }

    const formData = new FormData();
    formData.append('csv_file', uploadedFile);
    formData.append('mapping', JSON.stringify(mappingData));
    formData.append('delimiter', detectedDelimiter);
    formData.append('inventory_id', activeInventoryId);

    const overwrite = document.getElementById('import-overwrite-toggle')?.checked ? '1' : '0';
    formData.append('overwrite', overwrite);

    if (validatePrepareBtn) {
        validatePrepareBtn.disabled = true;
        validatePrepareBtn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Procesando...';
    }

    try {
        const result = await api.prepareCsvImport(formData);

        if (result.success) {
            pop_ups.success("Datos preparados correctamente. Confirmando importación...", "Éxito");

            const execResult = await api.executeImport();
            if (execResult.success) {
                pop_ups.success(execResult.message, "Importación Completada");
                closeImportModal();
                if (window.loadTableData) window.loadTableData();
            } else {
                throw new Error(execResult.message);
            }
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        pop_ups.error(error.message, "Error en Importación");
    } finally {
        if (validatePrepareBtn) {
            validatePrepareBtn.disabled = false;
            validatePrepareBtn.textContent = 'Confirmar e Importar';
        }
    }
}

