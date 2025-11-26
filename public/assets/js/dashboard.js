// assets/js/dashboard.js (Versión FINAL Unificada y Corregida)

// --- 1. IMPORTACIONES ---
import * as api from './api.js';
// Nano: Asumimos que este archivo existe y es necesario.
import * as setup from './setupMiCuentaDropdown.js';
import {notificationConfig, pop_ups} from './notifications/pop-up.js';
import {initializeImportModal, openImportModal, setStockifyColumns} from './import.js';

// --- 2. VARIABLES GLOBALES ---
let allData = []; // Guardo todos los datos para filtrar
let currentTableColumns = []; // Guardo las columnas de la tabla actual
let editingRowId = null; // Para saber qué fila estoy editando
let selectedSearchColumn = 'all'; // 'all' es el valor por defecto
let searchColumnBtn, searchColumnBtnText, searchColumnDropdown;
const protectedColumns = ['id', 'created_at'];

// Variables para el Modal de Eliminación (Tu Lógica)
let deleteModal, deleteConfirmInput, confirmDeleteBtn, deleteDbNameConfirmSpan, deleteErrorMsg;
let currentDbNameToDelete = '';
let columnListContainer, addColumnForm, columnListStatus;


// --- 3. FUNCIONES DEL NÚCLEO (Fusión y Correcciones de Lógica) ---

// -- Manejo de Stock --
async function handleStockUpdate(event) {
    const button = event.target.closest('.stock-btn');
    const input = event.target.closest('.stock-input');
    const cell = event.target.closest('.stock-cell');

    if (!cell || (!button && !input)) return;

    const itemId = cell.dataset.itemId;
    const stockInput = cell.querySelector('.stock-input');
    if (!itemId || !stockInput) {
        console.error("No se encontró itemId o stockInput para la celda.");
        return;
    }

    let action = null;
    let value = null;

    const originalRow = allData.find(row => (row.id ?? row.Id ?? row.ID) == itemId);
    const stockKey = originalRow ? Object.keys(originalRow).find(key => key.toLowerCase() === 'stock') : null;
    const originalValue = (originalRow && stockKey) ? originalRow[stockKey] : 0;

    if (button) {
        action = button.classList.contains('plus') ? 'add' : 'remove';
        value = 1;
    } else if (input && event.type === 'change') {
        action = 'set';
        value = parseInt(input.value, 10);
        if (isNaN(value) || value < 0) {
            pop_ups.warning("Ingresá un número de stock válido (mayor o igual a 0).", "Stock Inválido");
            if(stockInput) stockInput.value = originalValue ?? 0;
            return;
        }
    } else {
        return;
    }

    cell.querySelectorAll('button, input').forEach(el => el.disabled = true);

    try {
        const result = await api.updateStock(itemId, action, value);

        if (result.success) {
            stockInput.value = result.newStock;

            const rowIndex = allData.findIndex(row => (row.id ?? row.Id ?? row.ID) == itemId);
            if (rowIndex > -1 && stockKey) {
                allData[rowIndex][stockKey] = result.newStock;
            }
            pop_ups.info(`Stock actualizado a ${result.newStock}.`);
        } else {
            throw new Error(result.message || "Error desconocido del backend al actualizar stock.");
        }
    } catch (error) {
        pop_ups.error(`Error: ${error.message}`, "Error al actualizar stock");
        if(stockInput) stockInput.value = originalValue ?? 0;
    } finally {
        cell.querySelectorAll('button, input').forEach(el => el.disabled = false);
    }
}


/**
 * Renderiza la tabla principal con corrección de datos y estética unificada.
 */
// ... (Tus importaciones y variables globales se mantienen igual) ...

/**
 * Renderiza la tabla con soporte para MODO EDICIÓN y PREFERENCIAS FLEXIBLES.
 */
async function renderTable(columns, data) {
    const tableHead = document.querySelector('#data-table thead');
    const tableBody = document.querySelector('#data-table tbody');

    if (!tableHead || !tableBody) {
        console.error("Error crítico: No se encontraron los elementos de la tabla.");
        return;
    }

    // 1. Obtener Preferencias (Con manejo de errores)
    let inventoryPreferences = { min_stock: 1, sale_price: 1, receipt_price: 1, hard_gain: 1, percentage_gain: 1 };
    try {
        const response = await api.getCurrentInventoryPreferences();
        if (response && response.success) {
            inventoryPreferences = response;
        }
    } catch (error) {
        console.warn("Usando preferencias por defecto.");
    }

    // 2. Definir Columnas
    if ((!columns || columns.length === 0) && data && data.length > 0) {
        columns = Object.keys(data[0]);
        currentTableColumns = columns;
    }

    // 3. HEADERS
    const headerHTML = columns.map(col => {
        const c = col.toLowerCase();
        if (c === 'created_at' || c === 'user_id' || c === 'inventory_id') return '';
        if (c === 'min_stock' && inventoryPreferences.min_stock == 0) return '';
        if (c === 'sale_price' && inventoryPreferences.sale_price == 0) return '';
        if (c === 'receipt_price' && inventoryPreferences.receipt_price == 0) return '';
        if (c === 'hard_gain' && inventoryPreferences.hard_gain == 0) return '';
        if (c === 'percentage_gain' && inventoryPreferences.percentage_gain == 0) return '';

        // Títulos bonitos
        let label = col.charAt(0).toUpperCase() + col.slice(1).replace(/_/g, ' ');
        if (c === 'min_stock') label = 'Stock Mín.';
        if (c === 'sale_price') label = 'Precio Venta';
        if (c === 'receipt_price') label = 'Precio Compra';
        if (c === 'percentage_gain') label = 'Ganancia (%)';
        if (c === 'hard_gain') label = 'Ganancia ($)';
        if (c === 'id') label = 'ID';

        return `<th>${label}</th>`;
    }).join('') + '<th class="actions-header">Acciones</th>';

    tableHead.innerHTML = `<tr>${headerHTML}</tr>`;

    // 4. BODY
    if (!data || data.length === 0) {
        tableBody.innerHTML = `<tr><td colspan="100%" class="empty-state">Aún no hay datos ingresados.</td></tr>`;
    } else {
        tableBody.innerHTML = data.map(row => {
            const rowId = row['id'] ?? row['Id'] ?? row['ID'];
            const createdAt = row['created_at'] || '';
            const isEditing = (editingRowId == rowId); // ¿Esta fila se está editando?

            // Renderizado de Celdas
            const cellsHTML = columns.map(col => {
                const c = col.toLowerCase();
                // Mismos filtros que el header
                if (c === 'created_at' || c === 'user_id' || c === 'inventory_id') return '';
                if (c === 'min_stock' && inventoryPreferences.min_stock == 0) return '';
                if (c === 'sale_price' && inventoryPreferences.sale_price == 0) return '';
                if (c === 'receipt_price' && inventoryPreferences.receipt_price == 0) return '';
                if (c === 'hard_gain' && inventoryPreferences.hard_gain == 0) return '';
                if (c === 'percentage_gain' && inventoryPreferences.percentage_gain == 0) return '';

                let value = row[col] ?? '-';

                // --- MODO EDICIÓN ---
                if (isEditing) {
                    // Si es columna 'name', 'stock', precios, etc., mostramos INPUT
                    // (Evitamos editar columnas calculadas si es complejo, pero por ahora todo editable)
                    if (c === 'stock' || c.includes('price') || c.includes('gain') || c.includes('stock')) {
                        return `<td><input type="number" class="editing-input" data-col="${col}" value="${value}" step="0.01"></td>`;
                    }
                    return `<td><input type="text" class="editing-input" data-col="${col}" value="${value}"></td>`;
                }

                // --- MODO VISUALIZACIÓN ---

                // Stock Crítico
                if (c === 'stock') {
                    let minStock = row['min_stock'] ?? 0;
                    let classCritical = (inventoryPreferences.min_stock == 1 && Number(value) <= Number(minStock)) ? 'status-critical' : '';
                    return `<td class="${classCritical}">${value}</td>`;
                }

                // Moneda
                if ((c.includes('price') || c.includes('hard_gain')) && !isNaN(value) && value !== '-') {
                    value = `$${parseFloat(value).toFixed(2)}`;
                }
                // Porcentaje
                if (c.includes('percentage') && !isNaN(value) && value !== '-') {
                    value = `${value}%`;
                }

                return `<td>${value}</td>`;
            }).join('');

            // Celda de Acciones (Cambia según modo)
            let actionsHTML = '';
            if (isEditing) {
                actionsHTML = `
                    <td class="actions-cell">
                        <button class="action-btn save-btn" data-action="save" title="Guardar Cambios" style="color: var(--accent-green);">
                            <i class="ph ph-check"></i>
                        </button>
                        <button class="action-btn cancel-btn" data-action="cancel" title="Cancelar" style="color: var(--accent-red);">
                            <i class="ph ph-x"></i>
                        </button>
                    </td>
                `;
            } else {
                actionsHTML = `
                    <td class="actions-cell">
                        <button class="action-btn edit-btn" data-action="edit" title="Editar">
                            <i class="ph ph-pencil-simple"></i>
                        </button>
                        <button class="action-btn history-btn" data-action="history" title="Historial">
                            <i class="ph ph-clock"></i>
                        </button>
                        <button class="action-btn delete-btn" data-action="delete" title="Eliminar">
                            <i class="ph ph-trash"></i>
                        </button>
                    </td>
                `;
            }

            return `<tr data-id="${rowId}" data-created-at="${createdAt}">${cellsHTML}${actionsHTML}</tr>`;
        }).join('');
    }

    // 5. EVENTOS (Delegación actualizada)
    setupTableActions(tableBody, data);
}

function setupTableActions(tableBody, data) {
    const newBody = tableBody.cloneNode(true);
    tableBody.parentNode.replaceChild(newBody, tableBody);

    newBody.addEventListener('click', (e) => {
        const btn = e.target.closest('.action-btn');
        if (!btn) return;

        const tr = btn.closest('tr');
        const id = tr.dataset.id;
        const action = btn.dataset.action;

        if (action === 'edit') {
            // Activar modo edición para esta fila
            editingRowId = id;
            // Re-renderizar tabla para mostrar inputs en esta fila
            // Filtramos columnas para mantener la vista actual
            const filterCols = currentTableColumns.filter(c => c.toLowerCase() !== 'created_at');
            renderTable(filterCols, allData);
        }
        else if (action === 'save') {
            handleSaveRow(tr, id);
        }
        else if (action === 'cancel') {
            editingRowId = null; // Salir modo edición
            const filterCols = currentTableColumns.filter(c => c.toLowerCase() !== 'created_at');
            renderTable(filterCols, allData);
        }
        else if (action === 'history') {
            handleHistoryClick(tr, id);
        }
        else if (action === 'delete') {
            confirmDeleteRow(id);
        }
    });
}

// --- MANEJADORES DE ACCIONES ---

// 1. Historial con ID y Resaltado
function handleHistoryClick(tr, id) {
    // 1. Efecto Visual: Resaltar fila
    document.querySelectorAll('tr.highlight-row').forEach(r => r.classList.remove('highlight-row'));
    tr.classList.add('highlight-row');
    setTimeout(() => tr.classList.remove('highlight-row'), 4000); // Quitar a los 4s

    // 2. Datos
    const dateStr = tr.dataset.createdAt;
    const productName = tr.querySelector('td:nth-child(2)')?.textContent || 'Producto'; // Intentamos pillar el nombre

    if (dateStr && dateStr !== '0000-00-00 00:00:00') {
        const d = new Date(dateStr);
        const format = !isNaN(d) ? d.toLocaleString() : 'Fecha desconocida';

        // 3. Pop-up con ID y Nombre
        if (typeof pop_ups !== 'undefined') {
            pop_ups.info(
                `<b>Producto ID:</b> #${id}<br><b>Creado el:</b> ${format}`,
                "Historial de Registro"
            );
        } else {
            alert(`ID: ${id}\nCreado: ${format}`);
        }
    } else {
        if (typeof pop_ups !== 'undefined') pop_ups.warning("Este registro no tiene fecha de creación guardada.", "Sin Datos");
    }
}

// 2. Guardar Edición (API)
async function handleSaveRow(tr, id) {
    const inputs = tr.querySelectorAll('input.editing-input');
    let updatedData = {};

    // Recolectar datos de los inputs
    inputs.forEach(input => {
        updatedData[input.dataset.col] = input.value;
    });

    // Deshabilitar botón para evitar doble click
    const saveBtn = tr.querySelector('.save-btn');
    if(saveBtn) { saveBtn.disabled = true; saveBtn.innerHTML = '<i class="ph ph-spinner ph-spin"></i>'; }

    try {
        // Llamada a la API para actualizar (Asumimos que existe endpoint de update genérico)
        // Si usas el de Nano sería api.updateTableRow(id, updatedData)
        const response = await api.updateTableRow(id, updatedData); // Asegúrate que api.js tenga esto

        if (response.success) {
            pop_ups.success("Registro actualizado correctamente.");
            editingRowId = null;
            await loadTableData(); // Recargar datos frescos
        } else {
            throw new Error(response.message || "Error al actualizar.");
        }
    } catch (error) {
        pop_ups.error("Falló la actualización: " + error.message);
        if(saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = '<i class="ph ph-check"></i>'; }
    }
}

// 3. Eliminar Fila (API)
async function confirmDeleteRow(id) {
    // Preguntar antes de borrar
    if (!confirm("¿Estás seguro de eliminar este registro permanentemente?")) return;

    try {
        // Asumimos endpoint de borrado de fila (si no existe, hay que crearlo en backend)
        // api.deleteRecord es un nombre sugerido, revisa tu api.js
        // Si usas el código de Nano, busca su función de delete.
        // Si no existe, aquí simulo la llamada genérica:
        const response = await fetch('/api/table/delete-row.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id: id })
        }).then(res => res.json());

        if (response.success) {
            pop_ups.info("Registro eliminado.");
            // Eliminar visualmente antes de recargar para que se sienta rápido
            const row = document.querySelector(`tr[data-id="${id}"]`);
            if (row) row.remove();

            // Actualizar datos reales de fondo
            const allDataIndex = allData.findIndex(r => r.id == id);
            if (allDataIndex > -1) allData.splice(allDataIndex, 1);
        } else {
            throw new Error(response.message || "No se pudo eliminar.");
        }
    } catch (error) {
        pop_ups.error("Error al eliminar: " + error.message);
    }
}

// Función auxiliar para moneda
function formatCurrency(value) {
    if (value === undefined || value === null || value === '') return '-';
    return `$${parseFloat(value).toFixed(2)}`;
}



// --- 3. OTRAS FUNCIONES DEL NÚCLEO ---

// Tu lógica de Filtro y Edición se mantiene, se corrigió el alert por pop_ups.
function createInputForCell(columnName, value) {
    const colLower = columnName.toLowerCase();
    if (colLower === 'id' || colLower === 'created_at') {
        return value ?? '';
    }
    if (colLower === 'stock' || colLower === 'sale_price' || colLower === 'receipt_price') {
        return `<input type="number" data-column="${columnName}" value="${value ?? 0}">`;
    }
    return `<input type="text" data-column="${columnName}" value="${value ?? ''}">`;
}

function filterTable() {
    const searchInput = document.getElementById('search-input');
    if (!searchInput) return;
    const searchTerm = searchInput.value.toLowerCase();

    const filteredData = allData.filter(row => {
        if (searchTerm === '') return true; // Si no hay búsqueda, mostrar todo

        // Si buscamos en "Todas"
        if (selectedSearchColumn === 'all') {
            return Object.values(row).some(value =>
                String(value).toLowerCase().includes(searchTerm)
            );
        }
        // Si buscamos en una columna específica
        else {
            const rowKeys = Object.keys(row);
            const matchingKey = rowKeys.find(key => key.toLowerCase() === selectedSearchColumn.toLowerCase());

            if (matchingKey) {
                return String(row[matchingKey]).toLowerCase().includes(searchTerm);
            }
            return false;
        }
    });

    const filterableColumns = currentTableColumns.filter(
        col => col.toLowerCase() !== 'created_at'
    );

    // ¡OJO! Usamos allData filtrada, no la global
    renderTable(filterableColumns, filteredData);
}

function showDashboardView(viewId) {
    document.querySelectorAll('.dashboard-view').forEach(view => view.classList.add('hidden'));
    const viewToShow = document.getElementById(viewId);
    const transactionViews = ['sales','receipts', 'customers','providers'];
    if (viewToShow) { viewToShow.classList.remove('hidden'); }
    document.querySelectorAll('.menu-btn').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.targetView === viewId);
    });
}

function setupMenuNavigation() {
    const menuButtons = document.querySelectorAll('.menu-btn');
    menuButtons.forEach(button => {
        button.addEventListener('click', () => {
            const targetView = button.dataset.targetView;
            if (targetView === 'notifications') {
                loadNotifications(); // Carga el historial
            }
            if (targetView) { showDashboardView(targetView); }
            else { alert("Funcionalidad aún no implementada."); }
        });
    });
    console.log("Navegación del menú lateral configurada.");
}


function setupAccordion() {
    console.log("[INIT] Configurando acordeones inteligentes...");
    const headers = document.querySelectorAll('.accordion-header');

    headers.forEach(header => {
        // Evitamos duplicar listeners si la función se llama varias veces
        if (header.dataset.attached) return;
        header.dataset.attached = 'true';

        header.addEventListener('click', () => {
            const content = header.nextElementSibling;
            const isOpen = header.classList.toggle('active'); // Alternamos estado

            if (isOpen) {
                // --- ABRIR ---
                header.setAttribute('aria-expanded', 'true');
                content.classList.add('open');

                // 1. Asignamos la altura exacta actual para iniciar la animación
                content.style.maxHeight = content.scrollHeight + "px";

                // 2. TRUCO CLAVE: Al terminar la animación (400ms), quitamos el límite.
                // Esto permite que el contenido crezca dinámicamente (ej: al agregar columnas o desplegar opciones).
                setTimeout(() => {
                    if (header.classList.contains('active')) {
                        content.style.maxHeight = 'none';
                        content.style.overflow = 'visible'; // Permite que se vean sombras o tooltips
                    }
                }, 400);

            } else {
                // --- CERRAR ---
                header.setAttribute('aria-expanded', 'false');

                // 1. Restauramos la altura en píxeles (porque estaba en 'none')
                // Esto es necesario para que la animación de cierre funcione.
                content.style.maxHeight = content.scrollHeight + "px";
                content.style.overflow = 'hidden';

                // 2. Forzamos un "reflow" para que el navegador registre el cambio
                void content.offsetHeight;

                // 3. Colapsamos a 0
                content.style.maxHeight = '0';
                content.classList.remove('open');
            }
        });
    });
}

async function setupNanoFeatures() {
    try {
        console.log("[INIT] Inicializando funcionalidades de Nano...");
        // 1. Funciones de Navegación y Vistas (Rápidas)
        getReloadVariables();
        setupOrderBy();
        console.log("[INIT] Funciones de navegación pasadas");


        // 2. Funciones de Carga de Datos y UI (await)
        // El 'await' es necesario aquí para no sobrecargar el servidor
        const featurePromises = [
            await setupClients(),
            await setupProviders(),
            await setupSaleList(),
            await setupReceiptList(),
            await setupInventoryPicker(),
            await setupRecomendedColumns(),
            await setupInventoryInfoBtn(),
        ]
        console.log("[INIT] Funciones de Carga de Datos y UI pasadas");
        // 3. Funciones de Inicialización de UI y Gráficos (Donde fallaban los 'Element not found')
        setupTransactions();
        createCharts();
        setupStatPickers();
        await updateDailyStatistics('all');
        console.log("[INIT] Funciones de Inicialización de UI y Gráficos pasadas");

        console.log("[INIT] Funcionalidades de Nano inicializadas correctamente.");
    } catch (error) {
        // Esto captura solo errores sincrónicos, pero es buena práctica.
        console.error("Error en la inicialización de funcionalidades de Nano:", error);
        pop_ups.warning("Algunas funcionalidades avanzadas no pudieron cargarse.", "Error de Componente.");
    }
}

async function setupInventoryInfoBtn(){
    const btns = document.querySelectorAll('.inventory-info-btn');
    const closeBtn = document.getElementById('close-inventory-info-modal');

    btns.forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = document.getElementById('inventory-info-modal');
            const greyBg = document.getElementById('grey-background');
            modal.classList.remove('hidden');
            greyBg.classList.remove('hidden');
        })
    })
    closeBtn.addEventListener('click', () => {
        const modal = document.getElementById('inventory-info-modal');
        modal.classList.add('hidden');
    })
}


function handleTableClick(event) {
    const editBtn = event.target.closest('.edit-row-btn');
    const saveBtn = event.target.closest('.save-row-btn');
    const cancelBtn = event.target.closest('.cancel-row-btn');
    const stockBtn = event.target.closest('.stock-btn'); // Clic en +/- (si se reactiva)

    if (editBtn) {
        handleEditClick(editBtn);
    } else if (saveBtn) {
        handleSaveClick(saveBtn);
    } else if (cancelBtn) {
        handleCancelClick(cancelBtn);
    } else if (stockBtn) {
        console.log("Clic en botón de stock (lógica pendiente si se re-activa)");
    }
}

