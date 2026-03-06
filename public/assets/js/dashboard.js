// assets/js/dashboard.js

// --- 1. IMPORTACIONES ---
import * as api from './api.js';
import * as setup from './setupMiCuentaDropdown.js';
import {notificationConfig, pop_ups} from './notifications/pop-up.js';
import { salesModuleInstance } from './sales/sales.js';
import { purchaseModuleInstance } from './purchases/purchases.js';
import { customerModuleInstance } from './customers/customers.js';
import { providerModuleInstance } from './providers/providers.js';
import { employeeModuleInstance } from './employees/employees.js';
import { analyticsModuleInstance } from './analytics/analytics.js';
import { paymentsModuleInstance } from './payment/payment.js';
import {openImportModal} from './import.js';
import {ui_helper} from "./ui-helper.js";

// --- 2. VARIABLES GLOBALES ---
export let activeInventoryId = null;
let allData = []; // Guardo todos los datos para filtrar
let currentTableColumns = []; // Guardo las columnas de la tabla actual
let originalData = []; // COPIA DE SEGURIDAD PARA EL ORDEN ORIGINAL
let editingRowId = null; // Para saber qué fila estoy editando
let selectedSearchColumn = 'all'; // 'all' es el valor por defecto
let searchColumnBtn, searchColumnBtnText, searchColumnDropdown;
let isCriticalFilterActive = false; // Estado del filtro de peligro
let isActionsHidden = true; // false = Visible, true = Oculto (Pestaña activa)
let currentDollarRate = 0;
let columnSorter = null;
const protectedColumns = ['id', 'created_at'];
let activeDisplayColumns = [];

let deleteModal, deleteConfirmInput, confirmDeleteBtn, deleteDbNameConfirmSpan, deleteErrorMsg;
let currentDbNameToDelete = '';
let columnListContainer, addColumnForm, columnListStatus;

let currentSort = { column: null, state: 0 };

let columnMapping = { name: null, stock: null, sale_price: null, buy_price: null };
let activeFeatures = { min_stock: false, gain: false, gain_type: 'percent' };


function formatColumnName(col) {
    const lowerCol = col.toLowerCase();

    // 1. Mapeo exacto de columnas del sistema a Español
    const map = {
        'id': 'ID',
        'name': 'Nombre',
        'stock': 'Stock',
        'min_stock': 'Stock Mínimo',
        'sale_price': 'Precio de Venta',
        'receipt_price': 'Precio de Compra',
        'percentage_gain': 'Ganancia (%)',
        'hard_gain': 'Ganancia ($)',
        'created_at': 'Fecha de Creación',
        'updated_at': 'Última Actualización'
    };

    if (map[lowerCol]) {
        return map[lowerCol];
    }

    const cleanName = col.replace(/_/g, ' ');
    return cleanName.charAt(0).toUpperCase() + cleanName.slice(1);
}

function checkCriticalStatus() {
    const btn = document.getElementById('critical-filter-btn');
    if (!btn) return;

    // Recorremos los datos para ver si AL MENOS UNO es crítico
    const hasCriticalItems = allData.some(row => {
        // 1. Chequeo de Stock
        if (activeFeatures.min_stock) {
            const stock = parseFloat(row[columnMapping.stock]) || 0;
            const min = parseFloat(row['min_stock']) || 0;
            if (stock <= min) return true;
        }
        // 2. Chequeo de Ganancia (Pérdida)
        if (activeFeatures.gain) {
            const sale = parseFloat(row['sale_price']) || 0;
            const buy = parseFloat(row['receipt_price']) || 0;
            if (sale > 0 && buy > 0 && sale < buy) return true;
        }
        return false;
    });

    // Mostrar u ocultar botón
    if (hasCriticalItems) {
        btn.classList.remove('hidden');
    } else {
        btn.classList.add('hidden');
        // Si el botón desaparece y estaba activo, desactivamos el filtro
        if (isCriticalFilterActive) {
            isCriticalFilterActive = false;
            btn.style.backgroundColor = '#fff0f0';
            btn.style.color = 'var(--accent-red)';
            filterTable();
        }
    }
}

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
 * renderTable
 */
