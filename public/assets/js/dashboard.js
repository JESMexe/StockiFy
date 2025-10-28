// public/assets/js/dashboard.js (Versión Completa y Limpia)

// --- 1. IMPORTACIONES ---
import * as api from './api.js';
// Importo TODAS las funciones necesarias de import.js acá
import { openImportModal, initializeImportModal, setStockifyColumns } from './import.js';

// --- 2. VARIABLES GLOBALES ---
let allData = []; // Guardo todos los datos para filtrar
let currentTableColumns = []; // Guardo las columnas de la tabla actual

// --- 3. DEFINICIONES DE FUNCIONES ---

// -- Manejo de Stock --
async function handleStockUpdate(event) {
    const button = event.target.closest('.stock-btn');
    const input = event.target.closest('.stock-input');
    const cell = event.target.closest('.stock-cell');

    // Salgo si el evento no ocurrió en un control de stock
    if (!cell || (!button && !input)) return;

    const itemId = cell.dataset.itemId;
    const stockInput = cell.querySelector('.stock-input');
    if (!itemId || !stockInput) {
        console.error("No se encontró itemId o stockInput para la celda.");
        return;
    }

    let action = null;
    let value = null;

    // Busco el valor original ANTES de hacer cambios
    const originalRow = allData.find(row => (row.id ?? row.Id ?? row.ID) == itemId); // Busco ID case-insensitive
    const stockKey = originalRow ? Object.keys(originalRow).find(key => key.toLowerCase() === 'stock') : null;
    const originalValue = (originalRow && stockKey) ? originalRow[stockKey] : 0;

    if (button) { // Si fue clic en botón +/-
        action = button.classList.contains('plus') ? 'add' : 'remove';
        value = 1; // Ajusto de a 1
    } else if (input && event.type === 'change') { // Si cambió el valor del input
        action = 'set';
        value = parseInt(input.value, 10);
        if (isNaN(value) || value < 0) {
            alert("Ingresá un número de stock válido (mayor o igual a 0).");
            if(stockInput) stockInput.value = originalValue ?? 0; // Revierto al valor original
            return;
        }
    } else {
        return; // No hago nada si no es botón o cambio de input relevante
    }

    // Deshabilito controles de esta fila mientras proceso
    cell.querySelectorAll('button, input').forEach(el => el.disabled = true);

    try {
        console.log(`Intentando updateStock: itemId=${itemId}, action=${action}, value=${value}`);
        const result = await api.updateStock(itemId, action, value); // LLAMO A LA API

        if (result.success) {
            stockInput.value = result.newStock; // Actualizo visualmente
            // Actualizo mi copia local de los datos (allData)
            const rowIndex = allData.findIndex(row => (row.id ?? row.Id ?? row.ID) == itemId);
            if (rowIndex > -1 && stockKey) {
                allData[rowIndex][stockKey] = result.newStock;
                console.log("Dato local (allData) actualizado.");
            }
            console.log("Stock actualizado a:", result.newStock);
        } else {
            // Si la API devuelve success: false
            throw new Error(result.message || "Error desconocido del backend al actualizar stock.");
        }
    } catch (error) {
        console.error("Error al actualizar stock:", error);
        alert(`Error: ${error.message}`);
        // Revierto al valor original si la API falla
        if(stockInput) stockInput.value = originalValue ?? 0;
    } finally {
        // Rehabilito controles siempre
        cell.querySelectorAll('button, input').forEach(el => el.disabled = false);
    }
}