function handleEditClick(button) {
    const row = button.closest('tr');
    editingRowId = row.dataset.itemId;
    const filterableColumns = currentTableColumns.filter(
        col => col.toLowerCase() !== 'created_at'
    );
    renderTable(filterableColumns, allData);
}

async function handleSaveClick(button) {
    const row = button.closest('tr.editing-row');
    if (!row) return;
    const itemId = row.dataset.itemId;
    const dataToUpdate = {};
    let allInputsValid = true;

    row.querySelectorAll('input[data-column]').forEach(input => {
        const colName = input.dataset.column;
        const value = input.value;
        if (input.type === 'number' && isNaN(parseFloat(value))) {
            allInputsValid = false;
        }
        dataToUpdate[colName] = value;
    });

    if (!allInputsValid) {
        pop_ups.warning("Verificá que todos los campos numéricos tengan un número válido.", "¡Cuidado!");
        return;
    }
    button.disabled = true;

    try {
        const result = await api.updateTableRow(itemId, dataToUpdate);
        if (result.success && result.updatedItem) {
            const rowIndex = allData.findIndex(r => (r.id ?? r.Id ?? r.ID) == itemId);
            if (rowIndex > -1) {
                allData[rowIndex] = result.updatedItem; // Actualizo datos locales
            }
            editingRowId = null; // Salgo modo edición
            const filterableColumns = currentTableColumns.filter(
                col => col.toLowerCase() !== 'created_at'
            );
            renderTable(filterableColumns, allData); // Re-renderizo
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        pop_ups.error(`Error al guardar: ${error.message}`);
        button.disabled = false;
    }
}

function handleCancelClick() {
    editingRowId = null;
    renderTable(currentTableColumns, allData);
}

async function handleSaveNewRow(event) {
    const saveButton = event.target;
    const newRowElement = saveButton.closest('.editing-row');
    if (!newRowElement) return;

    const newItemData = {};
    newRowElement.querySelectorAll('input[data-column]').forEach(input => {
        const colName = input.dataset.column;
        newItemData[colName] = input.value.trim();
    });

    saveButton.disabled = true;
    saveButton.textContent = 'Guardando...';

    try {
        const result = await api.addItemToTable(newItemData);
        if (result.success && result.newItem) {
            allData.unshift(result.newItem);
            await renderTable(currentTableColumns, allData);
        } else {
            throw new Error(result.message || "Error al guardar la fila.");
        }
    } catch (error) {
        pop_ups.error(`Error al guardar: ${error.message}`);
        saveButton.disabled = false;
        saveButton.textContent = 'Guardar';
    }
}

function handleCancelNewRow(event) {
    const newRowElement = event.target.closest('.editing-row');
    if (newRowElement) {
        newRowElement.remove();
    }
}

function openDeleteModal() {
    currentDbNameToDelete = document.getElementById('table-title')?.textContent || '';
    if (!currentDbNameToDelete || !deleteModal) return;
    deleteDbNameConfirmSpan.textContent = currentDbNameToDelete;
    deleteConfirmInput.value = '';
    confirmDeleteBtn.disabled = true;
    deleteErrorMsg.textContent = '';
    deleteModal.classList.remove('hidden');
}

function closeDeleteModal() {
    if (deleteModal) deleteModal.classList.add('hidden');
}

function handleDeleteConfirmInput() {
    // Habilito el botón solo si el texto coincide exactamente
    if (deleteConfirmInput.value === currentDbNameToDelete) {
        confirmDeleteBtn.disabled = false;
        deleteErrorMsg.textContent = '';
    } else {
        confirmDeleteBtn.disabled = true;
        // Muestro un error sutil si empiezan a escribir mal
        if (deleteConfirmInput.value.length > 0) {
            deleteErrorMsg.textContent = 'El nombre no coincide.';
        } else {
            deleteErrorMsg.textContent = '';
        }
    }
}

async function handleConfirmDelete() {
    if (deleteConfirmInput.value !== currentDbNameToDelete) return;
    confirmDeleteBtn.disabled = true;
    confirmDeleteBtn.textContent = 'Eliminando...';
    try {
        const result = await api.deleteDatabase();
        if (result.success) {
            pop_ups.info(result.message, "Base de Datos Eliminada.");
            window.location.href = '/select-db.php';
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        pop_ups.error(`Error: ${error.message}`, "Error Crítico.");
        confirmDeleteBtn.disabled = false;
        confirmDeleteBtn.textContent = 'Eliminar Permanentemente';
    }
}

function renderColumnList() {
    if (!columnListContainer) return;

    const manageableColumns = currentTableColumns.filter(
        col => !protectedColumns.includes(col.toLowerCase())
    );

    if (manageableColumns.length === 0) {
        columnListStatus.textContent = 'No hay columnas personalizadas para gestionar.';
        columnListContainer.innerHTML = '';
        return;
    }

    columnListStatus.textContent = '';
    columnListContainer.innerHTML = manageableColumns.map(colName => `
        <div class="column-item" data-column-name="${colName}">
            <span>${colName}</span>
            <div class="column-actions">
                <button class="btn btn-secondary btn-sm rename-col-btn">Renombrar</button>
                <button class="btn btn-danger-secondary btn-sm drop-col-btn">Eliminar</button>
            </div>
        </div>
    `).join('');
}

async function handleAddColumn(e) {
    e.preventDefault();
    const input = document.getElementById('new-column-name');
    const columnName = input.value.trim();
    if (!columnName) return;

    try {
        const result = await api.manageTableColumn('add_column', { columnName });
        if (result.success) {
            pop_ups.success(`${result.message}`, "Columna añadida con éxito.");
            await loadTableData();
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        pop_ups.error(`Error al añadir columna: ${error.message}`, "Error al añadir la columna.");
    }
}

async function handleDropColumn(e) {
    const colItem = e.target.closest('.column-item');
    const columnName = colItem.dataset.columnName;

    try {
        const result = await pop_ups.prompt(
            'Confirmar Eliminación',
            `Para eliminar la columna "${columnName}" y todos sus datos, escribe el nombre de la columna:`,
            columnName,
            ''
        );

        if (result !== columnName) {
            pop_ups.warning('El nombre no coincide. Operación cancelada.', 'Eliminación Cancelada.');
            return;
        }

        const apiResult = await api.manageTableColumn('drop_column', { columnName });
        if (apiResult.success) {
            pop_ups.info(apiResult.message, "Columna Eliminada.");
            await loadTableData();
        } else {
            throw new Error(apiResult.message);
        }
    } catch (error) {
        if (error.message !== 'Acción cancelada por el usuario.') {
            pop_ups.error(`Error al eliminar columna: ${error.message}`, "Error Crítico.");
        } else {
            pop_ups.info('Cancelado', 'La operación fue cancelada.');
        }
    }
}

async function handleRenameColumn(e) {
    const colItem = e.target.closest('.column-item');
    const oldName = colItem.dataset.columnName;

    try {
        const newName = await pop_ups.prompt(
            'Renombrar Columna',
            `Ingresá el nuevo nombre para la columna "${oldName}":`,
            'Nuevo nombre',
            oldName
        );

        if (!newName || newName.trim() === '' || newName === oldName) {
            return;
        }

        const result = await api.manageTableColumn('rename_column', { oldName, newName });
        if (result.success) {
            pop_ups.success(`Columna renombrada a "${newName}".`, "Cambio Exitoso");
            await loadTableData();
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        if (error.message !== 'Acción cancelada por el usuario.') {
            pop_ups.error(`Error al renombrar: ${error.message}`, "Error Crítico");
        } else {
            pop_ups.info('Cancelado', 'La operación fue cancelada.');
        }
    }
}

async function loadNotifications() {
    const listContainer = document.getElementById('notifications-list');
    if (!listContainer) return;

    listContainer.innerHTML = '<p>Cargando historial...</p>';

    try {
        const response = await fetch('/api/notifications/get.php');
        const data = await response.json();

        if (!data.success) throw new Error(data.message);

        if (data.notifications.length === 0) {
            listContainer.innerHTML = '<p>No tenés notificaciones guardadas.</p>';
            return;
        }

        let html = '';
        let currentGroup = '';

        data.notifications.forEach(n => {
            const dateGroup = getRelativeDateGroup(n.created_at);

            if (dateGroup !== currentGroup) {
                html += `<h3 class="notification-date-header">${dateGroup}</h3>`;
                currentGroup = dateGroup;
            }

            const config = notificationConfig[n.type] || notificationConfig.info;
            html += `
            <div class="toast-notification show" 
                 style="--toast-color: ${config.color}; position: relative; opacity: 1; transform: none; transition: none; margin-bottom: 1rem; max-width: 100%;"
                 data-notification-id="${n.id}">
                
                <i class="toast-icon ph ${config.icon}"></i>
                
                <div class="toast-content">
                    <strong class="toast-title">${n.title}</strong>
                    <p class="toast-message">${n.message || ''}</p>
                    <small style="color: var(--color-gray); font-size: 0.8rem; margin-top: 5px; display: block;">
                        ${new Date(n.created_at).toLocaleString('es-AR', { hour: '2-digit', minute: '2-digit' })}
                    </small>
                </div>
                
                <button class="toast-close-btn" title="Eliminar notificación">
                    <i class="ph ph-x"></i>
                </button>
            </div>
            `;
        });

        listContainer.innerHTML = html;

    } catch (error) {
        listContainer.innerHTML = `<p style="color: var(--accent-red);">Error al cargar notificaciones: ${error.message}</p>`;
    }
}

function getRelativeDateGroup(dateString) {
    const date = new Date(dateString);
    const today = new Date();
    const yesterday = new Date(today);
    yesterday.setDate(yesterday.getDate() - 1);

    today.setHours(0, 0, 0, 0);
    yesterday.setHours(0, 0, 0, 0);
    const compDate = new Date(date);
    compDate.setHours(0, 0, 0, 0);

    if (compDate.getTime() === today.getTime()) {
        return 'Hoy';
    }
    if (compDate.getTime() === yesterday.getTime()) {
        return 'Ayer';
    }

    return date.toLocaleDateString('es-AR', {
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });
}

// --- Fila editable (Se adoptó la lógica de Nano con correcciones) ---
async function createEditableRow(columns) {
    const tr = document.createElement('tr');
    tr.classList.add('editing-row');

    // Consigo preferencias y defaults para columnas recomendadas de la tabla actual (LÓGICA DE NANO)
    const inventoryPreferencesResult = await api.getCurrentInventoryPreferences();
    if (!inventoryPreferencesResult.success) {
        console.error("Error crítico: No se encontraron las preferencias del inventario.");
        return;
    }
    var inventoryPreferences = inventoryPreferencesResult;

    const inventoryDefaultsResult = await api.getCurrentInventoryDefaults();
    if (!inventoryDefaultsResult.success) {
        console.error("Error crítico: No se encontraron los defaults del inventario.");
        return;
    }
    let inventoryDefaults = inventoryDefaultsResult;

    // Declaro variables para la asignación automatica de precio de Compra
    let receiptPrice = parseFloat(inventoryDefaults.receipt_price);
    let salePrice = parseFloat(inventoryDefaults.sale_price);
    let gainValue = parseFloat(inventoryDefaults.hard_gain);

    let receiptPriceInput = null;
    let salePriceInput = null;
    let gainValueInput = null;

    // Lógica de cálculo de precio de Nano (adoptada)
    inventoryDefaults = getAutoPrice(inventoryPreferences, inventoryDefaults, salePrice, gainValue);
    // FIN Lógica de cálculo de precio de Nano


    // SWITCH para cada columna (LÓGICA DE NANO)
    columns.forEach(col => {
        const td = document.createElement('td');
        const colLower = col.toLowerCase();
        let shouldAppend = true;

        switch (colLower) {
            case 'id':
            case 'created_at':
                td.textContent = '';
                shouldAppend = false;
                break;
            case 'stock':
                td.classList.add('stock-cell');
                td.innerHTML = `
                 <button class="stock-btn minus" disabled>-</button>
                 <input type="number" class="stock-input" value="0" min="0" data-column="${col}"> 
                 <button class="stock-btn plus" disabled>+</button>`;
                break;
            case 'name':
                const nameInput = document.createElement('input');
                nameInput.type = 'text';
                nameInput.placeholder = 'Nombre';
                nameInput.dataset.column = col;
                td.appendChild(nameInput);
                break;
            case 'min_stock':
                if (inventoryPreferences.min_stock === 1) {
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.placeholder = 'Stock Mínimo';
                    input.value = inventoryDefaults.min_stock;
                    input.dataset.column = col;
                    td.appendChild(input);
                }
                else{shouldAppend = false;}
                break;
            case 'sale_price':
                if (inventoryPreferences.sale_price === 1) {
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.placeholder = 'Precio de Venta';
                    input.value = salePrice;
                    input.dataset.column = col;
                    td.appendChild(input);
                    salePriceInput = input;
                }
                else{shouldAppend = false;}
                break;
            case 'receipt_price':
                if (inventoryPreferences.receipt_price === 1) {
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.placeholder = 'Precio de Compra';
                    input.value = receiptPrice;
                    receiptPriceInput = input;
                    input.dataset.column = col;
                    td.appendChild(input);
                }
                else{shouldAppend = false;}
                break;
            case 'hard_gain':
            case 'percentage_gain':
                if (inventoryPreferences.hard_gain === 1 || inventoryPreferences.percentage_gain === 1) {
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.placeholder = 'Margen de Ganancia';
                    input.value = inventoryDefaults.hard_gain;
                    gainValueInput = input;
                    input.dataset.column = col;
                    td.appendChild(input);
                }
                else{shouldAppend = false;}
                break;
            default:
                const input = document.createElement('input');
                input.type = 'text';
                input.placeholder = col.charAt(0).toUpperCase() + col.slice(1);
                input.dataset.column = col;
                td.appendChild(input);
                break;
        }
        if (shouldAppend) {
            tr.appendChild(td);
        }
    });

    // Lógica de Listener de Precio Automático (LÓGICA DE NANO)
    if (inventoryPreferences.auto_price === 1 && salePriceInput && receiptPriceInput && gainValueInput) {
        const updateReceiptPrice = () => {
            salePrice = salePriceInput.value;
            gainValue = gainValueInput.value;

            receiptPrice = getAutoPrice();
            receiptPriceInput.value = receiptPrice.toFixed(2);
        };

        salePriceInput.addEventListener('input', updateReceiptPrice);
        gainValueInput.addEventListener('input', updateReceiptPrice);
        updateReceiptPrice();
    }

    const actionTd = document.createElement('td');
    actionTd.classList.add('action-buttons');
    actionTd.innerHTML = `
        <button class="btn btn-primary save-new-row-btn">Guardar</button>
        <button class="btn btn-secondary cancel-new-row-btn">Cancelar</button>
    `;
    tr.appendChild(actionTd);

    return tr;
}

function getAutoPrice(inventoryPreferences, inventoryDefaults, salePrice, gainValue){
    let autoPrice;
    const type = inventoryPreferences.auto_price_type;

    try{
        switch (type) {
            case 'iva':
                autoPrice = parseFloat(salePrice) * 1.21;
                break;
            case 'gain':
                if (inventoryPreferences.percentage_gain === 1) {
                    autoPrice = parseFloat(salePrice) * (1 + (parseFloat(gainValue) / 100));
                }
                else {autoPrice = parseFloat(salePrice) + parseFloat(gainValue);}
                break;
            default:
                autoPrice = parseFloat(salePrice) * 1.21;
                if (inventoryPreferences.percentage_gain === 1) {
                    autoPrice = autoPrice * (1 + (parseFloat(gainValue) / 100));
                }
                else {autoPrice += parseFloat(gainValue);}
                break;
        }
        if (isNaN(autoPrice)){autoPrice = inventoryDefaults.receipt_price;}
        return autoPrice;
    }
    catch(error){
        console.log('Entrada Invalida. Valor de Compra no actualizado');
        return inventoryDefaults.receipt_price;
    }
}

// **handleAddRowClick (Adaptada para la nueva función createEditableRow)**
function handleAddRowClick() {
    const tableBody = document.querySelector('#data-table tbody');
    if (!tableBody || tableBody.querySelector('.editing-row')) return;
    const newRow = createEditableRow(currentTableColumns);
    tableBody.prepend(newRow); // Inserto al principio
    newRow.querySelector('.save-new-row-btn')?.addEventListener('click', handleSaveNewRow);
    newRow.querySelector('.cancel-new-row-btn')?.addEventListener('click', handleCancelNewRow);
}

// **--- INICIO: FUNCIONES AUXILIARES NECESARIAS (De Nano) ---**
function setupGreyBg(){
    const greyBg = document.getElementById('grey-background');
    const transactionModal = document.getElementById('transaction-picker-modal');
    const transactionContainer = document.getElementById('new-transaction-container');
    const mobileMenu = document.getElementById('mobile-menu');
    const inventoryInfoModal = document.getElementById('inventory-info-modal');
    greyBg.addEventListener('click', (event) =>{
        if (event.target === greyBg) {
            if (!transactionModal.classList.contains('hidden')) {
                hideTransactionModal();
            }
            else if (!mobileMenu.classList.contains('hidden')) {
                greyBg.classList.add('hidden');
                mobileMenu.classList.add('hidden');
            }
            else if(!transactionContainer.classList.contains('hidden')) {
                transactionContainer.classList.add('hidden');
                greyBg.classList.add('hidden');
                inventoryInfoModal.classList.add('hidden');
            }
            else{
                greyBg.classList.add('hidden');
                hideTransactionSuccess();
            }

        }
    })
}

function setupReturnBtn(){
    const returnBtn = document.getElementById('return-btn');
    const greyBg = document.getElementById('grey-background');

    returnBtn.addEventListener('click', () => {
        greyBg.classList.add('hidden');
        hideTransactionSuccess();
        hideTransactionModal();
    })
}
// **--- FIN: FUNCIONES AUXILIARES NECESARIAS (De Nano) ---**


// --- 4. INICIALIZACIÓN (LA ÚNICA FUNCIÓN init) ---
async function init() {
    console.log("[INIT] Iniciando dashboard...");

    // 1. HEADER (LÓGICA DE NANO - RUTAS LIMPIAS Y EN INGLÉS)
    const response = await api.checkUserAdmin();
    if (!response.success){
        alert('Ha ocurrido un error interno. Será deslogeado');
        window.location.href = '/logout.php';
    }
    const isAdmin = response.isAdmin;

    console.log("[INIT] Iniciando nav-bar...");

    const nav = document.getElementById('header-nav');
    if (nav) nav.innerHTML = `
                                <a href="/statistics.php" class="btn btn-secondary">Estadisticas</a>
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
    //PREPARA LOS FONDOS GRISES DE LOS MODALES
    setupReturnBtn();
    setupGreyBg();
    setupMobileMenu();

    //PREPARA EL DROPDOWN DE "MI CUENTA"
    setup.setupMiCuenta();

    // 2. CONFIGURACIÓN BÁSICA DE LA VISTA
    const tableTitleElement = document.getElementById('table-title');
    initializeImportModal(); // Inicializo modal al principio
    console.log("[INIT] Modal inicializado.");

    // --- Selecciono Elementos del Modal de Eliminación ---
    deleteModal = document.getElementById('delete-confirm-modal');
    deleteConfirmInput = document.getElementById('delete-confirm-input');
    confirmDeleteBtn = document.getElementById('confirm-delete-btn');
    deleteDbNameConfirmSpan = document.getElementById('delete-db-name-confirm');
    deleteErrorMsg = document.getElementById('delete-error-message');
    columnListContainer = document.getElementById('column-list-container');
    addColumnForm = document.getElementById('add-column-form');
    columnListStatus = document.getElementById('column-list-status');
    searchColumnBtn = document.getElementById('search-column-btn');
    searchColumnBtnText = searchColumnBtn?.querySelector('span');
    searchColumnDropdown = document.getElementById('search-column-dropdown');
    const closeDeleteBtn = document.getElementById('close-delete-modal-btn');
    const cancelDeleteBtn = document.getElementById('cancel-delete-btn');
    const deleteDbBtn = document.getElementById('delete-db-btn'); // El botón que abre el modal

    // --- Conecto Eventos del Modal de Eliminación ---
    deleteDbBtn?.addEventListener('click', openDeleteModal);
    closeDeleteBtn?.addEventListener('click', closeDeleteModal);
    cancelDeleteBtn?.addEventListener('click', closeDeleteModal);
    deleteConfirmInput?.addEventListener('input', handleDeleteConfirmInput); // Valida al escribir
    confirmDeleteBtn?.addEventListener('click', handleConfirmDelete);
    if(deleteModal) { // Cierro si se hace clic en el overlay
        deleteModal.addEventListener('click', (e) => { if (e.target === deleteModal) closeDeleteModal(); });
    }
    console.log("[INIT] Modal de Eliminación inicializado.");

    addColumnForm?.addEventListener('submit', handleAddColumn);
    columnListContainer?.addEventListener('click', (e) => {
        if (e.target.classList.contains('drop-col-btn')) {
            handleDropColumn(e);
        } else if (e.target.classList.contains('rename-col-btn')) {
            handleRenameColumn(e);
        }
    });

    await loadTableData();

    document.getElementById('search-input')?.addEventListener('input', filterTable);
    document.getElementById('add-row-btn')?.addEventListener('click', handleAddRowClick);

    searchColumnBtn?.addEventListener('click', () => {
        searchColumnDropdown.classList.toggle('hidden');
    });

    searchColumnDropdown?.addEventListener('click', (e) => {
        const item = e.target.closest('.search-dropdown-item');
        if (!item) return;

        selectedSearchColumn = item.dataset.column;
        if (searchColumnBtnText) searchColumnBtnText.textContent = (selectedSearchColumn === 'all') ? 'Todas' : selectedSearchColumn;

        searchColumnDropdown.querySelectorAll('.search-dropdown-item').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.column === selectedSearchColumn);
        });
        searchColumnDropdown.classList.add('hidden');
        filterTable();
    });

    // Oculto dropdown si se hace clic fuera
    document.addEventListener('click', (e) => {
        if (!searchColumnBtn?.contains(e.target) && !searchColumnDropdown?.contains(e.target)) {
            searchColumnDropdown?.classList.add('hidden');
        }
    });

    document.getElementById('debug-toast-form')?.addEventListener('submit', (e) => {
        e.preventDefault();
        const type = document.getElementById('debug-toast-type').value;
        const title = document.getElementById('debug-toast-title').value;
        const message = document.getElementById('debug-toast-message').value;

        pop_ups[type](message || 'Este es un mensaje de prueba.', title);

        // Limpio el form
        document.getElementById('debug-toast-title').value = '';
        document.getElementById('debug-toast-message').value = '';
    });

    // Listener para borrar notificaciones del historial
    document.getElementById('notifications-list')?.addEventListener('click', async (e) => {
        const deleteBtn = e.target.closest('.toast-close-btn');
        const notificationDiv = e.target.closest('.toast-notification');

        if (deleteBtn && notificationDiv) {
            const notificationId = notificationDiv.dataset.notificationId;
            if (!notificationId) return;

            notificationDiv.style.opacity = '0.5';

            try {
                const response = await fetch('/api/notifications/delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: notificationId })
                });
                const data = await response.json();

                if (data.success) {
                    notificationDiv.style.transition = "all 0.3s ease";
                    notificationDiv.style.transform = "translateX(100%)";
                    notificationDiv.style.opacity = "0";
                    setTimeout(() => notificationDiv.remove(), 300);
                } else {
                    throw new Error(data.message);
                }
            } catch (error) {
                console.error('Error al eliminar notificación:', error);
                notificationDiv.style.opacity = '1';
                pop_ups.error('No se pudo eliminar la notificación.');
            }
        }
    });


    // 3. CARGA CRÍTICA DE DATOS (Arregla los botones laterales y la carga de la tabla)
    try {
        console.log("[INIT] Llamando a api.getTableData...");
        const result = await api.getTableData();
        console.log("[INIT] Respuesta de getTableData:", result);

        if (result && result.success === true) {
            allData = result.data || []; // Aseguro que sea array
            currentTableColumns = result.columns || []; // Aseguro que sea array

            if (tableTitleElement) {
                tableTitleElement.textContent = result.inventoryName || 'Inventario';
            }

            console.log("[INIT] Llamando a renderTable...");
            renderTable(currentTableColumns, allData);
            console.log("[INIT] renderTable completado.");

            const searchInput = document.getElementById('search-input');
            if (searchInput) {
                searchInput.addEventListener('input', filterTable);
                console.log("[INIT] Listener de búsqueda añadido.");
            } else {
                console.warn("[INIT] Input de búsqueda no encontrado.");
            }
        } else {
            console.error("[INIT] getTableData devolvió success: false o respuesta inválida.");
            throw new Error(result?.message || 'Error al obtener datos de tabla.');
        }
        // 4. LISTENERS CRÍTICOS (Se activan SÓLO si la carga fue exitosa)
        setupMenuNavigation();
        setupAccordion();
        showDashboardView('view-db');

        setTimeout(() => {
            setupNanoFeatures().catch(error => {
                // Esto atrapará errores como 'Element not found' sin romper el flujo principal.
                console.error("Error en la inicialización de funciones de Nano:", error);
            });
        }, 50); // 50ms de delay para estabilizar la vista

    } catch (error) {
        console.error("[INIT] Error CATCH:", error);
        pop_ups.error(`Error al cargar el panel: ${error.message}. Serás redirigido.`, "Error Crítico de Carga");
        if (error.message.includes('No autorizado')) {
            window.location.href = '/login.php';
        } else {
            window.location.href = '/select-db.php';
        }
    }
}
// ------------------------------------------------------------------------------------------------------

function setupMobileMenu(){
    /*
    const menuBtn = document.getElementById('open-mobile-menu-btn')
    menuBtn.addEventListener('click', () => {
        const mobileMenu = document.getElementById('mobile-menu');
        const greyBg = document.getElementById('grey-background');
        greyBg.classList.remove('hidden');
        mobileMenu.classList.remove('hidden');
    })
    */
}

function getReloadVariables(){
    const urlParams = new URLSearchParams(window.location.search);
    const menuToOpen = urlParams.get('location');
    if (!menuToOpen) return;

    const menuToClick = document.querySelector(`.menu-btn[data-target-view="${menuToOpen}"]`);
    console.log(menuToClick);

    menuToClick.click();
}

async function setupSaleList(){
    const response = await api.getUserSales();
    if (response.success) {
        const saleList = response.saleList;
        await populateSaleView(saleList);
    }
}

async function populateSaleView(saleList) {
    const dateDescendingContainer = document.getElementById('sales-table-date-descending');
    const dateAscendingContainer = document.getElementById('sales-table-date-ascending');
    const idDescendingContainer = document.getElementById('sales-table-id-descending');
    const idAscendingContainer = document.getElementById('sales-table-id-ascending');
    const customerDescendingContainer = document.getElementById('sales-table-client-descending');
    const customerAscendingContainer = document.getElementById('sales-table-client-ascending');
    const priceDescendingContainer = document.getElementById('sales-table-price-descending');
    const priceAscendingContainer = document.getElementById('sales-table-price-ascending');

    const dateDescendingList = saleList.date.descending;
    const dateAscendingList = saleList.date.ascending;
    const idDescendingList = saleList.id.descending;
    const idAscendingList = saleList.id.ascending;
    const customerDescendingList = saleList.customer.descending;
    const customerAscendingList = saleList.customer.ascending;
    const priceDescendingList = saleList.price.descending;
    const priceAscendingList = saleList.price.ascending;


    for (const sale of dateDescendingList) {
        const saleDiv = await createSaleRow(sale);
        dateDescendingContainer.appendChild(saleDiv);
    }

    for (const sale of dateAscendingList) {
        const saleDiv = await createSaleRow(sale);
        dateAscendingContainer.appendChild(saleDiv);
    }

    for (const sale of idDescendingList) {
        const saleDiv = await createSaleRow(sale);
        idDescendingContainer.appendChild(saleDiv);
    }

    for (const sale of idAscendingList) {
        const saleDiv = await createSaleRow(sale);
        idAscendingContainer.appendChild(saleDiv);
    }

    for (const sale of customerDescendingList) {
        const saleDiv = await createSaleRow(sale);
        customerDescendingContainer.appendChild(saleDiv);
    }

    for (const sale of customerAscendingList) {
        const saleDiv = await createSaleRow(sale);
        customerAscendingContainer.appendChild(saleDiv);
    }

    for (const sale of priceDescendingList) {
        const saleDiv = await createSaleRow(sale);
        priceDescendingContainer.appendChild(saleDiv);
    }

    for (const sale of priceAscendingList) {
        const saleDiv = await createSaleRow(sale);
        priceAscendingContainer.appendChild(saleDiv);

    }

}

async function createSaleRow(sale){
    let customerName;

    if (sale.customer_id === null){customerName = "No asignado";}
    else{
        const result = await api.getCustomerById(sale.customer_id);
        const customer = result.clientInfo;
        customerName = customer.full_name;
    }

    const saleDiv = document.createElement('div');
    saleDiv.classList.add('sale-row');
    saleDiv.dataset.saleId = sale.id;
    saleDiv.innerHTML = `<div class="flex-column" style="width: fit-content; justify-content: space-between">
                            <h3>Número = ${sale.id}</h3>
                            <h2>$${sale.total_amount}</h2>   
                        </div>
                        <div class="flex-column" style="width: fit-content; text-align: right">
                            <p>${sale.sale_date}</p>
                            <p class="customer-name">Cliente = ${customerName}</p>   
                        </div>`;
    saleDiv.addEventListener('click', async () => {
        const saleInfo = await api.getFullSaleInfo(sale.id);
        showSaleModal(saleInfo);
    });
    return saleDiv;
}

function showSaleModal(saleInfo){
    const modal = document.getElementById('transaction-info-modal');

    modal.originalSaleInfo = JSON.parse(JSON.stringify(saleInfo));

    const itemList = saleInfo.itemList;
    let customerInfo;
    if (!saleInfo.customerInfo){
        customerInfo = `<div class="flex-row justify-between">
                                    <p>Cliente</p>
                                    <p>'No asignado'</p>
                                </div>`;
    }
    else{customerInfo = newCustomerInfo(saleInfo.customerInfo);}

    const saleList = itemList.map((item, index) => {
        const name = item.product_name;
        const amount = item.quantity;
        const price = item.unit_price;
        const totalPrice = item.total_price;

        return `<div class="flex-row sale-item-row" style="gap: 15px;" data-index="${index}">
                    <p style="width: 100px;overflow: hidden; text-wrap: nowrap; text-overflow: ellipsis">${name}</p>
                    <p style="width: 70px;" class="item-quantity">${amount}</p>
                    <p style="width: 65px;" class="item-price">${price}$</p>
                    <p style="width: 80px;" class="item-total">${totalPrice}$</p>
                 </div>`;
    }).join('');

    const saleTicket = `<div class="flex-column" style="gap: 15px; margin-top:10px">    
         <div class="flex-row" style="gap: 15px;"><h4 style="width: 100px">Nombre</h4>
         <h4 style="width: 70px">Cantidad</h4><h4 style="width: 65px">Precio de Venta</h4><h4 style="width: 80px">Precio Total</h4></div>
         <div id="sale-item-list-wrapper" class="flex-column" style="max-height: 200px; overflow-y: auto;">${saleList}</div>
         <hr>
    </div>`;

    modal.innerHTML = `<div class="flex-row justify-between"><p></p><div class="return-btn" style="top: 0; left: 0" id="close-info-modal">Volver</div></div>
                       <div class="product-list-container">
                       <div class="flex-row" style="justify-content: space-between; align-items: center">
                        <h3>Lista de Productos</h3>
                        <div class="flex-row all-center" style="gap: 10px;">
                            <div id="edit-controls-container" class="flex-row hidden" style="gap: 10px;">
                                <button id="save-sale-btn" class="btn btn-primary" style="padding: 5px 10px; font-size: 0.8rem; margin-top: 0;" disabled>Guardar</button>
                                <button id="cancel-sale-btn" class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.8rem; margin-top: 0;">Cancelar</button>
                            </div>
                            <button id="edit-sale-btn" class="btn btn-secondary hidden" style="padding: 5px 10px; font-size: 0.8rem; margin-top: 0;">Editar</button>
                            <img src="./assets/img/arrow-pointing-down.png" alt="Flecha" height="30px" id="product-list-btn" class="dropdown-arrow"/>
                        </div>
                        </div>
                        <div id="product-list-dropdown">${saleTicket}</div>
                        </div>
                        <div class="client-info-container">${customerInfo}</div>
                        <div id="final-total-container" style="margin-top: auto; text-align:right;" class="flex-column;">
                            <p style="font-size: 20px; font-weight: 600">Id = <span style="font-size: 17px; font-weight: 400">${saleInfo.id}</span></p>
                            <p style="font-size: 20px; font-weight: 600">Fecha = <span style="font-size: 17px; font-weight: 400">${saleInfo.saleDate}</span></p>
                            <h2 style="margin-bottom: 20px; text-align: right">Precio Total = $<span id="final-total-amount">${saleInfo.totalAmount}</span></h2>
                        </div>
                        `;

    modal.dataset.isEditing = 'false';

    const productListBtn = document.getElementById('product-list-btn');
    const listDropdown = document.getElementById('product-list-dropdown');
    const editBtn = document.getElementById('edit-sale-btn');

    productListBtn.addEventListener('click', () => {
        if (modal.dataset.isEditing === 'true') return;

        listDropdown.classList.toggle('visible');
        productListBtn.classList.toggle('rotated');

        if (listDropdown.classList.contains('visible')) {
            editBtn.classList.remove('hidden');
        } else {
            editBtn.classList.add('hidden');
        }
    })

    const closeBtn = document.getElementById('close-info-modal');

    closeBtn.addEventListener('click', closeTransactionInfoModal );

    const customerInfoBtn = document.getElementById('customer-info-btn');

    if (customerInfoBtn){
        customerInfoBtn.addEventListener('click', () => {
            if (modal.dataset.isEditing === 'true') return;

            const infoDropdown = document.getElementById('customer-info-dropdown');
            infoDropdown.classList.toggle('visible');
            customerInfoBtn.classList.toggle('rotated');
        })
    }

    document.getElementById('edit-sale-btn').addEventListener('click', enableSaleEditing);
    document.getElementById('cancel-sale-btn').addEventListener('click', handleCancelSale);
    document.getElementById('save-sale-btn').addEventListener('click', handleSaveSale);

    showTransactionInfoModal();
}

// --- NUEVAS FUNCIONES PARA EDICIÓN DE VENTAS ---

async function enableSaleEditing() {
    const modal = document.getElementById('transaction-info-modal');
    modal.dataset.isEditing = 'true';
    const originalSaleInfo = modal.originalSaleInfo;

    document.getElementById('edit-sale-btn').classList.add('hidden');
    document.getElementById('product-list-btn').classList.add('hidden');
    document.getElementById('edit-controls-container').classList.remove('hidden');

    const listWrapper = document.getElementById('sale-item-list-wrapper');
    const itemRows = listWrapper.querySelectorAll('.sale-item-row');

    for (const row of itemRows){
        const index = row.dataset.index;
        const itemData = originalSaleInfo.itemList[index];

        const response = await getProductData(itemData.item_id,itemData.inventory_id);
        if (!response.success) return;

        const productInfo = response.productInfo;

        const quantityCell = row.querySelector('.item-quantity');
        const priceCell = row.querySelector('.item-price');
        const totalCell = row.querySelector('.item-total');

        const itemMax = itemData.quantity + productInfo.stock;

        quantityCell.innerHTML = `<input type="number" class="edit-quantity" value="${itemData.quantity}" min="1" max="${itemMax}" style="width: 70px; text-align: right; padding: 4px;">`;
        priceCell.innerHTML = `<input type="number" class="edit-price" value="${itemData.unit_price}" min="0" step="0.01" style="width: 65px; text-align: right; padding: 4px;">$`;
        totalCell.innerHTML = `<input type="text" class="edit-total" value="${itemData.total_price.toFixed(2)}" style="width: 80px; border:none; background: #eee; text-align: right; padding: 4px; height: fit-content;" readonly>$`;
    }

    const finalTotalContainer = document.getElementById('final-total-container');
    finalTotalContainer.innerHTML = `<h2 style="margin-top:auto; margin-bottom: 20px; text-align: right">Precio Total = <input type="text" id="final-total-input" value="${originalSaleInfo.totalAmount.toFixed(2)}" style="width: fit-content; border:none; background: #eee; text-align: right; font-weight: 900; color: var(--color-black);" readonly></h2>`;

    modal.addEventListener('input', handleSaleEdit);
}

function handleSaleEdit(event) {
    if (!event.target.classList.contains('edit-quantity') && !event.target.classList.contains('edit-price')) {
        return;
    }

    const row = event.target.closest('.sale-item-row');
    if (!row) return;

    const quantityInput = row.querySelector('.edit-quantity');
    const priceInput = row.querySelector('.edit-price');
    const totalInput = row.querySelector('.edit-total');

    let newQuantity = parseInt(quantityInput.value) || 0;


    if (event.target.classList.contains('edit-quantity')) {
        const maxStock = parseInt(quantityInput.max, 10);

        if (!isNaN(maxStock) && newQuantity > maxStock) {

            newQuantity = maxStock;
            quantityInput.value = maxStock;
        }
    }
    const newPrice = parseFloat(priceInput.value) || 0;
    const newRowTotal = newQuantity * newPrice;

    totalInput.value = newRowTotal.toFixed(2);

    let overallTotal = 0;
    document.querySelectorAll('.edit-total').forEach(input => {
        overallTotal += parseFloat(input.value) || 0;
    });

    document.getElementById('final-total-input').value = overallTotal.toFixed(2);

    checkSaleChanges();
}

function checkSaleChanges() {
    const modal = document.getElementById('transaction-info-modal');
    const originalSaleInfo = modal.originalSaleInfo;
    let hasChanged = false;

    document.querySelectorAll('.sale-item-row').forEach(row => {
        const index = row.dataset.index;
        const originalItem = originalSaleInfo.itemList[index];

        const currentQuantity = row.querySelector('.edit-quantity').value;
        const currentPrice = row.querySelector('.edit-price').value;

        if (parseFloat(currentQuantity) !== originalItem.quantity) {
            hasChanged = true;
        }
        if (parseFloat(currentPrice) !== originalItem.unit_price) {
            hasChanged = true;
        }
    });

    document.getElementById('save-sale-btn').disabled = !hasChanged;
}

async function handleSaveSale() {
    const modal = document.getElementById('transaction-info-modal');
    const originalSaleInfo = modal.originalSaleInfo;

    const updatedSaleData = {
        sale_id: originalSaleInfo.id,
        items: [],
        newTotal: document.getElementById('final-total-input').value
    };
    document.querySelectorAll('.sale-item-row').forEach(row => {
        const index = row.dataset.index;
        const originalItem = originalSaleInfo.itemList[index];

        updatedSaleData.items.push({
            sale_item_id: originalItem.sale_id,
            product_id: originalItem.item_id,
            inventory_id: originalItem.inventory_id,
            product_name: originalItem.product_name,
            original_quantity: originalItem.quantity,
            new_quantity: row.querySelector('.edit-quantity').value,
            original_unit_price: originalItem.unit_price,
            new_unit_price: row.querySelector('.edit-price').value,
            new_total_price: row.querySelector('.edit-total').value
        });
    });

    console.log("Datos de Venta Modificados (listos para enviar al backend):", updatedSaleData);

    const response = await api.updateSaleList(updatedSaleData);
    if (response.success){alert("Se han guardados los cambios. Será redirigido."); window.location.reload();}
    else{alert("Ha ocurrido un error. No se pudieron guardar los cambios");console.log(response.error);}

    handleCancelSale();
}
function handleCancelSale() {
    const modal = document.getElementById('transaction-info-modal');
    const originalSaleInfo = modal.originalSaleInfo;

    modal.removeEventListener('input', handleSaleEdit);

    showSaleModal(originalSaleInfo);
}

function newCustomerInfo(clientInfo) {
    return `<div class="flex-row justify-between" style="align-items: center">     
                <div class="flex-row all-center" style="width: fit-content; gap: 10px">
                    <h3>Cliente: </h3>
                    <p style="font-weight: 600">${clientInfo.full_name}</p>
                </div>    
                <img src="./assets/img/arrow-pointing-down.png" alt="Flecha" height="30px" id="customer-info-btn" class="dropdown-arrow"/> 
            </div>
            <div class="flex-column" id="customer-info-dropdown">  
                <p>Email = ${(clientInfo.email !== null) ? clientInfo.email : 'No asignado'}</p>
                <p>Telefono = ${(clientInfo.phone !== null) ? clientInfo.phone : 'No asignado'}</p>
                <p>Dirección = ${(clientInfo.address !== null) ? clientInfo.address : 'No asignado'}</p>
                <p>DNI = ${(clientInfo.tax_id !== null) ? clientInfo.tax_id : 'No asignado'}</p>
                <p>Fecha de Creación = ${clientInfo.created_at}</p>
            </div>`;
}

function closeTransactionInfoModal(){
    const modal = document.getElementById('transaction-info-modal');
    const greyBg = document.getElementById('grey-background');
    modal.classList.add('hidden');
    greyBg.classList.add('hidden');
}


async function setupReceiptList(){
    const response = await api.getUserReceipts();
    if (response.success) {
        const itemList = response.receiptList;
        await populateReceiptView(itemList);
    }
}

async function populateReceiptView(itemList) {
    const dateDescendingContainer = document.getElementById('receipts-table-date-descending');
    const dateAscendingContainer = document.getElementById('receipts-table-date-ascending');
    const idDescendingContainer = document.getElementById('receipts-table-id-descending');
    const idAscendingContainer = document.getElementById('receipts-table-id-ascending');
    const providerDescendingContainer = document.getElementById('receipts-table-provider-descending');
    const providerAscendingContainer = document.getElementById('receipts-table-provider-ascending');
    const priceDescendingContainer = document.getElementById('receipts-table-price-descending');
    const priceAscendingContainer = document.getElementById('receipts-table-price-ascending');

    const dateDescendingList = itemList.date.descending;
    const dateAscendingList = itemList.date.ascending;
    const idDescendingList = itemList.id.descending;
    const idAscendingList = itemList.id.ascending;
    const providerDescendingList = itemList.provider.descending;
    const providerAscendingList = itemList.provider.ascending;
    const priceDescendingList = itemList.price.descending;
    const priceAscendingList = itemList.price.ascending;


    for (const receipt of dateDescendingList) {
        const receiptDiv = await createReceiptRow(receipt);
        dateDescendingContainer.appendChild(receiptDiv);
    }

    for (const receipt of dateAscendingList) {
        const receiptDiv = await createReceiptRow(receipt);
        dateAscendingContainer.appendChild(receiptDiv);
    }

    for (const receipt of idDescendingList) {
        const receiptDiv = await createReceiptRow(receipt);
        idDescendingContainer.appendChild(receiptDiv);
    }

    for (const receipt of idAscendingList) {
        const receiptDiv = await createReceiptRow(receipt);
        idAscendingContainer.appendChild(receiptDiv);
    }

    for (const receipt of providerDescendingList) {
        const receiptDiv = await createReceiptRow(receipt);
        providerDescendingContainer.appendChild(receiptDiv);
    }

    for (const receipt of providerAscendingList) {
        const receiptDiv = await createReceiptRow(receipt);
        providerAscendingContainer.appendChild(receiptDiv);
    }

    for (const receipt of priceDescendingList) {
        const receiptDiv = await createReceiptRow(receipt);
        priceDescendingContainer.appendChild(receiptDiv);
    }

    for (const receipt of priceAscendingList) {
        const receiptDiv = await createReceiptRow(receipt);
        priceAscendingContainer.appendChild(receiptDiv);

    }

}

async function createReceiptRow(receipt){
    let providerName;

    if (receipt.provider_id === null){providerName = "No asignado";}
    else{
        const result = await api.getProdivderById(receipt.provider_id);
        const provider = result.providerInfo;
        providerName = provider.full_name;
    }

    const receiptDiv = document.createElement('div');
    receiptDiv.classList.add('receipt-row');
    receiptDiv.dataset.receiptId = receipt.id;
    receiptDiv.innerHTML = `<div class="flex-column" style="width: fit-content; justify-content: space-between">
                            <h3>Número = ${receipt.id}</h3>
                            <h2>$${receipt.total_amount}</h2>   
                        </div>
                        <div class="flex-column" style="width: fit-content; text-align: right">
                            <p>${receipt.receipt_date}</p>
                            <p class="customer-name">Proveedor = ${providerName}</p>   
                        </div>`;
    receiptDiv.addEventListener('click', async () => {
        const receiptInfo = await api.getFullReceiptInfo(receipt.id);
        showReceiptModal(receiptInfo);
    });
    return receiptDiv;
}

function showReceiptModal(receiptInfo){
    const modal = document.getElementById('transaction-info-modal');

    modal.originalReceiptInfo = JSON.parse(JSON.stringify(receiptInfo));

    const itemList = receiptInfo.itemList;
    let providerInfo;
    if (!receiptInfo.providerInfo){
        providerInfo = `<div class="flex-row justify-between">
                                    <p>Proveedor</p>
                                    <p>'No asignado'</p>
                                </div>`;
    }
    else{providerInfo = newProviderInfo(receiptInfo.providerInfo);}

    const receiptList = itemList.map((item, index) => {
        const name = item.product_name;
        const amount = item.quantity;
        const price = item.unit_price;
        const totalPrice = item.total_price;

        return `<div class="flex-row receipt-item-row" style="gap: 15px;" data-index="${index}">
                    <p style="width: 100px;overflow: hidden; text-wrap: nowrap; text-overflow: ellipsis">${name}</p>
                    <p style="width: 70px;" class="item-quantity">${amount}</p>
                    <p style="width: 65px;" class="item-price">${price}$</p>
                    <p style="width: 80px;" class="item-total">${totalPrice}$</p>
                 </div>`;
    }).join('');

    const receiptTicket = `<div class="flex-column" style="gap: 15px; margin-top:10px">    
         <div class="flex-row" style="gap: 15px;"><h4 style="width: 100px">Nombre</h4>
         <h4 style="width: 70px">Cantidad</h4><h4 style="width: 65px">Precio de Compra</h4><h4 style="width: 80px">Precio Total</h4></div>
         <div id="receipt-item-list-wrapper" class="flex-column" style="max-height: 200px; overflow-y: auto;">${receiptList}</div>
         <hr>
    </div>`;

    modal.innerHTML = `<div class="flex-row justify-between"><p></p><div class="return-btn" style="top: 0; left: 0" id="close-info-modal">Volver</div></div>
                       <div class="product-list-container">
                       <div class="flex-row" style="justify-content: space-between; align-items: center">
                        <h3>Lista de Productos</h3>
                        <div class="flex-row all-center" style="gap: 10px;">
                            <div id="edit-controls-container" class="flex-row hidden" style="gap: 10px;">
                                <button id="save-receipt-btn" class="btn btn-primary" style="padding: 5px 10px; font-size: 0.8rem; margin-top: 0;" disabled>Guardar</button>
                                <button id="cancel-receipt-btn" class="btn btn-secondary" style="padding: 5px 10px; font-size: 0.8rem; margin-top: 0;">Cancelar</button>
                            </div>
                            <button id="edit-receipt-btn" class="btn btn-secondary hidden" style="padding: 5px 10px; font-size: 0.8rem; margin-top: 0;">Editar</button>
                            <img src="./assets/img/arrow-pointing-down.png" alt="Flecha" height="30px" id="product-list-btn" class="dropdown-arrow"/>
                        </div>
                        </div>
                        <div id="product-list-dropdown">${receiptTicket}</div>
                        </div>
                        <div class="provider-info-container">${providerInfo}</div>
                        <div id="final-total-container" style="margin-top: auto; text-align:right;" class="flex-column;">
                            <p style="font-size: 20px; font-weight: 600">Id = <span style="font-size: 17px; font-weight: 400">${receiptInfo.id}</span></p>
                            <p style="font-size: 20px; font-weight: 600">Fecha = <span style="font-size: 17px; font-weight: 400">${receiptInfo.receiptDate}</span></p>
                            <h2 style="margin-bottom: 20px; text-align: right">Precio Total = $<span id="final-total-amount">${receiptInfo.totalAmount}</span></h2>
                        </div>
                        `;

    modal.dataset.isEditing = 'false';

    const closeBtn = document.getElementById('close-info-modal');

    closeBtn.addEventListener('click', closeTransactionInfoModal );

    const productListBtn = document.getElementById('product-list-btn');
    const listDropdown = document.getElementById('product-list-dropdown');
    const editBtn = document.getElementById('edit-receipt-btn');

    productListBtn.addEventListener('click', () => {
        if (modal.dataset.isEditing === 'true') return;

        listDropdown.classList.toggle('visible');
        productListBtn.classList.toggle('rotated');

        if (listDropdown.classList.contains('visible')) {
            editBtn.classList.remove('hidden');
        } else {
            editBtn.classList.add('hidden');
        }
    })

    const providerInfoBtn = document.getElementById('provider-info-btn');

    if (providerInfoBtn){
        providerInfoBtn.addEventListener('click', () => {
            if (modal.dataset.isEditing === 'true') return;

            const infoDropdown = document.getElementById('provider-info-dropdown');
            infoDropdown.classList.toggle('visible');
            providerInfoBtn.classList.toggle('rotated');
        })
    }

    document.getElementById('edit-receipt-btn').addEventListener('click', enableReceiptEditing);
    document.getElementById('cancel-receipt-btn').addEventListener('click', handleCancelReceipt);
    document.getElementById('save-receipt-btn').addEventListener('click', handleSaveReceipt);

    showTransactionInfoModal();
}

function newProviderInfo(providerInfo) {
    return `<div class="flex-row justify-between" style="align-items: center">     
                <div class="flex-row all-center" style="width: fit-content; gap: 10px">
                    <h3>Proveedor: </h3>
                    <p style="font-weight: 600">${providerInfo.full_name}</p>
                </div>    
                <img src="./assets/img/arrow-pointing-down.png" alt="Flecha" height="30px" id="provider-info-btn" class="dropdown-arrow"/> 
            </div>
            <div class="flex-column" id="provider-info-dropdown">  
                <p>Email = ${(providerInfo.email !== null) ? providerInfo.email : 'No asignado'}</p>
                <p>Telefono = ${(providerInfo.phone !== null) ? providerInfo.phone : 'No asignado'}</p>
                <p>Dirección = ${(providerInfo.address !== null) ? providerInfo.address : 'No asignado'}</p>
                <p>Fecha de Creación = ${providerInfo.created_at}</p>
            </div>`;
}

// --- NUEVAS FUNCIONES PARA EDICIÓN DE COMPRAS ---

async function enableReceiptEditing() {
    const modal = document.getElementById('transaction-info-modal');
    modal.dataset.isEditing = 'true';
    const originalReceiptInfo = modal.originalReceiptInfo;

    document.getElementById('edit-receipt-btn').classList.add('hidden');
    document.getElementById('product-list-btn').classList.add('hidden');
    document.getElementById('edit-controls-container').classList.remove('hidden');



    const listWrapper = document.getElementById('receipt-item-list-wrapper');
    const itemRows = listWrapper.querySelectorAll('.receipt-item-row');

    for (const row of itemRows){
        const index = row.dataset.index;
        const itemData = originalReceiptInfo.itemList[index];

        const response = await api.getProductData(itemData.item_id, itemData.inventory_id);
        if (!response.success) return;

        const itemInfo = response.productInfo;

        let minStock = itemData.quantity - itemInfo.stock;
        if (minStock < 1) minStock = 1;

        const quantityCell = row.querySelector('.item-quantity');
        const priceCell = row.querySelector('.item-price');
        const totalCell = row.querySelector('.item-total');

        quantityCell.innerHTML = `<input type="number" class="edit-quantity" value="${itemData.quantity}" min="${minStock}" style="width: 70px; text-align: right; padding: 4px;">`;
        priceCell.innerHTML = `<input type="number" class="edit-price" value="${itemData.unit_price}" min="0" step="0.01" style="width: 65px; text-align: right; padding: 4px;">$`;
        totalCell.innerHTML = `<input type="text" class="edit-total" value="${itemData.total_price.toFixed(2)}" style="width: 80px; border:none; background: #eee; text-align: right; padding: 4px; height: fit-content;" readonly>$`;
    }

    const finalTotalContainer = document.getElementById('final-total-container');
    finalTotalContainer.innerHTML = `<h2 style="margin-top:auto; margin-bottom: 20px; text-align: right">Precio Total = <input type="text" id="final-total-input" value="${originalReceiptInfo.totalAmount.toFixed(2)}" style="width: fit-content; border:none; background: #eee; text-align: right; font-weight: 900; color: var(--color-black);" readonly></h2>`;

    modal.addEventListener('input', handleReceiptEdit);
}

function handleReceiptEdit(event) {
    if (!event.target.classList.contains('edit-quantity') && !event.target.classList.contains('edit-price')) {
        return;
    }

    const row = event.target.closest('.receipt-item-row');
    if (!row) return;

    const quantityInput = row.querySelector('.edit-quantity');
    const priceInput = row.querySelector('.edit-price');
    const totalInput = row.querySelector('.edit-total');

    let newQuantity = parseInt(quantityInput.value) || 0;
    const newPrice = parseFloat(priceInput.value) || 0;
    const newRowTotal = newQuantity * newPrice;

    totalInput.value = newRowTotal.toFixed(2);

    let overallTotal = 0;
    document.querySelectorAll('.edit-total').forEach(input => {
        overallTotal += parseFloat(input.value) || 0;
    });

    document.getElementById('final-total-input').value = overallTotal.toFixed(2);

    checkReceiptChanges();
}

function checkReceiptChanges() {
    const modal = document.getElementById('transaction-info-modal');
    const originalReceiptInfo = modal.originalReceiptInfo;
    let hasChanged = false;

    document.querySelectorAll('.receipt-item-row').forEach(row => {
        const index = row.dataset.index;
        const originalItem = originalReceiptInfo.itemList[index];

        const currentQuantity = row.querySelector('.edit-quantity').value;
        const currentPrice = row.querySelector('.edit-price').value;

        if (parseFloat(currentQuantity) !== originalItem.quantity) {
            hasChanged = true;
        }
        if (parseFloat(currentPrice) !== originalItem.unit_price) {
            hasChanged = true;
        }
    });

    document.getElementById('save-receipt-btn').disabled = !hasChanged;
}

async function handleSaveReceipt() {
    const modal = document.getElementById('transaction-info-modal');
    const originalReceiptInfo = modal.originalReceiptInfo;

    const updatedReceiptData = {
        receipt_id: originalReceiptInfo.id,
        items: [],
        newTotal: document.getElementById('final-total-input').value
    };
    document.querySelectorAll('.receipt-item-row').forEach(row => {
        const index = row.dataset.index;
        const originalItem = originalReceiptInfo.itemList[index];

        updatedReceiptData.items.push({
            receipt_item_id: originalItem.receipt_id,
            product_id: originalItem.item_id,
            inventory_id: originalItem.inventory_id,
            product_name: originalItem.product_name,
            original_quantity: originalItem.quantity,
            new_quantity: row.querySelector('.edit-quantity').value,
            original_unit_price: originalItem.unit_price,
            new_unit_price: row.querySelector('.edit-price').value,
            new_total_price: row.querySelector('.edit-total').value
        });
    });

    console.log("Datos de Compra Modificados (listos para enviar al backend):", updatedReceiptData);

    const response = await api.updateRececiptList(updatedReceiptData);

    if (response.success){alert("Se han guardados los cambios. Será redirigido."); window.location.reload();}
    else{alert("Ha ocurrido un error. No se pudieron guardar los cambios");console.log(response.error);}

    handleCancelReceipt();
}
function handleCancelReceipt() {
    const modal = document.getElementById('transaction-info-modal');
    const originalReceiptInfo = modal.originalReceiptInfo;

    modal.removeEventListener('input', handleReceiptEdit);

    showReceiptModal(originalReceiptInfo);
}

function setupOrderBy(){

    //DECLARACION DE VARIABLES IMPORTANTES

    const orderByBtns = document.querySelectorAll('.order-by-btn');
    const directionBtns = document.querySelectorAll('.direction-btn');
    const orderButtons = document.querySelectorAll('.order-btn');

    let viewOrder = '';
    let viewDirection = 'descending';

    //COMPORTAMIENTOS DEL DROPDOWN

    orderByBtns.forEach(button =>{
        button.addEventListener('click', (e) =>{
            e.stopPropagation();
            const currentContainer = button.closest('.order-by-container');
            const currentDropdown = currentContainer.querySelector('.order-by-dropdown');

            if(currentContainer.classList.contains('clicked')) {
                currentContainer.classList.remove('clicked');
                currentDropdown.classList.add('hidden');
            }
            else{
                currentContainer.classList.add('clicked');
                currentDropdown.classList.remove('hidden');
            }
            currentDropdown.addEventListener('click', (e) => {
                e.stopPropagation();
            })
            window.addEventListener('click', () => {
                if (currentContainer.classList.contains('clicked')) {
                    currentContainer.classList.remove('clicked');
                    currentDropdown.classList.add('hidden');
                }
            })
        })
    })


    //SELECCION DE ORDEN
    orderButtons.forEach(button =>{
        button.addEventListener('click', () =>{
            const menuContainer = button.closest('.menu-container');
            const directionButton = menuContainer.querySelector('.direction-btn');

            viewOrder = button.dataset.order;
            directionButton.dataset.order = viewOrder;

            showTransactionView(viewOrder,viewDirection,menuContainer);
        })
    })

    //SELECCION DE DIRECCION

    directionBtns.forEach(button =>{
        button.addEventListener('click', () =>{
            const menuContainer = button.closest('.menu-container');

            if (button.dataset.order === 'none'){
                const orderButton = menuContainer.querySelector('.order-btn');
                button.dataset.order = orderButton.dataset.order;
            }

            viewOrder = button.dataset.order;

            if (button.dataset.direction === 'descending') {
                button.innerHTML = `<i class="ph ph-arrow-up" style="margin-right: 5px"></i>
                                        <i class="ph ph-arrow-down hidden" style="margin-right: 5px"></i>`;
                viewDirection = 'ascending';
                button.dataset.direction = 'ascending';
            }
            else {
                button.innerHTML = `<i class="ph ph-arrow-up hidden" style="margin-right: 5px"></i>
                                        <i class="ph ph-arrow-down" style="margin-right: 5px"></i>`;
                viewDirection = 'descending';
                button.dataset.direction = 'descending';
            }

            showTransactionView(viewOrder,viewDirection,menuContainer);
        })
    })
}

//CAMBIO DE VIEW SEGUN EL ORDEN SELECCIONADO

function showTransactionView(viewOrder, viewDirection,menuContainer) {
    menuContainer.querySelectorAll('.transaction-view').forEach(view => view.classList.add('hidden'));
    const viewToShow =  document.getElementById(viewOrder + '-' + viewDirection);
    console.log(viewOrder + '-' + viewDirection);
    if (viewToShow) { viewToShow.classList.remove('hidden'); }
}

/* -------------------- FUNCIONES DE TRANSACCIONES -------------------- */

//Detecta la transaccion que se quiere agregar
function setupTransactions(){
    const newTransacionButtons = document.querySelectorAll('.new-transaction-btn');
    document.querySelectorAll('.new-transaction-btn').length
    const greyBg = document.getElementById('grey-background');

    configTransactionModalsReturn();
    configureTablePickerDropdown();

    newTransacionButtons.forEach(button =>{
        button.addEventListener('click', async () =>{
            const transactionModal = document.getElementById('new-transaction-container');
            const pickerModal = document.getElementById('transaction-picker-modal');
            const successModal = document.getElementById('transaction-success-modal');
            const infoModal = document.getElementById('transaction-info-modal');
            infoModal.classList.add('hidden');
            successModal.classList.add('hidden');
            pickerModal.classList.add('hidden');
            transactionModal.classList.remove('hidden');
            greyBg.classList.remove('hidden');
            await populateTransactionContainer(button.dataset.transaction);
        })
    })
}

function configureTablePickerDropdown(){
    const inventoryPickerName = document.getElementById('inventory-picker-name');
    const itemPickerHeader = document.getElementById('item-picker-header');

    inventoryPickerName.addEventListener('click', () => {
        itemPickerHeader.classList.toggle('visible');
    })
}

//Llena el modal de transacción segun la transacción elegida
async function populateTransactionContainer(transactionType){
    const transactionFormContainer = document.getElementById('transaction-form-container');
    let itemList= [];

    transactionFormContainer.innerHTML = getForm(transactionType);

    if (transactionType === 'sale' || transactionType ===  'receipt'){
        //Prepara el selector de productos
        await populateProductPicker(transactionType);
        //Prepara el selector de clientes
        await populateTransactionClientList();
        //Prepara el selector de provedores
        await populateTransactionProviderList();
        //Prepara los botones para subir/bajar la cantidad de productos
        setupQuantityBtns(transactionType,itemList);
        //Prepara los botones para eliminar items de la lista
        configRemoveProduct(itemList,transactionType);
        //Prepara los botones que abren los modales de productos/clientes/proveedores
        configurePickerBtns();
        //Renderiza la lista de productos
        renderProductList(itemList,transactionType);
    }

    else if (transactionType === 'customer') {configCreateClientBtn(); }
    else {configCreateProviderBtn(); }

    setupPickerConfirmBtns(itemList,transactionType);

    //Prepara los botones que completan las transacciones de compra/venta
    setupCompleteTransactionBtn(itemList);
}

function setupPickerConfirmBtns(itemList, transactionType){
    const allBtns = document.querySelectorAll('.picker-confirm-btn');

    allBtns.forEach(btn => {
        btn.onclick = () => {
            if (btn.disabled) return;

            const confirmType = btn.dataset.type;
            const selectedData = JSON.parse(btn.dataset.data);

            switch (confirmType) {
                case 'item':
                    const selectedProduct = selectedData;
                    selectedProduct.amount = 1;
                    Object.defineProperty(selectedProduct, 'totalPrice', {
                        get() {
                            if (transactionType === "sale") { return this.salePrice * this.amount }
                            else { return this.receiptPrice * this.amount }
                        }
                    });
                    addProduct(selectedProduct, itemList, transactionType);
                    break;

                case 'client':
                    selectClient(selectedData.id, selectedData.name);
                    break;

                case 'provider':
                    selectProvider(selectedData.id, selectedData.name);
                    break;
            }

            hideTransactionModal();
            btn.disabled = true;
            delete btn.dataset.item;
            document.querySelectorAll('.product-item.selected, .client-picker-item.selected, .provider-picker-item.selected').forEach(i => i.classList.remove('selected'));
        };
    })
}

function configTransactionModalsReturn(){
    const returnBtn = document.getElementById('modal-return-btn');
    const itemPickerHeader = document.getElementById('item-picker-header');

    returnBtn.addEventListener('click', () => {
        itemPickerHeader.classList.remove('visible');
        hideTransactionModal();
    })
}

function configRemoveProduct(itemList){
    const productListContainer = document.getElementById('product-list-container');

    if (productListContainer){
        productListContainer.addEventListener('click', (event) =>{
            const deleteButton = event.target.closest('.delete-product-btn');
            if (!deleteButton) return;

            const transactionType = deleteButton.dataset.type;

            const itemID = parseInt(deleteButton.dataset.pid,10);
            const tableID = parseInt(deleteButton.dataset.tid,10);

            const itemIndex = itemList.findIndex(item => item.pID === itemID && item.tID === tableID);

            if (itemIndex > -1) {
                itemList.splice(itemIndex, 1);
                renderProductList(itemList,transactionType);
            }
        })
    }
}

function configurePickerBtns(){
    const pickerBtns = document.querySelectorAll('.picker-btn');
    const pickerModalContainer = document.getElementById('transaction-picker-modal');

    pickerBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const modalID = btn.dataset.modalTarget;
            pickerModalContainer.classList.remove('hidden');
            showPickerModal(modalID);
        })
    })

    window.addEventListener('click', (event) => {

        const isPickerBtn = event.target.closest('.picker-btn');
        // Si el picker está visible Y el clic fue FUERA del picker
        if (!pickerModalContainer.classList.contains('hidden') &&
            !pickerModalContainer.contains(event.target) &&
            !isPickerBtn === null) {
            hideTransactionModal();
        }
    });
}

function setupQuantityBtns(transactionType, itemList){
    const productListContainer = document.getElementById('product-list-container');

    productListContainer.addEventListener('click', (event) => {
        const button = event.target.closest('.modify-quantity-btn');
        if (!button) return;

        const productID = parseInt(button.dataset.pid,10);
        const tableID = parseInt(button.dataset.tid,10);
        const type = button.dataset.type;

        hideTransactionError();
        let item = itemList.find(item => item.pID === productID && item.tID === tableID);
        if (!item) return;

        if (type === 'add') {
            if (item.amount === (item.stock) && transactionType === 'sale') {
                const errorMessage = 'La cantidad de un producto no puede ser mayor a su stock actual';
                showTransactionError(errorMessage);
            }
            else{item.amount++;}
        }
        else {if (item.amount > 1) {item.amount--;}
        else{
            const errorMessage = 'La cantidad de un producto no puede ser menor a 1';
            showTransactionError(errorMessage);
        }
        }
        renderProductList(itemList,transactionType);
    })
}

function createNewSaleRow(selectedProduct) {
    // Devuelve un string HTML para una fila de tabla (TR)
    return `
        <tr data-pid="${selectedProduct.pID}" data-tid="${selectedProduct.tID}">
            <td class="col-product" title="${selectedProduct.name} ${selectedProduct.tableName ? `(${selectedProduct.tableName})` : ''}">
                ${selectedProduct.name}
                ${selectedProduct.tableName ? `<span style="font-size: 0.8rem; color: var(--color-gray); display: block;">(${selectedProduct.tableName})</span>` : ''}
            </td>
            <td class="col-stock">${selectedProduct.stock}</td>
            <td class="col-quantity">
                <div class="quantity-cell">
                    <i class="modify-quantity-btn ph ph-minus-square" data-pid="${selectedProduct.pID}" data-tid="${selectedProduct.tID}" data-type="subtract"></i>
                    <span class="quantity-display">${selectedProduct.amount}</span>
                    <i class="modify-quantity-btn ph ph-plus-square" data-pid="${selectedProduct.pID}" data-tid="${selectedProduct.tID}" data-type="add"></i>
                </div>
            </td>
            <td class="col-price">$${(selectedProduct.salePrice || 0).toFixed(2)}</td>
            <td class="col-total">$${(selectedProduct.totalPrice || 0).toFixed(2)}</td>
            <td class="col-actions">
                <button type="button" class="btn delete-product-btn" data-type="sale" data-pid="${selectedProduct.pID}" data-tid="${selectedProduct.tID}">
                    <i class="ph ph-x"></i>
                </button>
            </td>
        </tr>
    `;
}

function createNewReceiptRow(selectedProduct) {
    // Devuelve un string HTML para una fila de tabla (TR)
    return `
        <tr data-pid="${selectedProduct.pID}" data-tid="${selectedProduct.tID}">
            <td class="col-product" title="${selectedProduct.name} ${selectedProduct.tableName ? `(${selectedProduct.tableName})` : ''}">
                ${selectedProduct.name}
                ${selectedProduct.tableName ? `<span style="font-size: 0.8rem; color: var(--color-gray); display: block;">(${selectedProduct.tableName})</span>` : ''}
            </td>
            <td class="col-stock">${selectedProduct.stock}</td>
            <td class="col-quantity">
                <div class="quantity-cell">
                    <i class="modify-quantity-btn ph ph-minus-square" data-pid="${selectedProduct.pID}" data-tid="${selectedProduct.tID}" data-type="subtract"></i>
                    <span class="quantity-display">${selectedProduct.amount}</span>
                    <i class="modify-quantity-btn ph ph-plus-square" data-pid="${selectedProduct.pID}" data-tid="${selectedProduct.tID}" data-type="add"></i>
                </div>
            </td>
            <td class="col-price">$${(selectedProduct.receiptPrice || 0).toFixed(2)}</td>
            <td class="col-total">$${(selectedProduct.totalPrice || 0).toFixed(2)}</td>
            <td class="col-actions">
                <button type="button" class="btn delete-product-btn" data-type="receipt" data-pid="${selectedProduct.pID}" data-tid="${selectedProduct.tID}">
                    <i class="ph ph-x"></i>
                </button>
            </td>
        </tr>
    `;
}



function renderProductList(itemList,transactionType){
    const productListContainer = document.getElementById('product-list-container');
    const totalPriceInput = document.getElementById('price-input');
    const totalPriceText = document.getElementById('price-text');
    let priceAcum = 0;

    productListContainer.innerHTML = '';

    if (itemList.length === 0) {
        productListContainer.innerHTML = `<div class="empty-product-list">
            <i class="ph ph-basket"></i>
            <p>Aún no hay productos en la lista.</p>
        </div>`;
        totalPriceInput.value = priceAcum;
        totalPriceText.textContent = priceAcum;
        return;
    }

    let tableRows = '';
    itemList.forEach(item =>{
        priceAcum += item.totalPrice;
        let newProductRow;
        if (transactionType === 'sale') {
            tableRows += createNewSaleRow(item);
        }
        else{
            tableRows += createNewReceiptRow(item);
        }
    })

    // Determina el encabezado de precio correcto
    const tableHeader = (transactionType === 'sale')
        ? '<th class="col-price">Precio Venta</th>'
        : '<th class="col-price">Precio Compra</th>';

    productListContainer.innerHTML = `
        <table class="transaction-product-table">
            <thead>
                <tr>
                    <th class="col-product">Producto</th>
                    <th class="col-stock">Stock</th>
                    <th class="col-quantity">Cantidad</th>
                    ${tableHeader}
                    <th class="col-total">Total</th>
                    <th class="col-actions"><i class="ph ph-trash"></i></th>
                </tr>
            </thead>
            <tbody>
                ${tableRows}
            </tbody>
        </table>
    `;

    totalPriceText.textContent = `$${priceAcum.toFixed(2)}`;
    totalPriceInput.value = priceAcum;
}

async function populateTransactionClientList(){
    const response = await api.getAllClients();
    const clientes = response.clientList;

    const clientModal = document.getElementById('client-list');

    clientModal.innerHTML = '';

    const none = {full_name: 'No Asignado', id: null};
    const noneSelected = createPersonItem(none,'sale');
    clientModal.appendChild(noneSelected);

    clientes.forEach(client => {
        const newClientItem = createPersonItem(client,'sale');
        clientModal.appendChild(newClientItem);
    })

    configureClientSelection();
}

function createPersonItem(person, transactionType){
    const item = document.createElement('div');

    item.innerHTML = `<div class="flex-column">
                            <div class="person-name">${person.full_name}</div>
                      </div>
                      <div class="flex-colum" style="text-align: right; width: 100%;">
                            <div class="person-id">${person.id || ''}</div>
                      </div>`
    ;
    item.dataset.id = person.id;
    item.dataset.name = person.full_name;
    item.className = 'flex-row';
    transactionType === 'sale' ? item.classList.add('client-picker-item') : item.classList.add('provider-picker-item');

    return item;
}

function configureClientSelection(){
    const clientPickers = document.querySelectorAll('.client-picker-item');
    clientPickers.forEach(client => {
        client.addEventListener('click', () => {
            const clientID = parseInt(client.dataset.id);
            const clientName = client.dataset.name;

            const modalBody = client.closest('.picker-modal');
            if (!modalBody) return;

            modalBody.querySelectorAll('.client-picker-item').forEach(item => item.classList.remove('selected'));
            client.classList.add('selected');

            const confirmBtn = modalBody.querySelector('.picker-confirm-btn');

            confirmBtn.dataset.data = JSON.stringify({id: clientID, name: clientName});
            confirmBtn.disabled = false;
        })
    })
}

function selectClient(clientID, clientName){
    const clientFormInput = document.getElementById('client-id-input');
    const formText = document.getElementById('sale-client-name');

    formText.innerHTML = (isNaN(clientID)) ? 'Ninguno' : clientName;
    clientFormInput.value = clientID;

    hideTransactionModal();
}

async function populateTransactionProviderList(){

    const response = await api.getAllProviders();
    const providers = response.providerList;

    const providerModal = document.getElementById('provider-list');

    providerModal.innerHTML = '';

    const none = {full_name: 'Ninguno', id: null};
    const noneSelected = createPersonItem(none,'receipt')
    providerModal.appendChild(noneSelected);

    providers.forEach(provider => {
        const newProviderItem = createPersonItem(provider,'receipt')
        providerModal.appendChild(newProviderItem);
    })

    configureProviderSelection();
}

function configureProviderSelection(){
    const providerPickers = document.querySelectorAll('.provider-picker-item');
    providerPickers.forEach(provider => {
        provider.addEventListener('click', () => {
            const providerID = parseInt(provider.dataset.id, 10);
            const providerName = provider.dataset.name;

            const modalBody = provider.closest('.picker-modal');
            if (!modalBody) return;

            modalBody.querySelectorAll('.provider-picker-item').forEach(i => i.classList.remove('selected'));
            provider.classList.add('selected');

            const confirmBtn = modalBody.querySelector('.picker-confirm-btn');

            confirmBtn.dataset.data = JSON.stringify({ id: providerID, name: providerName });
            confirmBtn.disabled = false;
        })
    })
}

function selectProvider(providerID,providerName){
    const providerFormInput = document.getElementById('provider-id-input');
    const formText = document.getElementById('receipt-provider-name');

    formText.innerHTML = (isNaN(providerID)) ? 'Ninguno' : providerName;
    providerFormInput.value = providerID;

    hideTransactionModal();
}

function addProduct(selectedProduct,itemList,transactionType) {
    if (itemList.find(item => item.pID === selectedProduct.pID && item.tID === selectedProduct.tID)){
        showTransactionError('El producto ya se encuentra en la lista');
        hideTransactionModal();
        return;
    }
    hideTransactionError();

    itemList.push(selectedProduct);

    renderProductList(itemList,transactionType);
    hideTransactionModal();
}

async function populateProductPicker(transactionType){
    const response = await api.getUserVerifiedTables();
    if (!response.success) {showTransactionError(response.error); return;}

    const tables = response['verifiedInventories'];

    populateTablePicker(tables);
    configureTablePickers();

    let products = [];

    try {
        for (const table of tables){
            const response = await api.getTableProducts(table.id);
            if (!response.success) {showTransactionError(response.error);return;}

            const tableProducts = response.productList;
            if (tableProducts.length === 0) {continue;}

            products.push(...tableProducts);
        }
    } catch (error) {
        console.error("Error al procesar los inventarios del usuario:", error);
        showTransactionError("Error inesperado de conexión.");
        return;
    }

    populateProductModal(products,tables,transactionType);
    configureProductSelection(products);
}

function populateTablePicker(tables){
    const itemPickerHeader = document.getElementById('item-picker-header');
    const inventoryPicker = document.createElement('div');

    itemPickerHeader.innerHTML = '';
    inventoryPicker.className = 'flex-column';

    const tableItem = document.createElement('div');

    tableItem.dataset.tID = 'all';
    tableItem.innerHTML = 'Todos';
    tableItem.className = 'product-table-picker';

    inventoryPicker.appendChild(tableItem);

    tables.forEach(table => {
        const tableItem = document.createElement('div');
        tableItem.dataset.tID = table.id;
        tableItem.innerHTML = table.name;
        tableItem.className = 'product-table-picker';

        inventoryPicker.appendChild(tableItem);
    })

    itemPickerHeader.appendChild(inventoryPicker);
}


function populateProductModal(products, tables, transactionType){
    const itemPickerBody = document.getElementById('item-list');

    itemPickerBody.innerHTML = '';

    const allProductsDiv = document.createElement('div');
    allProductsDiv.className = 'flex-column product-list';
    allProductsDiv.id = 'all';

    let emptyList = true;

    products.forEach(product => {
        emptyList = false;
        const item = createProductItem(product,transactionType);
        allProductsDiv.appendChild(item);
    })

    if (emptyList) {allProductsDiv.innerHTML = '<h3>No hay productos en el inventario</h3>';}

    const productListWrapper = document.createElement('div');
    productListWrapper.className = 'hidden';
    productListWrapper.id = 'product-list-wrapper';

    itemPickerBody.appendChild(allProductsDiv);

    tables.forEach(table => {
        emptyList = true;
        const tableProductsDiv = document.createElement('div');
        tableProductsDiv.className = 'hidden flex-column product-list';
        tableProductsDiv.id = table.id;

        products.forEach(product => {
            if (product.tID !== table.id) return;
            emptyList = false;
            const item = createProductItem(product,transactionType);
            tableProductsDiv.appendChild(item);
        })
        if (emptyList) {tableProductsDiv.innerHTML = '<h3>No hay productos en el inventario</h3>';}
        productListWrapper.appendChild(tableProductsDiv);
    })
    itemPickerBody.appendChild(productListWrapper);
}

function createProductItem(product,transactionType){
    const item = document.createElement('div');
    let price;
    if (transactionType === 'sale'){price = product.salePrice;}
    else {price = product.receiptPrice;}

    item.innerHTML = `<div class="flex-column">
                            <div class="product-name">${product.name}</div>
                            <div class="product-stock">Stock Disponible: <span>${product.stock}</span></div>
                      </div>
                      <div class="flex-colum" style="text-align: right; width: 100%;">
                            <div class="product-inventory-name">${product.tableName}</div>
                            <div class="product-price">$${price}</div>
                      </div>`
    ;
    item.dataset.tID = product.tID;
    item.dataset.pID = product.pID;
    item.className = 'flex-row product-item';

    if (product.stock === 0 && transactionType === 'sale') {item.classList.add('no-stock');}

    return item;
}


function configureProductSelection(products){
    const productItem = document.querySelectorAll('.product-item');
    productItem.forEach(item => {
        if (item.classList.contains('no-stock')) return;
        item.addEventListener('click', () => {
            const findProduct = products.find(product => product.pID === parseInt(item.dataset.pID,10) &&
                product.tID === parseInt(item.dataset.tID,10));
            if (!findProduct){
                showTransactionError('El producto no existe');
                return;
            }

            const modalBody = item.closest('.picker-modal');
            if (!modalBody) return;

            modalBody.querySelectorAll('.product-item').forEach(i => i.classList.remove('selected'));
            item.classList.add('selected');

            const selectedProduct = { ... findProduct };
            const confirmBtn = modalBody.querySelector('.picker-confirm-btn');

            confirmBtn.disabled = false;
            confirmBtn.dataset.data = JSON.stringify(selectedProduct);
        })
    })
}

function configureTablePickers(){
    const tablePickers = document.querySelectorAll('.product-table-picker');

    tablePickers.forEach(table => {
        table.addEventListener('click', () => {
            const tableID = table.dataset.tID;
            const itemPickerHeader = document.getElementById('item-picker-header');
            const inventoryPickerName = document.getElementById('inventory-picker-name');

            itemPickerHeader.classList.remove('visble');
            inventoryPickerName.innerHTML = table.innerHTML;
            showProductList(tableID);
        })
    })
}

function setupCompleteTransactionBtn(itemList){

    const buttons = document.querySelectorAll('.complete-transaction-btn');

    buttons.forEach(button => {
        button.addEventListener('click', async () => {
            const transactionType = button.dataset.type;

            if (transactionType ==='sale'){
                const emailInfo = await completeSale(itemList);
                if (emailInfo) {setupSendEmailBtn(emailInfo);}

            }
            else if (transactionType ==='receipt'){ await completeReceipt(itemList);}
        })
    })
}

async function completeSale(itemList){
    if (itemList.length === 0) {showTransactionError('Agregar al menos un producto.'); return;}

    let clientID = document.getElementById('client-id-input').value;
    const totalPrice = document.getElementById('price-input').value;
    const clientName = document.getElementById('sale-client-name').innerHTML;

    if (clientID === '' || isNaN(clientID)) {clientID = null;}

    const saleInfo = {'itemList' : itemList, 'clientID' : clientID, 'totalPrice' : totalPrice};

    var response = await api.createSale(saleInfo);
    if (!response.success) {showTransactionError('Ha ocurrido un error interno = ' + response.error); return;}

    const saleID = response.saleId;

    response = await api.getCustomerById(clientID);
    if (!response.success) {showTransactionError('Ha ocurrido un error interno = ' + response.error); return;}

    const client = response.clientInfo;
    var hasEmail = false, emailInfo = null;

    if (client){
        hasEmail = (client.email!==null);
    }

    const saleList = itemList.map(item => {
        const name = item.name;
        const amount = item.amount;
        const price = item.salePrice;
        const totalPrice = item.totalPrice;

        return `<div class="flex-row" style="gap: 15px;">
                <p style="width: 100px; text-align: right; overflow: hidden; text-wrap: nowrap; text-overflow: ellipsis">${name}</p>
                <p style="width: 70px; text-align: right">${amount}</p>
                <p style="width: 65px; text-align: right">${price}$</p>
                <p style="width: 80px; text-align: right">${totalPrice}$</p>
             </div>`;
    }).join('');

    const saleTicket = `<div style="margin-top: 20px; padding: 5px; border: var(--border-strong); border-radius: var(--border-radius)">
    <hr> <div class="flex-row" style="justify-content: space-between"><p>N° ${saleID}</p><h2 style="text-align: right">Lista de Productos</h2></div>
    
    <div class="flex-column" style="gap: 15px; margin-top: 15px;"> 
         
         <div class="flex-row" style="text-align: right; gap: 15px;"><h4 style="width: 100px">Nombre</h4>
         <h4 style="width: 70px">Cantidad</h4><h4 style="width: 65px">Precio de Venta</h4><h4 style="width: 80px">Precio Total</h4></div>
         <div class="flex-column" style="max-height: 200px; overflow-y: auto;">${saleList}</div>
         <hr>
         <div class="flex-column" style="text-align: right"><h4>Cliente</h4><p>${clientName}</p></div>
         <div class="flex-row" style="justify-content: right; gap:10px;"><h2>Precio Final =</h2><h1>${totalPrice}$</h1></div>
    </div></div>`;

    const transactionSuccessBody = `
    <h3 class="flex-row all-center" style="margin-top: 10px">Se ha registrado la venta con exito.</h3>
    <div>${saleTicket}</div>
    ${(hasEmail) ? `<div class="flex-row" style="justify-content: end">
<div class="btn btn-primary"  id="send-sale-email-btn" style="width: 50%; background-color: var(--accent-color); font-size: 0.85rem">Enviar Factura a Cliente</div></div>`
        : ''}
    <p style="margin-top: 15px; font-size: 0.75rem">Actualiza la pagina para ver tus cambios.</p>
    <div class="flex-column all-center">
        <div class="btn btn-primary reload-btn" data-location="sales">Actualizar Pagina</div>
        <div class="btn btn-secondary" id="success-return-btn">Cerrar</div>
    </div>`;

    if (hasEmail) {
        // Construir emailInfo en el formato esperado por send-email.php
        emailInfo = {
            to: client.email,
            subject: `Factura de su compra - Venta #${saleID}`,
            sale: {
                id: saleID,
                date: new Date().toISOString().slice(0,19).replace('T',' '),
                customer: client.name,
                items: itemList.map(item => ({
                    name: item.name,
                    quantity: item.amount,
                    unit_price: item.salePrice,
                    total: item.totalPrice
                })),
                total: totalPrice
            }
        };
    }

    showTransactionSuccess(transactionSuccessBody);
    return emailInfo;
}

async function completeReceipt(itemList){

    if (itemList.length === 0) {showTransactionError('Agregar al menos un producto.'); return;}

    let providerID = document.getElementById('provider-id-input').value;
    const providerName = document.getElementById('receipt-provider-name').innerHTML;
    const totalPrice = document.getElementById('price-input').value;

    if (providerID === '' || isNaN(providerID)) {providerID = null;}

    const receiptInfo = {'itemList' : itemList, 'providerID' : providerID, 'totalPrice' : totalPrice};

    var response = await api.createReceipt(receiptInfo);
    if (!response.success) {showTransactionError('Ha ocurrido un error interno = ' + response.error); return;}

    const receiptID = response.receiptId;

    const receiptList = itemList.map(item => {
        const name = item.name;
        const amount = item.amount;
        const price = item.receiptPrice;
        const totalPrice = item.totalPrice;

        return `<div class="flex-row" style="gap: 15px;">
                <p style="width: 100px; text-align: right; overflow: hidden; text-wrap: nowrap; text-overflow: ellipsis">${name}</p>
                <p style="width: 70px; text-align: right">${amount}</p>
                <p style="width: 65px; text-align: right">${price}$</p>
                <p style="width: 80px; text-align: right">${totalPrice}$</p>
             </div>`;
    }).join('');

    const receiptTicket = `<div style="margin-top: 20px; padding: 5px; border: var(--border-strong); border-radius: var(--border-radius)">
    <hr> <div class="flex-row" style="justify-content: space-between"><p>N° ${receiptID}</p><h2 style="text-align: right">Lista de Productos</h2></div>
    
    <div class="flex-column" style="gap: 15px; margin-top: 15px;"> 
         
         <div class="flex-row" style="text-align: right; gap: 15px;"><h4 style="width: 100px">Nombre</h4>
         <h4 style="width: 70px">Cantidad</h4><h4 style="width: 65px">Precio de Venta</h4><h4 style="width: 80px">Precio Total</h4></div>
         <div class="flex-column" style="max-height: 200px; overflow-y: auto;">${receiptList}</div>
         <hr>
         <div class="flex-column" style="text-align: right"><h4>Proveedor</h4><p>${providerName}</p></div>
         <div class="flex-row" style="justify-content: right; gap:10px;"><h2>Precio Final =</h2><h1>${totalPrice}$</h1></div>
    </div></div>`;

    const transactionSuccessBody = `
    <h3 class="flex-row all-center" style="margin-top: 10px">Se ha registrado la venta con exito.</h3>
    <div>${receiptTicket}</div>
    <p style="margin-top: 15px; font-size: 0.75rem">Actualiza la pagina para ver tus cambios.</p>
    <div class="flex-column all-center">
        <div class="btn btn-primary reload-btn" data-location="receipts">Actualizar Pagina</div>
        <div class="btn btn-secondary" id="success-return-btn">Cerrar</div>
    </div>`;

    showTransactionSuccess(transactionSuccessBody);
}

function configCreateClientBtn(){
    const customerForm = document.getElementById('customer-form');

    customerForm.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!customerForm.checkValidity()) {
            showTransactionError('Verifique que los campos ingresados tengan el formato indicado'); return;
        }
        completeCustomer();
    })
}