async function renderTable(columns, data) {
    if (data && Array.isArray(data) && data.length > 0) {
        window.allData = data;
        console.log("Datos sincronizados con Móvil");
    }

    // --- 1. DEFINICIONES CRÍTICAS (Las que faltaban) ---
    const currencySaleKey = '_meta_currency_sale';
    const currencyBuyKey = '_meta_currency_buy';

    const tableHead = document.querySelector('#data-table thead');
    const tableBody = document.querySelector('#data-table tbody');

    // Validación de seguridad
    if (!tableHead || !tableBody) return;

    // --- 2. CARGA DE PREFERENCIAS ---
    try {
        // Intentamos cargar preferencias si no están en memoria
        if (!columnMapping.name) {
            const prefs = await api.getCurrentInventoryPreferences();
            if (prefs && prefs.success) {
                if (prefs.mapping) columnMapping = prefs.mapping;
                window.columnMapping = columnMapping;
                window.activeFeatures = activeFeatures;
                if(prefs.features) activeFeatures = prefs.features;

                // Cargar visibilidad y orden si existen
                window.currentUserPreferences = window.currentUserPreferences || {};
                if(prefs.visible_columns) window.currentUserPreferences.visible_columns = prefs.visible_columns;
                if(prefs.column_order) window.currentUserPreferences.column_order = prefs.column_order;
            }
        }
    } catch (e) { console.warn("Usando prefs por defecto o error al cargar"); }

    // Definir columnas base si no vienen
    if ((!columns || columns.length === 0) && data && data.length > 0) {
        columns = Object.keys(data[0]);
        currentTableColumns = columns;
    }

    // --- 3. LÓGICA MAESTRA DE ORDEN Y VISIBILIDAD ---
    let displayColumns = [...columns];

    // A. Ordenamiento Personalizado (Drag & Drop)
    if (window.currentUserPreferences && Array.isArray(window.currentUserPreferences.column_order)) {
        const savedOrder = window.currentUserPreferences.column_order;

        displayColumns.sort((a, b) => {
            const idxA = savedOrder.indexOf(a);
            const idxB = savedOrder.indexOf(b);

            // Si es columna nueva (no está en el orden guardado), al final
            if (idxA === -1 && idxB === -1) return 0;
            if (idxA === -1) return 1;
            if (idxB === -1) return -1;

            return idxA - idxB;
        });
    } else {
        // FALLBACK: Orden por defecto (Sistema al final)
        const mappedBuyCol = columnMapping.buy_price;
        const mappedSaleCol = columnMapping.sale_price;
        const minStockCol = 'min_stock';
        const systemHiddenCols = ['created_at', 'user_id', 'inventory_id', 'updated_at', 'min_stock'];

        const normalColumns = columns.filter(col => {
            const colLower = col.toLowerCase();
            const isBuyCol = mappedBuyCol && colLower === mappedBuyCol.toLowerCase();
            const isSaleCol = mappedSaleCol && colLower === mappedSaleCol.toLowerCase();
            return !systemHiddenCols.includes(colLower) && !isBuyCol && !isSaleCol;
        });

        const orderedSpecialColumns = [];
        if (mappedBuyCol && columns.includes(mappedBuyCol)) orderedSpecialColumns.push(mappedBuyCol);
        if (mappedSaleCol && columns.includes(mappedSaleCol)) orderedSpecialColumns.push(mappedSaleCol);
        if (activeFeatures.min_stock && columns.includes(minStockCol)) orderedSpecialColumns.push(minStockCol);

        displayColumns = [...normalColumns, ...orderedSpecialColumns];
    }

    // B. Filtro de Visibilidad (Gestor de Columnas)
    if (window.currentUserPreferences && Array.isArray(window.currentUserPreferences.visible_columns)) {
        const visibleSet = window.currentUserPreferences.visible_columns;
        if (visibleSet.length > 0) {
            displayColumns = displayColumns.filter(col => visibleSet.includes(col));
        }
    } else {
        // Si no hay config, ocultamos IDs y timestamps por limpieza
        const systemHiddenDefault = ['created_at', 'user_id', 'inventory_id', 'updated_at'];
        displayColumns = displayColumns.filter(col => !systemHiddenDefault.includes(col.toLowerCase()));
    }

    activeDisplayColumns = displayColumns;

    // --- 4. RENDERIZADO DEL HEADER ---
    let actionsTh = '';
    if (!isActionsHidden) {
        actionsTh = `<th class="actions-header" onclick="window.toggleActionsColumn()" title="Ocultar columna">Acciones <i class="ph ph-caret-right" style="vertical-align: middle; margin-left: 5px;"></i></th>`;
    }

    const headerHTML = displayColumns.map(col => {
        let niceName = formatColumnName(col); // Asegúrate que esta función exista globalmente
        let label = niceName;

        // Iconos identificadores
        if (col === columnMapping.stock) label += ' <i class="ph-bold ph-package"></i>';
        if (col === columnMapping.sale_price) label += ' <i class="ph-bold ph-coin-vertical"></i>';
        if (col === columnMapping.buy_price) label += ' <i class="ph-bold ph-shopping-cart"></i>';
        if (col === columnMapping.name) label += '<i class="ph-bold ph-article"></i>';
        if (col === 'min_stock') label += ' <i class="ph-bold ph-folder-simple-minus"></i>';

        // Icono de ordenamiento
        let sortIcon = ' <i class="ph-fill ph-caret-up-down" style="font-size: 1.4em; color: var(--color-gray);"></i>';
        if (currentSort.column === col) {
            if (currentSort.state === 1) sortIcon = ' <i class="ph-fill ph-caret-up" style="font-size: 1.3em; color: var(--accent-color);"></i>';
            if (currentSort.state === 2) sortIcon = ' <i class="ph-fill ph-caret-down" style="; font-size: 1.3em; color: var(--accent-color);"></i>';
        }

        return `<th onclick="window.handleSort('${col}')" style="border-radius: 0; cursor: pointer; user-select: none;" title="Ordenar por ${niceName}">${sortIcon}${label}</th>`;
    }).join('');

    let gainHeader = '';
    if (activeFeatures.gain) {
        const gainLabel = activeFeatures.gain_type === 'percent' ? 'Ganancia (%)' : 'Ganancia ($)';
        gainHeader = `<th style="cursor: default;">${gainLabel}</th>`;
    }

    tableHead.innerHTML = `<tr>${headerHTML}${gainHeader}${actionsTh}</tr>`;

    // --- 5. RENDERIZADO DEL BODY ---
    if (!data || data.length === 0) {
        tableBody.innerHTML = `
        <div style="
            width:100%;
            height:100%;
            display:flex;
            align-items:center;
            justify-content:center;
            overflow: hidden;
        ">
            <img src="/assets/img/ImagenSinDatos.svg" 
                 alt="Sin datos"
                 style="
                    max-width:100%;
                    max-height:100%;
                    object-fit:contain;
                    overflow: hidden;
                 ">
        </div>
    `;
    } else {
        tableBody.innerHTML = data.map(row => {
            const rowId = row['id'] ?? row['Id'] ?? row['ID'];
            const isEditing = (editingRowId == rowId);

            const cellsHTML = displayColumns.map(col => {
                let value = row[col] ?? '-';
                let cellClass = '';

                // Lógica de Stocks y Precios
                const isSale = (col.toLowerCase() === columnMapping.sale_price?.toLowerCase());
                const isBuy = (col.toLowerCase() === columnMapping.buy_price?.toLowerCase());

                // Alerta Stock Mínimo
                if (col === columnMapping.stock && activeFeatures.min_stock) {
                    const min = parseFloat(row['min_stock']) || 0;
                    const current = parseFloat(value) || 0;
                    if (current <= min) cellClass = 'status-critical';
                }

                // Moneda
                let currency = 'ARS';
                if (isSale) currency = row[currencySaleKey] || 'ARS'; // ¡AQUÍ ESTABA EL ERROR!
                if (isBuy) currency = row[currencyBuyKey] || 'ARS';   // ¡Y AQUÍ!

                const symbol = (currency === 'USD') ? 'US$' : '$';

                // Formateo de Valor
                if ((isSale || isBuy) && !isNaN(value) && value !== '-') {
                    const numVal = parseFloat(value);
                    if (Math.abs(numVal) < 10 && numVal % 1 !== 0) {
                        value = `${symbol} ${parseFloat(numVal.toFixed(6))}`;
                    } else {
                        value = `${symbol} ${numVal.toFixed(2)}`;
                    }
                }

                // Modo Edición
                if (isEditing) {
                    const colKey = String(col).toLowerCase().trim();
                    const isIdCol = (colKey === 'id' || colKey === ' id' || colKey === 'ID');

                    if (isIdCol) {
                        const shown = (row[col] ?? value ?? '-');
                        return `<td class="${cellClass}"><span class="readonly-cell">${shown}</span></td>`;
                    }
                    if (isSale || isBuy) {
                        return `
                        <td>
                            <div class="flex-row" style="gap:0;">
                                <button type="button" class="btn-currency-toggle" 
                                    onclick="window.toggleRowCurrency(this, '${rowId}', '${isSale?'sale':'buy'}', '${currency}')"
                                    title="Cambiar a ${currency==='ARS'?'USD':'ARS'}"
                                    style="padding: 0 5px; font-size: 0.7rem; background: #eee; border: 1px solid #ccc; border-right: none; border-radius: 4px 0 0 4px; cursor: pointer;">
                                    ${currency}
                                </button>
                                <input type="text" class="editing-input form-control" data-col="${col}" value="${row[col] ?? ''}" data-currency-type="${isSale ? 'sale' : 'buy'}" data-current-currency="${currency}" style="width:100%; border-radius: 0 4px 4px 0;">
                            </div>
                        </td>`;
                    }
                    return `<td><input type="text" class="editing-input form-control" data-col="${col}" value="${row[col] ?? ''}" style="width:100%"></td>`;
                }
                return `<td class="${cellClass}">${value}</td>`;
            }).join('');

            // --- LÓGICA DE GANANCIA ---
            let gainHTML = '';
            if (activeFeatures.gain) {
                const saleColName = columnMapping.sale_price;
                const buyColName = columnMapping.buy_price;

                const rawSale = (saleColName && row[saleColName]) ? parseFloat(row[saleColName]) : 0;
                const rawBuy = (buyColName && row[buyColName]) ? parseFloat(row[buyColName]) : 0;

                const saleCurrency = row[currencySaleKey] || 'ARS';
                const buyCurrency = row[currencyBuyKey] || 'ARS';

                let profit = 0;
                let displayProfit = '-';
                let profitClass = '';

                // Cálculo con cotización
                if (currentDollarRate > 0 && rawSale > 0 && rawBuy > 0) {
                    const saleInArs = (saleCurrency === 'USD') ? rawSale * currentDollarRate : rawSale;
                    const buyInArs = (buyCurrency === 'USD') ? rawBuy * currentDollarRate : rawBuy;

                    if (activeFeatures.gain_type === 'fixed') {
                        if (saleCurrency === 'USD' && buyCurrency === 'USD') {
                            profit = rawSale - rawBuy;
                            displayProfit = `US$ ${profit.toFixed(2)}`;
                        } else {
                            profit = saleInArs - buyInArs;
                            displayProfit = `$${profit.toFixed(2)}`;
                        }
                    } else {
                        // Porcentaje
                        if (buyInArs > 0) {
                            profit = ((saleInArs - buyInArs) / buyInArs) * 100;
                            displayProfit = `${profit.toFixed(2)}%`;
                        } else if (saleInArs > 0) {
                            profit = 100;
                            displayProfit = `100%`;
                        } else {
                            profit = 0;
                            displayProfit = `0%`;
                        }
                    }

                    if (profit > 0) profitClass = 'text-green-600 font-bold';
                    else if (profit < 0) profitClass = 'status-critical';

                } else if (currentDollarRate === 0 && (saleCurrency === 'USD' || buyCurrency === 'USD')) {
                    displayProfit = 'Err Cotiz.';
                    profitClass = 'text-gray-500';
                } else if (rawSale > 0 && rawBuy > 0) {
                    // Sin conversión (todo ARS o todo USD sin cotización explícita)
                    if (activeFeatures.gain_type === 'fixed') {
                        profit = rawSale - rawBuy;
                        displayProfit = `$${profit.toFixed(2)}`;
                    } else {
                        profit = ((rawSale - rawBuy) / rawSale) * 100;
                        displayProfit = `${profit.toFixed(1)}%`;
                    }
                    if (profit > 0) profitClass = 'text-green-600 font-bold';
                    else if (profit < 0) profitClass = 'status-critical';
                }

                const style = profit > 0 ? 'color: var(--accent-green); font-weight: bold;' : '';
                gainHTML = `<td class="${profitClass}" style="${style}">${displayProfit}</td>`;
            }

            // --- LÓGICA DE ACCIONES ---
            let actionsHTML = '';
            if (!isActionsHidden) {
                if (isEditing) {
                    actionsHTML = `
                    <td class="actions-cell">
                        <div class="flex-row">
                            <button class="btn-icon action-save" onclick="window.saveRowChanges(this, '${rowId}')" title="Guardar"><i class="ph ph-check" style="color: var(--accent-green); font-weight: bold;"></i></button>
                            <button class="btn-icon action-cancel" onclick="window.cancelEditRow('${rowId}')" title="Cancelar"><i class="ph ph-x" style="color: var(--accent-red); font-weight: bold;"></i></button>
                        </div>
                    </td>`;
                } else {
                    actionsHTML = `
                    <td class="actions-cell">
                        <div class="flex-row">
                            <button class="btn-icon action-edit" title="Editar Fila" onclick="window.enableEditRow(this, '${rowId}')"><i class="ph ph-pencil-simple"></i></button>
                            <button class="btn-icon action-history" title="Ver Fecha Creación" onclick="window.showRowHistory('${row.created_at}', '${rowId}')"><i class="ph ph-clock"></i></button>
                            <button class="btn-icon action-delete" title="Eliminar Fila" onclick="window.confirmDeleteRow('${rowId}')"><i class="ph ph-trash" style="color: var(--accent-red);"></i></button>
                        </div>
                    </td>`;
                }
            }

            return `<tr id="row-${rowId}" data-id="${rowId}">${cellsHTML}${gainHTML}${actionsHTML}</tr>`;
        }).join('');
    }
}


window.toggleRowCurrency = async (btn, rowId, type, currentCurr) => {
    const targetCurr = (currentCurr === 'ARS') ? 'USD' : 'ARS';
    const input = btn.nextElementSibling;
    let val = parseFloat(input.value);

    if (isNaN(val)) return;

    const originalText = btn.textContent;
    btn.textContent = "...";

    try {
        const res = await fetch('/api/table/get-rate.php');
        const rateData = await res.json();

        // LÓGICA DE PROMEDIO (Simétrica)
        let rate = 0;

        // Si la API ya devuelve avg, usalo. Si no, calculalo.
        if (rateData.avg) {
            rate = parseFloat(rateData.avg);
        } else if (rateData.buy && rateData.sell) {
            rate = (parseFloat(rateData.buy) + parseFloat(rateData.sell)) / 2;
        } else {
            rate = parseFloat(rateData.sell || 1200);
        }

        let newVal = val;

        if (currentCurr === 'ARS' && targetCurr === 'USD') {
            newVal = val / rate;
        } else if (currentCurr === 'USD' && targetCurr === 'ARS') {
            newVal = val * rate;
        }

        // VISUALIZACIÓN: Si es chico, mostrar hasta 6 decimales. Si es grande, 2.
        if (Math.abs(newVal) < 10 && newVal !== 0) {
            // Cortamos ceros innecesarios (ej: 0.005000 -> 0.005)
            input.value = parseFloat(newVal.toFixed(6));
        } else {
            input.value = newVal.toFixed(2);
        }

        btn.textContent = targetCurr;

        // Reconfigurar botón para permitir volver
        btn.onclick = () => window.toggleRowCurrency(btn, rowId, type, targetCurr);

        // Guardar metadata para el saveRowChanges
        input.dataset.newCurrency = targetCurr;
        input.dataset.currencyType = type;

    } catch(e) {
        console.error(e);
        btn.textContent = originalText;
        pop_ups.error("Error obteniendo cotización");
    }
};

// --- FUNCIONES DE ACCIONES DE LA TABLA ---

window.showRowHistory = (dateString, id) => {
    pop_ups.info(`Este registro (ID: ${id}) fue creado el: <br><b>${dateString}</b>`, "Historial de Creación");
};

window.confirmDeleteRow = async(id) => {
    const confirmado = await pop_ups.confirm(
        'Eliminar Registro',
        '¿Estás seguro de que deseas eliminar esta fila permanentemente?'
    );

    if (!confirmado) {
        return;
    }

    try {
        const response = await fetch('/api/table/delete-row.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id: id })
        }).then(res => res.json());

        if (response.success) {
            pop_ups.info("Registro eliminado.", "Info.");
            const idx = allData.findIndex(r => (r.id ?? r.Id ?? r.ID) == id);
            if (idx > -1) {
                const item = allData[idx];
                const originalIdx = originalData.indexOf(item);
                if (originalIdx > -1) originalData.splice(originalIdx, 1);

                allData.splice(idx, 1);
            }

            checkCriticalStatus();
            filterTable();
        } else {
            throw new Error(response.message || "No se pudo eliminar.");
        }
    } catch (error) {
        pop_ups.error("Error al eliminar: " + error.message, "Error");
    }
}

