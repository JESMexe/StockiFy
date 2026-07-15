import { pop_ups } from '../notifications/pop-up.js?v=3.0';

// Obtener datos globales de dashboard.js para evitar doble importación que duplica eventos
const getColumnMapping = () => window.columnMapping || {};
const getAllData = () => window.allData || [];

let selectedIngredients = [];
let allCombos = [];

export function initCombos() {
    setupTabs();
    setupComboModal();
    setupAutocomplete();
    setupSearch();

    // Botón de recarga de combos
    const refreshBtn = document.getElementById('refresh-combos-btn');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', () => loadAndRenderCombos());
    }
}

// 1. Manejo de Solapas (Tabs)
function setupTabs() {
    const tabProducts = document.getElementById('tab-btn-products');
    const tabCombos = document.getElementById('tab-btn-combos');
    const sectionProducts = document.getElementById('products-view-section');
    const sectionCombos = document.getElementById('combos-view-section');

    if (!tabProducts || !tabCombos) return;

    tabProducts.addEventListener('click', () => {
        tabProducts.classList.add('active');
        tabCombos.classList.remove('active');
        sectionProducts.classList.remove('hidden');
        sectionCombos.classList.add('hidden');
    });

    tabCombos.addEventListener('click', () => {
        tabCombos.classList.add('active');
        tabProducts.classList.remove('active');
        sectionProducts.classList.add('hidden');
        sectionCombos.classList.remove('hidden');
        loadAndRenderCombos();
    });
}

// 2. Cargar y Renderizar Combos
export async function loadAndRenderCombos() {
    const tableBody = document.getElementById('combos-table-body');
    if (!tableBody) return;

    tableBody.innerHTML = `<tr><td colspan="7" style="text-align: center; padding: 2rem;"><i class="ph ph-spinner ph-spin" style="font-size: 1.5rem; color: var(--accent-violet);"></i> Cargando combos...</td></tr>`;

    try {
        const response = await fetch('/api/combos/get-all');
        const result = await response.json();

        if (result.success) {
            allCombos = result.combos || [];
            renderCombosTable(allCombos);
        } else {
            tableBody.innerHTML = `<tr><td colspan="7" style="text-align: center; color: var(--accent-red); font-weight: bold; padding: 2rem;">Error: ${result.message || 'No se pudieron cargar los combos.'}</td></tr>`;
        }
    } catch (err) {
        console.error("Error loading combos:", err);
        tableBody.innerHTML = `<tr><td colspan="7" style="text-align: center; color: var(--accent-red); font-weight: bold; padding: 2rem;">Error de conexión con el servidor.</td></tr>`;
    }
}