function configCreateProviderBtn(){
    const providerForm = document.getElementById('provider-form');

    providerForm.addEventListener('submit', (event) => {
        event.preventDefault();
        if (!providerForm.checkValidity()) {
            showTransactionError('Verifique que los campos ingresados tengan el formato indicado'); return;
        }
        completeProvider();
    })
}

async function completeCustomer(){
    const clientName = document.getElementById('client-name').value;

    if (clientName === ''){showTransactionError('Es obligatorio asignarle un nombre al cliente.'); return;}

    var clientEmail = document.getElementById('client-email').value;
    var clientPhone = document.getElementById('client-phone').value;
    var clientAddress = document.getElementById('client-address').value;
    var clientDNI = document.getElementById('client-dni').value;

    const clientList = await api.getAllClients();

    if (!clientList.success){
        {showTransactionError('Ha ocurrido un error interno.' + clientList.error); return;}
    }

    if (clientList.clientList.find(client => client.email === clientEmail) && clientEmail !== ''){
        showTransactionError('Ya existe un cliente registrado con ese email.'); return;
    }
    if (clientList.clientList.find(client => parseInt(client.phone,10) === parseInt(clientPhone,10)) && clientPhone !== ''){
        showTransactionError('Ya existe un cliente registrado con ese telefono.'); return;
    }
    if (clientList.clientList.find(client => client.tax_id === clientDNI) && clientDNI !== ''){
        showTransactionError('Ya existe un cliente registrado con ese dni.'); return;
    }

    clientEmail = (clientEmail === '') ? null : clientEmail;
    clientPhone = (clientPhone === '') ? null : clientPhone;
    clientAddress = (clientAddress === '') ? null : clientAddress;
    clientDNI = (clientDNI === '') ? null : clientDNI;

    const client = {'name' : clientName, 'email' : clientEmail, 'phone' : clientPhone, 'address' : clientAddress, 'dni' : clientDNI};

    const result = await api.createClient(client);

    if (!result.success){
        {showTransactionError('Ha ocurrido un error interno. No se pudo registrar el cliente'); return;}
    }

    console.log('Cliente registrado');

    clientEmail = (clientEmail === null) ? 'No asignado' : clientEmail;
    clientPhone = (clientPhone === null) ? 'No asignado' : clientPhone;
    clientAddress = (clientAddress === null) ? 'No asignado' : clientAddress;
    clientDNI = (clientDNI === null) ? 'No asignado' : clientDNI;

    const transactionSuccessBody = `
    <h3 class="flex-row all-center" style="margin-top: 10px">Se ha creado el cliente con éxito.</h3>
    <div class="flex-column" style="flex-wrap: wrap; gap: 15px; margin-top: 25px; overflow: hidden"> 
        <div class="flex-column"><h4>Nombre</h4><p>${clientName}</p></div>
        <div class="flex-column"><h4>Email</h4><p>${clientEmail}</p></div>
        <div class="flex-column"><h4>Telefono</h4><p>${clientPhone}</p></div>
        <div class="flex-column"><h4>Direccion</h4><p>${clientAddress}</p></div>
        <div class="flex-column"><h4>DNI</h4><p>${clientDNI}</p></div>
    </div>
    <p style="margin-top: 15px; font-size: 0.75rem">Actualiza la pagina para ver tus cambios.</p>
    <div class="flex-column all-center">
        <div class="btn btn-primary reload-btn" data-location="customers">Actualizar Pagina</div>
        <div class="btn btn-secondary" id="success-return-btn">Cerrar</div>
    </div>`;

    showTransactionSuccess(transactionSuccessBody);
}