window.toggleActionsColumn = () => {
    isActionsHidden = !isActionsHidden; // Invertir estado

    // Manejo de la Pestaña Flotante
    const tab = document.getElementById('restore-actions-tab');
    if (tab) {
        if (isActionsHidden) {
            tab.classList.remove('hidden'); // Mostrar pestaña
        } else {
            tab.classList.add('hidden');    // Ocultar pestaña
        }
    }

    filterTable(); // Re-renderizar la tabla
};

// --- FUNCIÓN DE ORDENAMIENTO (NUEVA) ---
window.handleSort = (column) => {
    // 1. Calcular nuevo estado
    if (currentSort.column === column) {
        currentSort.state = (currentSort.state + 1) % 3; // Ciclo 0 -> 1 -> 2 -> 0
    } else {
        currentSort.column = column;
        currentSort.state = 1; // Si es nueva columna, empieza Ascendente
    }

    // 2. Aplicar Orden
    if (currentSort.state === 0) {
        // Estado 0: Volver al orden original (copia de seguridad)
        allData = [...originalData];
    } else {
        // Estado 1 (Asc) o 2 (Desc)
        const direction = currentSort.state === 1 ? 1 : -1;

        allData.sort((a, b) => {
            let valA = a[column] ?? '';
            let valB = b[column] ?? '';

            // Detectar si son números para ordenar correctamente (1, 2, 10 en vez de 1, 10, 2)
            const numA = parseFloat(valA);
            const numB = parseFloat(valB);

            // Si ambos son números válidos, comparamos matemáticamente
            if (!isNaN(numA) && !isNaN(numB) && valA !== '' && valB !== '') {
                return (numA - numB) * direction;
            }

            // Si no, comparamos como texto (case insensitive)
            return String(valA).toLowerCase().localeCompare(String(valB).toLowerCase()) * direction;
        });
    }

    // 3. Refrescar tabla (respetando filtros de búsqueda si los hay)
    filterTable();
};

window.enableEditRow = (btn, id) => {
    editingRowId = id;
    const filterableColumns = currentTableColumns.filter(c => c.toLowerCase() !== 'created_at');
    renderTable(filterableColumns, allData);
};

/* =========================================
   2. GUARDAR CAMBIOS
   ========================================= */
window.saveRowChanges = async (btn, id) => {
    // 1. Localizar la fila
    let row = document.querySelector(`tr[data-id="${id}"]`);
    if (!row && btn) row = btn.closest('tr');

    if (!row) {
        pop_ups.error("Error interno: No se encuentra la fila.");
        return;
    }

    // 2. Recolectar datos
    // Buscamos por la clase 'editing-input' que pusimos en renderTable
    const inputs = row.querySelectorAll('.editing-input');
    const updates = {};
    const metaUpdates = {};
    let hasData = false;

    inputs.forEach(input => {
        // Intentamos leer data-column (nuevo estándar) o data-col (viejo)
        const colName = input.dataset.column || input.dataset.col;
        const val = input.value;

        // --- FILTRO DE SEGURIDAD ---
        // Si colName es undefined, vacío, o la cadena literal "undefined", LO SALTAMOS.
        if (!colName || colName === 'undefined' || colName === 'null') {
            console.warn("Input ignorado por falta de nombre de columna:", input);
            return;
        }

        // Guardamos dato
        updates[colName] = val;
        hasData = true;

        // Guardamos metadata de moneda (si hubo cambio)
        if (input.dataset.newCurrency) {
            const type = input.dataset.currencyType;
            const key = (type === 'sale') ? '_meta_currency_sale' : '_meta_currency_buy';
            metaUpdates[key] = input.dataset.newCurrency;
        }
    });

    if (!hasData) {
        pop_ups.warning("No se detectaron datos válidos para guardar.");
        return;
    }

    // 3. UI Feedback
    const originalContent = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="ph ph-spinner ph-spin"></i>';

    // 4. Enviar al Backend
    try {
        const payload = {
            id: id,
            inventory_id: activeInventoryId,
            data: updates,
            meta: metaUpdates
        };

        const response = await fetch('/api/table/update-row.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (result.success) {
            pop_ups.success("Guardado correctamente");
            editingRowId = null;
            await loadTableData();
        } else {
            throw new Error(result.message || "Error al guardar");
        }
    } catch (error) {
        console.error("Error Fetch:", error);
        pop_ups.error("Error al guardar: " + error.message);
    } finally {
        if(btn) {
            btn.disabled = false;
            btn.innerHTML = originalContent;
        }
    }
}

window.cancelEditRow = (id) => {
    editingRowId = null;
    filterTable();
};


// Función auxiliar para moneda
function formatCurrency(value) {
    if (value === undefined || value === null || value === '') return '-';
    return `$${parseFloat(value).toFixed(2)}`;
}


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
    const searchTerm = searchInput ? searchInput.value.toLowerCase() : '';

    const filteredData = allData.filter(row => {
        if (isCriticalFilterActive) {
            let isRowCritical = false;
            if (activeFeatures.min_stock) {
                const stock = parseFloat(row[columnMapping.stock]) || 0;
                const min = parseFloat(row['min_stock']) || 0;
                if (stock <= min) isRowCritical = true;
            }
            if (!isRowCritical && activeFeatures.gain) {
                const sale = parseFloat(row['sale_price']) || 0;
                const buy = parseFloat(row['receipt_price']) || 0;
                if (sale > 0 && buy > 0 && sale < buy) isRowCritical = true;
            }
            if (!isRowCritical) return false;
        }

        if (searchTerm === '') return true;

        if (selectedSearchColumn === 'all') {
            return Object.values(row).some(value => String(value).toLowerCase().includes(searchTerm));
        } else {
            const rowKeys = Object.keys(row);
            const matchingKey = rowKeys.find(key => key.toLowerCase() === selectedSearchColumn.toLowerCase());
            if (matchingKey) return String(row[matchingKey]).toLowerCase().includes(searchTerm);
            return false;
        }
    });

    // Renderizar
    const filterableColumns = currentTableColumns.filter(col => col.toLowerCase() !== 'created_at');
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

            // Lógica específica para Notificaciones
            if (targetView === 'notifications') {
                loadNotifications();
            }

            // Lógica específica para Ventas (CONECTANDO TU NUEVO MÓDULO)
            if (targetView === 'sales') {
                // Llamamos al init() del módulo que creamos en el Paso 2
                salesModuleInstance.init();
            }

            if (targetView === 'receipts') { // 'receipts' es el ID que usa el botón del menú
                purchaseModuleInstance.init();
            }

            // Lógica existente de ventas/compras...
            if (targetView === 'customers') {
                customerModuleInstance.init();
            }

            if (targetView === 'providers') {
                providerModuleInstance.init();
            }

            if (targetView === 'employees') {
                employeeModuleInstance.init();
            }

            if (targetView === 'analysis') {
                analyticsModuleInstance.init();
                injectCashButtonIntoAnalytics();
            }

            if (targetView === 'payments') {
                paymentsModuleInstance.init();
            }

            if (targetView) {
                showDashboardView(targetView);
            } else {
                if(typeof pop_ups !== 'undefined') pop_ups.info("Funcionalidad aún no implementada.");
            }
        });
    });
    console.log("Navegación del menú lateral configurada.");
}

/* =========================================================
   BOTÓN "CIERRE DE CAJA" TAMBIÉN EN ANALÍTICAS (PC)
   Reusa el modal #mobile-balance-modal ya existente
========================================================= */

// Fallback: si por algún motivo NO existieran, definimos open/close básicos
if (typeof window.openCashBalance !== 'function') {
    window.openCashBalance = () => {
        const modal = document.getElementById('mobile-balance-modal');
        if (!modal) return;

        modal.classList.remove('hidden');
        // opcional: bloquear scroll del fondo
        document.documentElement.style.overflow = 'hidden';
        document.body.style.overflow = 'hidden';

        // si tu app ya tiene esta función en otro archivo, la usamos
        if (typeof window.loadBalanceData === 'function') {
            window.loadBalanceData('today');
        }
    };
}

if (typeof window.closeCashBalance !== 'function') {
    window.closeCashBalance = () => {
        const modal = document.getElementById('mobile-balance-modal');
        if (!modal) return;

        modal.classList.add('hidden');
        document.documentElement.style.overflow = '';
        document.body.style.overflow = '';
    };
}

function injectCashButtonIntoAnalytics() {
    const analysis = document.getElementById('analysis');
    if (!analysis) return;

    // Evitar duplicarlo
    if (analysis.querySelector('#btn-cash-balance-desktop')) return;

    // Esperamos a que el módulo de analíticas renderice su HTML
    setTimeout(() => {
        if (analysis.querySelector('#btn-cash-balance-desktop')) return;

        // En tu analytics.js (por lo que venías usando) suele existir .table-header
        const header = analysis.querySelector('.table-header') || analysis;

        const btn = document.createElement('button');
        btn.id = 'btn-cash-balance-desktop';
        btn.className = 'btn btn-outline btn-xs cash-corner-btn';
        btn.type = 'button';
        btn.innerHTML = `<i class="ph ph-money"></i> Cierre de caja`;
        btn.style.marginLeft = '10px';

        btn.addEventListener('click', () => {
            window.openCashBalance();
        });

        header.appendChild(btn);
    }, 0);
}

function setupAccordion() {
    console.log("[INIT] Configurando acordeones inteligentes...");
    const headers = document.querySelectorAll('.accordion-header');

    try{
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
    } catch (error) {
        console.error(error);
    }

}

