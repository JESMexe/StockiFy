// public/assets/js/dashboard.js (Versión ÚNICA Y ORDENADA)

// --- 1. IMPORTACIONES ---
import * as api from './api.js';
import {pop_ups} from './notifications/pop-up.js';
import { openImportModal, initializeImportModal, setStockifyColumns } from './import.js';

// --- 2. VARIABLES GLOBALES ---
let allData = []; // Guardo todos los datos para filtrar
let currentTableColumns = []; // Guardo las columnas de la tabla actual
let editingRowId = null; // Para saber qué fila estoy editando
const protectedColumns = ['id', 'created_at'];

// Variables para el Modal de Eliminación
let deleteModal, deleteConfirmInput, confirmDeleteBtn, deleteDbNameConfirmSpan, deleteErrorMsg;
let currentDbNameToDelete = '';
let columnListContainer, addColumnForm, columnListStatus;

// --- 3. DEFINICIONES DE FUNCIONES ---

// == FUNCIONES DEL PANEL PRINCIPAL (TABLA, FILTRO, NAVEGACIÓN) ==

function renderTable(columns, data) {
    const tableHead = document.querySelector('#data-table thead');
    const tableBody = document.querySelector('#data-table tbody');
    if (!tableHead || !tableBody) {
        console.error("Error crítico: No se encontraron los elementos thead o tbody.");
        return;
    }

    // Añado listeners al tbody (se quitan y añaden para evitar duplicados)
    tableBody.removeEventListener('click', handleTableClick);
    tableBody.addEventListener('click', handleTableClick);

    // Cabecera: Añado una columna extra para "Acciones"
    tableHead.innerHTML = `<tr>${columns.map(col => `<th>${col.charAt(0).toUpperCase() + col.slice(1)}</th>`).join('')}<th>Acciones</th></tr>`;

    if (!data || data.length === 0) {
        // Estado vacío
        tableBody.innerHTML = `<tr><td colspan="${columns.length + 1}" class="empty-table-message">Aún no hay datos ingresados. <button id="import-data-empty-btn" class="btn btn-primary btn-inline">¿Deseas importarlos?</button></td></tr>`;
        const importBtn = document.getElementById('import-data-empty-btn');
        if (importBtn) {
            importBtn.addEventListener('click', () => {
                setStockifyColumns(currentTableColumns); // Le paso las columnas
                openImportModal();
            });
        }
    } else {
        // Estado con datos
        tableBody.innerHTML = data.map(row => {
            const rowId = row['id'] ?? row['Id'] ?? row['ID']; // Busco ID
            if (rowId === undefined) console.warn("Fila sin ID encontrada:", row);

            // Si esta fila es la que estoy editando, muestro inputs
            if (rowId == editingRowId) { // Uso '==' por si uno es string y otro int
                return `
                <tr class="editing-row" data-item-id="${rowId}">
                    ${columns.map(col => `<td>${createInputForCell(col, row[col])}</td>`).join('')}
                    <td class="action-buttons">
                        <button class="btn btn-primary save-row-btn"><i class="ph ph-check"></i></button>
                        <button class="btn btn-secondary cancel-row-btn"><i class="ph ph-x"></i></button>
                    </td>
                </tr>`;
            } else {
                // Fila normal (modo vista)
                return `
                <tr data-item-id="${rowId}">
                    ${columns.map(col => {
                    let value = row[col];
                    if (value === undefined && col.toLowerCase() === 'id') { value = row['id']; }
                    return `<td>${value ?? ''}</td>`;
                }).join('')}
                    <td class="action-cell">
                        <button class="btn btn-secondary edit-row-btn"><i class="ph ph-pencil"></i> Editar</button>
                    </td>
                </tr>`;
            }
        }).join('');
    }
}

function createInputForCell(columnName, value) {
    const colLower = columnName.toLowerCase();
    if (colLower === 'id' || colLower === 'created_at') {
        return value ?? ''; // No editable
    }
    if (colLower === 'stock' || colLower === 'precio') { // Asumo que precio también es numérico
        return `<input type="number" data-column="${columnName}" value="${value ?? 0}">`;
    }
    return `<input type="text" data-column="${columnName}" value="${value ?? ''}">`;
}

function filterTable() {
    const searchInput = document.getElementById('search-input');
    if (!searchInput) return;
    const searchTerm = searchInput.value.toLowerCase();
    const filteredData = allData.filter(row =>
        Object.values(row).some(value =>
            String(value).toLowerCase().includes(searchTerm)
        )
    );
    renderTable(currentTableColumns, filteredData); // Renderizo con datos filtrados
}

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