async function setupProviders(){
    const response = await api.getOrderedProviders();
    if (response.success){
        const providers = response.providerList;
        populateProviderModal(providers);
    }
    else{
        console.log('no salio bien' + response.error);
    }
}

function populateEmptyProviderModal(){
    const providerModals = document.querySelectorAll('.provider-view');
    providerModals.forEach(modal => {
        modal.innerHTML = `<div class="flex-row"><div class="flex-column">
            <h2>No has creado ningun Proveedor</h2>
            <button class="btn btn-primary new-transaction-btn" data-transaction="provider">Crea tu Primer Proveedor</button>
            </div></div>`;
    })
}

function populateProviderModal(providers){
    const providersByNameDesc = providers.name.descending;
    const providersByNameAsc = providers.name.ascending;
    const providersByEmailDesc = providers.email.descending;
    const providersByEmailAsc = providers.email.ascending;
    const providersByDateDesc = providers.date.descending;
    const providersByDateAsc = providers.date.ascending;
    const providersByPhoneDesc = providers.phone.descending;
    const providersByPhoneAsc = providers.phone.ascending;
    const providersByAddressDesc = providers.address.descending;
    const providersByAddressAsc = providers.address.ascending;

    const providerEmailDescending = document.getElementById('providers-table-email-descending');
    const providerEmailAscending = document.getElementById('providers-table-email-ascending');
    const providerDateDescending = document.getElementById('providers-table-date-descending');
    const providerDateAscending = document.getElementById('providers-table-date-ascending');
    const providerNameDescending = document.getElementById('providers-table-name-descending');
    const providerNameAscending = document.getElementById('providers-table-name-ascending');
    const providerPhoneDescending = document.getElementById('providers-table-phone-descending');
    const providerPhoneAscending = document.getElementById('providers-table-phone-ascending');
    const providerAddressDescending = document.getElementById('providers-table-address-descending');
    const providerAddressAscending = document.getElementById('providers-table-address-ascending');

    providersByNameDesc.forEach(provider =>{
        providerNameDescending.appendChild(createProviderRow(provider));
    })
    providersByNameAsc.forEach(provider =>{
        providerNameAscending.appendChild(createProviderRow(provider));
    })
    providersByEmailDesc.forEach(provider=>{
        providerEmailDescending.appendChild(createProviderRow(provider));
    })
    providersByEmailAsc.forEach(provider =>{
        providerEmailAscending.appendChild(createProviderRow(provider));
    })
    providersByDateDesc.forEach(provider =>{
        providerDateDescending.appendChild(createProviderRow(provider));
    })
    providersByDateAsc.forEach(provider =>{
        providerDateAscending.appendChild(createProviderRow(provider));
    })
    providersByPhoneDesc.forEach(provider =>{
        providerPhoneDescending.appendChild(createProviderRow(provider));
    })
    providersByPhoneAsc.forEach(provider =>{
        providerPhoneAscending.appendChild(createProviderRow(provider));
    })
    providersByAddressDesc.forEach(provider =>{
        providerAddressDescending.appendChild(createProviderRow(provider));
    })
    providersByAddressAsc.forEach(provider =>{
        providerAddressAscending.appendChild(createProviderRow(provider));
    })
}

