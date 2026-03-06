/**
 * STOCKIFY MOBILE COMPANION - FINAL ROBUSTO
 * Lógica exclusiva para la versión móvil.
 */
import { pop_ups } from "../notifications/pop-up.js";
import * as api from "../api.js";

export function initMobileApp() {
    console.log("📱 StockiFy Mobile Mode Iniciado");

    const checkerInput = document.getElementById('checker-input');
    if (checkerInput) {
        checkerInput.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') window.performPriceCheck();
        });
        checkerInput.addEventListener('input', function (e) {
            window.filterCheckerList(e.target.value);
        });
        checkerInput.addEventListener('focus', function () {
            if(!this.value) window.filterCheckerList('');
        });
    }

    const returnBtn = document.getElementById('return-btn');
    if (returnBtn) {
        returnBtn.addEventListener('click', closeMobileTransaction);
    }

    // Configurar cierre de Price Checker
    const closeChecker = document.querySelector('#mobile-price-checker-modal .close-icon');
    if(closeChecker) closeChecker.onclick = window.closePriceChecker;
}

function closeMobileTransaction() {
    // Intentamos cerrar todos los posibles modales
    const ids = ['create-sale-modal', 'create-purchase-modal', 'grey-background', 'new-transaction-container'];
    ids.forEach(id => {
        const el = document.getElementById(id);
        if(el) {
            el.classList.add('hidden');
            el.style.display = 'none';
        }
    });
}

// --------------------------------------------------------
// 1. APERTURA DE TRANSACCIONES (VENTA / COMPRA)
// --------------------------------------------------------

window.openMobileTransaction = function(type) {
    console.log("Iniciando transacción:", type);

    let attempts = 0;
    const maxAttempts = 30; // 3 segundos

    const checkModuleInterval = setInterval(() => {
        attempts++;
        let module = null;

        if (type === 'sale') module = window.salesModuleInstance;
        else if (type === 'purchase') module = window.purchasesModule || window.purchaseModuleInstance;

        if (module) {
            clearInterval(checkModuleInterval);
            console.log(`✅ Módulo ${type} conectado en intento ${attempts}`);

            try {
                // 1. Inicializar el módulo (crea el HTML si no existe)
                if (module.init && !module.isInitialized) module.init();

                // [FIX NUCLEAR] EL SECUESTRO DE VENTANA
                // Buscamos la ventana del módulo y la movemos al BODY para que sea visible
                const modalId = type === 'sale' ? 'create-sale-modal' : 'create-purchase-modal';
                const modal = document.getElementById(modalId);

                if (modal) {
                    // Si el modal está "atrapado" dentro de otro div que no sea el body, lo sacamos.
                    if (modal.parentElement !== document.body) {
                        console.warn(`🔧 Moviendo ${modalId} al body para hacerlo visible.`);
                        document.body.appendChild(modal);
                    }
                } else {
                    console.error(`⚠️ No se encontró el modal #${modalId}`);
                }

                // 2. Abrir la ventana
                setTimeout(() => {
                    if (module.openCreateModal) {
                        module.openCreateModal();
                    } else {
                        alert(`Error: El módulo ${type} no está listo.`);
                    }
                }, 50);

            } catch (err) {
                console.error(err);
                alert(`Error crítico: ${err.message}`);
            }

        } else if (attempts >= maxAttempts) {
            clearInterval(checkModuleInterval);
            if(confirm("El sistema está tardando en cargar. ¿Recargar?")) location.reload();
        }
    }, 100);
};

// NOTE:
// La versión móvil NO debe depender de dashboard.js para tener mapping/data.
// Cargamos lo necesario directamente desde la API:
// - getCurrentInventoryPreferences()  -> mapping/features del inventario activo
// - getTableData()                   -> filas del inventario activo (usa $_SESSION['active_inventory_id'])

// --------------------------------------------------------
// 2. CONSULTOR DE PRECIOS (PRICE CHECKER)
// --------------------------------------------------------
window.openMobilePriceChecker = async function() {
    const modal = document.getElementById('mobile-price-checker-modal');
    if (!modal) return;

    try {
        await ensureCheckerDataLoaded();

        modal.classList.remove('hidden');

        const input = document.getElementById('checker-input');
        input.value = '';
        input.focus();

        document.getElementById('checker-result').classList.add('hidden');
        document.getElementById('checker-error').classList.add('hidden');

        window.renderCheckerList(window.allData);
    } catch (e) {
        console.error(e);
        pop_ups.error(e.message || "Error al cargar datos");
    }
};

