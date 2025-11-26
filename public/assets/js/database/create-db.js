// public/assets/js/database/create-db.js (Versión Corregida y Unificada)
import * as api from '../api.js';
import { openImportModal, initializeImportModal, setStockifyColumns } from '../import.js';
import * as setup from "../setupMiCuentaDropdown.js";

/**
 * Función auxiliar para leer valores numéricos de inputs.
 */
function getNum(id) {
    const el = document.getElementById(id);
    if (!el || el.classList.contains('hidden')) {
        return 0; // Si el input está oculto o no existe, su valor por defecto es 0
    }
    const value = el.value.trim();
    return (value === '' || isNaN(parseFloat(value))) ? 0 : parseFloat(value);
}

/**
 * LÓGICA CLAVE: Lee la configuración de tu HTML estético.
 */
function getUserPreferences() {

    // 1. Función auxiliar para leer el estado de los botones (si tienen la clase 'active')
    const getActiveState = (selector) => {
        const btn = document.querySelector(`.rc-btn[data-toggle="${selector}"]`);
        return btn?.classList.contains('active') ? 1 : 0;
    };

    // 2. Leer el estado de los botones
    const isGainActive = getActiveState('gain');
    const isStockActive = getActiveState('stock');

    // 3. Leer los radio buttons de "Margen de Ganancia"
    const gainTypeRadios = document.querySelectorAll('input[name="gain-type"]');
    let gainType = 'Porcentaje'; // Default
    gainTypeRadios.forEach(radio => {
        if (radio.checked) {
            gainType = radio.closest('label').textContent.trim();
        }
    });

    // 4. Leer el estado del checkbox "Establecer ahora" para el stock
    const setStockNow = document.getElementById('set-stock-now')?.checked;

    const preferences = {
        min_stock: {
            active: isStockActive,
            // Solo toma el valor si "Establecer ahora" está marcado
            default: (isStockActive && setStockNow) ? getNum('stock-value') : 0
        },
        sale_price: {
            active: getActiveState('sale'),
            default: 0 // Tu HTML no tiene input para esto, así que es 0
        },
        receipt_price: {
            active: getActiveState('buy'),
            default: 0 // Tu HTML no tiene input para esto, así que es 0
        },
        percentage_gain: {
            active: isGainActive && gainType === 'Porcentaje' ? 1 : 0,
            default: 0 // Tu HTML no tiene input para esto, así que es 0
        },
        hard_gain: {
            active: isGainActive && gainType === 'Valor fijo' ? 1 : 0,
            default: 0 // Tu HTML no tiene input para esto, así que es 0
        },
        // Tu HTML actual no tiene la sección de "Auto-Precio", así que la seteamos en 0
        auto_price: 0,
        auto_price_type: null
    };

    return preferences;
}

/**
 * Maneja los listeners de los botones del acordeón y los toggles.
 * (Esta función reemplaza a la antigua prepareRecomendedColumns)
 */
function setupAccordionToggles() {
    // 1. Lógica del Acordeón Principal
    const rcToggleHeader = document.getElementById('rc-toggle-header');
    const columnsContainer = document.getElementById('recomended-columns-form');

    rcToggleHeader.addEventListener('click', () => {
        rcToggleHeader.classList.toggle('open');
        columnsContainer.classList.toggle('open');
    });

    // 2. Lógica de los botones internos (Stock, Ganancia, etc.)
    document.querySelectorAll('.rc-btn[data-toggle]').forEach(btn => {
        btn.addEventListener('click', () => {
            const active = btn.classList.toggle('active');
            const col = btn.dataset.toggle;
            const extra = document.getElementById(`${col}-extra`);
            if (extra) {
                extra.classList.toggle('hidden', !active);
            }
        });
    });

    // 3. Lógica del checkbox "Establecer ahora" (para Stock Mínimo)
    const setNow = document.getElementById('set-stock-now');
    const stockInput = document.getElementById('stock-value');
    if (setNow && stockInput) {
        setNow.addEventListener('change', () => {
            stockInput.classList.toggle('hidden', !setNow.checked);
        });
    }
}

async function checkUserStatus() {
    try {
        const profileData = await api.getUserProfile();
        if (!profileData.success) {
            window.location.href = '/login.php'; // RUTA LIMPIA
        }
    } catch (error) {
        console.error("Error:", error);
        window.location.href = '/login.php'; // RUTA LIMPIA
    }
}