async function setupFeatures() {
    try {
        getReloadVariables();
        setupOrderBy();

        await setupInventoryPicker();
        await setupRecomendedColumns();
        await setupInventoryInfoBtn();

    } catch (error) {
        console.error("Error en la inicialización de funcionalidades:", error);
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
    // Buscar la fila (Soporte para tr.editing-row o tr normal en edición)
    const row = button.closest('tr');
    if (!row) return;

    // Obtener ID (Soporte para data-itemId o data-id)
    const itemId = row.dataset.itemId || row.dataset.id;

    const dataToUpdate = {};
    const metaUpdates = {}; // Para monedas (ARS/USD)
    let allInputsValid = true;

    // Usamos el selector corregido: input[data-column]
    const inputs = row.querySelectorAll('input[data-column]');

    if (inputs.length === 0) {
        console.warn("No se encontraron inputs editables en la fila", row);
        return;
    }

    inputs.forEach(input => {
        const colName = input.dataset.column;
        const value = input.value;

        // Validación básica de números
        if (input.type === 'number' && isNaN(parseFloat(value)) && value !== '') {
            allInputsValid = false;
        }

        dataToUpdate[colName] = value;

        // Capturar cambios de moneda (_meta_currency)
        if (input.dataset.newCurrency) {
            const type = input.dataset.currencyType; // 'sale' o 'buy'
            const key = (type === 'sale') ? '_meta_currency_sale' : '_meta_currency_buy';
            metaUpdates[key] = input.dataset.newCurrency;
        }
    });

    if (!allInputsValid) {
        pop_ups.warning("Verificá que todos los campos numéricos tengan un número válido.", "¡Cuidado!");
        return;
    }

    // Feedback visual
    button.disabled = true;
    const originalContent = button.innerHTML;
    button.innerHTML = '<i class="ph ph-spinner ph-spin"></i>';

    try {
        // Enviamos data y meta (si existe)
        const payload = {
            id: itemId, // Aseguramos enviar el ID en el cuerpo también
            ...dataToUpdate,
            ...metaUpdates
        };

        // NOTA: Tu API espera (itemId, dataToUpdate). Si necesitas enviar meta,
        // revisa si api.updateTableRow soporta un 3er argumento o si debes meterlo en dataToUpdate.
        // Asumo envío estándar según tu código original:
        const result = await api.updateTableRow(itemId, payload);

        if (result.success) {
            // Actualizar datos locales
            if (result.updatedItem) {
                const rowIndex = allData.findIndex(r => (r.id ?? r.Id ?? r.ID) == itemId);
                if (rowIndex > -1) {
                    allData[rowIndex] = result.updatedItem;
                }
            }

            pop_ups.success("Guardado correctamente");
            editingRowId = null;

            // Recargar tabla manteniendo filtros
            const filterableColumns = currentTableColumns.filter(
                col => col.toLowerCase() !== 'created_at'
            );
            renderTable(filterableColumns, allData);

        } else {
            throw new Error(result.message || "Error desconocido");
        }
    } catch (error) {
        console.error(error);
        pop_ups.error(`Error al guardar: ${error.message}`);
    } finally {
        button.disabled = false;
        button.innerHTML = originalIcon || '<i class="ph ph-check"></i>'; // Fallback icon
    }
}

function handleCancelClick() {
    editingRowId = null;
    renderTable(currentTableColumns, allData);
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

    // --- NUEVO: Bloquear Scroll del Body ---
    document.body.style.overflow = 'hidden';
}

function closeDeleteModal() {
    if (deleteModal)
        deleteModal.classList.add('hidden');
    document.body.style.overflow = '';
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

    const messageLoading = document.getElementById('column-list-status');

    // Columnas protegidas siempre
    const protectedCols = ['id', 'created_at', 'updated_at'];
    // Columnas protegidas si están activas (Features)
    if (activeFeatures.min_stock) protectedCols.push('min_stock');

    // Filtramos para mostrar solo las gestionables
    const manageableColumns = currentTableColumns.filter(
        col => !protectedCols.includes(col.toLowerCase())
    );

    columnListContainer.innerHTML = manageableColumns.map(colName => {
        return `
        <div class="column-item" data-column-name="${colName}">
            <span>${formatColumnName(colName)}</span>
            <div class="column-actions">
                <button class="btn btn-secondary btn-sm rename-col-btn">Renombrar</button>
                <button class="btn btn-danger-secondary btn-sm drop-col-btn">Eliminar</button>
            </div>
        </div>`;
    }).join('');

    messageLoading.style.display = 'none';
}

// Manejador del Click en el Ojo
columnListContainer?.addEventListener('click', (e) => {
    const btn = e.target.closest('.visibility-btn');
    if (btn) {
        const colItem = btn.closest('.column-item');
        handleToggleVisibility(colItem.dataset.columnName);
    } else if (e.target.classList.contains('drop-col-btn')) {
        handleDropColumn(e);
    } else if (e.target.classList.contains('rename-col-btn')) {
        handleRenameColumn(e);
    }
});

async function handleToggleVisibility(columnName) {
    try {
        const response = await api.manageTableColumn('toggle_visibility', { columnName });
        if (response.success) {
            await loadTableData(); // Recargar para actualizar vista
        } else {
            throw new Error(response.message);
        }
    } catch (error) {
        pop_ups.error("No se pudo cambiar visibilidad: " + error.message);
    }
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

// dashboard.js - Función loadNotifications
async function loadNotifications() {
    const listContainer = document.getElementById('notifications-list');
    if (!listContainer) return;

    listContainer.innerHTML = '<p>Cargando historial...</p>';

    try {
        // Agregamos un timestamp para evitar cache de la API
        const response = await fetch(`/api/notifications/get.php?t=${new Date().getTime()}`);
        const data = await response.json();

        if (!data.success) throw new Error(data.message);
        if (data.notifications.length === 0) {
            listContainer.innerHTML = '<p>No tenés notificaciones en este inventario.</p>';
            return;
        }

        const groups = {};
        data.notifications.forEach(n => {
            const dateLabel = getRelativeDateGroup(n.created_at);
            if (!groups[dateLabel]) groups[dateLabel] = [];
            groups[dateLabel].push(n);
        });

        let html = '';

        for (const [label, items] of Object.entries(groups)) {
            html += `<h3 class="notification-date-header">${label}</h3>`;
            items.forEach(n => {
                const config = notificationConfig[n.type];

                let icon, color;

                if (config) {
                    // TIPO SISTEMA (Warning, Info, Success reales)
                    // Usamos la configuración tal cual
                    icon = config.icon;
                    color = config.color;
                } else {
                    // TIPO MANUAL (Es un color, ej: 'var(--accent-green)' o '#ff0000')
                    // Forzamos el ícono de nota y usamos el tipo como color
                    icon = 'ph-note';
                    color = n.type;
                }

                // CORRECCIÓN CLAVE: Agregamos style="color: ${color}" DIRECTAMENTE al ícono
                html += `
                <div class="toast-notification show" 
                     style="--toast-color: ${color}; position: relative; margin-bottom: 1rem; max-width: 100%; border-left: 4px solid ${color};"
                     data-notification-id="${n.id}">
                    
                    <i class="toast-icon ph ${icon}" style="color: ${color}; font-size: 1.5rem;"></i>
                    
                    <div class="toast-content">
                        <strong class="toast-title" style="color: ${color}">${n.title}</strong>
                        <p class="toast-message">${n.message || ''}</p>
                        <small style="color: var(--color-gray); font-size: 0.8rem; margin-top: 5px; display: block;">
                            ${new Date(n.created_at).toLocaleString('es-AR', { hour: '2-digit', minute: '2-digit' })}
                        </small>
                    </div>
                    
                    <button class="toast-close-btn" title="Eliminar"><i class="ph ph-x"></i></button>
                </div>
                `;
            });
        }
        listContainer.innerHTML = html;
    } catch (error) {
        listContainer.innerHTML = `<p style="color: var(--accent-red);">Error: ${error.message}</p>`;
    }
}

/* --- AGREGAR EN dashboard.js --- */

function populateProductPicker() {
    const container = document.querySelector('#item-picker-modal .picker-list');
    if (!container) return;

    container.innerHTML = ''; // Limpiamos la lista anterior

    if (!allData || allData.length === 0) {
        container.innerHTML = '<div class="empty-state">No hay productos en el inventario.</div>';
        return;
    }

    // Usamos el mapping para saber qué columna es qué (por si en la DB se llama 'nombre_prod' en vez de 'name')
    const nameCol = columnMapping.name || 'name';
    const stockCol = columnMapping.stock || 'stock';
    const priceCol = columnMapping.sale_price || 'sale_price';

    allData.forEach(item => {
        // Normalizamos valores
        const name = item[nameCol] || 'Sin Nombre';
        const stock = parseFloat(item[stockCol]) || 0;
        const price = parseFloat(item[priceCol]) || 0;
        const id = item.id || item.Id || item.ID;

        // Creamos el elemento HTML usando las clases que definimos en el CSS nuevo
        const itemDiv = document.createElement('div');
        itemDiv.className = `product-picker-item ${stock <= 0 ? 'disabled' : ''}`; // Deshabilitar si no hay stock
        itemDiv.dataset.id = id;
        itemDiv.dataset.json = JSON.stringify(item); // Guardamos la data para usarla al clickear

        itemDiv.innerHTML = `
            <div class="picker-item-info">
                <span class="picker-item-name">${name}</span>
                <span class="picker-item-stock ${stock <= 5 ? 'text-red' : ''}">Stock: ${stock}</span>
            </div>
            <div class="picker-item-right">
                <span class="picker-item-price">$${price.toFixed(2)}</span>
                <i class="ph-bold ph-check picker-item-check"></i>
            </div>
        `;

        // Evento Click: Seleccionar producto
        itemDiv.addEventListener('click', () => {
            if (stock <= 0) return; // Evitar click en sin stock

            // Toggle de selección visual
            itemDiv.classList.toggle('selected');

            // Habilitar botón de confirmar si hay al menos uno seleccionado
            const hasSelection = container.querySelectorAll('.product-picker-item.selected').length > 0;
            const confirmBtn = document.querySelector('#item-picker-modal .picker-confirm-btn');
            if(confirmBtn) confirmBtn.disabled = !hasSelection;
        });

        container.appendChild(itemDiv);
    });
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



async function createEditableRow(columns) {
    const tr = document.createElement('tr');
    tr.classList.add('editing-row');

    // 1. Defaults de Precios
    let inventoryDefaults = {};
    try {
        const res = await api.getCurrentInventoryDefaults();
        if(res.success) inventoryDefaults = res;
    } catch(e) {}

    // 2. Iteramos las columnas VISUALES
    columns.forEach(col => {
        const td = document.createElement('td');
        const colName = col;
        const colLower = col.toLowerCase();

        // --- A. Columnas de Sistema (Solo lectura - Guion) ---
        if (['id', 'created_at', 'updated_at', 'ganancia', 'percentage_gain', 'hard_gain'].includes(colLower)) {
            td.textContent = '-';
            td.style.textAlign = 'center';
            td.style.color = '#ccc';
            td.style.verticalAlign = 'middle';
            tr.appendChild(td);
            return;
        }

        // --- B. Definir Inputs ---
        let inputType = 'text';
        let align = 'left';
        let val = '';
        let extraAttrs = '';

        // Lógica Numérica
        if (columnMapping.stock === colName || ['stock', 'min_stock', 'cantidad', 'amount'].includes(colLower)) {
            inputType = 'number';
            align = 'center';
            val = (colLower === 'min_stock') ? (inventoryDefaults.min_stock || 0) : 0;
        }
        else if (columnMapping.sale_price === colName || ['precio', 'price', 'costo', 'cost'].some(k => colLower.includes(k))) {
            inputType = 'number';
            align = 'left';
            extraAttrs = 'step="0.01"';
            if (colName === columnMapping.sale_price) val = inventoryDefaults.sale_price || 0;
            if (colName === columnMapping.buy_price) val = inventoryDefaults.receipt_price || 0;
        }

        // Excepción de Texto
        if (['nombre', 'name', 'categoria', 'category', 'sku', 'detalle', 'codigo'].includes(colLower)) {
            inputType = 'text';
            align = 'left';
            val = '';
            extraAttrs = '';
        }

        td.innerHTML = `<input type="${inputType}" class="form-control" value="${val}" data-column="${colName}" ${extraAttrs} style="width:100%; text-align:${align}">`;
        tr.appendChild(td);
    });

    // 3. Celda Virtual para Ganancia (Si existe en la tabla visual)
    const headers = document.querySelectorAll('#data-table thead th');
    const hasGainHeader = Array.from(headers).some(th => th.textContent.includes('Ganancia'));

    if (hasGainHeader) {
        const tdGain = document.createElement('td');
        tdGain.textContent = '-';
        tdGain.style.textAlign = 'center';
        tdGain.style.verticalAlign = 'middle';
        tdGain.style.color = '#ccc';
        tr.appendChild(tdGain);
    }

    // 4. BOTONES DE ACCIÓN (ESTILO IDÉNTICO A EDICIÓN)
    const actionTd = document.createElement('td');
    actionTd.classList.add('actions-cell'); // Usamos la misma clase que en tu ejemplo
    actionTd.style.verticalAlign = 'middle';

    actionTd.innerHTML = `
        <div class="flex-row">
            <button class="btn-icon save-new-row-btn" title="Guardar" style="border: none; cursor: pointer;">
                <i class="ph ph-check" style="color: var(--accent-green); font-weight: bold;"></i>
            </button>
            <button class="btn-icon cancel-new-row-btn" title="Cancelar" style="border: none; cursor: pointer;">
                <i class="ph ph-x" style="color: var(--accent-red); font-weight: bold;"></i>
            </button>
        </div>`;
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


async function handleAddRowClick() {
    const tableBody = document.querySelector('#data-table tbody');
    if (!tableBody || tableBody.querySelector('.editing-row')) return;

    // Abrir columna de acciones si está cerrada para ver el botón Guardar
    if (isActionsHidden && typeof toggleActionsColumn === 'function') {
        toggleActionsColumn();
    }

    const addBtn = document.getElementById('add-row-btn');
    if(addBtn) addBtn.disabled = true;

    try {
        // CORRECCIÓN: Usamos activeDisplayColumns para respetar el orden visual (Nombre, Precio, Stock...)
        // Si por alguna razón está vacía, usamos currentTableColumns como respaldo.
        const colsToUse = (activeDisplayColumns && activeDisplayColumns.length > 0)
            ? activeDisplayColumns
            : currentTableColumns;

        const newRow = await createEditableRow(colsToUse);

        tableBody.prepend(newRow);

        newRow.querySelector('.save-new-row-btn')?.addEventListener('click', handleSaveNewRow);
        newRow.querySelector('.cancel-new-row-btn')?.addEventListener('click', handleCancelNewRow);

        // Foco en el primer input real
        const firstInput = newRow.querySelector('input:not([disabled]):not([type="hidden"])');
        if(firstInput) firstInput.focus();

    } catch (error) {
        console.error("Error:", error);
    } finally {
        if(addBtn) addBtn.disabled = false;
    }
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
        const payload = {
            data: newItemData,
            inventory_id: activeInventoryId
        }

        const response = await fetch('/api/table/add-item.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (result.success && result.newItem) {
            allData.unshift(result.newItem);
            newRowElement.remove();
            await renderTable(currentTableColumns, allData);

            if(typeof pop_ups !== 'undefined') pop_ups.success("Fila creada exitosamente.");
        } else {
            throw new Error(result.message || "Error al guardar la fila.");
        }
    } catch (error) {
        if(typeof pop_ups !== 'undefined') pop_ups.error(`Error al guardar: ${error.message}`);
        saveButton.disabled = false;
        saveButton.textContent = 'Guardar';
    }
}

// **--- INICIO: FUNCIONES AUXILIARES NECESARIAS ---**
function setupGreyBg(){
    const greyBg = document.getElementById('grey-background');
    const transactionModal = document.getElementById('transaction-picker-modal');
    const transactionContainer = document.getElementById('new-transaction-container');
    const mobileMenu = document.getElementById('mobile-menu');
    const inventoryInfoModal = document.getElementById('inventory-info-modal');
    greyBg.addEventListener('click', (event) =>{
        if (event.target === greyBg) {
            if (!mobileMenu.classList.contains('hidden')) {
                greyBg.classList.add('hidden');
                mobileMenu.classList.add('hidden');
            }
            else if(!transactionContainer.classList.contains('hidden')) {
                transactionContainer.classList.add('hidden');
                greyBg.classList.add('hidden');
                inventoryInfoModal.classList.add('hidden');
            }

        }
    })
}


// --- 4. INICIALIZACIÓN (LA ÚNICA FUNCIÓN init) ---
// --- 4. INICIALIZACIÓN (LA ÚNICA FUNCIÓN init) ---
async function init() {
    console.log("[INIT] Iniciando dashboard...");

    // 1. HEADER (LÓGICA DE NANO - RUTAS LIMPIAS Y EN INGLÉS)
    const response = await api.checkUserAdmin();
    if (!response.success){
        alert('Ha ocurrido un error interno. Será deslogeado');
        window.location.href = '/logout.php';
        return; // Agregado return para detener ejecución si falla
    }
    const isAdmin = response.isAdmin;

    console.log("[INIT] Iniciando nav-bar...");
    ui_helper.renderHeader('dashboard');
    console.log("[INIT] nav-bar iniciado.");

    // PREPARA LOS FONDOS GRISES DE LOS MODALES
    setupGreyBg();
    setupMobileMenu();

    // PREPARA EL DROPDOWN DE "MI CUENTA"
    setup.setupMiCuenta();

    // 2. CONFIGURACIÓN BÁSICA DE LA VISTA
    const tableTitleElement = document.getElementById('table-title');

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
    const deleteDbBtn = document.getElementById('delete-db-btn');

    // --- Conecto Eventos del Modal de Eliminación ---
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


    window.addEventListener('open-column-config', () => {
        const btn = document.getElementById('identify-columns-btn');
        if(btn) btn.click();

        const mainConfigBtn = document.getElementById('config-table-btn');
        if(mainConfigBtn) {
            mainConfigBtn.click();
            setTimeout(() => {
                const tabBtn = document.querySelector('[data-tab="identification"]');
                if(tabBtn) tabBtn.click();
            }, 300);
        }
    });

    const openImportBtn = document.getElementById('open-import-modal-btn');
    if (openImportBtn) {
        openImportBtn.addEventListener('click', async () => {
            let activePrefs = {};
            try {
                const response = await api.getCurrentInventoryPreferences();
                if (response.success) activePrefs = response;
            } catch (e) {
                console.warn("No se pudieron cargar preferencias para el filtro de importación.");
            }

            const systemCols = ['id', 'created_at', 'updated_at', 'user_id', 'inventory_id', 'percentage_gain', 'hard_gain'];

            const colsToMap = currentTableColumns.filter(col => {
                return !systemCols.includes(col.toLowerCase());
            });

            setStockifyColumns(colsToMap);
            openImportModal();
        });
    }

    const criticalBtn = document.getElementById('critical-filter-btn');
    if (criticalBtn) {
        criticalBtn.addEventListener('click', () => {
            isCriticalFilterActive = !isCriticalFilterActive; // Toggle

            if (isCriticalFilterActive) {
                criticalBtn.style.backgroundColor = 'var(--accent-red)';
                criticalBtn.style.color = '#fff';
            } else {
                criticalBtn.style.backgroundColor = '#fff0f0';
                criticalBtn.style.color = 'var(--accent-red)';
            }

            filterTable();
        });
    }

    document.getElementById('search-input')?.addEventListener('input', filterTable);
    document.getElementById('add-row-btn')?.addEventListener('click', handleAddRowClick);

    searchColumnBtn?.addEventListener('click', () => {
        searchColumnDropdown.classList.toggle('hidden');
    });

    searchColumnDropdown?.addEventListener('click', (e) => {
        const item = e.target.closest('.search-dropdown-item');
        if (!item) return;

        selectedSearchColumn = item.dataset.column;
        if (searchColumnBtnText) {
            searchColumnBtnText.textContent = (selectedSearchColumn === 'all')
                ? 'Todas'
                : formatColumnName(selectedSearchColumn);
        }

        searchColumnDropdown.querySelectorAll('.search-dropdown-item').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.column === selectedSearchColumn);
        });
        searchColumnDropdown.classList.add('hidden');
        filterTable();
    });

    document.addEventListener('click', (e) => {
        if (!searchColumnBtn?.contains(e.target) && !searchColumnDropdown?.contains(e.target)) {
            searchColumnDropdown?.classList.add('hidden');
        }
    });

    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.picker-btn');

        if (btn) {
            const targetId = btn.dataset.modalTarget;

            if (targetId === 'item-picker-modal') {
                showPickerModal(targetId);
                populateProductPicker();
            }
        }
    });

    // Debug Toast y Notificaciones
    // dashboard.js - Evento de crear nota
    document.getElementById('create-note-form')?.addEventListener('submit', async (e) => {
        e.preventDefault();

        const rawType = document.getElementById('note-type').value; // Ej: 'success', 'warning' o '#ff0000'
        const title = document.getElementById('note-title').value;
        const message = document.getElementById('note-message').value;

        // --- EL TRUCO ---
        // Si el usuario eligió un tipo de sistema (ej: 'success'), extraemos su COLOR.
        // Guardamos el COLOR como 'type'. Así el lector lo tratará como nota personalizada (Ícono Nota + Color).
        const config = notificationConfig[rawType];
        const finalType = config ? config.color : rawType;

        const payload = {
            type: finalType, // Enviamos 'var(--accent-green)' en vez de 'success'
            title: title,
            message: message,
            inventory_id: activeInventoryId
        };

        try {
            const response = await fetch('/api/notifications/create.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const res = await response.json();

            if (res.success) {
                e.target.reset();
                await loadNotifications();
            } else {
                pop_ups.error(res.message || "Error al guardar");
            }
        } catch (error) {
            pop_ups.error("Error de conexión al servidor");
        }
    });

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

    // 3. CARGA DE DATOS Y FEATURE SETUP
    try {
        console.log("[INIT] Cargando datos de tabla...");
        await loadTableData(); // ESTA ES LA ÚNICA LLAMADA QUE DEBE QUEDAR

        setupMenuNavigation();
        console.log("[INIT] Post setupMenuNavigation()...");
        setupAccordion();
        console.log("[INIT] Post setupAccordion()...");
        showDashboardView('view-db');
        console.log("[INIT] Post showDashboardView('view-db')...");

        setTimeout(() => {
            setupFeatures().catch(error => {
                console.error("Error en la inicialización de funciones:", error);
            });
        }, 50);

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


function renderProductList(itemList, transactionType) {
    const container = document.getElementById('product-list-container');
    const totalPriceText = document.getElementById('price-text');
    const totalPriceInput = document.getElementById('price-input');

    container.innerHTML = '';
    let total = 0;

    if (itemList.length === 0) {
        container.innerHTML = '<div class="empty-state-small">No hay productos seleccionados.</div>';
        totalPriceText.textContent = '$0.00';
        return;
    }

    itemList.forEach((item, index) => {
        const itemTotal = item.amount * item.salePrice;
        total += itemTotal;

        const row = document.createElement('div');
        row.className = 'transaction-item-row';
        row.innerHTML = `
            <div class="item-info">
                <span class="item-name">${item.name}</span>
                <span class="item-meta">${item.tableName || 'Inventario'} | Stock: ${item.stock}</span>
            </div>
            
            <div class="item-controls">
                <div class="quantity-control">
                    <button type="button" class="qty-btn minus" data-index="${index}">-</button>
                    <span class="qty-val">${item.amount}</span>
                    <button type="button" class="qty-btn plus" data-index="${index}">+</button>
                </div>
                
                <div class="item-price">
                    <span class="unit-price">$${item.salePrice} c/u</span>
                    <span class="row-total">$${itemTotal.toFixed(2)}</span>
                </div>

                <button type="button" class="btn-icon delete-item-btn" data-index="${index}">
                    <i class="ph ph-trash" style="color: var(--accent-red);"></i>
                </button>
            </div>
        `;
        container.appendChild(row);
    });

    totalPriceText.textContent = `$${total.toFixed(2)}`;
    totalPriceInput.value = total;

    // Conectar eventos de esta renderización
    connectItemEvents(itemList, transactionType);
}

function connectItemEvents(itemList, transactionType) {
    // Botón Menos
    document.querySelectorAll('.qty-btn.minus').forEach(btn => {
        btn.addEventListener('click', () => {
            const idx = btn.dataset.index;
            if (itemList[idx].amount > 1) {
                itemList[idx].amount--;
                renderProductList(itemList, transactionType);
            }
        });
    });

    // Botón Más
    document.querySelectorAll('.qty-btn.plus').forEach(btn => {
        btn.addEventListener('click', () => {
            const idx = btn.dataset.index;
            // Validar stock máximo
            if (itemList[idx].amount < itemList[idx].stock) {
                itemList[idx].amount++;
                renderProductList(itemList, transactionType);
            } else {
                pop_ups.warning(`No puedes superar el stock disponible (${itemList[idx].stock}).`);
            }
        });
    });

    // Botón Borrar
    document.querySelectorAll('.delete-item-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const idx = btn.dataset.index;
            itemList.splice(idx, 1);
            renderProductList(itemList, transactionType);
        });
    });
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


