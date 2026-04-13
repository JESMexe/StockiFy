
import * as api from './api.js';
import { pop_ups } from "./notifications/pop-up.js";

let modalElement, closeModalBtn, importCancelBtn, dropZone, fileInput, importStatus;
let step1, step2, mappingForm, validatePrepareBtn;
let uploadedFile = null;
let currentStockifyColumns = [];
let detectedDelimiter = ','; // Variable para guardar el delimitador detectado

window.setStockifyColumns = function(columns) {
    currentStockifyColumns = columns || [];
};

export function openImportModal() {
    modalElement = document.getElementById('import-modal');
    if (!modalElement) return;

    modalElement.classList.remove('hidden');
    importStatus = document.getElementById('import-status');
    fileInput = document.getElementById('csv-file-input');
    dropZone = document.getElementById('import-drop-zone');
    step1 = document.getElementById('import-step-1');
    step2 = document.getElementById('import-step-2');
    validatePrepareBtn = document.getElementById('validate-prepare-btn');
    mappingForm = document.getElementById('mapping-form');

    showStep(1);
    if(importStatus) importStatus.textContent = '';
    uploadedFile = null;
    detectedDelimiter = ',';
    if (fileInput) fileInput.value = '';

    setupEventListeners();
}

function closeImportModal() {
    if (modalElement) modalElement.classList.add('hidden');
}

function showStep(stepNumber) {
    if (!step1 || !step2) return;
    step1.classList.toggle('hidden', stepNumber !== 1);
    step2.classList.toggle('hidden', stepNumber !== 2);

    if(validatePrepareBtn) validatePrepareBtn.classList.toggle('hidden', stepNumber !== 2);
    if(mappingForm) mappingForm.classList.toggle('hidden', stepNumber !== 2);
}

function setupEventListeners() {
    closeModalBtn = document.getElementById('close-modal-btn');
    importCancelBtn = document.getElementById('import-cancel-btn');

    if(closeModalBtn) {
        const newBtn = closeModalBtn.cloneNode(true);
        closeModalBtn.parentNode.replaceChild(newBtn, closeModalBtn);
        closeModalBtn = newBtn;
        closeModalBtn.addEventListener('click', closeImportModal);
    }

    if(importCancelBtn) {
        const newCancel = importCancelBtn.cloneNode(true);
        importCancelBtn.parentNode.replaceChild(newCancel, importCancelBtn);
        importCancelBtn = newCancel;
        importCancelBtn.addEventListener('click', closeImportModal);
    }

    if(validatePrepareBtn) {
        const newValidate = validatePrepareBtn.cloneNode(true);
        validatePrepareBtn.parentNode.replaceChild(newValidate, validatePrepareBtn);
        validatePrepareBtn = newValidate;
        validatePrepareBtn.addEventListener('click', handlePrepareImport);
    }

    if(dropZone) {
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

    if(fileInput) {
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
    if(importStatus) importStatus.innerHTML = `<i class="ph ph-spinner ph-spin"></i> Analizando <b>${file.name}</b>...`;

    const formData = new FormData();
    formData.append('csv_file', file);

    try {
        const response = await api.getCsvHeaders(formData);

        if (response.success) {
            detectedDelimiter = response.delimiter || ',';
            console.log("Delimitador detectado:", detectedDelimiter);

            generateMappingTable(response.headers, response.ui_headers || response.headers);
            showStep(2);
            if(importStatus) importStatus.textContent = '';
        } else {
            throw new Error(response.message || 'Error al leer cabeceras.');
        }
    } catch (error) {
        console.error(error);
        if(importStatus) importStatus.textContent = '';
        pop_ups.error(error.message, "Error de Lectura");
    }
}

function generateMappingTable(csvHeaders, uiHeaders) {
    if (!mappingForm) return;
    mappingForm.innerHTML = '';

    const gridContainer = document.createElement('div');
    gridContainer.className = 'mapping-grid';
    gridContainer.style.display = 'grid';
    gridContainer.style.gridTemplateColumns = 'repeat(auto-fill, minmax(300px, 1fr))';
    gridContainer.style.gap = '15px';

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

        csvHeaders.forEach((headerVal, index) => {
            const option = document.createElement('option');
            option.value = headerVal;
            option.textContent = uiHeaders[index];

            const cleanSys = sysCol.toLowerCase().replace(/[^a-z0-9]/g, '');
            const cleanCsv = headerVal.toLowerCase().replace(/[^a-z0-9]/g, '');

            if (cleanSys === cleanCsv || cleanCsv.includes(cleanSys) || cleanSys.includes(cleanCsv)) {
                option.selected = true;
                select.style.borderColor = 'var(--primary-color, #4CAF50)';
                select.style.backgroundColor = '#f0fff4';
            }
            select.appendChild(option);
        });

        card.appendChild(label);
        card.appendChild(select);
        gridContainer.appendChild(card);
    });

    mappingForm.appendChild(gridContainer);
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

    if(validatePrepareBtn) {
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
        if(validatePrepareBtn) {
            validatePrepareBtn.disabled = false;
            validatePrepareBtn.textContent = 'Confirmar e Importar';
        }
    }
}

