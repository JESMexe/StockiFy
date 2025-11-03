// public/assets/js/import.js
import * as api from './api.js';

let modalElement, closeModalBtn, importCancelBtn, dropZone, fileInput, importStatus;
let step1, step2, mappingForm, validatePrepareBtn; // Cambio nombre de botones
let uploadedFile = null;
let currentStockifyColumns = [];

function setStockifyColumns(columns) {
    currentStockifyColumns = columns;
    console.log("Columnas de StockiFy actualizadas en import.js:", currentStockifyColumns);
}

function openImportModal() {
    console.log("Función openImportModal FUE LLAMADA.");
    if (!modalElement) return;
    console.log("A");

    modalElement.classList.remove('hidden');
    showStep(1); // Siempre empezamos en el paso 1
    importStatus.textContent = '';
    uploadedFile = null;
    if (fileInput) fileInput.value = '';
}

function closeImportModal() {
    console.log("Intentando cerrar modal. Elemento:", modalElement); // DEBUG 1
    if (!modalElement) {
        console.error("Error: modalElement no encontrado al intentar cerrar."); // DEBUG Error
        return;
    }
    modalElement.classList.add('hidden');
    console.log("Clase 'hidden' añadida al modal. Clases actuales:", modalElement.className); // DEBUG 2
}

function showStep(stepNumber) {
    if (!step1 || !step2 || !validatePrepareBtn) return;
    step1.classList.toggle('hidden', stepNumber !== 1);
    step2.classList.toggle('hidden', stepNumber !== 2);
    validatePrepareBtn.classList.toggle('hidden', stepNumber !== 2);

    if (mappingForm) {
        mappingForm.classList.toggle('hidden', stepNumber !== 2);
    }
}

// --- Manejo de Archivo ---
function handleFileSelect(file) {
    if (!file || !file.type.match('text/csv')) { /* ... (mensaje error) ... */ return; }
    uploadedFile = file;
    importStatus.textContent = `Archivo seleccionado: ${file.name}`;
    importStatus.style.color = 'var(--accent-green)';
    // Directamente intento leer las cabeceras
    fetchHeaders();
}

async function fetchHeaders() {
    if (!uploadedFile) return;

    importStatus.textContent = 'Leyendo cabeceras...';

    const formData = new FormData();
    formData.append('csvFile', uploadedFile);

    try {
        // Llamo a la API para obtener cabeceras CSV
        const result = await api.getCsvHeaders(formData);

        if (result.success) {
            console.log("Cabeceras CSV:", result.csvHeaders);
            console.log("Columnas StockiFy (del input):", currentStockifyColumns);

            // Uso las columnas del input como destino
            populateMappingUI(result.csvHeaders, currentStockifyColumns);
            showStep(2);
            importStatus.textContent = '';
        } else {
            throw new Error(result.message || 'Error desconocido del servidor.');
        }
    } catch (error) {
        importStatus.textContent = `Error al leer cabeceras: ${error.message}`;
        importStatus.style.color = 'var(--accent-red)';
    } finally {
        // Habilitar dropZone si lo deshabilitamos
    }
}

// --- Mapeo y Preparación ---
/**
 * Genera dinámicamente la interfaz para que el usuario mapee
 * las columnas del CSV a las columnas de StockiFy.
 * @param {string[]} csvHeaders - Array con los nombres de columna del archivo CSV.
 * @param {string[]} stockifyColumns - Array con los nombres de columna de la tabla StockiFy.
 */
function populateMappingUI(csvHeaders, stockifyColumns) {
    if (!mappingForm) return;
    mappingForm.innerHTML = ''; // Limpio el placeholder

    // Creo las cabeceras visuales
    mappingForm.insertAdjacentHTML('beforeend', `
        <div style="text-align: left; font-weight: bold;">Columna CSV</div>
        <div></div> <div style="text-align: left; font-weight: bold;">Columna StockiFy</div>
    `);

    // Por cada columna de StockiFy, creo una fila de mapeo
    stockifyColumns.forEach(stockifyCol => {
        // Ignoro 'id' y 'created_at' ya que son automáticas
        if (stockifyCol.toLowerCase() === 'id' || stockifyCol.toLowerCase() === 'created_at') {
            return;
        }

        // Creo el <select> para las columnas del CSV
        const select = document.createElement('select');
        select.name = stockifyCol; // El 'name' será la columna StockiFy de destino
        select.classList.add('csv-column-select');

        // Opción por defecto "No importar"
        const defaultOption = document.createElement('option');
        defaultOption.value = "";
        defaultOption.textContent = "-- Ignorar esta columna --";
        select.appendChild(defaultOption);

        // Opciones con las cabeceras del CSV
        csvHeaders.forEach((csvHeader, index) => {
            const option = document.createElement('option');
            option.value = index; // Guardo el ÍNDICE de la columna CSV
            option.textContent = csvHeader;
            if (csvHeader.trim().toLowerCase() === stockifyCol.trim().toLowerCase()) {
                option.selected = true;
            }
            select.appendChild(option);
        });

        const csvColDiv = document.createElement('div');
        csvColDiv.appendChild(select);
        const arrowDiv = document.createElement('div');
        arrowDiv.innerHTML = '&rarr;';
        arrowDiv.style.textAlign = 'center';
        const stockifyColDiv = document.createElement('div');
        stockifyColDiv.textContent = stockifyCol;
        stockifyColDiv.style.fontWeight = '500';

        // Añado la fila al grid del formulario
        mappingForm.appendChild(csvColDiv);
        mappingForm.appendChild(arrowDiv);
        mappingForm.appendChild(stockifyColDiv);
    });

    // Añado la opción de sobrescribir
    mappingForm.insertAdjacentHTML('beforeend', `
        <div style="grid-column: 1 / -1; margin-top: 1rem; text-align: left;">
            <input type="checkbox" id="overwrite-data" name="overwrite" value="true">
            <label for="overwrite-data"> Reemplazar todos los datos existentes en esta tabla</label>
        </div>
    `);
}