window.closePriceChecker = function() {
    const modal = document.getElementById('mobile-price-checker-modal');
    if(modal) modal.classList.add('hidden');
};

window.renderCheckerList = function(products) {
    const container = document.getElementById('checker-list-container');
    const checkerBody = document.getElementById('checker-body');
    if (!container) return;
    checkerBody.style.borderBottom = '2px solid #1b1b1b';
    container.style.display = 'block'; container.innerHTML = '';
    if (!products || products.length === 0) { container.innerHTML = '<p style="text-align:center; padding:10px; color:#999;">No hay datos.</p>'; return; }
    const list = products.slice(0, 30);
    const map = window.columnMapping || {};
    const colName = map.name || 'nombre'; const colPrice = map.sale_price || 'preciodeventa';
    list.forEach(p => {
        const name = p[colName] || p['name'] || p['nombre'] || 'Producto';
        const price = parseFloat(p[colPrice] || p['sale_price'] || p['precio'] || 0);
        const item = document.createElement('div');
        item.style.padding = '10px'; item.style.margin = '5px'; item.style.borderRadius = '15px'; item.style.border = '2px solid #1b1b1b'; item.style.display = 'flex'; item.style.justifyContent = 'space-between'; item.style.alignItems = 'center';
        item.innerHTML = `<div style="display:flex; flex-direction:column; gap:4px; max-width: 70%;"><span style="font-weight:600; color:#1b1b1b; font-size:1rem;">${name}</span><span style="font-size:0.8rem; color:#888;">#${p.id || '-'}</span></div><span style="color:var(--accent-green); font-weight:bold; font-size:1.1rem;">${new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(price)}</span>`;
        item.onclick = () => { document.getElementById('checker-input').value = name; window.performPriceCheck(p); };
        container.appendChild(item);
    });
};

window.filterCheckerList = function(term) {
    const data = window.allData || [];
    if (!term || term.trim() === '') { window.renderCheckerList(data); return; }
    const lowerTerm = term.toLowerCase().trim();
    const map = window.columnMapping || {}; const colName = map.name || 'nombre';
    const filtered = data.filter(p => {
        const name = String(p[colName] || p['name'] || p['nombre'] || '').toLowerCase();
        const sku = String(p.sku || '').toLowerCase();
        const barcode = String(p.barcode || p.codigo_barras || '').toLowerCase();
        return name.includes(lowerTerm) || sku.includes(lowerTerm) || barcode.includes(lowerTerm);
    });
    window.renderCheckerList(filtered);
};

window.performPriceCheck = function(directProduct = null) {
    const input = document.getElementById('checker-input');
    const resultCard = document.getElementById('checker-result');
    const checkerBody = document.getElementById('checker-body');
    const errorMsg = document.getElementById('checker-error');
    const listContainer = document.getElementById('checker-list-container');
    if(listContainer) listContainer.style.display = 'none';
    let found = directProduct;
    const data = window.allData || [];
    checkerBody.style.borderBottom = '2px solid #1b1b1b';
    if (!found) {
        if (!input || !input.value.trim()) return;
        const query = input.value.toLowerCase().trim();
        const map = window.columnMapping || {}; const colName = map.name || 'nombre';
        found = data.find(p => String(p.barcode || p.codigo_barras || '').toLowerCase() === query);
        if (!found) found = data.find(p => String(p[colName] || p['nombre'] || '').toLowerCase().includes(query));
        checkerBody.style.borderBottom = '2px solid #1b1b1b';
    }
    if (found) {
        checkerBody.style.borderBottom = 'none';
        const map = window.columnMapping || {};
        const colName = map.name || 'nombre'; const colSale = map.sale_price || 'preciodeventa'; const colBuy = map.buy_price || 'preciodecompra'; const colStock = map.stock || 'stock';
        document.getElementById('res-name').textContent = found[colName] || found['nombre'] || 'Producto';
        const saleVal = parseFloat(String(found[colSale] ?? '').replace(',', '.'));
        document.getElementById('res-price').textContent = new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(isNaN(saleVal) ? 0 : saleVal);
        document.getElementById('res-stock').textContent = found[colStock] || 0;
        const buyVal = parseFloat(String(found[colBuy] ?? '').replace(',', '.'));
        document.getElementById('res-cost').textContent = new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(isNaN(buyVal) ? 0 : buyVal);
        resultCard.classList.remove('hidden'); errorMsg.classList.add('hidden');
    } else {
        checkerBody.style.borderBottom = '2px solid #1b1b1b';
        resultCard.classList.add('hidden'); errorMsg.classList.remove('hidden');
    }
};