// == MANEJADORES DE CLICS DE LA TABLA ==

function handleTableClick(event) {
    const editBtn = event.target.closest('.edit-row-btn');
    const saveBtn = event.target.closest('.save-row-btn');
    const cancelBtn = event.target.closest('.cancel-row-btn');
    const stockBtn = event.target.closest('.stock-btn'); // Clic en +/- de stock (si volvemos a ponerlos)

    if (editBtn) {
        handleEditClick(editBtn);
    } else if (saveBtn) {
        handleSaveClick(saveBtn);
    } else if (cancelBtn) {
        handleCancelClick(cancelBtn);
    } else if (stockBtn) {
        // Esta función 'handleStockUpdate' ya no existe, la lógica
        // de edición de stock ahora está dentro de 'handleSaveClick'
        // Si querés que +/- funcionen, necesitamos una lógica separada.
        // Por ahora, solo 'Editar' funciona.
        console.log("Clic en botón de stock (lógica pendiente si se re-activa)");
    }
}

// -- Funciones de Edición de Fila --
function handleEditClick(button) {
    const row = button.closest('tr');
    editingRowId = row.dataset.itemId;
    renderTable(currentTableColumns, allData); // Vuelvo a renderizar
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
        pop_ups.warning("Verificá que todos los campos numéricos tengan un número válido.");
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
            renderTable(currentTableColumns, allData); // Re-renderizo
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        pop_ups.warning(`Error al guardar: ${error.message}`);
        button.disabled = false;
    }
}

function handleCancelClick() {
    editingRowId = null; // Salgo modo edición
    renderTable(currentTableColumns, allData); // Re-renderizo
}

// -- Funciones para Añadir Fila --
function createEditableRow(columns) {
    const tr = document.createElement('tr');
    tr.classList.add('editing-row');
    columns.forEach(col => {
        const td = document.createElement('td');
        // Llamo a la función auxiliar para crear el input correcto
        td.innerHTML = createInputForCell(col, ''); // Valor inicial vacío
        tr.appendChild(td);
    });
    // Celda de Acciones
    const actionTd = document.createElement('td');
    actionTd.classList.add('action-buttons');
    actionTd.innerHTML = `<button class="btn btn-primary save-new-row-btn">Guardar</button><button class="btn btn-secondary cancel-new-row-btn">Cancelar</button>`;
    tr.appendChild(actionTd);
    return tr;
}

function handleAddRowClick() {
    const tableBody = document.querySelector('#data-table tbody');
    if (!tableBody || tableBody.querySelector('.editing-row')) return;
    const newRow = createEditableRow(currentTableColumns);
    tableBody.prepend(newRow); // Inserto al principio
    newRow.querySelector('.save-new-row-btn')?.addEventListener('click', handleSaveNewRow);
    newRow.querySelector('.cancel-new-row-btn')?.addEventListener('click', handleCancelNewRow);
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
            allData.unshift(result.newItem); // Añado al principio
            renderTable(currentTableColumns, allData); // Re-renderizo
        } else {
            throw new Error(result.message || "Error al guardar la fila.");
        }
    } catch (error) {
        pop_ups.warning(`Error al guardar: ${error.message}`);
        // No borro la fila, dejo que el usuario corrija
        saveButton.disabled = false;
        saveButton.textContent = 'Guardar';
    }
}

function handleCancelNewRow(event) {
    const newRowElement = event.target.closest('.editing-row');
    if (newRowElement) {
        newRowElement.remove(); // Elimino la fila
    }
}

// == FUNCIONES DEL MODAL DE ELIMINACIÓN ==
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
    if (deleteConfirmInput.value === currentDbNameToDelete) {
        confirmDeleteBtn.disabled = false;
        deleteErrorMsg.textContent = '';
    } else {
        confirmDeleteBtn.disabled = true;
        deleteErrorMsg.textContent = (deleteConfirmInput.value.length > 0) ? 'El nombre no coincide.' : '';
    }
}