function renderCombosTable(list) {
    const tableBody = document.getElementById('combos-table-body');
    if (!tableBody) return;

    if (list.length === 0) {
        tableBody.innerHTML = `<tr><td colspan="7" style="text-align: center; font-style: italic; padding: 2.5rem; color: #777;">No tienes combos creados en este inventario. ¡Haz clic en "+ Nuevo Combo" para empezar!</td></tr>`;
        return;
    }

    tableBody.innerHTML = list.map(combo => {
        const ingredientsStr = combo.items.map(i => `${i.quantity}x ${i.name}`).join(', ');

        // Alerta de stock bajo si dynamic_stock es 0
        const stockStyle = combo.dynamic_stock <= 0
            ? 'color: var(--accent-red); font-weight: 900; background: var(--accent-red-20); padding: 4px 8px; border-radius: 4px; border: 1.5px solid var(--accent-red);'
            : 'font-weight: bold;';

        const profitabilityStyle = combo.price < combo.cost_price
            ? 'color: var(--accent-red); font-weight: bold;'
            : 'font-weight: bold;';

        const statusChecked = combo.is_active == 1 ? 'checked' : '';
        const isPublicVisible = (combo.public_visible == 1);
        const eyeIcon = isPublicVisible ? 'ph-fill ph-eye' : 'ph ph-eye-slash';
        const eyeColor = isPublicVisible ? 'color: var(--accent-green);' : 'color: var(--color-gray);';
        const eyeTitle = isPublicVisible ? 'Público: Visible en el catálogo' : 'Privado: Oculto del catálogo';

        return `
            <tr data-id="${combo.id}">
                <td style="text-align: left; padding-left: 15px; font-weight: 600; border: none; display: flex; align-items: center; gap: 8px;">
                    ${combo.name}
                </td>
                <td style="text-align: center; font-weight: bold; font-size: 1.05rem;">$${parseFloat(combo.price).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                <td style="text-align: center; ${profitabilityStyle}">$${parseFloat(combo.cost_price).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</td>
                <td style="text-align: left; padding-left: 15px; max-width: 250px; white-space: normal; line-height: 1.4; color: #555;">${ingredientsStr || '<span style="color:#aaa;">Sin productos</span>'}</td>
                <td style="text-align: center;">
                    <span class="combo-stock-badge" data-id="${combo.id}" style="${stockStyle} cursor: pointer; text-decoration: underline dotted;" title="Ver detalle de stock (Cuello de Botella)">
                        ${combo.dynamic_stock}
                    </span>
                </td>
                <td style="text-align: center;">
                    <label class="switch-container" style="display: inline-flex; align-items: center; cursor: pointer; margin: 0 auto;">
                        <input type="checkbox" class="toggle-combo-status-btn" data-id="${combo.id}" ${statusChecked} style="cursor: pointer; width: 18px; height: 18px;">
                    </label>
                </td>
                <td style="text-align: center;">
                    <div style="display: flex; gap: 8px; justify-content: center; align-items: center;">
                        <button type="button" class="btn-icon toggle-combo-visibility-btn" data-id="${combo.id}" title="${eyeTitle}" style="border: none; background: transparent; cursor: pointer; padding: 4px;">
                            <i class="${eyeIcon}" style="${eyeColor} font-size: 1.25rem;"></i>
                        </button>
                        <button type="button" class="btn-icon action-edit edit-combo-btn" data-id="${combo.id}" title="Editar Fila">
                            <i class="ph ph-pencil-simple"></i>
                        </button>
                        <button type="button" class="btn-icon action-delete delete-combo-btn" data-id="${combo.id}" title="Eliminar Fila">
                            <i class="ph ph-trash" style="color: var(--accent-red);"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }).join('');

    // Agregar listeners a los botones de la tabla
    tableBody.querySelectorAll('.edit-combo-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const comboId = parseInt(btn.dataset.id);
            const combo = allCombos.find(c => c.id === comboId);
            if (combo) openComboModal(combo);
        });
    });

    tableBody.querySelectorAll('.delete-combo-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const comboId = parseInt(btn.dataset.id);
            handleDeleteCombo(comboId);
        });
    });

    tableBody.querySelectorAll('.toggle-combo-status-btn').forEach(chk => {
        chk.addEventListener('change', (e) => {
            const comboId = parseInt(chk.dataset.id);
            const isChecked = chk.checked ? 1 : 0;
            handleToggleComboStatus(comboId, isChecked);
        });
    });

    tableBody.querySelectorAll('.combo-stock-badge').forEach(badge => {
        badge.addEventListener('click', (e) => {
            const comboId = parseInt(badge.dataset.id);
            const combo = allCombos.find(c => c.id === comboId);
            if (combo) showStockBottleneckDetails(combo);
        });
    });

    tableBody.querySelectorAll('.toggle-combo-visibility-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const comboId = parseInt(btn.dataset.id);
            const combo = allCombos.find(c => c.id === comboId);
            if (combo) {
                const currentVisibility = combo.public_visible == 1 ? 1 : 0;
                const newVisibility = currentVisibility === 1 ? 0 : 1;
                handleToggleComboVisibility(comboId, newVisibility);
            }
        });
    });
}

// 3. Autocompletado de Componentes
function setupAutocomplete() {
    const searchInput = document.getElementById('combo-search-products');
    const suggestionsDiv = document.getElementById('combo-suggestions');
    if (!searchInput || !suggestionsDiv) return;

    searchInput.addEventListener('input', (e) => {
        const val = e.target.value.toLowerCase().trim();
        if (!val) {
            suggestionsDiv.innerHTML = '';
            suggestionsDiv.classList.add('hidden');
            return;
        }

        const columnMapping = getColumnMapping();
        const allData = getAllData();

        const nameCol = columnMapping.name || 'name';
        const costCol = columnMapping.receipt_price || columnMapping.buy_price || 'receipt_price';
        const stockCol = columnMapping.stock || 'stock';

        // Dividir búsqueda en términos individuales
        const searchTerms = val.split(/\s+/).filter(t => t.length > 0);

        // Filtrar productos del inventario activo
        const matches = allData.filter(p => {
            const nameVal = String(p[nameCol] || '').toLowerCase();
            const codeVal = String(p.sku || p.code || p.id || '').toLowerCase();
            const searchableText = `${nameVal} ${codeVal}`;

            // Cada término de búsqueda debe coincidir en alguna parte
            return searchTerms.every(term => searchableText.includes(term));
        }).slice(0, 8);

        if (matches.length === 0) {
            suggestionsDiv.innerHTML = '<div class="suggestion-item" style="color: #888; font-style: italic; cursor: default; padding: 10px;">No se encontraron productos</div>';
        } else {
            suggestionsDiv.innerHTML = matches.map(p => `
                <div class="suggestion-item" data-id="${p.id}" style="padding: 10px; border-bottom: 1px solid #eee; cursor: pointer; font-weight: 600;">
                    ${p[nameCol]} <span style="font-size:0.75rem; color:#888;">(Stock: ${p[stockCol] || 0} | Costo: $${parseFloat(p[costCol] || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })})</span>
                </div>
            `).join('');
        }
        suggestionsDiv.classList.remove('hidden');
    });

    suggestionsDiv.addEventListener('click', (e) => {
        const item = e.target.closest('.suggestion-item');
        if (!item || !item.dataset.id) return;

        const pid = parseInt(item.dataset.id);
        const columnMapping = getColumnMapping();
        const allData = getAllData();

        const nameCol = columnMapping.name || 'name';
        const costCol = columnMapping.receipt_price || columnMapping.buy_price || 'receipt_price';
        const stockCol = columnMapping.stock || 'stock';

        const prod = allData.find(p => parseInt(p.id) === pid);
        if (prod) {
            addIngredient({
                product_id: prod.id,
                name: prod[nameCol] || `Producto #${prod.id}`,
                cost: parseFloat(prod[costCol] || 0),
                stock: parseFloat(prod[stockCol] || 0),
                quantity: 1
            });
        }

        searchInput.value = '';
        suggestionsDiv.innerHTML = '';
        suggestionsDiv.classList.add('hidden');
    });

    // Cerrar sugerencias al hacer clic afuera
    document.addEventListener('click', (e) => {
        if (!searchInput.contains(e.target) && !suggestionsDiv.contains(e.target)) {
            suggestionsDiv.classList.add('hidden');
        }
    });
}