function createProviderRow (provider){
    const providerDiv = document.createElement('div');
    providerDiv.classList.add('provider-row');
    providerDiv.innerHTML = `<div>
                                <h3>${provider.full_name}</h3>
                            </div>
                            <div><p style="font-size: 15px">${provider.created_at}</p></div>`

    providerDiv.addEventListener('click', () => {
        showProviderInfoModal(provider);
    })
    return providerDiv;
}

function showProviderInfoModal(provider){
    const modal = document.getElementById('transaction-info-modal');

    const providerEmail = (provider.email === null) ? '' : provider.email;
    const providerPhone = (provider.phone === null) ? '' : provider.phone;
    const providerAddress = (provider.address === null) ? '' : provider.address;

    modal.innerHTML = `<form class="flex-column" method="get" action="/dashboard.php" id="provider-info-form">
                                <h4 class="transaction-error-message hidden" style="color: var(--accent-red)"></h4>
                                <label for="provider-name"><h2>Nombre</h2></label>
                                <input type="text" name="name" id="provider-name" placeholder="No asignado." value="${provider.full_name}" required>
                                <hr>
                                <label for="provider-email" class="flex-row" style="gap: 5px"><h2>Email</h2><p>(Opcional)</p></label>
                                <input type="email" name="email" id="provider-email" placeholder="No asignado." value="${providerEmail}">
                                <label for="provider-phone" class="flex-row" style="gap: 5px"><h2>Telefono </h2><p>(Opcional)</p></label>
                                <input type="text" name="phone" id="provider-phone" placeholder="No asignado." 
                                value="${providerPhone}" minlength="8" pattern="[0-9]+">
                                <label for="client-address" class="flex-row" style="gap: 5px"><h2>Dirección </h2><p>(Opcional)</p></label>
                                <input type="text" name="address" id="provider-address" placeholder="No asignado." value="${providerAddress}">
                                <button class="btn btn-primary" id="save-provider-btn" disabled>Guardar Cambios</button>
                                </form>`;

    const saveBtn = document.getElementById('save-provider-btn');

    const form = document.getElementById('provider-info-form');
    let formInitialState = {};

    const formInputs = form.querySelectorAll('input');

    formInputs.forEach(input => {
        formInitialState[input.name] = input.value;
    })

    form.addEventListener('input', () => {
        const currentInputs = form.querySelectorAll('input');

        const modified = Array.from(currentInputs).some(input => {
            return formInitialState[input.name] !== input.value;
        });

        saveBtn.disabled = !modified;
    })

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        await saveProviderChanges(provider.id);
    })

    showTransactionInfoModal();
}