// Funciones globales para usar en onclick (por simplicidad)
window.updateCartQty = (index, delta) => {
    const item = saleCart[index];
    const newQty = item.qty + delta;

    if (newQty > item.stock) {
        pop_ups.warning(`Stock máximo alcanzado (${item.stock}).`);
        return;
    }
    if (newQty < 1) {
        removeFromCart(index);
        return;
    }
    item.qty = newQty;
    renderCart();
};


/* =========================================
   CONFIGURACIÓN RECOMENDADA (COMPLETA)
   ========================================= */
async function setupRecomendedColumns() {
    // 1. Cargar Preferencias Actuales desde el Backend
    let prefs = {};
    try {
        const res = await api.getCurrentInventoryPreferences();
        if(res.success) {
            prefs = res;
            if(res.mapping) columnMapping = res.mapping;
            if(res.features) activeFeatures = res.features;
        }
    } catch(e) { console.error("Error cargando prefs:", e); }

    // --- A. FORMULARIO DE MAPEO (Identificación de Columnas) ---
    const mapForm = document.getElementById('mapping-columns-form');
    if (mapForm) {
        const systemFields = [
            { key: 'name', label: 'Nombre del Producto', id: 'map-name-col' },
            { key: 'stock', label: 'Stock Actual', id: 'map-stock-col' },
            { key: 'buy_price', label: 'Precio de Compra', id: 'map-buy-price-col' },
            { key: 'sale_price', label: 'Precio de Venta', id: 'map-sale-price-col' }
        ];

        let html = `
            <div class="clean-mapping-layout">
                <div class="mapping-header">Campo en StockiFy</div>
                <div class="mapping-header center"></div>
                <div class="mapping-header">Tu Columna en DB</div>
        `;

        systemFields.forEach(field => {
            const currentValue = (prefs.mapping && prefs.mapping[field.key]) ? prefs.mapping[field.key] : '';

            html += `
                <div class="mapping-row">
                    <div class="db-field-wrapper">
                        <span class="db-field-name">${field.label}</span>
                    </div>
                    <div class="arrow-wrapper">
                        <i class="ph ph-arrow-right" style="color: var(--accent-color)"></i>
                    </div>
                    <div class="input-wrapper">
                        <select id="${field.id}" class="rustic-select">
                            <option value="">-- Sin Asignar --</option>
                            ${currentTableColumns.map(col => {
                // Filtramos columnas internas del sistema para que no molesten
                if(['id','created_at','min_stock'].includes(col.toLowerCase())) return '';

                // Marcamos como seleccionada si coincide con lo guardado
                const isSelected = (col.toLowerCase() === String(currentValue).toLowerCase()) ? 'selected' : '';
                return `<option value="${col}" ${isSelected}>${formatColumnName(col)}</option>`;
            }).join('')}
                        </select>
                    </div>
                </div>
            `;
        });

        html += `</div>`; // Cierre grid
        html += `
            <div class="flex-row" style="justify-content: flex-end; margin-top: 1rem;">
                <button type="submit" class="btn btn-primary" style="width: auto; padding: 8px 30px;">
                    GUARDAR REFERENCIAS
                </button>
            </div>
        `;

        mapForm.innerHTML = html;

        // Guardar Mapeo
        mapForm.onsubmit = async (e) => {
            e.preventDefault();
            const newMap = {
                name: document.getElementById('map-name-col').value,
                stock: document.getElementById('map-stock-col').value,
                buy_price: document.getElementById('map-buy-price-col').value,
                sale_price: document.getElementById('map-sale-price-col').value
            };
            const res = await api.setCurrentInventoryPreferences({ mapping: newMap });
            if(res.success) {
                columnMapping = newMap;
                pop_ups.success("Referencias guardadas correctamente.", "Éxito");
                await loadTableData();
            }
        };
    }

    // --- B. FEATURES (Alertas y Ganancias) ---
    const featForm = document.getElementById('automated-features-form');
    if (featForm) {
        const chkMin = document.getElementById('feature-min-stock');
        const chkGain = document.getElementById('feature-gain');
        const radiosGain = document.getElementsByName('gain-type');

        // Cargar estado actual de los checkbox
        if(prefs.features) {
            chkMin.checked = !!prefs.features.min_stock;
            chkGain.checked = !!prefs.features.gain;
            radiosGain.forEach(r => { if(r.value === prefs.features.gain_type) r.checked = true; });
        }

        // Lógica de importación de Min Stock
        const selImport = document.getElementById('import-min-stock-source');
        const btnImport = document.getElementById('btn-import-min-stock');

        if(selImport) {
            selImport.innerHTML = '<option value="">-- Elegir columna origen --</option>';
            currentTableColumns.forEach(col => {
                if(['id','created_at','min_stock'].includes(col.toLowerCase())) return;
                const opt = document.createElement('option');
                opt.value = col;
                opt.textContent = formatColumnName(col);
                selImport.appendChild(opt);
            });
        }

        if(btnImport) {
            btnImport.onclick = async () => {
                const source = selImport.value;
                if(!source) return pop_ups.warning("Elegí una columna.");
                const res = await api.manageTableColumn('copy_data', { source: source, target: 'min_stock' });
                if(res.success) pop_ups.success("Datos importados.");
            };
        }

        // Guardar Features
        featForm.onsubmit = async (e) => {
            e.preventDefault();
            const newFeat = {
                min_stock: chkMin.checked,
                min_stock_val: 0,
                gain: chkGain.checked,
                gain_type: document.querySelector('input[name="gain-type"]:checked')?.value || 'fixed'
            };
            const res = await api.setCurrentInventoryPreferences({ features: newFeat });
            if(res.success) {
                activeFeatures = newFeat;
                pop_ups.success("Configuración aplicada.");
                await loadTableData();
            }
        };

        // Lógica visual para desplegar opciones (Toggle)
        const setupToggle = (chk, id) => {
            const el = document.getElementById(id);
            if (!chk || !el) return;

            if(chk.checked) el.classList.add('open');
            else el.classList.remove('open'); // Asegurar estado inicial correcto

            if (!chk.dataset.toggleAttached) {
                chk.addEventListener('change', () => el.classList.toggle('open', chk.checked));
                chk.dataset.toggleAttached = 'true';
            }
        };
        setupToggle(chkMin, 'wrap-min-stock');
        setupToggle(chkGain, 'wrap-gain');
    }

    // --- C. CONVERSIÓN DE MONEDA (NUEVO MÓDULO) ---
    const currencyForm = document.getElementById('currency-conversion-form');

    // Si no existe el div en el HTML, intentamos crearlo dinámicamente al final del contenedor principal
    if (!currencyForm) {
        // Fallback por si te olvidaste de agregarlo en dashboard.php
        const parent = document.getElementById('mapping-columns-form')?.parentNode;
        if(parent) {
            const newDiv = document.createElement('div');
            newDiv.id = 'currency-conversion-form';
            newDiv.style.marginTop = '20px';
            parent.appendChild(newDiv);
            // Volvemos a llamar recursivamente para que ahora sí entre al if de abajo
            // O simplemente continuamos con la variable newDiv
        }
    }

    const targetDiv = document.getElementById('currency-conversion-form');
    if (targetDiv) {
        targetDiv.innerHTML = `
        <div class="rustic-block" style="padding-top: 20px; margin-top: 20px;">
            <div class="block-header" style="color: var(--accent-color); font-weight: bold; margin-bottom: 10px;">
                <i class="ph ph-currency-circle-dollar"></i> Conversión Masiva de Precios
            </div>
            <p style="font-size:0.9rem; color:#666; margin-bottom:15px;">
                Esta herramienta convierte <b>toda la columna</b> seleccionada de una moneda a otra (ej: de Pesos a Dólares) usando la cotización Blue del día.
                <br><small style="color: var(--accent-color);">* Todos los valores de tu tabla se verán afectados.</small>
            </p>
            
            <div class="flex-row" style="gap:15px; align-items:flex-end; background: #f9f9f9; padding: 15px; border-radius: 8px;">
                <div style="flex:1;">
                    <label class="micro-label" style="display:block; margin-bottom:5px;">Columna a Convertir</label>
                    <select id="curr-conv-col" class="rustic-select" style="width:100%;">
                        <option value="sale_price">Precio de Venta</option>
                        <option value="buy_price">Precio de Compra</option>
                    </select>
                </div>
                <div style="flex:1;">
                    <label class="micro-label" style="display:block; margin-bottom:5px;">Moneda Destino</label>
                    <select id="curr-conv-target" class="rustic-select" style="width:100%;">
                        <option value="USD">Dólar (USD)</option>
                        <option value="ARS">Pesos (ARS)</option>
                    </select>
                </div>
                <button id="btn-start-conversion" class="btn btn-primary" style="height:38px; display: flex; align-items: center; gap: 6px;">
                    <i class="ph ph-arrows-left-right"></i> Convertir
                </button>
            </div>
            <div id="conv-result" style="margin-top:10px; font-size:0.85rem; font-weight:bold; color: var(--accent-green);"></div>
        </div>
        `;

        const btnConv = document.getElementById('btn-start-conversion');
        if (btnConv) {
            btnConv.onclick = async () => {
                const colSelect = document.getElementById('curr-conv-col');
                const targetSelect = document.getElementById('curr-conv-target');

                const colType = colSelect.value;
                const targetCurr = targetSelect.value;

                // Texto amigable para la confirmación
                const colName = colSelect.options[colSelect.selectedIndex].text;

                if(!await pop_ups.confirm(
                    "¿Confirmar Conversión Masiva?",

                    `Vas a convertir TODOS los valores de "${colName}" a ${targetCurr}. Esta acción no se puede deshacer.`
                )) return;

                btnConv.disabled = true;
                btnConv.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Procesando...';

                try {
                    const rateToSend = (currentDollarRate && currentDollarRate > 0) ? currentDollarRate : 1;

                    const res = await fetch('/api/table/convert-currency.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/json'},
                        body: JSON.stringify({
                            inventory_id: activeInventoryId,
                            column_type: colType,
                            target_currency: targetCurr,
                            rate: rateToSend // <--- AGREGAMOS ESTO
                        })
                    });
                    const data = await res.json();

                    if(data.success) {
                        pop_ups.success(data.message || "Conversión finalizada.");
                        // Mostrar detalle técnico (cotización usada)
                        let rateText = "";
                        if(data.rate_used) {
                            // rate_used puede venir como objeto o float simple según tu PHP
                            const val = (typeof data.rate_used === 'object') ? data.rate_used.sell : data.rate_used;
                            rateText = `(Cotización aplicada: $${val})`;
                        }
                        document.getElementById('conv-result').textContent = `✅ Conversión exitosa. ${rateText}`;

                        await loadTableData(); // Recargar tabla para ver los cambios
                    } else {
                        pop_ups.error(data.message || "Error al convertir.");
                        document.getElementById('conv-result').textContent = '';
                    }
                } catch(e) {
                    console.error(e);
                    pop_ups.error("Error de conexión con el servidor.");
                }

                btnConv.disabled = false;
                btnConv.innerHTML = '<i class="ph ph-arrows-left-right"></i> Convertir';
            };
        }
    }
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

    const getVal = (input) => (input && input.value !== '') ? parseFloat(input.value) : 0;

    const gainDefault = getVal(gainDefaultInput);
    const minStockDefault = getVal(minStockDefaultInput);
    const salePriceDefault = getVal(salePriceDefaultInput);
    const receiptPriceDefault = getVal(receiptPriceDefaultInput);

    let auto_price_type = null;
    if (autoPrice.checked) {
        const checkedRadio = document.querySelector('input[name="price-type"]:checked');
        if(checkedRadio) auto_price_type = checkedRadio.value;
    } else {
        auto_price_type = null;
    }


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
    try {
        const user = await api.getUserProfile();
        if (!user) return;

        const response = await api.getUserVerifiedTables();

        // Debug para ver qué devuelve realmente la API
        console.log("Respuesta API Inventarios:", response);

        if (!response.success) {
            console.error("Error cargando inventarios:", response.message);
            return;
        }

        // CORRECCIÓN: Buscamos la propiedad correcta.
        // Si no es 'verifiedInventories', intentamos con 'data', 'tables' o 'inventories'.
        const inventories = response.verifiedInventories || response.inventories || response.data || [];

        if (!Array.isArray(inventories)) {
            console.error("Formato de inventarios incorrecto:", inventories);
            return;
        }

        const inventoriesDropdown = document.getElementById('inventories-dropdown');
        if (!inventoriesDropdown) return;

        inventoriesDropdown.innerHTML = ''; // Limpiar antes de llenar

        // Opción "Todos" (si la lógica lo requiere)
        // inventoriesDropdown.innerHTML = '<div class="inventory-btn" data-value="all"><h4>Todos</h4></div>';

        inventories.forEach(inventory => {
            const inventoryBtn = document.createElement('div');
            inventoryBtn.className = 'inventory-btn';
            inventoryBtn.dataset.value = inventory.id;
            // Aseguramos que 'name' exista, si no usamos un fallback
            const name = inventory.name || inventory.table_name || 'Inventario ' + inventory.id;
            inventoryBtn.innerHTML = `<h4>${name}</h4>`;
            inventoriesDropdown.appendChild(inventoryBtn);
        });

        const inventoryPicker = document.getElementById('inventory-picker');
        if (!inventoryPicker) return;

        // Delegación de eventos (más limpio que agregar listener a cada btn)
        inventoriesDropdown.addEventListener('click', (e) => {
            const btn = e.target.closest('.inventory-btn');
            if (btn) {
                // Actualizar estadísticas
                if (typeof updateDailyStatistics === 'function') {
                    updateDailyStatistics(btn.dataset.value);
                }
                // Actualizar texto del botón principal
                inventoryPicker.innerHTML = `<h4>${btn.innerText}</h4>`;

                // Cerrar dropdown
                inventoriesDropdown.classList.add('hidden');
                inventoryPicker.classList.remove('clicked');
            }
        });

        // Toggle del dropdown
        inventoryPicker.addEventListener('click', (e) => {
            e.stopPropagation();
            const isClosed = inventoriesDropdown.classList.contains('hidden');

            if (isClosed) {
                inventoriesDropdown.classList.remove('hidden');
                inventoryPicker.classList.add('clicked');
            } else {
                inventoriesDropdown.classList.add('hidden');
                inventoryPicker.classList.remove('clicked');
            }
        });

        // Cerrar al hacer clic fuera
        window.addEventListener('click', (event) => {
            if (!inventoryPicker.contains(event.target) && !inventoriesDropdown.contains(event.target)) {
                inventoriesDropdown.classList.add('hidden');
                inventoryPicker.classList.remove('clicked');
            }
        });

    } catch (error) {
        console.error("Error fatal en setupInventoryPicker:", error);
    }
}
/* ---------------------- FIN DE FUNCIONES DE NANO  ---------------------- */