// -- Renderizado de Tabla --
function renderTable(columns, data) {
    const tableHead = document.querySelector('#data-table thead');
    const tableBody = document.querySelector('#data-table tbody');
    if (!tableHead || !tableBody) {
        console.error("Error crítico: No se encontraron los elementos thead o tbody.");
        return;
    }

    // Quito listeners viejos ANTES de añadir los nuevos
    tableBody.removeEventListener('click', handleStockUpdate);
    tableBody.removeEventListener('change', handleStockUpdate);
    // Añado listeners al tbody usando delegación
    tableBody.addEventListener('click', handleStockUpdate);
    tableBody.addEventListener('change', handleStockUpdate);
    console.log("Listeners de stock (click y change) añadidos/actualizados en tbody.");

    if (!data || data.length === 0) {
        // Estado vacío
        tableHead.innerHTML = ''; // Limpio cabecera
        tableBody.innerHTML = `
            <tr>
                <td colspan="${columns.length || 1}" class="empty-table-message">
                    Aún no hay datos ingresados.
                    <button id="import-data-empty-btn" class="btn btn-primary btn-inline">¿Deseas importarlos?</button>
                </td>
            </tr>`;
        // Busco y conecto el botón DESPUÉS de insertarlo
        const importBtn = document.getElementById('import-data-empty-btn');
        if (importBtn) {
            importBtn.addEventListener('click', () => {
                setStockifyColumns(currentTableColumns); // Le paso las columnas actuales
                openImportModal();
            });
            console.log("Listener añadido al botón 'import-data-empty-btn'.");
        } else {
            console.error("No se encontró el botón #import-data-empty-btn después de renderizar tabla vacía.");
        }
    } else {
        // Estado con datos
        // Renderizo cabeceras (con primera letra mayúscula)
        tableHead.innerHTML = `<tr>${columns.map(col => `<th>${col.charAt(0).toUpperCase() + col.slice(1)}</th>`).join('')}</tr>`;
        // Renderizo filas
        tableBody.innerHTML = data.map(row => {
            const rowId = row['id'] ?? row['Id'] ?? row['ID']; // Busco ID case-insensitive
            if (rowId === undefined) console.warn("Fila sin ID encontrada:", row); // Aviso si falta ID

            return `<tr>
                ${columns.map(col => {
                let value = row[col];
                // Fallback ID minúscula (por si acaso)
                if (value === undefined && col.toLowerCase() === 'id') { value = row['id']; }

                // Genero controles de stock si la columna es 'stock' (case-insensitive)
                if (col.toLowerCase() === 'stock') {
                    return `<td class="stock-cell" data-item-id="${rowId}"><button class="stock-btn minus">-</button><input type="number" class="stock-input" value="${value ?? 0}" min="0"><button class="stock-btn plus">+</button></td>`;
                } else {
                    // Para otras columnas, muestro el valor
                    return `<td>${value ?? ''}</td>`;
                }
            }).join('')}
            </tr>`;
        }).join('');
    }
}

// -- Filtrado --
function filterTable() {
    const searchInput = document.getElementById('search-input');
    // Verifico si el input existe antes de usarlo
    if (!searchInput) return;
    const searchTerm = searchInput.value.toLowerCase();

    const filteredData = allData.filter(row =>
        Object.values(row).some(value =>
            String(value).toLowerCase().includes(searchTerm)
        )
    );
    // Vuelvo a renderizar con los datos filtrados, usando las columnas originales
    renderTable(currentTableColumns, filteredData);
}

// -- Navegación --
function showDashboardView(viewId) {
    document.querySelectorAll('.dashboard-view').forEach(view => view.classList.add('hidden'));
    const viewToShow = document.getElementById(viewId);
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
            if (targetView) { showDashboardView(targetView); }
            else { alert("Funcionalidad aún no implementada."); }
        });
    });
    console.log("Navegación del menú lateral configurada.");
}