async function saveProviderChanges(providerID){

    const providerName = document.getElementById('provider-name').value;

    if (providerName === ''){showTransactionError('Es obligatorio asignarle un nombre al proveedor.'); return;}

    var providerEmail = document.getElementById('provider-email').value;
    var providerPhone = document.getElementById('provider-phone').value;
    var providerAddress = document.getElementById('provider-address').value;

    const providerList = await api.getAllProviders();

    if (!providerList.success){
        {showTransactionError('Ha ocurrido un error interno.' + providerList.error); return;}
    }

    if (providerList.providerList.find(provider => provider.email === providerEmail && provider.id !== providerID) && providerEmail !== ''){
        showTransactionError('Ya existe un proveedor registrado con ese email.'); return;
    }
    if (providerList.providerList.find(provider => parseInt(provider.phone,10) === parseInt(providerPhone,10) && provider.id !== providerID)
        && providerPhone !== ''){
        showTransactionError('Ya existe un proveedor registrado con ese telefono.'); return;
    }

    providerEmail = (providerEmail === '') ? null : providerEmail;
    providerPhone = (providerPhone === '') ? null : providerPhone;
    providerAddress = (providerAddress === '') ? null : providerAddress;

    const providerData = {'name' : providerName, 'email' : providerEmail, 'phone' : providerPhone, 'address' : providerAddress, 'id' : providerID};

    const response = await api.updateProvider(providerData);

    if (!response.success){{showTransactionError('Ha ocurrido un error interno. No se pudo actualizar el proveedor');
        console.log(response.error);
        return;}}
    alert('Proveedor actualizado con exito. Será redirigido');
    window.location.href = '/dashboard.php?location=customers';
}

function showTransactionInfoModal(){
    const modal = document.getElementById('transaction-info-modal');
    const greyBg = document.getElementById('grey-background');
    const pickerModal = document.getElementById('transaction-picker-modal');
    const successModal = document.getElementById('transaction-success-modal');
    const transactionModal = document.getElementById('new-transaction-container');

    pickerModal.classList.add('hidden');
    successModal.classList.add('hidden');
    transactionModal.classList.add('hidden');

    modal.classList.remove('hidden');
    greyBg.classList.remove('hidden');
}

async function setupClients(){
    const response = await api.getOrderedClients();
    if (response.success){
        const clients = response.clientList;
        populateClientModal(clients);
    }
    else{
        pop_ups.error('No salió bien:' + response.error, "Error.");
    }
}

function populateEmptyClientModal(){
    const clientModals = document.querySelectorAll('.customer-view');
    clientModals.forEach(modal => {
        modal.innerHTML = `<div class="flex-row"><div class="flex-column">
            <h2>No has creado ningun cliente</h2>
            <button class="btn btn-primary new-transaction-btn" data-transaction="customer">Crea tu Primer Cliente</button>
            </div></div>`;
    })
}

function populateClientModal(clients){
    const clientsByNameDesc = clients.name.descending;
    const clientsByNameAsc = clients.name.ascending;
    const clientsByEmailDesc = clients.email.descending;
    const clientsByEmailAsc = clients.email.ascending;
    const clientsByDateDesc = clients.date.descending;
    const clientsByDateAsc = clients.date.ascending;
    const clientsByPhoneDesc = clients.phone.descending;
    const clientsByPhoneAsc = clients.phone.ascending;
    const clientsByAddressDesc = clients.address.descending;
    const clientsByAddressAsc = clients.address.ascending;
    const clientsByDniDesc = clients.tax_id.descending;
    const clientsByDniAsc = clients.tax_id.ascending;

    const customerEmailDescending = document.getElementById('customers-table-email-descending');
    const customerEmailAscending = document.getElementById('customers-table-email-ascending');
    const customerDateDescending = document.getElementById('customers-table-date-descending');
    const customerDateAscending = document.getElementById('customers-table-date-ascending');
    const customerNameDescending = document.getElementById('customers-table-name-descending');
    const customerNameAscending = document.getElementById('customers-table-name-ascending');
    const customerPhoneDescending = document.getElementById('customers-table-phone-descending');
    const customerPhoneAscending = document.getElementById('customers-table-phone-ascending');
    const customerAddressDescending = document.getElementById('customers-table-address-descending');
    const customerAddressAscending = document.getElementById('customers-table-address-ascending');
    const customerDniDescending = document.getElementById('customers-table-dni-descending');
    const customerDniAscending = document.getElementById('customers-table-dni-ascending');

    clientsByNameDesc.forEach(client =>{
        customerNameDescending.appendChild(createClientRow(client));
    })
    clientsByNameAsc.forEach(client =>{
        customerNameAscending.appendChild(createClientRow(client));
    })
    clientsByEmailDesc.forEach(client =>{
        customerEmailDescending.appendChild(createClientRow(client));
    })
    clientsByEmailAsc.forEach(client =>{
        customerEmailAscending.appendChild(createClientRow(client));
    })
    clientsByDateDesc.forEach(client =>{
        customerDateDescending.appendChild(createClientRow(client));
    })
    clientsByDateAsc.forEach(client =>{
        customerDateAscending.appendChild(createClientRow(client));
    })
    clientsByPhoneDesc.forEach(client =>{
        customerPhoneDescending.appendChild(createClientRow(client));
    })
    clientsByPhoneAsc.forEach(client =>{
        customerPhoneAscending.appendChild(createClientRow(client));
    })
    clientsByAddressDesc.forEach(client =>{
        customerAddressDescending.appendChild(createClientRow(client));
    })
    clientsByAddressAsc.forEach(client =>{
        customerAddressAscending.appendChild(createClientRow(client));
    })
    clientsByDniDesc.forEach(client =>{
        customerDniDescending.appendChild(createClientRow(client));
    })
    clientsByDniAsc.forEach(client =>{
        customerDniAscending.appendChild(createClientRow(client));
    })
}

function createClientRow (client){
    const clientDiv = document.createElement('div');
    clientDiv.classList.add('client-row');
    clientDiv.innerHTML = `<div>
                                <h3>${client.full_name}</h3>
                            </div>
                            <div><p style="font-size: 15px">${client.created_at}</p></div>`;

    clientDiv.addEventListener('click', () => {
        showClientInfoModal(client);
    })
    return clientDiv;
}

function showClientInfoModal(client){
    const modal = document.getElementById('transaction-info-modal');

    const clientEmail = (client.email === null) ? '' : client.email;
    const clientPhone = (client.phone === null) ? '' : client.phone;
    const clientAddress = (client.address === null) ? '' : client.address;
    const clientDNI = (client.tax_id === null) ? '' : client.tax_id;

    modal.innerHTML = `<form class="flex-column" method="get" action="/dashboard.php" id="customer-info-form">
                                <h4 class="transaction-error-message hidden" style="color: var(--accent-red)"></h4>
                                <label for="client-name"><h2>Nombre</h2></label>
                                <input type="text" name="name" id="client-name" placeholder="No asignado." value="${client.full_name}" required>
                                <hr>
                                <label for="client-email" class="flex-row" style="gap: 5px"><h2>Email</h2><p>(Opcional)</p></label>
                                <input type="email" name="email" id="client-email" placeholder="No asignado." value="${clientEmail}">
                                <label for="client-phone" class="flex-row" style="gap: 5px"><h2>Telefono </h2><p>(Opcional)</p></label>
                                <input type="text" name="phone" id="client-phone" placeholder="No asignado." 
                                value="${clientPhone}" minlength="8" pattern="[0-9]+">
                                <label for="client-address" class="flex-row" style="gap: 5px"><h2>Dirección </h2><p>(Opcional)</p></label>
                                <input type="text" name="address" id="client-address" placeholder="No asignado." value="${clientAddress}">
                                <label for="client-dni" class="flex-row" style="gap: 5px"><h2>D.N.I </h2><p>(Opcional)</p></label>
                                <input type="text" name="tax-id" id="client-dni" placeholder="No asignado." value="${clientDNI}" 
                                pattern="\\d{1,2}\\.\\d{3}\\.\\d{3}">           
                                <button class="btn btn-primary" id="save-customer-btn" disabled>Guardar Cambios</button>                            
                                </form>`;

    const saveBtn = document.getElementById('save-customer-btn');

    const form = document.getElementById('customer-info-form');
    let formInitialState = {};

    const formInputs = form.querySelectorAll('input');

    formInputs.forEach(input => {
        formInitialState[input.name] = input.value;
    })

    form.addEventListener('input', () => {
        const currentInputs = form.querySelectorAll('input');

        const modified = Array.from(currentInputs).some(input => {
            return formInitialState[input.name] !== input.value;
        });

        saveBtn.disabled = !modified;
    })

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        await saveCustomerChanges(client.id);
    })

    showTransactionInfoModal();
}

