// public/assets/js/import.js
import * as api from './api.js';

// --- Variables Globales ---
let modalElement, closeModalBtn, importCancelBtn, dropZone, fileInput, importStatus;
let step1, step2, mappingForm, validatePrepareBtn; // Cambio nombre de botones
let uploadedFile = null;
let currentStockifyColumns = [];

function setStockifyColumns(columns) {
    currentStockifyColumns = columns;
    console.log("Columnas de StockiFy actualizadas en import.js:", currentStockifyColumns);
}

// --- Funciones del Modal ---
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
    // Añado la clase 'hidden'
    modalElement.classList.add('hidden');
    console.log("Clase 'hidden' añadida al modal. Clases actuales:", modalElement.className); // DEBUG 2
}

function showStep(stepNumber) {
    if (!step1 || !step2 || !validatePrepareBtn) return;
    step1.classList.toggle('hidden', stepNumber !== 1);
    step2.classList.toggle('hidden', stepNumber !== 2);
    // Cambiamos nextButton por validatePrepareBtn
    validatePrepareBtn.classList.toggle('hidden', stepNumber !== 2);

    if (mappingForm) {
        mappingForm.classList.toggle('hidden', stepNumber !== 2);
    }
}

// --- Manejo de Archivo ---
function handleFileSelect(file) { /* ... (sin cambios, PERO ahora llama a fetchHeaders directamente) ... */
    if (!file || !file.type.match('text/csv')) { /* ... (mensaje error) ... */ return; }
    uploadedFile = file;
    importStatus.textContent = `Archivo seleccionado: ${file.name}`;
    importStatus.style.color = 'var(--accent-green)';
    // Directamente intento leer las cabeceras
    fetchHeaders();
}

async function fetchHeaders() {
    if (!uploadedFile) return; // Ya no muestro alerta, handleFileSelect valida

    importStatus.textContent = 'Leyendo cabeceras...';
    // Podríamos deshabilitar dropZone aquí

    const formData = new FormData();
    formData.append('csvFile', uploadedFile);

    try {
        // Llamo a la API para obtener cabeceras CSV
        const result = await api.getCsvHeaders(formData); // Asumimos que getCsvHeaders existe

        if (result.success) {
            console.log("Cabeceras CSV:", result.csvHeaders);
            console.log("Columnas StockiFy (del input):", currentStockifyColumns);

            // Uso las columnas del input como destino
            populateMappingUI(result.csvHeaders, currentStockifyColumns);
            showStep(2); // Paso al mapeo
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

    // Por cada columna de StockiFy, creamos una fila de mapeo
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
        console.error("Falta archivo subido o formulario de mapeo."); // DEBUG
        return;
    }

    validatePrepareBtn.disabled = true;
    validatePrepareBtn.textContent = 'Procesando...';
    importStatus.textContent = 'Validando y preparando datos...';
    console.log("Estado inicial seteado, procesando formulario..."); // DEBUG 2

    let mappingData, mapping, formData;
    try {
        mappingData = new FormData(mappingForm);
        console.log("FormData del mapeo creado:", mappingData); // DEBUG 3
        mapping = {};
        mappingData.forEach((value, key) => {
            if (key !== 'overwrite' && value !== "") {
                mapping[key] = parseInt(value, 10);
            }
        });
        console.log("Objeto de mapeo creado:", mapping); // DEBUG 4

        formData = new FormData();
        formData.append('csvFile', uploadedFile);
        formData.append('mapping', JSON.stringify(mapping));
        formData.append('overwrite', mappingData.has('overwrite') ? 'true' : 'false');
        console.log("FormData final creado, listo para enviar."); // DEBUG 5

    } catch (formError) {
        // Si hay un error al procesar el formulario ANTES de la llamada API
        console.error("Error al procesar datos del formulario:", formError);
        importStatus.textContent = `Error al procesar el mapeo: ${formError.message}`;
        importStatus.style.color = 'var(--accent-red)';
        validatePrepareBtn.disabled = false;
        validatePrepareBtn.textContent = 'Validar y Preparar Datos';
        return; // Detengo aquí si falla el form
    }

    try {
        console.log("Intentando llamar a api.prepareCsvImport..."); // DEBUG 6
        const result = await api.prepareCsvImport(formData);
        console.log("Respuesta de prepareCsvImport:", result); // DEBUG 7

        if (result.success) {
            console.log("API prepare fue exitosa. Actualizando estado y cerrando modal..."); // DEBUG Success

            // Verifico si la función global existe antes de llamarla
            if (typeof window.updateImportStatus === 'function') {
                window.updateImportStatus(`✔️ ${result.rowCount} filas preparadas para importar.`);
                console.log("Estado de importación actualizado."); // DEBUG Status Update
            } else {
                console.error("La función window.updateImportStatus no está definida."); // DEBUG Error
            }

            // Llamo a la función para cerrar
            closeImportModal();
            console.log("Modal cerrado."); // DEBUG Modal Close

        } else {
            // Esto no debería ejecutarse si success es true
            console.error("Resultado de API prepare fue false:", result.message); // DEBUG API False
            throw new Error(result.message);
        }
    } catch (apiError) {
        console.error("Error en la llamada API o procesamiento:", apiError); // DEBUG
        importStatus.textContent = `Error al preparar datos: ${apiError.message}`;
        importStatus.style.color = 'var(--accent-red)';
    } finally {
        validatePrepareBtn.disabled = false;
        validatePrepareBtn.textContent = 'Validar y Preparar Datos';
        console.log("Bloque finally ejecutado."); // DEBUG 8
    }
}


// --- Inicialización ---
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
        console.error("Error: Elemento #import-modal no encontrado."); // Aviso si falta el modal
        return;
    }

    modalElement.classList.add('hidden');
    showStep(1);

    // --- Listeners (UNA SOLA VEZ) ---

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

    // Botón principal del modal (Paso 2)
    validatePrepareBtn?.addEventListener('click', handleValidateAndPrepare);

    console.log("Modal de importación inicializado."); // Confirmación
}

// Exportamos solo lo necesario
export { openImportModal, initializeImportModal, setStockifyColumns };