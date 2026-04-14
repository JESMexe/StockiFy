import * as api from '../api.js';
import * as setup from "../setupMiCuentaDropdown.js";

function getNum(id) {
    const el = document.getElementById(id);
    if (!el || el.classList.contains('hidden')) {
        return 0;
    }
    const value = el.value.trim();
    return (value === '' || isNaN(parseFloat(value))) ? 0 : parseFloat(value);
}

function getUserPreferences() {
    const getActiveState = (selector) => {
        const btn = document.querySelector(`.rc-btn[data-toggle="${selector}"]`);
        return btn?.classList.contains('active') ? 1 : 0;
    };

    const isGainActive = getActiveState('gain');
    const isStockActive = getActiveState('stock');

    const gainTypeRadios = document.querySelectorAll('input[name="gain-type"]');
    let gainType = 'Porcentaje';
    gainTypeRadios.forEach(radio => {
        if (radio.checked) {
            gainType = radio.closest('label').textContent.trim();
        }
    });

    const setStockNow = document.getElementById('set-stock-now')?.checked;

    const preferences = {
        min_stock: {
            active: isStockActive,
            default: (isStockActive && setStockNow) ? getNum('stock-value') : 0
        },
        sale_price: {
            active: getActiveState('sale'),
            default: 0
        },
        receipt_price: {
            active: getActiveState('buy'),
            default: 0
        },
        percentage_gain: {
            active: isGainActive && gainType === 'Porcentaje' ? 1 : 0,
            default: 0
        },
        hard_gain: {
            active: isGainActive && gainType === 'Valor fijo' ? 1 : 0,
            default: 0
        },
        auto_price: 0,
        auto_price_type: null
    };

    return preferences;
}

function setupAccordionToggles() {
    const rcToggleHeader = document.getElementById('rc-toggle-header');
    const columnsContainer = document.getElementById('recomended-columns-form');
    const arrowIcon = rcToggleHeader.querySelector('.rc-arrow');

    rcToggleHeader.addEventListener('click', () => {
        rcToggleHeader.classList.toggle('open');

        if (columnsContainer.classList.contains('hidden')) {
            columnsContainer.classList.remove('hidden');
            if (arrowIcon) arrowIcon.style.transform = 'rotate(180deg)';
        } else {
            columnsContainer.classList.add('hidden');
            if (arrowIcon) arrowIcon.style.transform = 'rotate(0deg)';
        }
    });

    document.querySelectorAll('.rc-btn[data-toggle]').forEach(btn => {
        btn.addEventListener('click', () => {
            const active = btn.classList.toggle('active');
            const col = btn.dataset.toggle;
            const extra = document.getElementById(`${col}-extra`);
            if (extra) {
                if (active) {
                    extra.classList.remove('hidden');
                } else {
                    extra.classList.add('hidden');
                }
            }
        });
    });

    const setNow = document.getElementById('set-stock-now');
    const stockInput = document.getElementById('stock-value');
    if (setNow && stockInput) {
        setNow.addEventListener('change', () => {
            if (setNow.checked) {
                stockInput.classList.remove('hidden');
            } else {
                stockInput.classList.add('hidden');
            }
        });
    }
}

async function checkUserStatus() {
    try {
        const profileData = await api.getUserProfile();
        if (!profileData.success) {
            window.location.href = '/login';
        }
    } catch (error) {
        console.error("Error:", error);
        window.location.href = '/login';
    }
}