// Agregar ingrediente al estado temporal
function addIngredient(ing) {
    // Si ya existe, no agregarlo de nuevo
    const exists = selectedIngredients.some(item => item.product_id === ing.product_id);
    if (exists) {
        pop_ups.warning("Este producto ya está agregado en el combo.");
        return;
    }

    selectedIngredients.push(ing);
    renderIngredientsList();
    updateRentabilityAlert();
}

function renderIngredientsList() {
    const listContainer = document.getElementById('combo-ingredients-list');
    if (!listContainer) return;

    if (selectedIngredients.length === 0) {
        listContainer.innerHTML = `<div style="color: #666; font-style: italic; text-align: center; padding: 15px;">Ningún producto componente agregado. Usa el buscador de arriba.</div>`;
        return;
    }

    listContainer.innerHTML = selectedIngredients.map((ing, idx) => `
        <div class="ingredient-row" data-index="${idx}">
            <span style="font-weight: 600; font-size: 0.95rem;">${ing.name} <span style="font-weight: normal; color: #888; font-size: 0.8rem;">(Costo: $${ing.cost.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })})</span></span>
            <div style="display: flex; align-items: center; gap: 10px;">
                <label style="font-size: 0.8rem; font-weight: 600;">Cant:</label>
                <input type="number" class="ingredient-qty-input" data-index="${idx}" step="1" min="1" value="${Math.round(ing.quantity) || 1}" style="width: 70px; padding: 6px; border: 2px solid #1b1b1b; border-radius: 4px; font-weight: 600; text-align: center;">
                <button type="button" class="btn-neo-brutal btn-danger remove-ingredient-btn" data-index="${idx}" style="width: 32px; height: 32px; padding: 0;">
                    <i class="ph ph-trash"></i>
                </button>
            </div>
        </div>
    `).join('');

    // Handlers
    listContainer.querySelectorAll('.ingredient-qty-input').forEach(input => {
        input.addEventListener('change', (e) => {
            const idx = parseInt(input.dataset.index);
            const val = parseInt(input.value, 10);
            if (val > 0 && !isNaN(val)) {
                selectedIngredients[idx].quantity = val;
                input.value = val; // Asegurar entero visual
                updateRentabilityAlert();
            } else {
                input.value = Math.round(selectedIngredients[idx].quantity) || 1;
            }
        });
    });

    listContainer.querySelectorAll('.remove-ingredient-btn').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const idx = parseInt(btn.dataset.index);
            selectedIngredients.splice(idx, 1);
            renderIngredientsList();
            updateRentabilityAlert();
        });
    });
}