async function saveCustomerChanges(customerID){

    const customerName = document.getElementById('client-name').value;

    if (customerName === ''){showTransactionError('Es obligatorio asignarle un nombre al cliente.'); return;}

    var customerEmail = document.getElementById('client-email').value;
    var customerPhone = document.getElementById('client-phone').value;
    var customerAddress = document.getElementById('client-address').value;
    var customerDNI = document.getElementById('client-dni').value;

    const customerList = await api.getAllClients();

    if (!customerList.success){
        {   pop_ups.error('Ha ocurrido un error interno.' + customerList.error, "Error.");
            showTransactionError('Ha ocurrido un error interno.' + customerList.error); return;}
    }

    if (customerList.clientList.find(client => client.email === customerEmail && client.id !== customerID) && customerEmail !== ''){
        pop_ups.warning('Ya existe un cliente registrado con ese email.', "Mail duplicado.");
        showTransactionError('Ya existe un cliente registrado con ese email.'); return;
    }
    if (customerList.clientList.find(client => parseInt(client.phone,10) === parseInt(customerPhone,10) && client.id !== customerID)
        && customerPhone !== ''){
        pop_ups.warning('Ya existe un cliente registrado con ese teléfono.', "Teléfono duplicado.");
        showTransactionError('Ya existe un cliente registrado con ese teléfono.'); return;

    }

    customerEmail = (customerEmail === '') ? null : customerEmail;
    customerPhone = (customerPhone === '') ? null : customerPhone;
    customerAddress = (customerAddress === '') ? null : customerAddress;
    customerDNI = (customerDNI === '') ? null : customerDNI;

    const customerData = {'name' : customerName, 'email' : customerEmail, 'phone' : customerPhone, 'address' : customerAddress, 'tax_id' : customerDNI,'id' : customerID};

    const response = await api.updateCustomer(customerData);

    if (!response.success){{
        pop_ups.error('Ha ocurrido un error interno.' + customerList.error, "Error.");
        showTransactionError('Ha ocurrido un error interno. No se pudo actualizar el cliente');
        console.log(response.error);
        return;}}
    pop_ups.success('Cliente actualizado con exito. Será redirigido', "Cliente actualizado.");
    window.location.href = '/dashboard.php?location=customers';
}

async function completeProvider(){
    const providerName = document.getElementById('provider-name').value;

    if (providerName === ''){pop_ups.warning('Es obligatorio asignarle un nombre al proveedor.', "Obviedad.");
        showTransactionError('Es obligatorio asignarle un nombre al proveedor.'); return;}

    var providerEmail = document.getElementById('provider-email').value;
    var providerPhone = document.getElementById('provider-phone').value;
    var providerAddress = document.getElementById('provider-address').value;

    const providerList = await api.getAllProviders();

    if (!providerList.success){
        {showTransactionError('Ha ocurrido un error interno.' + providerList.error); return;}
    }

    if (providerList.providerList.find(provider => provider.email === providerEmail) && providerEmail !== ''){
        pop_ups.warning('Ya existe un proveedor registrado con ese email.', "Mail duplicado.");
        showTransactionError('Ya existe un provedor registrado con ese email.'); return;
    }
    if (providerList.providerList.find(provider => parseInt(provider.phone,10) === parseInt(providerPhone,10)) && providerPhone !== ''){
        pop_ups.warning('Ya existe un proveedor registrado con ese teléfono.', "Teléfono duplicado.");
        showTransactionError('Ya existe un provedor registrado con ese teléfono.'); return;
    }

    providerEmail = (providerEmail === '') ? null : providerEmail;
    providerPhone = (providerPhone === '') ? null : providerPhone;
    providerAddress = (providerAddress === '') ? null : providerAddress;


    const provider = {'name' : providerName, 'email' : providerEmail, 'phone' : providerPhone, 'address' : providerAddress};

    const result = await api.createProvider(provider);

    if (!result.success){
        {   pop_ups.error('Ha ocurrido un error interno.' + customerList.error, "Error.");
            showTransactionError('Ha ocurrido un error interno. No se pudo registrar el provedor '); return;}
    }

    providerEmail = (providerEmail === null) ? 'No asignado' : providerEmail;
    providerPhone = (providerPhone === null) ? 'No asignado' : providerPhone;
    providerAddress = (providerAddress === null) ? 'No asignado' : providerAddress;

    const transactionSuccessBody = `
    <h3 class="flex-row all-center" style="margin-top: 10px">Se ha creado el proveedor con éxito.</h3>
    <div class="flex-column" style="flex-wrap: wrap; gap: 15px; margin-top: 25px; overflow: hidden"> 
        <div class="flex-column"><h4>Nombre</h4><p>${providerName}</p></div>
        <div class="flex-column"><h4>Email</h4><p>${providerEmail}</p></div>
        <div class="flex-column"><h4>Telefono</h4><p>${providerPhone}</p></div>
        <div class="flex-column"><h4>Direccion</h4><p>${providerAddress}</p></div>
    </div>
    <p style="margin-top: 15px; font-size: 0.75rem">Actualiza la página para ver tus cambios.</p>
    <div class="flex-column all-center">
        <div class="btn btn-primary reload-btn" data-location="providers">Actualizar Página</div>
        <div class="btn btn-secondary" id="success-return-btn">Cerrar</div>
    </div>`;

    pop_ups.success('Proveedor creado con exito. Recargá la página para ver tus cambios.', "Proveedor nuevo.");
    showTransactionSuccess(transactionSuccessBody);
}

function setupReloadBtns(){
    const reloadBtns = document.querySelectorAll('.reload-btn');
    const successReturnBtn = document.getElementById('success-return-btn');

    successReturnBtn.addEventListener('click', () => {
        hideTransactionSuccess();
    })
    reloadBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const menuToOpen = btn.dataset.location;
            const url = window.location.pathname + '?location=' + menuToOpen;
            window.location.href = url;
        })
    })
}

function setupSendEmailBtn(emailInfo){
    const sendEmailBtn = document.getElementById('send-sale-email-btn');
    if (!sendEmailBtn) {return; }

    sendEmailBtn.addEventListener('click',() =>{
        sendSaleEmail(emailInfo);
    })
}

async function sendSaleEmail(emailInfo){
    const sendEmailBtn = document.getElementById('send-sale-email-btn');
    const originalText = sendEmailBtn.textContent;

    try {
        sendEmailBtn.textContent = 'Enviando...';
        sendEmailBtn.disabled = true;

        const response = await api.sendSaleEmail(emailInfo);

        if (response.success) {
            sendEmailBtn.textContent = '✓ Enviado';
            sendEmailBtn.style.backgroundColor = 'var(--success-color)';
            // Deshabilitar permanentemente después de éxito
            sendEmailBtn.onclick = null;
        } else {
            throw new Error(response.error || 'Error al enviar el email');
        }
    } catch (error) {
        console.error('Error:', error);
        sendEmailBtn.textContent = '✗ Error al enviar';
        sendEmailBtn.style.backgroundColor = 'var(--error-color)';
        // Permitir reintentar después de 2 segundos
        setTimeout(() => {
            sendEmailBtn.textContent = originalText;
            sendEmailBtn.style.backgroundColor = 'var(--accent-color-medium-opacity)';
            sendEmailBtn.disabled = false;
        }, 2000);
    }
}

function showTransactionSuccess(body){
    const transactionModal = document.getElementById('new-transaction-container');
    const modalContainer = document.getElementById('transaction-success-modal');
    const modalContainerBody = document.getElementById('success-modal-body');

    modalContainerBody.innerHTML = body;
    modalContainer.classList.remove('hidden');
    transactionModal.classList.add('hidden');
    setupReloadBtns();
}


//  ---- Funciones auxiliares de Transacciones ----


function getForm(transactionType){
    let HTML;

    switch (transactionType) {
        case 'sale':
            HTML = getSaleForm();
            break;
        case 'receipt':
            HTML = getReceiptForm();
            break;
        case 'customer':
            HTML = getCustomerForm();
            break;
        case 'provider':
            HTML = getProviderForm();
            break;
    }

    return HTML;
}

function getSaleForm() {
    // El 'form' ahora tiene un layout de 3 partes: header, body (scroll), summary (footer)
    return `<form class="flex-column" style="height: 100%;" method="get" action="/dashboard.php">
                <h4 class="transaction-error-message hidden" style="color: var(--accent-red); padding: 0 2rem; margin-top: 1.5rem;"></h4>
                
                <div class="transaction-header flex-row" style="justify-content: space-between; align-items: center;">
                    <div>
                        <h3>Cliente</h3>
                        <p id="sale-client-name">Ninguno</p>
                    </div>
                    <button type="button" class="btn btn-secondary picker-btn" data-modal-target="client-picker-modal">Cambiar</button>
                </div>

                <div class="transaction-body">
                    <div class="flex-row" style="gap: 20px; justify-content: space-between; align-items: center;">
                        <h3>Productos</h3>
                        <button type="button" class="btn btn-primary picker-btn" data-modal-target="item-picker-modal" data-type="sale">
                            <i class="ph ph-plus"></i> Agregar Producto
                        </button>
                    </div>       
                    
                    <div id="product-list-container" style="margin-top: 1rem;">
                        </div>
                </div>
                
                <div class="transaction-summary">
                    <div class="total-display">
                        <h2>Total: <span id="price-text">$0.00</span></h2>
                    </div>
                    <button type="button" class="btn btn-primary complete-transaction-btn" data-type="sale">
                        Confirmar Venta
                    </button>
                </div>

                <input name="price" id="price-input" value="0" hidden>
                <input name="product-list" id="product-list" hidden>
                <input name="client-data" id="client-id-input" hidden>
            </form>`;
}

// REEMPLAZAR esta función en dashboard.js
function getReceiptForm() {
    return `<form class="flex-column" style="height: 100%;" method="get" action="/dashboard.php">
                <h4 class="transaction-error-message hidden" style="color: var(--accent-red); padding: 0 2rem; margin-top: 1.5rem;"></h4>
                
                <div class="transaction-header flex-row" style="justify-content: space-between; align-items: center;">
                    <div>
                        <h3>Proveedor</h3>
                        <p id="receipt-provider-name">Ninguno</p>
                    </div>
                    <button type="button" class="btn btn-secondary picker-btn" data-modal-target="provider-picker-modal">Cambiar</button>
                </div>

                <div class="transaction-body">
                    <div class="flex-row" style="gap: 20px; justify-content: space-between; align-items: center;">
                        <h3>Productos</h3>
                        <button type="button" class="btn btn-primary picker-btn" data-modal-target="item-picker-modal" data-type="receipt">
                            <i class="ph ph-plus"></i> Agregar Producto
                        </button>
                    </div>       
                    
                    <div id="product-list-container" style="margin-top: 1rem;">
                        </div>
                </div>
                
                <div class="transaction-summary">
                    <div class="total-display">
                        <h2>Total: <span id="price-text">$0.00</span></h2>
                    </div>
                    <button type="button" class="btn btn-primary complete-transaction-btn" data-type="receipt">
                        Confirmar Compra
                    </button>
                </div>

                <input name="price" id="price-input" value="0" hidden>
                <input name="product-list" id="product-list" hidden>
                <input name="provider-id" id="provider-id-input" hidden>
            </form>`;
}

function getCustomerForm() {
    return `<form class="flex-column" method="get" action="/dashboard.php" id="customer-form">
                                <h4 class="transaction-error-message hidden" style="color: var(--accent-red)"></h4>
                                <label for="client-name"><h2>Nombre</h2></label>
                                <input type="text" name="name" id="client-name" placeholder="Juan Perez" value="" required>
                                <hr>
                                <label for="client-email" class="flex-row" style="gap: 5px"><h2>Email</h2><p>(Opcional)</p></label>
                                <input type="email" name="email" id="client-email" placeholder="cliente@gmail.com" value="">
                                <label for="client-phone" class="flex-row" style="gap: 5px"><h2>Telefono </h2><p>(Opcional)</p></label>
                                <input type="text" name="phone" id="client-phone" placeholder="Numero sin espacios." 
                                value="" minlength="8" pattern="[0-9]+">
                                <label for="client-address" class="flex-row" style="gap: 5px"><h2>Dirección </h2><p>(Opcional)</p></label>
                                <input type="text" name="address" id="client-address" placeholder="Calle 1085, Localidad" value="">
                                <label for="client-dni" class="flex-row" style="gap: 5px"><h2>D.N.I </h2><p>(Opcional)</p></label>
                                <input type="text" name="tax-id" id="client-dni" placeholder="11.111.111" value="" 
                                pattern="\\d{1,2}\\.\\d{3}\\.\\d{3}">           
                                <button class="btn btn-primary complete-transaction-btn" data-type="customer">Crear Cliente</button>
                                </form>`;
}

function getProviderForm() {
    return `<form class="flex-column" method="get" action="/dashboard.php" id="provider-form">
                                <h4 class="transaction-error-message hidden" style="color: var(--accent-red)"></h4>
                                <label for="client-name"><h2>Nombre</h2></label>
                                <input type="text" name="name" id="provider-name" placeholder="Distribuidora 1" value="" required>
                                <hr>
                                <label for="client-email" class="flex-row" style="gap: 5px"><h2>Email</h2><p>(Opcional)</p></label>
                                <input type="email" name="email" id="provider-email" placeholder="cliente@gmail.com" value="">
                                <label for="client-phone" class="flex-row" style="gap: 5px"><h2>Telefono </h2><p>(Opcional)</p></label>
                                <input type="text" name="phone" id="provider-phone" placeholder="Numero sin espacios." 
                                value="" minlength="8" pattern="[0-9]+">
                                <label for="client-address" class="flex-row" style="gap: 5px"><h2>Dirección </h2><p>(Opcional)</p></label>
                                <input type="text" name="address" id="provider-address" placeholder="Calle 1085, Localidad" value="">       
                                <button class="btn btn-primary complete-transaction-btn" data-type="customer">Crear Proveedor</button>
                                </form>`;
}

function showTransactionError(message){
    const transactionError = document.querySelectorAll('.transaction-error-message');

    transactionError.forEach(error => {
        error.innerHTML = message;
        error.classList.remove('hidden')
    });
}

//'Picker modal' Son los container para seleccionar productos y clientes/proveedores para agregar a la transaccion.
function showPickerModal(pickerModalID){
    document.querySelectorAll('.picker-modal').forEach(modal => modal.classList.add('hidden'));
    const allBtns = document.querySelectorAll('.picker-confirm-btn');

    allBtns.forEach(btn => btn.disabled = true);

    const pickerToShow = document.getElementById(pickerModalID);
    const transactionModal = document.getElementById('transaction-picker-modal');
    const transactionContainer = document.getElementById('new-transaction-container');

    if (pickerModalID !== 'item-picker-modal'){
        const inventoryPickerContainer = document.getElementById('inventory-picker-container');
        inventoryPickerContainer.classList.add('hidden');
    }
    else{
        const inventoryPickerContainer = document.getElementById('inventory-picker-container');
        inventoryPickerContainer.classList.remove('hidden');
    }

    const productItems = document.querySelectorAll('.product-item');
    productItems.forEach(item => item.classList.remove('selected'));

    transactionContainer.classList.add('hidden');
    pickerToShow.classList.remove('hidden');
    transactionModal.classList.remove('hidden');
}

function showProductList(tableID){
    document.querySelectorAll('.product-list').forEach(wrapper => wrapper.classList.add('hidden'));
    const productListWrapper = document.getElementById('product-list-wrapper');
    const productListToShow = document.getElementById(tableID);

    productListWrapper.classList.remove('hidden');
    productListToShow.classList.remove('hidden');
}

function hideTransactionError(){
    const transactionError = document.querySelectorAll('.transaction-error-message');

    transactionError.forEach(error => {
        error.classList.add('hidden')
    });
}

function hideTransactionModal(){
    const transactionModal = document.getElementById('transaction-picker-modal');
    transactionModal.classList.add('hidden');
    const transactionContainer = document.getElementById('new-transaction-container');
    transactionContainer.classList.remove('hidden');
}

function hideTransactionSuccess(){
    const modalContainer = document.getElementById('transaction-success-modal');
    const greyBg = document.getElementById('grey-background');

    modalContainer.classList.add('hidden');
    greyBg.classList.add('hidden');
}

