// public/assets/js/database/create-db.js
import * as api from '../api.js';
import { openImportModal, initializeImportModal, setStockifyColumns } from '../import.js';
import * as setup from "../setupMiCuentaDropdown.js";

document.addEventListener('DOMContentLoaded', async () => {
    // === Inicialización base ===
    initializeImportModal();
    await checkUserStatus();

    // === Header y menú “Mi cuenta” ===
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

    prepareRecomendedColumns();

    // === Formularios y botones ===
    const createDbForm = document.getElementById('createDbForm');
    const messageDiv = document.getElementById('message');
    const prepareImportBtn = document.getElementById('prepare-import-btn');
    const importStatusDiv = document.getElementById('import-prepared-status');

    if (!createDbForm || !prepareImportBtn) return;

    // === Botón para abrir el modal de importación ===
    prepareImportBtn.addEventListener('click', () => {
        const columnsInputValue = document.getElementById('columnsInput')?.value.trim();
        const cols = columnsInputValue
            ? columnsInputValue.split(',').map(s => s.trim()).filter(Boolean)
            : [];

        if (cols.length === 0) {
            alert("Por favor, primero definí las columnas (separadas por coma) antes de importar.");
            return;
        }

        setStockifyColumns(cols);
        openImportModal();
    });

    // === Envío del formulario principal ===
    createDbForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        const dbName = document.getElementById('dbNameInput').value.trim();
        const columns = document.getElementById('columnsInput').value.trim();
        const submitButton = createDbForm.querySelector('button[type="submit"]');
        const preferences = getUserPreferences();

        if (!dbName || !columns) {
            messageDiv.textContent = 'Por favor, completa el nombre y las columnas.';
            return;
        }

        submitButton.disabled = true;
        submitButton.textContent = 'Creando...';
        messageDiv.textContent = '';

        try {
            const result = await api.createDatabase(dbName, columns, preferences);
            if (result.success) {
                messageDiv.textContent = result.message + "\nSerás redirigido al panel.";
                setTimeout(() => {
                    window.location.href = '/dashboard.php';
                }, 2000);
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

    // === Integración con import.js ===
    window.updateImportStatus = (message) => {
        if (importStatusDiv) {
            importStatusDiv.textContent = message;
            prepareImportBtn.textContent = "Modificar Importación CSV";
        }
    };
});

// ======================================================
// ================ Funciones auxiliares ================
// ======================================================

async function checkUserStatus() {
    try {
        const profileData = await api.getUserProfile();
        if (!profileData.success) {
            window.location.href = '/index.php';
        }
    } catch (error) {
        console.error("Error:", error);
        window.location.href = '/index.php';
    }
}

// === Funciones de configuración de columnas ===
function prepareRecomendedColumns() {
    const columnsContainer = document.getElementById('recomended-columns-form');
    const openColumnasRecomendadasBtn = document.getElementById('open-columnas-recomendadas-btn');

    if (!columnsContainer || !openColumnasRecomendadasBtn) return;

    openColumnasRecomendadasBtn.addEventListener('click', () => {
        columnsContainer.classList.toggle('visible');
        openColumnasRecomendadasBtn.classList.toggle('is-rotated');
    });

    const gainCheckbox = document.getElementById('gain-input');
    const minStockCheckbox = document.getElementById('min-stock-input');
    const salePriceCheckbox = document.getElementById('sale-price-input');
    const receiptPriceCheckbox = document.getElementById('receipt-price-input');
    const autoPriceCheckbox = document.getElementById('auto-price-input');
    const autoIvaRadio = document.getElementById('auto-iva-input');
    const autoGainRadio = document.getElementById('auto-gain-input');
    const autoIvaGainRadio = document.getElementById('auto-iva-gain-input');
    const autoPriceTypeContainer = document.getElementById('auto-price-type-container');
    const autoPriceLabel = document.getElementById('auto-price-checkbox');

    function updateMinStockInput() {
        const defaultInput = document.getElementById('min-stock-default-input');
        defaultInput.disabled = !minStockCheckbox.checked;
        defaultInput.classList.toggle('visible', minStockCheckbox.checked);
        if (!minStockCheckbox.checked) defaultInput.value = "";
    }

    function updateSalePriceInput() {
        const defaultInput = document.getElementById('sale-price-default-input');
        defaultInput.disabled = !salePriceCheckbox.checked;
        defaultInput.classList.toggle('visible', salePriceCheckbox.checked);

        if (!salePriceCheckbox.checked) {
            autoPriceCheckbox.checked = false;
            autoIvaRadio.checked = false;
            autoGainRadio.checked = false;
            autoIvaGainRadio.checked = false;
            autoPriceLabel.classList.remove('visible');
            autoPriceTypeContainer.classList.remove('visible');
        } else if (receiptPriceCheckbox.checked) {
            autoPriceLabel.classList.add('visible');
        }
        updateReceiptPriceInput();
    }

    function updateReceiptPriceInput() {
        const defaultInput = document.getElementById('receipt-price-default-input');
        defaultInput.disabled = !receiptPriceCheckbox.checked;
        defaultInput.classList.toggle('visible', receiptPriceCheckbox.checked);

        if (!receiptPriceCheckbox.checked) {
            autoPriceLabel.classList.remove('visible');
            autoPriceCheckbox.checked = false;
            autoPriceTypeContainer.classList.remove('visible');
        } else if (salePriceCheckbox.checked) {
            autoPriceLabel.classList.add('visible');
        }
        updateAutoPrice();
    }

    function updateGainInput() {
        const defaultInput = document.getElementById('gain-default-input');
        const percentageRadio = document.getElementById('percentage-gain-input');
        const hardRadio = document.getElementById('hard-gain-input');
        const gainTypeContainer = document.getElementById('gain-type-container');

        const active = gainCheckbox.checked;
        defaultInput.disabled = !active;
        percentageRadio.disabled = !active;
        hardRadio.disabled = !active;

        defaultInput.classList.toggle('visible', active);
        gainTypeContainer.classList.toggle('visible', active);

        if (!active) {
            percentageRadio.checked = false;
            hardRadio.checked = false;
            defaultInput.value = "";
        } else {
            percentageRadio.checked = true;
            autoIvaRadio.checked = true;
        }
        updateAutoPrice();
    }

    function updateAutoPrice() {
        const active = autoPriceCheckbox.checked;
        autoPriceTypeContainer.classList.toggle('visible', active);

        if (active) {
            autoIvaRadio.checked = true;
            autoGainRadio.disabled = !gainCheckbox.checked;
            autoIvaGainRadio.disabled = !gainCheckbox.checked;
        } else {
            autoIvaRadio.checked = false;
            autoGainRadio.checked = false;
            autoIvaGainRadio.checked = false;
        }
    }

    // === Listeners ===
    [gainCheckbox, minStockCheckbox, salePriceCheckbox, receiptPriceCheckbox, autoPriceCheckbox]
        .forEach(cb => cb?.addEventListener('change', () => {
            updateGainInput();
            updateMinStockInput();
            updateSalePriceInput();
            updateReceiptPriceInput();
            updateAutoPrice();
        }));

    // === Inicialización inicial ===
    updateGainInput();
    updateMinStockInput();
    updateSalePriceInput();
    updateReceiptPriceInput();
    updateAutoPrice();
    document.addEventListener('DOMContentLoaded', () => {
        const form = document.getElementById('recomended-columns-form');
        const btn = document.getElementById('open-columnas-recomendadas-btn');

        if (form && btn) {
            btn.addEventListener('click', () => {
                form.classList.toggle('visible');
                btn.classList.toggle('is-rotated');
            });
        }
    });

}

// === Obtiene las preferencias del usuario ===
function getUserPreferences() {
    const getNum = id => parseFloat(document.getElementById(id)?.value) || 0;

    return {
        min_stock: {
            active: document.getElementById('min-stock-input')?.checked ? 1 : 0,
            default: getNum('min-stock-default-input')
        },
        sale_price: {
            active: document.getElementById('sale-price-input')?.checked ? 1 : 0,
            default: getNum('sale-price-default-input')
        },
        receipt_price: {
            active: document.getElementById('receipt-price-input')?.checked ? 1 : 0,
            default: getNum('receipt-price-default-input')
        },
        percentage_gain: {
            active: document.getElementById('percentage-gain-input')?.checked ? 1 : 0,
            default: getNum('gain-default-input')
        },
        hard_gain: {
            active: document.getElementById('hard-gain-input')?.checked ? 1 : 0,
            default: getNum('gain-default-input')
        },
        auto_price: document.getElementById('auto-price-input')?.checked ? 1 : 0,
        auto_price_type: document.querySelector('input[name="price-type"]:checked')?.value || null
    };
}

document.addEventListener('DOMContentLoaded', () => {
    // Acordeón
    const header = document.getElementById('rc-toggle-header');
    const content = document.querySelector('.rc-content');
    header.addEventListener('click', () => {
        header.classList.toggle('open');
        content.classList.toggle('open');
    });

    // Botones de columnas
    document.querySelectorAll('.rc-btn').forEach(btn => {
        btn.addEventListener('click', () => {
            const active = btn.classList.toggle('active');
            const col = btn.dataset.toggle;
            const extra = document.getElementById(`${col}-extra`);
            if (extra) extra.classList.toggle('hidden', !active);
        });
    });

    // Stock mínimo -> mostrar input si marca “establecer ahora”
    const setNow = document.getElementById('set-stock-now');
    const stockInput = document.getElementById('stock-value');
    if (setNow && stockInput) {
        setNow.addEventListener('change', () => {
            stockInput.classList.toggle('hidden', !setNow.checked);
        });
    }
});

