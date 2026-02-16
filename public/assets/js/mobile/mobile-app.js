/**
 * STOCKIFY MOBILE COMPANION - FINAL ROBUSTO
 * Lógica exclusiva para la versión móvil.
 */
import { pop_ups } from "../notifications/pop-up.js";

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
    console.log("📱 Iniciando transacción:", type);

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

// ... (MANTÉN AQUÍ EL CÓDIGO DE PRICE CHECKER Y CIERRE DE CAJA IGUAL QUE ANTES) ...
// Copia el resto de funciones (openMobilePriceChecker, openCashBalance, etc)
// tal cual las tenías en la versión anterior.

// --------------------------------------------------------
// 2. CONSULTOR DE PRECIOS (PRICE CHECKER)
// --------------------------------------------------------
window.openMobilePriceChecker = function() {
    const modal = document.getElementById('mobile-price-checker-modal');
    if (modal) {
        modal.classList.remove('hidden');
        const input = document.getElementById('checker-input');
        input.value = ''; input.focus();
        document.getElementById('checker-result').classList.add('hidden');
        document.getElementById('checker-error').classList.add('hidden');
        window.renderCheckerList(window.allData || []);
    }
};

window.closePriceChecker = function() {
    const modal = document.getElementById('mobile-price-checker-modal');
    if(modal) modal.classList.add('hidden');
};

window.renderCheckerList = function(products) {
    const container = document.getElementById('checker-list-container');
    if (!container) return;
    container.style.display = 'block'; container.innerHTML = '';
    if (!products || products.length === 0) { container.innerHTML = '<p style="text-align:center; padding:10px; color:#999;">No hay datos.</p>'; return; }
    const list = products.slice(0, 30);
    const map = window.columnMapping || {};
    const colName = map.name || 'nombre'; const colPrice = map.sale_price || 'preciodeventa';
    list.forEach(p => {
        const name = p[colName] || p['name'] || p['nombre'] || 'Producto';
        const price = parseFloat(p[colPrice] || p['sale_price'] || p['precio'] || 0);
        const item = document.createElement('div');
        item.style.padding = '15px'; item.style.borderBottom = '1px solid #eee'; item.style.display = 'flex'; item.style.justifyContent = 'space-between'; item.style.alignItems = 'center';
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
    const errorMsg = document.getElementById('checker-error');
    const listContainer = document.getElementById('checker-list-container');
    if(listContainer) listContainer.style.display = 'none';
    let found = directProduct;
    const data = window.allData || [];
    if (!found) {
        if (!input || !input.value.trim()) return;
        const query = input.value.toLowerCase().trim();
        const map = window.columnMapping || {}; const colName = map.name || 'nombre';
        found = data.find(p => String(p.barcode || p.codigo_barras || '').toLowerCase() === query);
        if (!found) found = data.find(p => String(p[colName] || p['nombre'] || '').toLowerCase().includes(query));
    }
    if (found) {
        const map = window.columnMapping || {};
        const colName = map.name || 'nombre'; const colSale = map.sale_price || 'preciodeventa'; const colBuy = map.buy_price || 'preciodecompra'; const colStock = map.stock || 'stock';
        document.getElementById('res-name').textContent = found[colName] || found['nombre'] || 'Producto';
        document.getElementById('res-price').textContent = new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(found[colSale] || 0);
        document.getElementById('res-stock').textContent = found[colStock] || 0;
        document.getElementById('res-cost').textContent = new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(found[colBuy] || 0);
        resultCard.classList.remove('hidden'); errorMsg.classList.add('hidden');
    } else {
        resultCard.classList.add('hidden'); errorMsg.classList.remove('hidden');
    }
};

// --------------------------------------------------------
// 3. CIERRE DE CAJA
// --------------------------------------------------------
window.openCashBalance = function() {
    const modal = document.getElementById('mobile-balance-modal');
    if(modal) { modal.classList.remove('hidden'); window.loadBalanceData('today'); }
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
        const response = await fetch(`/api/analytics/get-cash-balance.php?period=${period}`);
        const result = await response.json();
        if(result.success) {
            const data = result.data;
            incomeEl.textContent = new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(data.income);
            expenseEl.textContent = new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(data.expenses);
            totalEl.textContent = new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(data.balance);
            totalEl.className = '';
            if (data.balance >= 0) totalEl.classList.add('text-green'); else totalEl.classList.add('text-red');
            const labels = { 'today': 'Resumen de Hoy', 'month': 'Este Mes', 'year': 'Este Año' };
            dateEl.textContent = labels[period] || 'Resumen';
        } else { totalEl.textContent = "Error"; pop_ups.error("Error al cargar datos."); }
    } catch (error) { totalEl.textContent = "Error"; pop_ups.error("Error de conexión."); }
};