// --- INICIO DE EJECUCIÓN ---
document.addEventListener('DOMContentLoaded', async () => {

    // === Inicialización base ===
    initializeImportModal();
    await checkUserStatus();

    // === Header y menú “Mi cuenta” (RUTAS CORREGIDAS) ===
    const nav = document.getElementById('header-nav');
    if (nav) {
        nav.innerHTML = `
            <a href="/statistics.php" class="btn btn-secondary">Estadísticas</a>
            <div id="dropdown-container">
                <div class="btn btn-secondary" id="mi-cuenta-btn">Mi Cuenta</div>
                <div class="flex-column hidden" id="mi-cuenta-dropdown">
                    <a href="/configuration.php" class="btn btn-secondary">Configuración</a>
                    <a href="/configuration.php" class="btn btn-secondary">Modificaciones de Stock</a>
                    <a href="/configuration.php" class="btn btn-secondary">Soporte</a>
                    <a href="/logout.php" class="btn btn-secondary">Cerrar Sesión</a>
                </div>
            </div>
        `;
        setup.setupMiCuenta();
    }

    // === Llamada al preparador de Columnas ===
    setupAccordionToggles(); // <-- Llama la nueva función que SÍ funciona

    // === Formularios y botones ===
    const createDbForm = document.getElementById('createDbForm');
    const messageDiv = document.getElementById('message');
    const columnsInput = document.getElementById('columnsInput'); // <-- Tu Textarea

    if (!createDbForm) return;

    // --- Event Listener para el ENVÍO FINAL ---
    createDbForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        const preferences = getUserPreferences(); // <-- Llama la nueva función que SÍ funciona
        const dbName = document.getElementById('dbNameInput').value.trim();
        const submitButton = createDbForm.querySelector('button[type="submit"]');

        // Lógica de columnas (de tu HTML/JS)
        let columnList = columnsInput.value.split(',')
            .map(col => col.trim().toLowerCase().replace(/ /g, ''))
            .filter(col => col.length > 0); // Filtra vacíos

        // Evitar duplicados (Nano)
        columnList = [...new Set(columnList)];

        // === LÓGICA DE FUSIÓN DE COLUMNAS ===
        if (!columnList.includes('stock')) columnList.unshift('stock');
        /* const hasNameCol = columnList.includes('name') || columnList.includes('nombre');
        if (!hasNameCol) {
            columnList.unshift('name');
        }
        */

        // 2. Columnas recomendadas (basadas en los botones 'active')
        if (preferences.min_stock.active && !columnList.includes('min_stock')) columnList.push('min_stock');
        if (preferences.sale_price.active && !columnList.includes('sale_price')) columnList.push('sale_price');
        if (preferences.receipt_price.active && !columnList.includes('receipt_price')) columnList.push('receipt_price');

        if (preferences.percentage_gain.active && !columnList.includes('percentage_gain')) {
            columnList.push('percentage_gain');
        }
        if (preferences.hard_gain.active && !columnList.includes('hard_gain')) {
            columnList.push('hard_gain');
        }

        const finalColumns = columnList.join(',');

        if (!dbName || !finalColumns || finalColumns === "stock,name") {
            messageDiv.textContent = 'Por favor, completa el nombre y al menos una columna personalizada.';
            return;
        }

        submitButton.disabled = true;
        submitButton.textContent = 'Creando...';
        messageDiv.textContent = '';

        try {
            // Enviamos el payload que el Controller de Nano espera
            const result = await api.createDatabase({
                dbName: dbName,
                columns: finalColumns,
                preferences: preferences
            });

            if (result.success) {
                messageDiv.textContent = result.message + "\nSerás redirigido al panel.";
                window.location.href = '/dashboard.php'; // RUTA LIMPIA
            } else {
                messageDiv.textContent = `Error: ${result.message}`;
            }
        } catch (error) {
            messageDiv.textContent = `Error: ${error.message}`;
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = 'Crear Base de Datos';
        }
    });

    // --- Lógica del botón de Importación Opcional ---
    const prepareImportBtn = document.getElementById('prepare-import-btn');
    const importStatusDiv = document.getElementById('import-prepared-status');

    prepareImportBtn.addEventListener('click', () => {
        // Le pasamos las columnas que el usuario ya escribió
        const currentCols = columnsInput.value.split(',').map(c => c.trim()).filter(c => c);
        // Le pasamos las columnas recomendadas (si están activas)
        const prefs = getUserPreferences();
        if(prefs.min_stock.active) currentCols.push('min_stock');
        if(prefs.sale_price.active) currentCols.push('sale_price');
        if(prefs.receipt_price.active) currentCols.push('receipt_price');
        if(prefs.hard_gain.active) currentCols.push('hard_gain');
        if(prefs.percentage_gain.active) currentCols.push('percentage_gain');

        const finalColsForImport = [...currentCols];
        const uniqueCols = [...new Set(finalColsForImport)];

        setStockifyColumns(uniqueCols);
        openImportModal();
    });

    // Función global para que import.js pueda actualizar el estado
    window.updateImportStatus = (message) => {
        if(importStatusDiv) {
            importStatusDiv.textContent = message;
            prepareImportBtn.textContent = "Modificar Importación CSV";
        }
    }
});