async function setupRecomendedColumns(){
    const preferences = await api.getCurrentInventoryPreferences();
    const form = document.getElementById('recomended-columns-form');

    if (!preferences.success) {
        console.error("Error crítico: No se encontraron las preferencias del inventario." + preferences.error);
        return;
    }

    const defaults = await api.getCurrentInventoryDefaults();
    if (!defaults.success) {
        console.error("Error crítico: No se encontraron los valores por defecto del inventario." + defaults.error);
        return;
    }

    const gainCheckbox = document.getElementById('gain-input');
    const minStockCheckbox = document.getElementById('min-stock-input');
    const salePriceCheckbox = document.getElementById('sale-price-input');
    const receiptPriceCheckbox = document.getElementById('receipt-price-input');
    const percentageRadio = document.getElementById('percentage-gain-input');
    const hardRadio = document.getElementById('hard-gain-input');

    const autoPriceCheckbox = document.getElementById('auto-price-input');
    const autoIvaRadio = document.getElementById('auto-iva-input');
    const autoGainRadio = document.getElementById('auto-gain-input');
    const autoIvaGainRadio = document.getElementById('auto-iva-gain-input');

    const autoPriceLabel = document.getElementById('auto-price-checkbox');

    const autoPriceTypeContainer = document.getElementById('auto-price-type-container');

    autoPriceCheckbox.checked = (preferences.auto_price === 1);

    gainCheckbox.checked = (preferences.percentage_gain === 1 || preferences.hard_gain === 1);
    minStockCheckbox.checked = (preferences.min_stock === 1);
    salePriceCheckbox.checked = (preferences.sale_price === 1);
    receiptPriceCheckbox.checked = (preferences.receipt_price === 1);
    percentageRadio.checked = (preferences.percentage_gain === 1);
    hardRadio.checked = (preferences.hard_gain === 1);

    const minStockDefaultInput = document.getElementById('min-stock-default-input');
    minStockDefaultInput.value = (minStockCheckbox.checked) ? defaults.min_stock : '';

    const gainDefaultInput = document.getElementById('gain-default-input');
    gainDefaultInput.value = (gainCheckbox.checked) ? defaults.hard_gain : '';

    const salePriceDefaultInput = document.getElementById('sale-price-default-input');
    salePriceDefaultInput.value = (salePriceCheckbox.checked) ? defaults.sale_price : '';

    const receiptPriceDefaultInput = document.getElementById('receipt-price-default-input');
    receiptPriceDefaultInput.value = (receiptPriceCheckbox.checked) ? defaults.receipt_price : '';

    function toggleWrapper(inputId, isVisible) {
        const inputEl = document.getElementById(inputId);
        if (!inputEl) return;

        // Buscamos el wrapper más cercano (el padre o el abuelo)
        const wrapper = inputEl.closest('.reveal-wrapper');

        if (wrapper) {
            if (isVisible) {
                wrapper.classList.add('open');
                inputEl.disabled = false;
            } else {
                wrapper.classList.remove('open');
                inputEl.disabled = true; // Deshabilitar input al cerrar
            }
        }
    }

    function updateMinStockInput() {
        const isChecked = minStockCheckbox.checked;
        const defaultInput = document.getElementById('min-stock-default-input');

        defaultInput.disabled = !isChecked;

        if (!isChecked) defaultInput.value = "";
        else defaultInput.value = defaults.min_stock;

        toggleWrapper('min-stock-default-input', isChecked);
        checkFormState();
    }

    function updateSalePriceInput() {
        const isChecked = salePriceCheckbox.checked;
        const defaultInput = document.getElementById('sale-price-default-input');

        if (!isChecked) {
            // Lógica de cascada: cerrar todo lo de abajo
            autoPriceCheckbox.checked = false;
            updateAutoPrice(); // Cerrar automatización también
            document.getElementById('auto-price-wrapper').classList.add('hidden'); // Ocultar sección entera
        } else {
            defaultInput.value = defaults.sale_price;
            document.getElementById('auto-price-wrapper').classList.remove('hidden'); // Mostrar sección entera
            updateReceiptPriceInput();
        }

        toggleWrapper('sale-price-default-input', isChecked);
        checkFormState();
    }

    function updateReceiptPriceInput() {
        const isChecked = receiptPriceCheckbox.checked;
        const defaultInput = document.getElementById('receipt-price-default-input');

        if (!isChecked) {
            defaultInput.value = "";
        } else {
            defaultInput.value = defaults.receipt_price;
        }

        toggleWrapper('receipt-price-default-input', isChecked);
        checkFormState();
    }

    function updateGainSymbol() {
        const symbolSpan = document.getElementById('gain-symbol');
        if (percentageRadio.checked) {
            symbolSpan.textContent = '%';
        } else if (hardRadio.checked) {
            symbolSpan.textContent = '$';
        }
        checkFormState(); // Verificar si hay cambios para habilitar botón guardar
    }

    function updateGainInput() {
        const isChecked = gainCheckbox.checked;
        const defaultInput = document.getElementById('gain-default-input');

        percentageRadio.disabled = !isChecked;
        hardRadio.disabled = !isChecked;

        if (!isChecked) {
            // Si desmarcamos el padre, limpiamos y cerramos
            percentageRadio.checked = false;
            hardRadio.checked = false;
            defaultInput.value = "";
            document.getElementById('wrapper-gain-type').classList.remove('open');
        } else {
            // Si marcamos el padre, restauramos valores SOLO si no hay ninguno seleccionado
            // (Esto evita el bug de que se resetee al cambiar de radio)
            if (!percentageRadio.checked && !hardRadio.checked) {
                percentageRadio.checked = (preferences.percentage_gain === 1);
                hardRadio.checked = (preferences.hard_gain === 1);

                // Default de seguridad si la preferencia vino vacía
                if (!percentageRadio.checked && !hardRadio.checked) {
                    percentageRadio.checked = true;
                }
            }

            defaultInput.value = defaults.hard_gain;
            document.getElementById('wrapper-gain-type').classList.add('open');
        }

        // Actualizamos el símbolo visual y animaciones
        updateGainSymbol();
        toggleWrapper('gain-default-input', isChecked);
        updateAutoPrice();
        checkFormState();
    }

    // --- LISTENERS CORREGIDOS ---
    // El checkbox padre controla toda la sección
    gainCheckbox.addEventListener('change', updateGainInput);

    // Los radios SOLO actualizan el símbolo visual (ya no resetean todo)
    percentageRadio.addEventListener('change', updateGainSymbol);
    hardRadio.addEventListener('change', updateGainSymbol);

    function updateAutoPrice(){
        const isChecked = autoPriceCheckbox.checked;
        const typeWrapper = document.getElementById('wrapper-auto-price');

        if (!isChecked){
            autoIvaRadio.checked = false;
            autoGainRadio.checked = false;
            autoIvaGainRadio.checked = false;
            autoPriceTypeContainer.classList.remove('open');
        }
        else{
            if (preferences.auto_price !== 1 || !gainCheckbox.checked){
                autoIvaRadio.checked = true;
            } else {
                autoIvaRadio.checked = (preferences.auto_price_type === 'iva');
                autoGainRadio.checked = (preferences.auto_price_type === 'gain');
                autoIvaGainRadio.checked = (preferences.auto_price_type === 'gain-iva');
            }
            if (!gainCheckbox.checked){
                autoGainRadio.disabled = true;
                autoIvaGainRadio.disabled = true;
            } else{
                autoGainRadio.disabled = false;
                autoIvaGainRadio.disabled = false;
            }


            autoGainRadio.disabled = !gainCheckbox.checked;
            autoIvaGainRadio.disabled = !gainCheckbox.checked;

            if(typeWrapper) typeWrapper.classList.add('open');
        }
        checkFormState();
    }

    gainCheckbox.addEventListener('change', updateGainInput);
    minStockCheckbox.addEventListener('change', updateMinStockInput);
    salePriceCheckbox.addEventListener('change', updateSalePriceInput);
    receiptPriceCheckbox.addEventListener('change', updateReceiptPriceInput);
    autoPriceCheckbox.addEventListener('change', updateAutoPrice);

    const saveBtn = document.getElementById('save-changes-btn');

    const allTrackedInputs = [
        gainCheckbox, minStockCheckbox, salePriceCheckbox, receiptPriceCheckbox,
        percentageRadio, hardRadio,
        gainDefaultInput, minStockDefaultInput, salePriceDefaultInput, receiptPriceDefaultInput, autoPriceCheckbox,
        autoIvaRadio, autoGainRadio, autoIvaGainRadio
    ];

    let initialState = {};

    function captureInitialState() {
        initialState = {};
        allTrackedInputs.forEach(input => {
            const prop = (input.type === 'checkbox' || input.type === 'radio') ? 'checked' : 'value';
            initialState[input.id] = input[prop];
        });
    }

    function checkFormState() {
        let isChanged = false;

        for (const input of allTrackedInputs) {
            const prop = (input.type === 'checkbox' || input.type === 'radio') ? 'checked' : 'value';
            if (input[prop] !== initialState[input.id]) {
                isChanged = true;
                break;
            }
        }
        saveBtn.disabled = !isChanged;
    }

    updateGainInput();
    updateMinStockInput();
    updateSalePriceInput();
    updateReceiptPriceInput();
    updateAutoPrice();

    captureInitialState();
    saveBtn.disabled = true;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const setPreferences = getUserPreferences();

        const response = await api.setCurrentInventoryPreferences(setPreferences);

        if (response){
            saveBtn.textContent = 'Guardado!';

            alert('Preferencias guardadas correctamente.');
            window.location.href = '/dashboard.php';
        }
        else{
            alert('Ha ocurrido un error al guardar las preferencias del inventario.');
        }
    })

    form.addEventListener('input', checkFormState);
}

function getUserPreferences(){
    const minStockCheckbox = document.getElementById('min-stock-input');
    const salePriceCheckbox = document.getElementById('sale-price-input');
    const receiptPriceCheckbox = document.getElementById('receipt-price-input');
    const percentageRadio = document.getElementById('percentage-gain-input');
    const hardRadio = document.getElementById('hard-gain-input');
    const autoPrice = document.getElementById('auto-price-input');

    const gainDefaultInput = document.getElementById('gain-default-input');
    const minStockDefaultInput = document.getElementById('min-stock-default-input');
    const salePriceDefaultInput = document.getElementById('sale-price-default-input');
    const receiptPriceDefaultInput = document.getElementById('receipt-price-default-input');

    const gainDefault = (gainDefaultInput.value === '') ? 0 : parseFloat(gainDefaultInput.value);
    const minStockDefault = (minStockDefaultInput.value === '') ? 0 : parseFloat(minStockDefaultInput.value);
    const salePriceDefault = (salePriceDefaultInput.value === '') ? 0 : parseFloat(salePriceDefaultInput.value);
    const receiptPriceDefault = (receiptPriceDefaultInput.value === '') ? 0 : parseFloat(receiptPriceDefaultInput.value);

    let auto_price_type;

    if (autoPrice.checked){auto_price_type = document.querySelector('input[name="price-type"]:checked').value;}
    else{auto_price_type = null;}


    const preferences = {
        min_stock: {active: (minStockCheckbox.checked) ? 1 : 0, default: minStockDefault},
        sale_price: {active: (salePriceCheckbox.checked) ? 1 : 0, default: salePriceDefault},
        receipt_price: {active: (receiptPriceCheckbox.checked) ? 1 : 0, default: receiptPriceDefault},
        percentage_gain: {active: (percentageRadio.checked) ? 1 : 0, default: gainDefault},
        hard_gain: {active: (hardRadio.checked) ? 1 : 0, default: gainDefault},
        auto_price: (autoPrice.checked) ? 1 : 0,
        auto_price_type: auto_price_type
    }

    return preferences;
}

// -- Estadisticas --

async function updateDailyStatistics(inventoryId) {
    const hourlyStatistics = await api.getDailyStatistics(inventoryId);
    if (hourlyStatistics){
        const groupedStatistics = groupHourlyData(hourlyStatistics);
        populateGroupedStatistics(groupedStatistics);
        populateHourlyGraphs(hourlyStatistics);
    }
}

function createCharts(){
    const stockIngresado = document.getElementById('stock-ingresado-graph');
    const stockVendido = document.getElementById('stock-vendido-graph');
    const ventas = document.getElementById('ventas-graph');
    const compras = document.getElementById('compras-graph');
    const gastos = document.getElementById('gastos-graph');
    const ingresos = document.getElementById('ingresos-graph');
    const ganancias = document.getElementById('ganancias-graph');
    const clientes = document.getElementById('clientes-graph');
    const proveedores = document.getElementById('proveedores-graph');

    var options = {
        name:{
        },
        chart: {
            type: 'area',
            height: 400,
            width: 300
        },
        series: [],
        xaxis: {
            categories: []
        },
        noData: {
            text: "Cargando datos..." // Mensaje mientras no hay datos
        }
    };

    const stockIngresadoChart = new ApexCharts(stockIngresado,options);
    stockIngresadoChart.render();
    stockIngresado.myChartInstance = stockIngresadoChart;

    const stockVendidoChart = new ApexCharts(stockVendido,options);
    stockVendidoChart.render();
    stockVendido.myChartInstance = stockVendidoChart;

    const gastosChart = new ApexCharts(gastos,options);
    gastosChart.render();
    gastos.myChartInstance = gastosChart;

    const ingresosChart = new ApexCharts(ingresos,options);
    ingresosChart.render();
    ingresos.myChartInstance = ingresosChart;

    const gananciasChart = new ApexCharts(ganancias,options);
    gananciasChart.render();
    ganancias.myChartInstance = gananciasChart;

    const clientesChart = new ApexCharts(clientes,options);
    clientesChart.render();
    clientes.myChartInstance = clientesChart;

    const proveedoresChart = new ApexCharts(proveedores,options);
    proveedoresChart.render();
    proveedores.myChartInstance = proveedoresChart;

    const ventasChart = new ApexCharts(ventas,options);
    ventasChart.render();
    ventas.myChartInstance = ventasChart;

    const comprasChart = new ApexCharts(compras,options);
    comprasChart.render();
    compras.myChartInstance = comprasChart;

}

function setupStatPickers(){
    const statPickers = document.querySelectorAll('.daily-stat-item');
    statPickers.forEach(picker => {
        picker.addEventListener('click', () => {
            document.querySelectorAll('.stat-graph').forEach(graph => graph.classList.add('hidden'));

            const graphContainerID = picker.dataset.graph + '-container';
            console.log(graphContainerID);

            const containerToShow = document.getElementById(graphContainerID);
            containerToShow.classList.remove('hidden');

        })
    })
}

function populateHourlyGraphs(hourlyStatistics){

    const hours = [];
    const currentHour = new Date().getHours();
    var i;

    for (i = 0; i <= currentHour ; i++){
        hours.push(i + " hs");
    }

    const stockIngresado = document.getElementById('stock-ingresado-graph');
    const stockVendido = document.getElementById('stock-vendido-graph');
    const ventas = document.getElementById('ventas-graph');
    const compras = document.getElementById('compras-graph');
    const gastos = document.getElementById('gastos-graph');
    const ingresos = document.getElementById('ingresos-graph');
    const ganancias = document.getElementById('ganancias-graph');
    const clientes = document.getElementById('clientes-graph');
    const proveedores = document.getElementById('proveedores-graph');

    var options = {
        chart: {
            type: 'area',
            height: 400,
            width: 300
        },
        series: [{
            data: hourlyStatistics.stock.stockIngresado
        }],
        xaxis: {
            categories: hours
        }
    };

    const stockIngresadoChart = stockIngresado.myChartInstance;
    stockIngresadoChart.updateOptions(options);

    options = {
        chart: {
            type: 'area',
            height: 400,
            width: 300
        },
        series: [{
            data: hourlyStatistics.stock.stockVendido
        }],
        xaxis: {
            categories: hours
        }
    };
    const stockVendidoChart = stockVendido.myChartInstance;
    stockVendidoChart.updateOptions(options);

    options = {
        chart: {
            type: 'area',
            height: 400,
            width: 300
        },
        series: [{
            data: hourlyStatistics.monetarias.gastos
        }],
        xaxis: {
            categories: hours
        }
    };
    const gastosChart = gastos.myChartInstance;
    gastosChart.updateOptions(options);

    options = {
        chart: {
            type: 'area',
            height: 400,
            width: 300
        },
        series: [{
            data: hourlyStatistics.monetarias.ingresos
        }],
        xaxis: {
            categories: hours
        }
    };
    const ingresosChart = ingresos.myChartInstance;
    ingresosChart.updateOptions(options);

    options = {
        chart: {
            type: 'area',
            height: 400,
            width: 300
        },
        series: [{
            data: hourlyStatistics.monetarias.ganancias
        }],
        xaxis: {
            categories: hours
        }
    };
    const gananciasChart = ganancias.myChartInstance;
    gananciasChart.updateOptions(options);

    options = {
        chart: {
            type: 'area',
            height: 400,
            width: 300
        },
        series: [{
            data: hourlyStatistics.transacciones.ventasRealizadas
        }],
        xaxis: {
            categories: hours
        }
    };
    const ventasChart = ventas.myChartInstance;
    ventasChart.updateOptions(options);

    options = {
        chart: {
            type: 'area',
            height: 400,
            width: 300
        },
        series: [{
            data: hourlyStatistics.transacciones.comprasRealizadas
        }],
        xaxis: {
            categories: hours
        }
    };
    const comprasChart = compras.myChartInstance;
    comprasChart.updateOptions(options);

    options = {
        chart: {
            type: 'area',
            height: 400,
            width: 300
        },
        series: [{
            data: hourlyStatistics.conexiones.nuevosClientes
        }],
        xaxis: {
            categories: hours
        }
    };
    const clientesChart = clientes.myChartInstance;
    clientesChart.updateOptions(options);

    options = {
        chart: {
            type: 'area',
            height: 400,
            width: 300
        },
        series: [{
            data: hourlyStatistics.conexiones.nuevosProveedores
        }],
        xaxis: {
            categories: hours
        }
    };
    const proveedoresChart = proveedores.myChartInstance;
    proveedoresChart.updateOptions(options);
}

function populateGroupedStatistics(stats){
    const stockIngresado = document.getElementById('daily-stock-ingresado');
    const stockVendido = document.getElementById('daily-stock-vendido');
    const gastos = document.getElementById('daily-gastos');
    const ingresos = document.getElementById('daily-ingresos');
    const ganancias = document.getElementById('daily-ganancias');
    const clientes = document.getElementById('daily-clientes');
    const proveedores = document.getElementById('daily-proveedores');
    const ventas = document.getElementById('daily-ventas');
    const compras = document.getElementById('daily-compras');

    stockIngresado.innerHTML = stats.stock.stockIngresado;
    stockVendido.innerHTML = stats.stock.stockVendido;
    gastos.innerHTML = stats.monetarias.gastos;
    ingresos.innerHTML = stats.monetarias.ingresos;
    ganancias.innerHTML = stats.monetarias.ganancias;
    clientes.innerHTML = stats.conexiones.nuevosClientes;
    proveedores.innerHTML = stats.conexiones.nuevosProveedores;
    ventas.innerHTML = stats.transacciones.ventasRealizadas;
    compras.innerHTML = stats.transacciones.comprasRealizadas;
}

function groupHourlyData(hourlyData) {
    var groupedData = {
        conexiones: {
            nuevosClientes: 0,
            nuevosProveedores: 0
        },
        transacciones: {
            ventasRealizadas: 0,
            comprasRealizadas: 0
        },
        stock: {
            stockIngresado: 0,
            stockVendido: 0
        },
        monetarias: {
            gastos: 0,
            ingresos: 0,
            ganancias: 0
        }
    };

    groupedData.conexiones.nuevosClientes = hourlyData.conexiones.nuevosClientes.reduce((acum,valor) => {
        return acum + valor;
    })
    groupedData.conexiones.nuevosProveedores = hourlyData.conexiones.nuevosProveedores.reduce((acum,valor) => {
        return acum + valor;
    })
    groupedData.transacciones.ventasRealizadas = hourlyData.transacciones.ventasRealizadas.reduce((acum,valor) => {
        return acum + valor;
    })
    groupedData.transacciones.comprasRealizadas = hourlyData.transacciones.comprasRealizadas.reduce((acum,valor) => {
        return acum + valor;
    })
    groupedData.stock.stockIngresado = hourlyData.stock.stockIngresado.reduce((acum,valor) => {
        return acum + valor;
    })
    groupedData.stock.stockVendido = hourlyData.stock.stockVendido.reduce((acum,valor) => {
        return acum + valor;
    })
    groupedData.monetarias.gastos = hourlyData.monetarias.gastos.reduce((acum,valor) => {
        return acum + valor;
    })
    groupedData.monetarias.ingresos = hourlyData.monetarias.ingresos.reduce((acum,valor) => {
        return acum + valor;
    })
    groupedData.monetarias.ganancias = hourlyData.monetarias.ganancias.reduce((acum,valor) => {
        return acum + valor;
    })

    return groupedData;
}

async function setupInventoryPicker() {
    const user = await api.getUserProfile();
    if (user){
        const response = await api.getUserVerifiedTables();
        if (!response.success){console.log(response.message); return;}

        const inventories = response.verifiedInventories;

        const inventoriesDropdown = document.getElementById('inventories-dropdown');

        inventories.forEach(inventory => {
            const inventoryBtn = document.createElement('div');

            inventoryBtn.className = 'inventory-btn';
            inventoryBtn.dataset.value = inventory.id;
            inventoryBtn.innerHTML = `<h4>${inventory.name}</h4>`;

            inventoriesDropdown.appendChild(inventoryBtn);
        })

        const inventoryPicker = document.getElementById('inventory-picker');


        const allBtns = inventoriesDropdown.querySelectorAll('.inventory-btn');
        allBtns.forEach(btn =>{
            btn.addEventListener('click', () => {
                updateDailyStatistics(btn.dataset.value);
                inventoryPicker.innerHTML = `<h4>${btn.innerHTML}</h4>`;
            })
        })



        inventoryPicker.addEventListener('click', () => {
            if(!inventoryPicker.classList.contains('clicked')){
                inventoriesDropdown.classList.remove('hidden');
                inventoryPicker.classList.add('clicked');
            }
            else{
                inventoriesDropdown.classList.add('hidden');
                inventoryPicker.classList.remove('clicked');
            }
        });

        window.addEventListener('click', (event) => {
            if (inventoryPicker.classList.contains('clicked') && !inventoryPicker.contains(event.target) &&
                !inventoriesDropdown.contains(event.target)) {
                inventoriesDropdown.classList.add('hidden');
                inventoryPicker.classList.remove('clicked');
            }
        });

        inventoriesDropdown.addEventListener('click', () => {
            if(!inventoryPicker.classList.contains('clicked')){
                inventoriesDropdown.classList.remove('hidden');
                inventoryPicker.classList.add('clicked');
            }
            else{
                inventoriesDropdown.classList.add('hidden');
                inventoryPicker.classList.remove('clicked');
            }
        });

    }
}
/* ---------------------- FIN DE FUNCIONES DE NANO  ---------------------- */

/* ---------------------- Gestionar columnas ---------------------- */

async function loadTableData() {
    try {
        console.log("[loadTableData] Llamando a api.getTableData...");
        const result = await api.getTableData();

        if (result && result.success === true) {
            allData = result.data || [];
            currentTableColumns = result.columns || [];

            const filterTableColumns = currentTableColumns.filter(
                col => col.toLowerCase() !== 'created_at'
            );

            document.getElementById('table-title').textContent = result.inventoryName || 'Inventario';

            console.log("[loadTableData] Llamando a renderTable y renderColumnList...");
            await renderTable(filterTableColumns, allData);
            renderColumnList(); // Asegúrate de que la lista de config. también se actualice

            // Construyo el dropdown de columnas de búsqueda
            if (searchColumnDropdown) {
                searchColumnDropdown.innerHTML = `
          <button class="search-dropdown-item ${selectedSearchColumn === 'all' ? 'active' : ''}" data-column="all">
            <i class="ph ph-check"></i> Todas las Columnas
          </button>`;

                const filterableColumns = currentTableColumns.filter(
                    col => col.toLowerCase() !== 'created_at'
                );

                filterableColumns.forEach(col => {
                    const item = document.createElement('button');
                    item.className = 'search-dropdown-item';
                    if (selectedSearchColumn === col) {
                        item.classList.add('active');
                    }
                    item.dataset.column = col;
                    item.innerHTML = `<i class="ph ph-check"></i> ${col}`;
                    searchColumnDropdown.appendChild(item);
                });
            }

        } else {
            pop_ups.error("[loadTableData] getTableData devolvió success: false.");
            throw new Error(result?.message || 'Error al obtener datos de tabla.');
        }
    } catch (error) {
        pop_ups.error("[loadTableData] Error CATCH:", error);
        pop_ups.warning(`Error al cargar datos: ${error.message} Serás redirigido.`);
        if (error.message.includes('No autorizado')) {
            window.location.href = '/login.php';
        } else {
            window.location.href = '/select-db.php';
        }
    }
}







document.addEventListener('DOMContentLoaded', init);