async function handleValidateAndPrepare(event) {
    event.preventDefault();

    if (!uploadedFile || !mappingForm) {
        console.error("Falta archivo subido o formulario de mapeo.");
        return;
    }

    validatePrepareBtn.disabled = true;
    validatePrepareBtn.textContent = 'Procesando...';
    importStatus.textContent = 'Validando y preparando datos...';

    // Preparo el FormData como antes
    const mappingData = new FormData(mappingForm);
    const mapping = {};
    mappingData.forEach((value, key) => {
        if (key !== 'overwrite' && value !== "") {
            mapping[key] = parseInt(value, 10);
        }
    });
    const formData = new FormData();
    formData.append('csvFile', uploadedFile);
    formData.append('mapping', JSON.stringify(mapping));
    formData.append('overwrite', mappingData.has('overwrite') ? 'true' : 'false');

    try {
        console.log("Intentando llamar a api.prepareCsvImport..."); // DEBUG 1
        // 1. Solo llamo a PREPARAR
        const resultPrepare = await api.prepareCsvImport(formData);
        console.log("Respuesta de prepareCsvImport:", resultPrepare); // DEBUG 2

        if (resultPrepare.success) {
            console.log("API prepare fue exitosa."); // DEBUG 3

            // Verifico si estoy en create-db.php (donde existe window.updateImportStatus)
            if (typeof window.updateImportStatus === 'function') {
                console.log("Estoy en create-db.php. Actualizando estado."); // DEBUG 4
                window.updateImportStatus(`✔️ ${resultPrepare.rowCount} filas preparadas para importar.`);
            } else {
                // Estoy en dashboard.php, acá SÍ llamo a executeImport
                console.log("Estoy en dashboard.php. Llamando a executeImport..."); // DEBUG 4b
                const resultExecute = await api.executeImport();
                console.log("Respuesta de executeImport:", resultExecute);
                if (resultExecute.success) {
                    alert(`${resultExecute.insertedRows} filas importadas con éxito.`);
                    location.reload(); // Recargo el dashboard
                } else {
                    throw new Error(resultExecute.message); // Error en execute
                }
            }

            closeImportModal(); // Cierro el modal
            console.log("Modal cerrado."); // DEBUG 5

        } else {
            // Si resultPrepare.success es false
            console.error("Resultado de API prepare fue false:", resultPrepare.message);
            throw new Error(resultPrepare.message);
        }
    } catch (apiError) {
        console.error("Error en la llamada API o procesamiento:", apiError);
        importStatus.textContent = `Error: ${apiError.message}`;
        importStatus.style.color = 'var(--accent-red)';
    } finally {
        validatePrepareBtn.disabled = false;
        validatePrepareBtn.textContent = 'Validar y Preparar Datos';
        console.log("Bloque finally ejecutado.");
    }
}


// --- Inicialización ---
function initializeImportModal() {
    modalElement = document.getElementById('import-modal');
    closeModalBtn = document.getElementById('close-modal-btn');
    importCancelBtn = document.getElementById('import-cancel-btn');
    dropZone = document.getElementById('drop-zone');
    fileInput = document.getElementById('csv-file-input');
    importStatus = document.getElementById('import-status');
    step1 = document.getElementById('import-step-1');
    step2 = document.getElementById('import-step-2');
    mappingForm = document.getElementById('mapping-form');
    validatePrepareBtn = document.getElementById('validate-prepare-btn');

    if (!modalElement) {
        console.error("Error: Elemento #import-modal no encontrado.");
        return;
    }

    modalElement.classList.add('hidden');
    showStep(1);


    // Cerrar con X o Cancelar
    closeModalBtn?.addEventListener('click', closeImportModal);
    importCancelBtn?.addEventListener('click', closeImportModal);
    // Cerrar haciendo clic en el fondo
    modalElement.addEventListener('click', (event) => {
        if (event.target === modalElement) {
            closeImportModal();
        }
    });

    // Abrir selector de archivo al hacer clic en dropzone
    dropZone?.addEventListener('click', () => fileInput?.click());
    // Manejar archivo seleccionado
    fileInput?.addEventListener('change', (event) => { if (event.target.files.length > 0) handleFileSelect(event.target.files[0]); });

    // Manejar Drag and Drop
    dropZone?.addEventListener('dragover', (e) => { e.preventDefault(); dropZone.classList.add('drag-over'); });
    dropZone?.addEventListener('dragleave', () => dropZone.classList.remove('drag-over'));
    dropZone?.addEventListener('drop', (e) => { e.preventDefault(); dropZone.classList.remove('drag-over'); if (e.dataTransfer.files.length > 0) handleFileSelect(e.dataTransfer.files[0]); });

    // Botón principal del modal
    validatePrepareBtn?.addEventListener('click', handleValidateAndPrepare);

    console.log("Modal de importación inicializado."); // Confirmación
}

// Exportamos solo lo necesario
export { openImportModal, initializeImportModal, setStockifyColumns };