// Control de rentabilidad en el cliente
function updateRentabilityAlert() {
    const alertDiv = document.getElementById('combo-rentability-alert');
    const alertText = document.getElementById('combo-rentability-text');
    const priceInput = document.getElementById('combo-price-input');

    if (!alertDiv || !priceInput) return;

    const salePrice = parseFloat(priceInput.value) || 0;

    // Calcular costo total
    let totalCost = 0;
    selectedIngredients.forEach(ing => {
        totalCost += ing.cost * ing.quantity;
    });

    if (selectedIngredients.length > 0 && salePrice > 0 && salePrice < totalCost) {
        const fmtSalePrice = salePrice.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        const fmtTotalCost = totalCost.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        alertText.innerHTML = `<strong>Advertencia de rentabilidad:</strong> El precio de venta propuesto ($${fmtSalePrice}) es <strong>menor</strong> que el costo total de adquisición de los componentes ($${fmtTotalCost}). Esto generará pérdidas.`;
        alertDiv.classList.remove('hidden');
        alertDiv.classList.add('danger');
    } else {
        alertDiv.classList.add('hidden');
        alertDiv.classList.remove('danger');
    }
}

// 4. Modal de Creación/Edición
function setupComboModal() {
    const modal = document.getElementById('combo-modal');
    const openBtn = document.getElementById('add-combo-btn');
    const closeBtn = document.getElementById('close-combo-modal-btn');
    const cancelBtn = document.getElementById('cancel-combo-btn');
    const form = document.getElementById('combo-form');
    const priceInput = document.getElementById('combo-price-input');

    if (!modal) return;

    if (openBtn) {
        openBtn.addEventListener('click', () => openComboModal());
    }

    if (closeBtn) closeBtn.addEventListener('click', closeComboModal);
    if (cancelBtn) cancelBtn.addEventListener('click', closeComboModal);

    if (priceInput) {
        priceInput.addEventListener('input', updateRentabilityAlert);
    }

    if (form) {
        form.addEventListener('submit', handleFormSubmit);
    }
}