// ---- 4. INICIALIZACIÓN ----
async function init() {
    console.log("[INIT] Iniciando dashboard...");
    const nav = document.getElementById('header-nav');
    if (nav) nav.innerHTML = `<a href="/logout.php" class="btn btn-secondary">Cerrar Sesión</a>`;
    const tableTitleElement = document.getElementById('table-title');

    initializeImportModal(); // Inicializo modal al principio
    console.log("[INIT] Modal inicializado.");

    // --- Selecciono Elementos del Modal de Eliminación ---
    deleteModal = document.getElementById('delete-confirm-modal');
    deleteConfirmInput = document.getElementById('delete-confirm-input');
    confirmDeleteBtn = document.getElementById('confirm-delete-btn');
    deleteDbNameConfirmSpan = document.getElementById('delete-db-name-confirm');
    deleteErrorMsg = document.getElementById('delete-error-message');
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

    try {
        console.log("[INIT] Llamando a api.getTableData...");
        const result = await api.getTableData();
        console.log("[INIT] Respuesta de getTableData:", result);

        if (result && result.success === true) { // Verifico explícitamente success
            allData = result.data || []; // Aseguro que sea array
            currentTableColumns = result.columns || []; // Aseguro que sea array

            if (tableTitleElement) {
                tableTitleElement.textContent = result.inventoryName || 'Inventario';
            }

            console.log("[INIT] Llamando a renderTable...");
            renderTable(currentTableColumns, allData);
            console.log("[INIT] renderTable completado.");

            // Añado listener SOLO si el input existe
            const searchInput = document.getElementById('search-input');
            if (searchInput) {
                searchInput.addEventListener('input', filterTable);
                console.log("[INIT] Listener de búsqueda añadido.");
            } else {
                console.warn("[INIT] Input de búsqueda no encontrado.");
            }
            setupMenuNavigation(); // Configuro menú lateral
            showDashboardView('view-db'); // Muestro la vista de tabla por defecto
            document.getElementById('add-row-btn')?.addEventListener('click', handleAddRowClick);
        } else {
            console.error("[INIT] getTableData devolvió success: false o respuesta inválida.");
            throw new Error(result?.message || 'Error al obtener datos de tabla.');
        }
    } catch (error) {
        console.error("[INIT] Error CATCH:", error);
        alert(`Error al cargar el panel: ${error.message}. Serás redirigido.`);
        if (error.message.includes('No autorizado')) {
            window.location.href = '/login.php';
        } else {
            window.location.href = '/select-db.php';
        }
    }
}

function createEditableRow(columns) {
    const tr = document.createElement('tr');
    tr.classList.add('editing-row'); // Clase para estilos específicos

    columns.forEach(col => {
        const td = document.createElement('td');
        // Ignoramos 'id' y 'created_at' para la entrada
        if (col.toLowerCase() === 'id' || col.toLowerCase() === 'created_at') {
            td.textContent = ''; // Celda vacía para columnas automáticas
        } else if (col.toLowerCase() === 'stock') {
            // Reutilizamos la estructura de controles de stock
            td.classList.add('stock-cell'); // Aplico estilo flex
            td.innerHTML = `
                 <button class="stock-btn minus" disabled>-</button>
                 <input type="number" class="stock-input" value="0" min="0" data-column="${col}"> 
                 <button class="stock-btn plus" disabled>+</button>
             `;
        }

        else {
            // Input de texto genérico para otras columnas
            const input = document.createElement('input');
            input.type = 'text';
            input.placeholder = col.charAt(0).toUpperCase() + col.slice(1);
            input.dataset.column = col; // Guardo el nombre de la columna acá
            td.appendChild(input);
        }
        tr.appendChild(td);
    });

    // Añadimos celda para botones Guardar/Cancelar
    const actionTd = document.createElement('td');
    actionTd.classList.add('action-buttons');
    actionTd.innerHTML = `
        <button class="btn btn-primary save-new-row-btn">Guardar</button>
        <button class="btn btn-secondary cancel-new-row-btn">Cancelar</button>
    `;
    tr.appendChild(actionTd);

    return tr;
}