/* ---------------------- Gestionar columnas ---------------------- */

export async function loadTableData() {
    try {
        console.log("[loadTableData] Iniciando carga de seguridad y sincronización...");

        // 1. RESET TOTAL de variables globales
        // Esto es crucial para que la DB nueva no intente usar nombres de columnas de la anterior
        allData = [];
        originalData = [];
        currentTableColumns = [];
        columnMapping = { name: null, stock: null, sale_price: null, buy_price: null };
        activeFeatures = { min_stock: false, gain: false, gain_type: 'percent' };

        // 2. OBTENER DATOS DE LA TABLA (Backend usa $_SESSION['active_inventory_id'])
        console.log("[loadTableData] Llamando a api.getTableData...");
        const result = await api.getTableData();

        if (result && result.success === true) {
            allData = result.data || [];
            originalData = [...allData];
            currentTableColumns = result.columns || [];

            // Sincronizar IDs de inventario activo
            activeInventoryId = result.inventoryId || result.inventory_id;
            window.activeInventoryId = activeInventoryId;
            console.log("Sincronizando Inventario ID:", activeInventoryId);
            window.allData = result.data;
            console.log("Datos cargados en Móvil:", window.allData.length);

            // 3. CARGA CRÍTICA: Obtener preferencias reales guardadas para este ID
            // Hacemos esto ANTES de renderizar para que el sistema sepa cómo se llaman las columnas
            const prefs = await api.getCurrentInventoryPreferences();
            if (prefs && prefs.success) {
                columnMapping = prefs.mapping || columnMapping;
                activeFeatures = prefs.features || activeFeatures;

                if (prefs.visible_columns) {
                    window.currentUserPreferences = window.currentUserPreferences || {};
                    window.currentUserPreferences.visible_columns = prefs.visible_columns;
                }

                console.log("[loadTableData] Preferencias recuperadas con éxito.");
            }

            // 4. PREPARACIÓN DE UI
            const filterableColumns = currentTableColumns.filter(
                col => col.toLowerCase() !== 'created_at'
            );

            const tableTitle = document.getElementById('table-title');
            if (tableTitle) tableTitle.textContent = result.inventoryName || 'Inventario';

            // 5. RENDERIZADO (Ahora con los mapeos correctos ya cargados)
            console.log("[loadTableData] Renderizando tabla con mapeo activo:", columnMapping);
            await renderTable(filterableColumns, allData);

            // Actualizar componentes de gestión
            if(typeof renderColumnList === 'function') renderColumnList();
            if(typeof setupRecomendedColumns === 'function') await setupRecomendedColumns();

            checkCriticalStatus();

            // 6. RECONSTRUCCIÓN DEL BUSCADOR
            // Filtramos las columnas de precio del buscador basándonos en el mapeo cargado
            if (searchColumnDropdown) {
                searchColumnDropdown.innerHTML = `
                  <button class="search-dropdown-item ${selectedSearchColumn === 'all' ? 'active' : ''}" data-column="all">
                    <i class="ph ph-check"></i> Todas las Columnas
                  </button>`;

                const saleCol = columnMapping.sale_price ? columnMapping.sale_price.toLowerCase() : null;
                const buyCol = columnMapping.buy_price ? columnMapping.buy_price.toLowerCase() : null;
                const excludedSystemCols = ['percentage_gain', 'hard_gain', 'ganancia'];

                filterableColumns.forEach(col => {
                    const colLower = col.toLowerCase();
                    // Si la columna está mapeada como precio o es un cálculo de sistema, la excluimos
                    if (colLower === saleCol || colLower === buyCol || excludedSystemCols.includes(colLower)) {
                        return;
                    }

                    const item = document.createElement('button');
                    item.className = 'search-dropdown-item';
                    if (selectedSearchColumn === col) item.classList.add('active');
                    item.dataset.column = col;
                    item.innerHTML = `<i class="ph ph-check"></i> ${formatColumnName(col)}`;
                    searchColumnDropdown.appendChild(item);
                });
            }

            console.log("[loadTableData] Ciclo de carga completado.");

        } else {
            throw new Error(result?.message || 'Fallo en la comunicación con la API de datos.');
        }
    } catch (error) {
        console.error("🛑 ¡ERROR DE CARGA!", error);
        pop_ups.error(error.message || String(error), "Error de Sincronización");

        if (error.message.includes('No autorizado')) {
            window.location.href = '/login.php';
        } else {
            // Si el error persiste, volvemos a la selección de DB para limpiar sesión
            setTimeout(() => { window.location.href = '/select-db.php'; }, 1500);
        }
    }
}