async function handleConfirmDelete() {
    if (deleteConfirmInput.value !== currentDbNameToDelete) return;
    confirmDeleteBtn.disabled = true;
    confirmDeleteBtn.textContent = 'Eliminando...';
    try {
        const result = await api.deleteDatabase();
        if (result.success) {
            pop_ups.warning(result.message);
            window.location.href = '/select-db.php';
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        //deleteErrorMsg.textContent = `Error: ${error.message}`;
        pop_ups.error(`Error: ${error.message}`);
        confirmDeleteBtn.disabled = false;
        confirmDeleteBtn.textContent = 'Eliminar Permanentemente';
    }
}

// ---- 4. INICIALIZACIÓN (LA ÚNICA FUNCIÓN init) ----
async function init() {
    console.log("[INIT] Iniciando dashboard...");
    const nav = document.getElementById('header-nav');
    if (nav) nav.innerHTML = `<a href="/logout.php" class="btn btn-secondary">Cerrar Sesión</a>`;
    const tableTitleElement = document.getElementById('table-title');

    initializeImportModal();
    console.log("[INIT] Modal de Importación inicializado.");

    // Selecciono Elementos del Modal de Eliminación
    deleteModal = document.getElementById('delete-confirm-modal');
    deleteConfirmInput = document.getElementById('delete-confirm-input');
    confirmDeleteBtn = document.getElementById('confirm-delete-btn');
    deleteDbNameConfirmSpan = document.getElementById('delete-db-name-confirm');
    deleteErrorMsg = document.getElementById('delete-error-message');
    columnListContainer = document.getElementById('column-list-container');
    addColumnForm = document.getElementById('add-column-form');
    columnListStatus = document.getElementById('column-list-status');
    const closeDeleteBtn = document.getElementById('close-delete-modal-btn');
    const cancelDeleteBtn = document.getElementById('cancel-delete-btn');
    const deleteDbBtn = document.getElementById('delete-db-btn');

    // Conecto Eventos del Modal de Eliminación
    deleteDbBtn?.addEventListener('click', openDeleteModal);
    closeDeleteBtn?.addEventListener('click', closeDeleteModal);
    cancelDeleteBtn?.addEventListener('click', closeDeleteModal);
    deleteConfirmInput?.addEventListener('input', handleDeleteConfirmInput);
    confirmDeleteBtn?.addEventListener('click', handleConfirmDelete);
    if(deleteModal) {
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

    setupMenuNavigation();
    showDashboardView('view-db');
}

function renderColumnList() {
    if (!columnListContainer) return;

    // Filtramos las columnas que NO son protegidas para la lista de gestión
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
            pop_ups.warning(result.message);
            await loadTableData();
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        pop_ups.error(`Error al añadir columna: ${error.message}`);
    }
}

async function handleDropColumn(e) {
    const colItem = e.target.closest('.column-item');
    const columnName = colItem.dataset.columnName;

    if (!confirm(`¿Estás seguro de que querés eliminar la columna "${columnName}"? Esta acción no se puede deshacer y borrará todos los datos de esa columna.`)) {
        return;
    }

    try {
        const result = await api.manageTableColumn('drop_column', { columnName });
        if (result.success) {
            pop_ups.info(result.message);
            await loadTableData();
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        pop_ups.warning(`Error al eliminar columna: ${error.message}`);
    }
}

async function handleRenameColumn(e) {
    const colItem = e.target.closest('.column-item');
    const oldName = colItem.dataset.columnName;
    const newName = prompt(`Ingresá el nuevo nombre para la columna "${oldName}":`, oldName);

    if (!newName || newName.trim() === '' || newName === oldName) {
        return;
    }

    try {
        const result = await api.manageTableColumn('rename_column', { oldName, newName });
        if (result.success) {
            await loadTableData();
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        pop_ups.warning(`Error al renombrar columna: ${error.message}`);
    }
}

async function loadTableData() {
    try {
        console.log("[loadTableData] Llamando a api.getTableData...");
        const result = await api.getTableData();

        if (result && result.success === true) {
            allData = result.data || [];
            currentTableColumns = result.columns || [];

            document.getElementById('table-title').textContent = result.inventoryName || 'Inventario';

            console.log("[loadTableData] Llamando a renderTable y renderColumnList...");
            renderTable(currentTableColumns, allData);
            renderColumnList(); // Asegúrate de que la lista de config. también se actualice

        } else {
            pop_ups.error("[loadTableData] getTableData devolvió success: false.");
            throw new Error(result?.message || 'Error al obtener datos de tabla.');
        }
    } catch (error) {
        pop_ups.error("[loadTableData] Error CATCH:", error);
        pop_ups.warning(`Error al cargar datos: ${error.message}. Serás redirigido.`);
        if (error.message.includes('No autorizado')) {
            window.location.href = '/login.php';
        } else {
            window.location.href = '/select-db.php';
        }
    }
}

// --- 5. Ejecución ---
document.addEventListener('DOMContentLoaded', init);