function handleAddRowClick() {
    const tableBody = document.querySelector('#data-table tbody');
    if (!tableBody || tableBody.querySelector('.editing-row')) {
        // Si no hay body o ya hay una fila editándose, no hago nada
        return;
    }
    // Creo la nueva fila editable
    const newRow = createEditableRow(currentTableColumns);
    // La inserto al PRINCIPIO del tbody
    tableBody.prepend(newRow);

    // Conecto los botones Guardar/Cancelar de ESTA fila
    newRow.querySelector('.save-new-row-btn')?.addEventListener('click', handleSaveNewRow);
    newRow.querySelector('.cancel-new-row-btn')?.addEventListener('click', handleCancelNewRow);
}

/**
 * Maneja el clic en el botón "Guardar" de la nueva fila.
 */
async function handleSaveNewRow(event) {
    const saveButton = event.target;
    const newRowElement = saveButton.closest('.editing-row');
    if (!newRowElement) return;

    const newItemData = {};
    let isValid = true;

    // Recopilo los datos de los inputs de la fila
    newRowElement.querySelectorAll('input[data-column]').forEach(input => {
        const colName = input.dataset.column;
        const value = input.value.trim();
        // Acá podrías añadir validaciones más específicas si querés
        newItemData[colName] = value;
    });

    console.log("Datos a guardar:", newItemData); // Para depurar

    saveButton.disabled = true;
    saveButton.textContent = 'Guardando...';

    try {
        const result = await api.addItemToTable(newItemData); // Llamo a la API
        if (result.success && result.newItem) {
            // ¡Éxito! Reemplazo la fila editable por una normal con los datos guardados
            allData.unshift(result.newItem); // Añado el nuevo item al principio de mis datos locales
            renderTable(currentTableColumns, allData); // Vuelvo a renderizar toda la tabla
        } else {
            throw new Error(result.message || "Error al guardar la fila.");
        }
    } catch (error) {
        alert(`Error al guardar: ${error.message}`);
        saveButton.disabled = false;
        saveButton.textContent = 'Guardar';
    }
}

function handleCancelNewRow(event) {
    const cancelButton = event.target;
    const newRowElement = cancelButton.closest('.editing-row');
    if (newRowElement) {
        newRowElement.remove(); // Simplemente elimino la fila
    }
}

// public/assets/js/dashboard.js

// ... (importaciones, variables globales allData, currentTableColumns) ...

// ---- VARIABLES GLOBALES PARA MODAL DE ELIMINACIÓN ----
let deleteModal, deleteConfirmInput, confirmDeleteBtn, deleteDbNameConfirmSpan, deleteErrorMsg;
let currentDbNameToDelete = ''; // Guardamos el nombre a confirmar

// ---- FUNCIONES DEL MODAL DE ELIMINACIÓN ----
function openDeleteModal() {
    currentDbNameToDelete = document.getElementById('table-title')?.textContent || ''; // Tomo el nombre actual
    if (!currentDbNameToDelete || !deleteModal) return;

    deleteDbNameConfirmSpan.textContent = currentDbNameToDelete; // Muestro el nombre en el modal
    deleteConfirmInput.value = ''; // Limpio el input
    confirmDeleteBtn.disabled = true; // Deshabilito el botón final
    deleteErrorMsg.textContent = ''; // Limpio errores
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
    if (deleteConfirmInput.value !== currentDbNameToDelete) return; // Doble chequeo

    confirmDeleteBtn.disabled = true;
    confirmDeleteBtn.textContent = 'Eliminando...';

    try {
        const result = await api.deleteDatabase(); // Llamo a la API
        if (result.success) {
            alert(result.message);
            window.location.href = '/select-db.php'; // Redirijo a la selección
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        deleteErrorMsg.textContent = `Error: ${error.message}`;
        confirmDeleteBtn.disabled = false; // Rehabilito si falla
        confirmDeleteBtn.textContent = 'Eliminar Permanentemente';
    }
}
// --- 5. Ejecución ---
document.addEventListener('DOMContentLoaded', init);