/* =========================================
   LISTENERS GENERALES (Búsqueda, Paginación, etc.)
   ========================================= */
function setupEventListeners() {
    // 1. Buscador
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            currentSearchTerm = e.target.value;
            currentPage = 1; // Volver a primera página al buscar
            loadTableData();
        });
    }

    // 2. Selector de Filas por página
    const rowCountSelect = document.getElementById('row-count');
    if (rowCountSelect) {
        rowCountSelect.addEventListener('change', (e) => {
            rowsPerPage = parseInt(e.target.value);
            currentPage = 1;
            loadTableData();
        });
    }

    // 3. Paginación (Anterior / Siguiente)
    const prevBtn = document.getElementById('prev-page');
    const nextBtn = document.getElementById('next-page');

    if (prevBtn) {
        prevBtn.addEventListener('click', () => {
            if (currentPage > 1) {
                currentPage--;
                loadTableData();
            }
        });
    }

    if (nextBtn) {
        nextBtn.addEventListener('click', () => {
            // La validación de si hay más páginas se hace visualmente en updatePagination
            // pero aquí intentamos avanzar y loadTableData manejará los límites
            currentPage++;
            loadTableData();
        });
    }

    // 4. Checkbox "Seleccionar Todo" (Si existe en tu HTML estático, aunque renderTable lo suele recrear)
    const selectAll = document.getElementById('select-all-rows');
    if (selectAll) {
        selectAll.addEventListener('change', (e) => {
            const checkboxes = document.querySelectorAll('.row-select');
            checkboxes.forEach(cb => cb.checked = e.target.checked);
            // Si tenés una función para mostrar el botón de borrar masivo, llámala acá
            // toggleBulkDeleteButton();
        });
    }
}



