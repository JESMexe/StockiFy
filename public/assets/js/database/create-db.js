// public/assets/js/database/create-db.js
import * as api from '../api.js';
import * as setup from "../setupMiCuentaDropdown.js";

/**
 * Función auxiliar para leer valores numéricos de inputs.
 */
function getNum(id) {
    const el = document.getElementById(id);
    if (!el || el.classList.contains('hidden')) {
        return 0;
    }
    const value = el.value.trim();
    return (value === '' || isNaN(parseFloat(value))) ? 0 : parseFloat(value);
}

/**
 * LÓGICA CLAVE: Lee la configuración de preferencias.
 */
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

/**
 * SOLUCIÓN ACORDEÓN:
 * Usamos 'hidden' para garantizar que se muestre/oculte sin depender de transiciones complejas CSS.
 */
function setupAccordionToggles() {
    // 1. Lógica del Acordeón Principal
    const rcToggleHeader = document.getElementById('rc-toggle-header');
    const columnsContainer = document.getElementById('recomended-columns-form');
    const arrowIcon = rcToggleHeader.querySelector('.rc-arrow');

    rcToggleHeader.addEventListener('click', () => {
        // Alternamos la clase 'open' para estilos visuales (color, negrita, etc)
        rcToggleHeader.classList.toggle('open');

        // Alternamos la visibilidad real del contenedor
        if (columnsContainer.classList.contains('hidden')) {
            columnsContainer.classList.remove('hidden');
            // Rotamos flecha si existe
            if (arrowIcon) arrowIcon.style.transform = 'rotate(180deg)';
        } else {
            columnsContainer.classList.add('hidden');
            if (arrowIcon) arrowIcon.style.transform = 'rotate(0deg)';
        }
    });

    // 2. Lógica de los botones internos (Stock, Ganancia, etc.)
    document.querySelectorAll('.rc-btn[data-toggle]').forEach(btn => {
        btn.addEventListener('click', () => {
            const active = btn.classList.toggle('active');
            const col = btn.dataset.toggle;
            const extra = document.getElementById(`${col}-extra`);
            if (extra) {
                // Si está activo, quitamos el hidden. Si no, lo ponemos.
                if (active) {
                    extra.classList.remove('hidden');
                } else {
                    extra.classList.add('hidden');
                }
            }
        });
    });

    // 3. Lógica del checkbox "Establecer ahora"
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
            window.location.href = '/login.php';
        }
    } catch (error) {
        console.error("Error:", error);
        window.location.href = '/login.php';
    }
}


// --- INICIO DE EJECUCIÓN ---
document.addEventListener('DOMContentLoaded', async () => {

    await checkUserStatus();

    // === Header y menú “Mi cuenta” ===
    const nav = document.getElementById('header-nav');
    if (nav) {
        nav.innerHTML = `
            <div id="dropdown-container">
                <div class="btn btn-secondary" id="mi-cuenta-btn">Mi Cuenta</div>
                <div class="flex-column hidden" id="mi-cuenta-dropdown">
                    <a href="/settings.php" class="btn btn-secondary">Configuración</a>
                    <a href="/settings.php" class="btn btn-secondary">Soporte</a>
                    <a href="/logout.php" class="btn btn-secondary">Cerrar Sesión</a>
                </div>
            </div>
        `;
        setup.setupMiCuenta();
    }

    // === Inicializar Acordeón ===
    setupAccordionToggles();

    // === Formulario ===
    const createDbForm = document.getElementById('createDbForm');
    const messageDiv = document.getElementById('message');
    const columnsInput = document.getElementById('columnsInput');

    if (!createDbForm) return;

    // --- ENVÍO DEL FORMULARIO ---
    createDbForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        const preferences = getUserPreferences();
        const dbName = document.getElementById('dbNameInput').value.trim();
        const submitButton = createDbForm.querySelector('button[type="submit"]');

        // Lógica de columnas
        let columnList = columnsInput.value.split(',')
            .map(col => col.trim().toLowerCase().replace(/ /g, ''))
            .filter(col => col.length > 0);

        columnList = [...new Set(columnList)];

        // Aseguramos 'stock'
        if (!columnList.includes('stock')) columnList.unshift('stock');

        // Columnas recomendadas activas
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
                    window.location.href = '/dashboard.php';
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

    // Se eliminó la lógica del botón Prepare Import porque el botón ya no existe.
});