document.addEventListener('DOMContentLoaded', async () => {

    await checkUserStatus();

    const nav = document.getElementById('header-nav');
    if (nav) {
        nav.innerHTML = `
            <div id="dropdown-container">
                <div class="btn btn-secondary" id="mi-cuenta-btn">Mi Cuenta</div>
                <div class="flex-column hidden" id="mi-cuenta-dropdown">
                    <a href="/settings" class="btn btn-secondary">Configuración</a>
                    <a href="/settings" class="btn btn-secondary">Soporte</a>
                    <a href="/logout" class="btn btn-secondary">Cerrar Sesión</a>
                </div>
            </div>
        `;
        setup.setupMiCuenta();
    }

    setupAccordionToggles();

    const createDbForm = document.getElementById('createDbForm');
    const messageDiv = document.getElementById('message');
    const columnsInput = document.getElementById('columnsInput');

    if (!createDbForm) return;

    // --- LOGICA DE RECOMENDACIONES Y PREVIEW ---
    const btnSimple = document.getElementById('btn-tmpl-simple');
    const btnModerate = document.getElementById('btn-tmpl-moderate');
    const btnLarge = document.getElementById('btn-tmpl-large');
    const previewContainer = document.getElementById('column-preview-tags');

    const tmplSimple = "Nombre, Precio, Stock, Descripción, Categoría";
    const tmplModerate = "Nombre, Precio de Venta, Precio de Compra, Stock, SKU, Código de Barras, Descripción, Categoría General, Categoría Específica";
    const tmplLarge = "ID, SKU, Código de Barras, Nombre, Stock, Categoría General, Categoría Específica, Marca, Línea, Temporada, Precio de Venta, Precio de Compra, Material, Peso, Garantía, Ubicación, Proveedor";

    const updatePreview = () => {
        if(!previewContainer) return;
        const val = columnsInput.value;
        const cols = val.split(',').map(c => c.trim().replace(/\s+/g, ' ')).filter(c => c.length > 0);
        
        if(cols.length === 0){
            previewContainer.innerHTML = '<span style="color: #999; font-size: 0.85rem; font-style: italic;">Escribí las columnas arriba separadas por coma...</span>';
            return;
        }

        const uniqueCols = [...new Set(cols)];
        previewContainer.innerHTML = uniqueCols.map(c => `
            <span style="background: var(--accent-color-20); color: var(--accent-color); border: 1px solid var(--accent-color); border-radius: 12px; padding: 2px 10px; font-size: 0.8rem; white-space: nowrap;">
                ${c}
            </span>
        `).join('');
    };

    if (btnSimple && btnModerate && btnLarge && columnsInput) {
        btnSimple.addEventListener('click', () => { columnsInput.value = tmplSimple; updatePreview(); });
        btnModerate.addEventListener('click', () => { columnsInput.value = tmplModerate; updatePreview(); });
        btnLarge.addEventListener('click', () => { columnsInput.value = tmplLarge; updatePreview(); });
        columnsInput.addEventListener('input', updatePreview);
    }
    // ------------------------------------------

    createDbForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        const preferences = getUserPreferences();
        const dbName = document.getElementById('dbNameInput').value.trim();
        const submitButton = createDbForm.querySelector('button[type="submit"]');

        let columnList = columnsInput.value.split(',')
            .map(col => col.trim().replace(/\s+/g, ' '))
            .filter(col => col.length > 0);

        columnList = [...new Set(columnList)];

        const lowerCols = columnList.map(c => c.toLowerCase());

        if (!lowerCols.includes('stock')) columnList.unshift('stock');

        if (preferences.min_stock.active && !lowerCols.includes('min_stock')) columnList.push('min_stock');
        if (preferences.sale_price.active && !lowerCols.includes('sale_price')) columnList.push('sale_price');
        if (preferences.receipt_price.active && !lowerCols.includes('receipt_price')) columnList.push('receipt_price');

        if (preferences.percentage_gain.active && !lowerCols.includes('percentage_gain')) {
            columnList.push('percentage_gain');
        }
        if (preferences.hard_gain.active && !lowerCols.includes('hard_gain')) {
            columnList.push('hard_gain');
        }

        const finalColumns = columnList.join(',');

        if (!dbName || !finalColumns || finalColumns.toLowerCase() === "stock,name") {
            messageDiv.textContent = 'Por favor, completa el nombre y al menos una columna personalizada.';
            return;
        }

        submitButton.disabled = true;
        submitButton.textContent = 'Creando...';
        messageDiv.textContent = '';

        try {
            const result = await api.createDatabase({
                dbName: dbName,
                columns: finalColumns,
                preferences: preferences
            });

            if (result.success) {
                messageDiv.textContent = result.message + "\nSerás redirigido al panel.";

                if (result.inventory_id) {
                    window.activeInventoryId = result.inventory_id;
                }

                messageDiv.textContent = result.message + "\nRedirigiendo...";

                setTimeout(() => {
                    window.location.href = '/dashboard';
                }, 1000);
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

});