window.loadTableData = loadTableData;

if (document.getElementById('data-table')) {
    document.addEventListener('DOMContentLoaded', init);
}

document.addEventListener('DOMContentLoaded', async () => {

    setupEventListeners();

    const ratePromise = fetch('/api/table/get-rate.php')
        .then(res => res.json())
        .catch(err => { console.warn("Fallo cotización", err); return null; });

    try {
        const rateData = await ratePromise;

        if(rateData && rateData.success && rateData.sell) {
            currentDollarRate = parseFloat(rateData.sell);
            console.log(`Cotización (Cached): $${currentDollarRate}`);
        }
    } catch(e) {
        console.error("Error crítico obteniendo cotización:", e);
    }

    await loadTableData();

    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

/* =======================================================
   GESTIÓN DE COLUMNAS (VISIBILIDAD)
   ======================================================= */

window.openColumnManager = function() {
    const modal = document.getElementById('column-manager-modal');
    const listContainer = document.getElementById('column-manager-list');
    const modalBody = modal.querySelector('.modal-body');

    if (!modal || !listContainer) return;

    // 1. Inyectar Banner Explicativo (Solo si no existe ya)
    if (!modalBody.querySelector('.info-banner')) {
        const banner = document.createElement('div');
        banner.className = 'info-banner';
        banner.innerHTML = `
            <i class="ph-fill ph-info"></i>
            <div>
                <p><strong>Personaliza tu vista.</strong><br>
                Arrastra desde los puntos para reordenar.<br>
                Desmarca la casilla para ocultar la columna.</p>
            </div>
        `;
        // Insertar al principio del body, antes de la lista
        modalBody.insertBefore(banner, listContainer);
    }

    // 2. Obtener y Ordenar Columnas (Igual que antes)
    let allCols = currentTableColumns || [];

    if (window.currentUserPreferences && Array.isArray(window.currentUserPreferences.column_order)) {
        const savedOrder = window.currentUserPreferences.column_order;
        allCols.sort((a, b) => {
            const idxA = savedOrder.indexOf(a);
            const idxB = savedOrder.indexOf(b);
            if (idxA === -1 && idxB === -1) return 0;
            if (idxA === -1) return 1;
            if (idxB === -1) return -1;
            return idxA - idxB;
        });
    }

    let visibleCols = allCols;
    if (window.currentUserPreferences && window.currentUserPreferences.visible_columns) {
        visibleCols = window.currentUserPreferences.visible_columns;
    }

    // 3. Generar HTML (NUEVO DISEÑO)
    listContainer.innerHTML = '';

    allCols.forEach(col => {
        // Filtramos columnas técnicas
        if (['created_at', 'updated_at', 'user_id', 'inventory_id'].includes(col.toLowerCase())) return;

        const isChecked = visibleCols.includes(col);
        const niceName = formatColumnName(col);

        const item = document.createElement('div');
        // Usamos la nueva clase .sortable-item
        item.className = 'sortable-item';
        item.dataset.column = col;

        // ESTRUCTURA VISUAL: [Handle] [Nombre] [Checkbox]
        item.innerHTML = `
            <div style="display:flex; align-items:center; flex-grow:1;">
                <div class="drag-handle" title="Arrastrar para mover">
                    <i class="ph-bold ph-dots-six-vertical"></i>
                </div>
                
                <span class="col-name">${niceName}</span>
            </div>
            
            <input type="checkbox" value="${col}" ${isChecked ? 'checked' : ''} 
                   style="width: 20px; height: 20px; cursor: pointer; accent-color: var(--accent-color);">
        `;

        listContainer.appendChild(item);
    });

    modal.classList.remove('hidden');

    // 4. Inicializar Sortable (Con handle específico)
    if (columnSorter) columnSorter.destroy();
    columnSorter = new Sortable(listContainer, {
        animation: 150,
        handle: '.drag-handle',
        ghostClass: 'sortable-ghost',
    });
};

window.closeColumnManager = function() {
    const modal = document.getElementById('column-manager-modal');
    if (modal) modal.classList.add('hidden');
};


window.saveColumnPreferences = async function() {
    const listContainer = document.getElementById('column-manager-list');
    // Obtenemos todos los items del modal (ya ordenados visualmente por el usuario)
    const items = listContainer.querySelectorAll('.sortable-item');

    const visibleCols = [];
    const orderedCols = [];

    items.forEach(item => {
        const colName = item.dataset.column;
        const checkbox = item.querySelector('input[type="checkbox"]');

        // Guardamos TODAS las columnas en el orden que quedaron (para saber el orden futuro)
        orderedCols.push(colName);

        // Si está marcada, la agregamos a visibles
        if (checkbox.checked) {
            visibleCols.push(colName);
        }
    });

    try {
        const response = await api.setCurrentInventoryPreferences({
            visible_columns: visibleCols,
            column_order: orderedCols // [NUEVO] Enviamos el orden
        });

        if (response.success) {
            pop_ups.success("Configuración guardada.", "Éxito");

            if (!window.currentUserPreferences) window.currentUserPreferences = {};
            window.currentUserPreferences.visible_columns = visibleCols;
            window.currentUserPreferences.column_order = orderedCols;

            window.closeColumnManager();

            // Redibujamos la tabla
            if (typeof renderTable === 'function') {
                await renderTable(currentTableColumns, allData);
            } else {
                if (window.loadTableData) window.loadTableData();
            }

        } else {
            throw new Error(response.message || 'Error al guardar');
        }
    } catch (error) {
        console.error(error);
        pop_ups.error("No se pudo guardar la configuración.", "Error");
    }
};

document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        const modal = document.getElementById('mobile-balance-modal');
        if (modal && !modal.classList.contains('hidden')) window.closeCashBalance();
    }
});

// =========================================================
// Balance modal (usable en PC también)
// =========================================================
(function () {
    function getBalanceModal() {
        // Si quedó duplicado por error, esto agarra el primero
        return document.getElementById('mobile-balance-modal');
    }

    window.openCashBalance = function () {
        const modal = getBalanceModal();
        if (!modal) {
            console.warn("No existe #mobile-balance-modal en el DOM");
            return;
        }

        // Abrir aunque el CSS mobile lo tenga oculto en desktop
        modal.classList.remove('hidden');
        modal.classList.add('is-open');

        // Fuerza visual (por si hay media queries)
        modal.style.display = 'flex';
        modal.style.position = 'fixed';
        modal.style.inset = '0';
        modal.style.zIndex = '99999';

        document.documentElement.style.overflow = 'hidden';
        document.body.style.overflow = 'hidden';

        // Cargar data
        if (typeof window.loadBalanceData === 'function') {
            window.loadBalanceData('today');
        }
    };

    window.closeCashBalance = function () {
        const modal = getBalanceModal();
        if (!modal) return;

        modal.classList.add('hidden');
        modal.classList.remove('is-open');

        // Limpia overrides
        modal.style.display = '';
        modal.style.position = '';
        modal.style.inset = '';
        modal.style.zIndex = '';

        document.documentElement.style.overflow = '';
        document.body.style.overflow = '';
    };

    // Cerrar con ESC + click afuera
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') {
            const modal = getBalanceModal();
            if (modal && !modal.classList.contains('hidden')) window.closeCashBalance();
        }
    });

    document.addEventListener('click', (e) => {
        const modal = getBalanceModal();
        if (!modal || modal.classList.contains('hidden')) return;
        if (e.target === modal) window.closeCashBalance();
    });
})();

document.addEventListener('click', (e) => {
    const modal = document.getElementById('mobile-balance-modal');
    if (!modal || modal.classList.contains('hidden')) return;

    if (e.target === modal) window.closeCashBalance();
});


window.allData = allData;
window.columnMapping = columnMapping;

window.salesModuleInstance = salesModuleInstance;
window.purchasesModule = purchaseModuleInstance;