function openComboModal(combo = null) {
    const modal = document.getElementById('combo-modal');
    const title = document.getElementById('combo-modal-title');
    const form = document.getElementById('combo-form');
    const idInput = document.getElementById('combo-id-input');
    const nameInput = document.getElementById('combo-name-input');
    const priceInput = document.getElementById('combo-price-input');
    const submitBtn = document.getElementById('save-combo-btn');

    if (!modal) return;

    form.reset();
    selectedIngredients = [];

    if (combo) {
        title.textContent = "Editar Combo / Promoción";
        idInput.value = combo.id;
        nameInput.value = combo.name;
        priceInput.value = combo.price;
        submitBtn.textContent = "Guardar Cambios";

        // Mapear ingredientes
        selectedIngredients = combo.items.map(item => ({
            product_id: item.product_id,
            name: item.name,
            cost: parseFloat(item.cost || 0),
            stock: parseFloat(item.stock || 0),
            quantity: parseFloat(item.quantity)
        }));
    } else {
        title.textContent = "Crear Nuevo Combo";
        idInput.value = "";
        submitBtn.textContent = "Crear Combo";
    }

    renderIngredientsList();
    updateRentabilityAlert();
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeComboModal() {
    const modal = document.getElementById('combo-modal');
    if (modal) modal.classList.add('hidden');
    document.body.style.overflow = '';
}

async function handleFormSubmit(e) {
    e.preventDefault();

    const comboId = document.getElementById('combo-id-input').value;
    const name = document.getElementById('combo-name-input').value.trim();
    const price = parseFloat(document.getElementById('combo-price-input').value);

    if (!name) {
        pop_ups.error("El nombre del combo es obligatorio.");
        return;
    }

    if (isNaN(price) || price < 0) {
        pop_ups.error("El precio debe ser un número válido igual o mayor a 0.");
        return;
    }

    if (selectedIngredients.length === 0) {
        pop_ups.error("Debes agregar al menos un producto componente al combo.");
        return;
    }

    const payload = {
        id: comboId ? parseInt(comboId) : null,
        name: name,
        price: price,
        items: selectedIngredients.map(ing => ({
            product_id: ing.product_id,
            quantity: ing.quantity
        }))
    };

    const endpoint = comboId ? '/api/combos/update' : '/api/combos/create';
    const submitBtn = document.getElementById('save-combo-btn');
    const originalText = submitBtn.textContent;

    submitBtn.disabled = true;
    submitBtn.textContent = "Guardando...";

    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const result = await response.json();

        if (result.success) {
            pop_ups.success(result.message || "Combo guardado con éxito.");
            closeComboModal();
            loadAndRenderCombos();
        } else {
            pop_ups.error(result.message || "Error al guardar el combo.");
        }
    } catch (err) {
        console.error("Error saving combo:", err);
        pop_ups.error("Error de conexión al intentar guardar.");
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    }
}

// 5. Eliminar Combo
async function handleDeleteCombo(comboId) {
    if (typeof Swal === 'undefined') {
        if (!confirm("¿Estás seguro de que deseas eliminar este combo?")) return;
        executeDelete(comboId);
        return;
    }

    const result = await Swal.fire({
        title: '¿Eliminar Combo?',
        text: 'Esta acción no se puede deshacer y el combo dejará de estar disponible para la venta.',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#1b1b1b',
        cancelButtonColor: '#d33',
        confirmButtonText: 'Sí, eliminar',
        cancelButtonText: 'Cancelar',
        customClass: {
            popup: 'swal-custom-popup',
            confirmButton: 'swal-custom-confirm-btn'
        }
    });

    if (result.isConfirmed) {
        executeDelete(comboId);
    }
}

async function executeDelete(comboId) {
    try {
        const response = await fetch('/api/combos/delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: comboId })
        });
        const result = await response.json();

        if (result.success) {
            pop_ups.success("Combo eliminado con éxito.");
            loadAndRenderCombos();
        } else {
            pop_ups.error(result.message || "No se pudo eliminar el combo.");
        }
    } catch (err) {
        console.error("Error deleting combo:", err);
        pop_ups.error("Error de conexión al intentar eliminar.");
    }
}

// 6. Activar/Desactivar Combo
async function handleToggleComboStatus(comboId, status) {
    try {
        const response = await fetch('/api/combos/toggle-active', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: comboId, is_active: status })
        });
        const result = await response.json();

        if (result.success) {
            pop_ups.success(status === 1 ? "Combo activado" : "Combo desactivado");
            // Recargar para actualizar el POS o la tabla si es necesario
            loadAndRenderCombos();
        } else {
            pop_ups.error(result.message || "No se pudo actualizar el estado del combo.");
            loadAndRenderCombos(); // Revertir visualmente recargando
        }
    } catch (err) {
        console.error("Error toggling combo status:", err);
        pop_ups.error("Error de conexión al actualizar estado.");
        loadAndRenderCombos();
    }
}

// 6.5. Cambiar visibilidad de Combo en catálogo
async function handleToggleComboVisibility(comboId, visibility) {
    try {
        const response = await fetch('/api/combos/toggle-visibility', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: comboId, public_visible: visibility })
        });
        const result = await response.json();

        if (result.success) {
            pop_ups.success(visibility === 1 ? "Combo visible en catálogo" : "Combo ocultado del catálogo");
            loadAndRenderCombos();
        } else {
            pop_ups.error(result.message || "No se pudo actualizar la visibilidad del combo.");
            loadAndRenderCombos();
        }
    } catch (err) {
        console.error("Error toggling combo visibility:", err);
        pop_ups.error("Error de conexión al actualizar visibilidad.");
        loadAndRenderCombos();
    }
}