// --------------------------------------------------------
// 3. CIERRE DE CAJA
// --------------------------------------------------------
window.openCashBalance = function() {
    const modal = document.getElementById('mobile-balance-modal');
    if(modal) {
        modal.classList.remove('hidden');
        window.loadBalanceData('today');
    }
};
window.closeCashBalance = function() {
    document.getElementById('mobile-balance-modal').classList.add('hidden');
};
window.loadBalanceData = async function(period) {
    const totalEl = document.getElementById('balance-total');
    const incomeEl = document.getElementById('balance-income');
    const expenseEl = document.getElementById('balance-expense');
    const dateEl = document.getElementById('balance-date-label');
    document.querySelectorAll('.period-btn').forEach(btn => btn.classList.remove('active'));
    const activeBtn = document.getElementById(`btn-period-${period}`);
    if(activeBtn) activeBtn.classList.add('active');
    totalEl.innerHTML = '<i class="ph ph-spinner ph-spin"></i>';
    try {
        const url = `/api/analytics/get-cash-balance.php?period=${encodeURIComponent(period)}&_ts=${Date.now()}`;
        const response = await fetch(url, { cache: 'no-store' });

        if (!response.ok) throw new Error(`HTTP ${response.status}`);

        const result = await response.json();

        if (result.success) {
            const data = result.data;

            incomeEl.textContent = new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(data.income);
            expenseEl.textContent = new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(data.expenses);
            totalEl.textContent = new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(data.balance);

            totalEl.className = '';
            totalEl.classList.add(data.balance >= 0 ? 'text-green' : 'text-red');

            const labels = { today: 'Resumen de Hoy', month: 'Este Mes', year: 'Este Año' };
            dateEl.textContent = labels[period] || 'Resumen';
        } else {
            totalEl.textContent = "Error";
            pop_ups.error(result.message || "Error al cargar datos.");
        }
    } catch (error) {
        console.error(error);
        totalEl.textContent = "Error";
        pop_ups.error("Error de conexión.");
    }
};

async function ensureCheckerDataLoaded() {
    // 1) Mapping (preferencias del inventario activo)
    const needsMapping =
        !window.columnMapping ||
        !window.columnMapping.name ||
        !window.columnMapping.stock ||
        !window.columnMapping.sale_price;

    if (needsMapping) {
        const pref = await api.getCurrentInventoryPreferences();
        if (!pref || !pref.success) throw new Error(pref?.message || "No se pudo cargar preferencias del inventario");
        window.columnMapping = pref.mapping || {};
        window.activeFeatures = pref.features || {};
        window.activeFeatures = pref.features || {};
    }

    // Validación mínima (esto es lo que realmente necesita el Price Checker)
    const map = window.columnMapping || {};
    const required = [];
    if (!map.name) required.push('Nombre');
    if (!map.stock) required.push('Stock');
    if (!map.sale_price) required.push('Precio de Venta');
    if (required.length) {
        throw new Error(`Faltan columnas obligatorias en Configuraciones: ${required.join(', ')}`);
    }

    // buy_price no es obligatorio para listar, pero sí para mostrar "Costo".
    // Si no está, mostramos costo = 0 sin romper.

    // 2) Productos del inventario activo
    if (!window.allData || !Array.isArray(window.allData) || window.allData.length === 0) {
        const table = await api.getTableData();
        if (!table || !table.success) throw new Error(table?.message || "No se pudieron cargar productos del inventario");
        window.allData = table.data || [];
        window.activeInventoryId = table.inventoryId || table.inventory_id || null;
    }

    return true;
}