// 7. Buscador local en la tabla de combos
function setupSearch() {
    const searchInput = document.getElementById('combos-table-search');
    if (!searchInput) return;

    searchInput.addEventListener('input', (e) => {
        const val = e.target.value.toLowerCase().trim();
        const rows = document.querySelectorAll('#combos-table-body tr');

        rows.forEach(row => {
            if (row.cells.length < 2) return; // Fila de cargando o vacía

            const comboId = parseInt(row.dataset.id);
            const combo = allCombos.find(c => c.id === comboId);
            if (!combo) return;

            const nameMatch = combo.name.toLowerCase().includes(val);
            const ingMatch = combo.items.some(i => i.name.toLowerCase().includes(val));

            if (nameMatch || ingMatch) {
                row.classList.remove('hidden');
            } else {
                row.classList.add('hidden');
            }
        });
    });
}

// 8. Ventanita de análisis de stock cuello de botella
function showStockBottleneckDetails(combo) {
    if (!combo.items || combo.items.length === 0) {
        Swal.fire({
            title: 'Análisis de Stock',
            text: 'Este combo no contiene componentes asignados.',
            icon: 'info',
            confirmButtonColor: '#1b1b1b',
            customClass: {
                popup: 'swal-custom-popup',
                confirmButton: 'swal-custom-confirm-btn'
            }
        });
        return;
    }

    let minPossible = null;
    const itemsWithPossible = combo.items.map(item => {
        const possible = Math.floor(item.stock / item.quantity);
        if (minPossible === null || possible < minPossible) {
            minPossible = possible;
        }
        return { ...item, possible };
    });

    const bottleneckItems = itemsWithPossible.filter(item => item.possible === minPossible);
    const bottleneckNames = bottleneckItems.map(item => `<strong>${item.name}</strong>`).join(', ');

    const htmlBody = `
        <div style="text-align: left; font-family: inherit; font-size: 0.95rem; line-height: 1.5; color: #1b1b1b;">
            <p style="margin-bottom: 12px;">
                El stock disponible de esta promo es de <strong>${combo.dynamic_stock}</strong>. Este número se calcula dinámicamente de acuerdo al stock actual de sus componentes (regla de cuello de botella).
            </p>
            <div style="border: 2.5px solid #1b1b1b; border-radius: 8px; background: #f9f9f9; padding: 12px; margin-bottom: 15px; box-shadow: 4px 4px 0px #1b1b1b;">
                <h4 style="margin: 0 0 8px 0; font-weight: 900; text-transform: uppercase; font-size: 0.8rem; color: #555;">Desglose de Componentes:</h4>
                <ul style="margin: 0; padding-left: 20px; font-weight: bold;">
                    ${itemsWithPossible.map(item => {
        const isLimit = item.possible === minPossible;
        const itemStyle = isLimit ? 'color: var(--accent-red);' : '';
        const limitLabel = isLimit
            ? ' <span style="background: var(--accent-red-20); color: var(--accent-red); font-size: 0.65rem; padding: 1px 5px; border-radius: 3px; border: 1px solid var(--accent-red); font-weight: 900; margin-left: 4px;">LIMITANTE</span>'
            : '';
        return `
                            <li style="margin-bottom: 4px; ${itemStyle}">
                                ${item.name}: ${item.stock} disponibles &rarr; Alcanza para <strong>${item.possible}</strong> combos (usa ${item.quantity} c/u)${limitLabel}
                            </li>
                        `;
    }).join('')}
                </ul>
            </div>
            <p style="margin: 0; padding: 10px; border-radius: 6px; border: 1.5px dashed var(--accent-color); background: var(--accent-color-20); font-weight: bold; font-size: 0.85rem; color: var(--accent-color);">
                <i class="ph ph-info" style="vertical-align: middle; margin-right: 4px;"></i>
                El componente limitador es ${bottleneckNames}.
            </p>
        </div>
    `;

    Swal.fire({
        title: `Stock de: ${combo.name}`,
        html: htmlBody,
        confirmButtonText: 'Entendido',
        confirmButtonColor: '#1b1b1b',
        customClass: {
            popup: 'swal-custom-popup',
            confirmButton: 'swal-custom-confirm-btn'
        }
    });
}
