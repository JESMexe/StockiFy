/**
 */
import { pop_ups } from "../notifications/pop-up.js?v=3.0";
import * as api from "../api.js?v=2.1";
import { getWhatsAppLink } from "../universal-functions.js";

export async function initMobileApp() {
    console.log("📱 StockiFy Mobile Mode Iniciado");
    updateMobileInventoryName();
    updateMobileDollarInfo();

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

    const closeChecker = document.querySelector('#mobile-price-checker-modal .close-icon');
    if(closeChecker) closeChecker.onclick = window.closePriceChecker;
}

async function updateMobileDollarInfo() {
    try {
        const rateData = await api.getExchangeRate();
        const priceEl = document.getElementById('mobile-dollar-price');
        const sourceEl = document.getElementById('mobile-dollar-source');
        if (priceEl && sourceEl) {
            priceEl.textContent = parseFloat(rateData.avg || rateData.buy).toFixed(2);
            
            let type = rateData.type || (rateData.source === 'manual' ? 'manual' : 'api');
            let apiSource = rateData.api_source || 'blue';
            
            let readable = 'MEP';
            if (type === 'manual') {
                readable = 'Manual';
            } else {
                let s = apiSource.toLowerCase();
                if (s.includes('blue')) {
                    readable = 'Blue';
                } else if (s.includes('oficial')) {
                    readable = 'Oficial';
                } else if (s.includes('mep') || s.includes('bolsa')) {
                    readable = 'MEP';
                } else if (s.includes('cripto')) {
                    readable = 'Cripto';
                } else if (s.includes('mayorista')) {
                    readable = 'Mayorista';
                } else if (s.includes('ccl')) {
                    readable = 'CCL';
                } else if (s.includes('tarjeta')) {
                    readable = 'Tarjeta';
                } else {
                    readable = s.charAt(0).toUpperCase() + s.slice(1);
                }
            }
            
            sourceEl.textContent = `Modo: ${readable}`;
        }
    } catch (e) {
        console.warn("No se pudo cargar la cotización en la vista móvil.", e);
        const priceEl = document.getElementById('mobile-dollar-price');
        const sourceEl = document.getElementById('mobile-dollar-source');
        if (priceEl && sourceEl) {
            priceEl.textContent = "Error";
            sourceEl.textContent = "Sin Conexión";
        }
    }
}

function closeMobileTransaction() {
    const ids = ['create-sale-modal', 'create-purchase-modal', 'grey-background', 'new-transaction-container'];
    ids.forEach(id => {
        const el = document.getElementById(id);
        if(el) {
            el.classList.add('hidden');
            el.style.display = 'none';
        }
    });
}

window.openMobileEntityList = function(type) {
    const modal = document.getElementById('mobile-entity-list-modal');
    if (!modal) return;

    const menuBtn = document.querySelector(`.menu-btn[data-target-view="${type}"]`);
    if (!menuBtn) { pop_ups.info("No tienes acceso a esta sección o no está lista."); return; }

    const configs = {
        employees: { title: 'Trabajadores', icon: 'ph-identification-card', subtitle: 'Lista del equipo' },
        providers:  { title: 'Proveedores',  icon: 'ph-truck',              subtitle: 'Lista de proveedores' },
        customers:  { title: 'Clientes',     icon: 'ph-users',              subtitle: 'Lista de clientes' },
    };
    const cfg = configs[type] || { title: 'Lista', icon: 'ph-users', subtitle: '' };

    const iconEl = document.getElementById('m-entity-icon');
    const titleEl = document.getElementById('m-entity-title');
    const subtitleEl = document.getElementById('m-entity-subtitle');
    if (iconEl) iconEl.className = `ph ${cfg.icon}`;
    if (titleEl) titleEl.textContent = cfg.title;
    if (subtitleEl) subtitleEl.textContent = cfg.subtitle;

    const sheet = modal.querySelector('.m-entity-sheet');
    if (sheet) sheet.classList.remove('closing');
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    document.getElementById('m-entity-body').innerHTML = '<div style="text-align:center;padding:50px;color:#aaa;"><i class="ph ph-circle-notch ph-spin" style="font-size:2rem;"></i></div>';

    loadMobileEntityData(type);
};

window.closeMobileEntityList = function() {
    const modal = document.getElementById('mobile-entity-list-modal');
    if (!modal) return;
    const sheet = modal.querySelector('.m-entity-sheet');
    if (sheet) {
        sheet.classList.add('closing');
        let done = false;
        const finish = () => {
            if (done) return; done = true;
            modal.classList.add('hidden');
            sheet.classList.remove('closing');
            document.body.style.overflow = '';
        };
        sheet.addEventListener('animationend', finish, { once: true });
        setTimeout(finish, 350);
    } else {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
};

window.openMobileEntityDetail = function(item, type) {
    const modal = document.getElementById('mobile-entity-detail-modal');
    if (!modal) return;

    const typeTitles = { employees: 'Empleado', providers: 'Proveedor', customers: 'Cliente' };
    const titleEl = document.getElementById('m-detail-title');
    if (titleEl) titleEl.textContent = typeTitles[type] || 'Perfil';

    const name = item.full_name || item.name || 'Desconocido';
    const initial = name.charAt(0).toUpperCase();

    document.getElementById('m-detail-avatar').textContent = initial;
    document.getElementById('m-detail-name').textContent = name;

    const catEl = document.getElementById('m-detail-cat');
    catEl.innerHTML = item.category_name
        ? `<i class="ph ph-tag"></i> ${item.category_name}`
        : `<i class="ph ph-user"></i> Sin categoría`;

    const waLink = getWhatsAppLink(item.phone);
    const waEl = document.getElementById('m-detail-wa');
    waEl.innerHTML = waLink
        ? `<a href="${waLink}" target="_blank" class="m-entity-wa-hero-btn" title="WhatsApp"><i class="ph-fill ph-whatsapp-logo"></i></a>`
        : '';

    let html = '';

    if (waLink) {
        html += `<a href="${waLink}" target="_blank" rel="noopener" class="m-entity-wa-btn"><i class="ph-fill ph-whatsapp-logo"></i> Contactar por WhatsApp</a>`;
    }

    const contactFields = [];
    if (item.dni)   contactFields.push({ icon: 'ph-identification-card',  label: 'DNI',      value: item.dni });
    if (item.phone) contactFields.push({ icon: 'ph-phone',                label: 'Teléfono', value: item.phone });
    if (item.email) contactFields.push({ icon: 'ph-envelope',             label: 'Email',    value: item.email });
    if (item.cuit)  contactFields.push({ icon: 'ph-identification-badge', label: 'CUIT',     value: item.cuit });

    if (contactFields.length) {
        html += `<div class="m-entity-fields-section">
            <div class="m-entity-section-title"><i class="ph ph-address-book"></i> Contacto</div>
            ${contactFields.map(f => `<div class="m-entity-field-row">
                <div class="m-entity-field-label"><i class="ph ${f.icon}"></i> ${f.label}</div>
                <div class="m-entity-field-value">${f.value}</div>
            </div>`).join('')}
        </div>`;
    }

    if (item.custom_data && typeof item.custom_data === 'object') {
        const entries = Object.entries(item.custom_data).filter(([, v]) => v);
        if (entries.length) {
            html += `<div class="m-entity-fields-section">
                <div class="m-entity-section-title"><i class="ph ph-list-bullets"></i> Datos Adicionales</div>
                ${entries.map(([label, value]) => `<div class="m-entity-field-row">
                    <div class="m-entity-field-label"><i class="ph ph-dot-outline"></i> ${label}</div>
                    <div class="m-entity-field-value">${value}</div>
                </div>`).join('')}
            </div>`;
        }
    }

    if (item.created_at) {
        const safeDate = String(item.created_at).replace(/-/g, '/');
        const date = new Date(safeDate).toLocaleDateString('es-AR');
        html += `<div class="m-entity-fields-section">
            <div class="m-entity-section-title"><i class="ph ph-clock"></i> Registro</div>
            <div class="m-entity-field-row">
                <div class="m-entity-field-label"><i class="ph ph-calendar-blank"></i> Registrado</div>
                <div class="m-entity-field-value">${date}</div>
            </div>
        </div>`;
    }

    document.getElementById('m-entity-detail-body').innerHTML = html || '<div style="text-align:center;padding:30px;color:#aaa;">Sin datos adicionales</div>';

    const sheet = modal.querySelector('.m-entity-detail-sheet');
    if (sheet) sheet.classList.remove('closing');
    modal.classList.remove('hidden');
};

window.closeMobileEntityDetail = function(closeAll) {
    const modal = document.getElementById('mobile-entity-detail-modal');
    if (!modal) return;
    const sheet = modal.querySelector('.m-entity-detail-sheet');
    if (sheet) {
        sheet.classList.add('closing');
        let done = false;
        const finish = () => {
            if (done) return; done = true;
            modal.classList.add('hidden');
            sheet.classList.remove('closing');
            if (closeAll) window.closeMobileEntityList();
        };
        sheet.addEventListener('animationend', finish, { once: true });
        setTimeout(finish, 350);
    } else {
        modal.classList.add('hidden');
        if (closeAll) window.closeMobileEntityList();
    }
};

const _ENTITY_COLORS = ['#88C0D0', '#A3BE8C', '#EBCB8B', '#BF616A', '#B48EAD'];

async function loadMobileEntityData(type) {
    const container = document.getElementById('m-entity-body');
    try {
        let endpoint = '';
        if (type === 'providers') endpoint = '/api/providers/get-all';
        if (type === 'customers') endpoint = '/api/customers/get-all';
        if (type === 'employees') endpoint = '/api/employees/get-all';

        const response = await fetch(endpoint + '?order=DESC');
        const data = await response.json();

        let list = [];
        if (type === 'providers') list = data.providers || [];
        if (type === 'customers') list = data.customers || [];
        if (type === 'employees') list = data.employees || [];

        window._mEntityItems = list;
        window._mEntityType  = type;

        const subtitleEl = document.getElementById('m-entity-subtitle');
        if (subtitleEl && list.length) {
            const labels = { employees: 'trabajadores', providers: 'proveedores', customers: 'clientes' };
            subtitleEl.textContent = `${list.length} ${labels[type] || 'registros'}`;
        }

        if (!list.length) {
            container.innerHTML = '<div style="text-align:center;padding:50px;color:#aaa;"><i class="ph ph-users" style="font-size:2.5rem;display:block;margin-bottom:10px;"></i><p>No hay registros.</p></div>';
            return;
        }

        container.innerHTML = list.map((item, idx) => {
            const name    = item.full_name || item.name || 'Desconocido';
            const initial = name.charAt(0).toUpperCase();
            const color   = _ENTITY_COLORS[idx % _ENTITY_COLORS.length];
            const color20 = color + '33';
            const sub     = (type === 'employees')
                ? (item.category_name ? '' : (item.phone || item.email || ''))
                : (item.phone || item.email || item.cuit || '');
            const tagHtml = (type === 'employees' && item.category_name)
                ? `<div class="m-entity-card-tag" style="color:${color};border-color:${color};background:${color20};"><i class="ph ph-tag"></i> ${item.category_name}</div>`
                : '';
            return `<div class="m-entity-card" data-id="${item.id}">
                <div class="m-entity-avatar" style="background:${color20};color:${color};">${initial}</div>
                <div class="m-entity-card-info">
                    <div class="m-entity-card-name">${name}</div>
                    ${sub ? `<div class="m-entity-card-sub">${sub}</div>` : ''}
                    ${tagHtml}
                </div>
                <i class="ph-bold ph-caret-right m-entity-card-arrow"></i>
            </div>`;
        }).join('');

        container.querySelectorAll('.m-entity-card').forEach(card => {
            card.addEventListener('click', () => {
                const item = window._mEntityItems.find(i => String(i.id) === String(card.dataset.id));
                if (item) window.openMobileEntityDetail(item, type);
            });
        });

    } catch (e) {
        container.innerHTML = '<div style="text-align:center;padding:40px;color:var(--accent-red,#BF616A);font-weight:bold;">Error al cargar los datos.</div>';
    }
}

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
                if (module.init && !module.isInitialized) module.init();

                const modalId = type === 'sale' ? 'create-sale-modal' : 'create-purchase-modal';
                const modal = document.getElementById(modalId);

                if (modal) {
                    if (modal.parentElement !== document.body) {
                        console.warn(`🔧 Moviendo ${modalId} al body para hacerlo visible.`);
                        document.body.appendChild(modal);
                    }
                } else {
                    console.error(`⚠️ No se encontró el modal #${modalId}`);
                }

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


window.openMobileExpense = function() {
    console.log("Iniciando registro de gasto rápido móvil");

    let attempts = 0;
    const maxAttempts = 30; // 3 segundos

    const checkModuleInterval = setInterval(() => {
        attempts++;
        const module = window.purchasesModule || window.purchaseModuleInstance;

        if (module) {
            clearInterval(checkModuleInterval);
            console.log("✅ Módulo compras conectado para gasto rápido");
            try {
                if (module.init && !module.isInitialized) module.init();
                
                const modal = document.getElementById('quick-expense-modal');
                if (modal) {
                    if (modal.parentElement !== document.body) {
                        document.body.appendChild(modal);
                    }
                }
                
                setTimeout(() => {
                    if (module.openQuickModal) {
                        module.openQuickModal();
                    } else {
                        alert("Error: El módulo de compras no está listo.");
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


window.openMobilePriceChecker = async function() {
    const modal = document.getElementById('mobile-price-checker-modal');
    if (!modal) return;

    try {
        await ensureCheckerDataLoaded();

        const sheet = modal.querySelector('.m-checker-sheet');
        if (sheet) sheet.classList.remove('closing');
        modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';

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
    if (!modal) return;
    const sheet = modal.querySelector('.m-checker-sheet');
    if (sheet) {
        sheet.classList.add('closing');
        let done = false;
        const finish = () => {
            if (done) return;
            done = true;
            modal.classList.add('hidden');
            sheet.classList.remove('closing');
            document.body.style.overflow = '';
        };
        sheet.addEventListener('animationend', finish, { once: true });
        setTimeout(finish, 350);
    } else {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
};

window.renderCheckerList = function(products) {
    const container = document.getElementById('checker-list-container');
    if (!container) return;
    container.style.display = 'flex';
    container.innerHTML = '';
    if (!products || products.length === 0) {
        container.innerHTML = '<div class="m-checker-error"><i class="ph ph-magnifying-glass"></i><p>No hay productos que coincidan.</p></div>';
        return;
    }
    const map = window.columnMapping || {};
    const colName = map.name || 'nombre';
    const colPrice = map.sale_price || 'preciodeventa';
    // Show all products (no artificial limit)
    products.forEach(p => {
        const name  = p[colName] || p['name'] || p['nombre'] || 'Producto';
        const price = parseFloat(p[colPrice] || p['sale_price'] || p['precio'] || 0);
        const priceFmt = new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(price);
        const item = document.createElement('div');
        item.className = 'm-checker-list-item';
        item.innerHTML = `
            <div class="m-checker-list-item-info">
                <span class="m-checker-list-item-name">${name}</span>
                <span class="m-checker-list-item-id">#${p.id || '-'}</span>
            </div>
            <span class="m-checker-list-item-price">${priceFmt}</span>`;
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
    const input       = document.getElementById('checker-input');
    const resultCard  = document.getElementById('checker-result');
    const errorMsg    = document.getElementById('checker-error');
    const listContainer = document.getElementById('checker-list-container');
    if (listContainer) listContainer.style.display = 'none';
    let found = directProduct;
    const data = window.allData || [];
    if (!found) {
        if (!input || !input.value.trim()) return;
        const query = input.value.toLowerCase().trim();
        const map = window.columnMapping || {};
        const colName = map.name || 'nombre';
        // 1. Exact barcode match
        found = data.find(p => String(p.barcode || p.codigo_barras || p['Codigo de Barras'] || '').toLowerCase() === query);
        // 2. Partial barcode match
        if (!found) found = data.find(p => String(p.barcode || p.codigo_barras || p['Codigo de Barras'] || '').toLowerCase().includes(query));
        // 3. Partial SKU match
        if (!found) found = data.find(p => String(p.sku || p.SKU || p['sku'] || '').toLowerCase().includes(query));
        // 4. Partial name match
        if (!found) found = data.find(p => String(p[colName] || p['nombre'] || '').toLowerCase().includes(query));
    }
    if (found) {
        const map     = window.columnMapping || {};
        const colName = map.name  || 'nombre';
        const colSale = map.sale_price || 'preciodeventa';
        const colBuy  = map.buy_price  || 'preciodecompra';
        const colStock = map.stock     || 'stock';
        document.getElementById('res-name').textContent  = found[colName]  || found['nombre'] || 'Producto';
        const saleVal = parseFloat(String(found[colSale] ?? '').replace(',', '.'));
        document.getElementById('res-price').textContent = new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(isNaN(saleVal) ? 0 : saleVal);
        document.getElementById('res-stock').textContent = found[colStock] || 0;
        const buyVal  = parseFloat(String(found[colBuy]  ?? '').replace(',', '.'));
        document.getElementById('res-cost').textContent  = new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(isNaN(buyVal) ? 0 : buyVal);
        resultCard.classList.remove('hidden');
        errorMsg.classList.add('hidden');
    } else {
        resultCard.classList.add('hidden');
        errorMsg.classList.remove('hidden');
    }
};

window.checkerBackToList = function() {
    const resultCard    = document.getElementById('checker-result');
    const errorMsg      = document.getElementById('checker-error');
    const listContainer = document.getElementById('checker-list-container');
    if (resultCard)    resultCard.classList.add('hidden');
    if (errorMsg)      errorMsg.classList.add('hidden');
    if (listContainer) listContainer.style.display = 'flex';
    const input = document.getElementById('checker-input');
    if (input) { input.value = ''; input.focus(); }
    window.renderCheckerList(window.allData);
};

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
        const url = `/api/statistics/get-cash-balance.php?period=${encodeURIComponent(period)}&_ts=${Date.now()}`;
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

            const labels = { today: 'Resumen de Hoy', week: 'Esta Semana', month: 'Este Mes', year: 'Este Año' };
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

window.openMobileMetrics = async function() {
    const modal = document.getElementById('mobile-metrics-modal');
    if(!modal) return;
    modal.classList.remove('hidden');
    window.loadMobileMetricsData('today');
};

window.loadMobileMetricsData = async function(period = 'today') {
    const modal = document.getElementById('mobile-metrics-modal');
    const loader = document.getElementById('metrics-loader');
    const content = document.getElementById('metrics-content');

    // Actualizar botón activo
    document.querySelectorAll('.metrics-period-btn').forEach(btn => btn.classList.remove('active'));
    const activeBtn = document.getElementById(`metrics-btn-${period}`);
    if (activeBtn) activeBtn.classList.add('active');

    // Actualizar etiqueta del periodo
    const periodLabels = { today: 'Hoy', month: 'Este Mes', year: 'Este Año', total: 'Total Histórico' };
    const periodLabel = document.getElementById('mobile-m-period-label');
    if (periodLabel) periodLabel.textContent = periodLabels[period] || 'Hoy';

    // Actualizar etiquetas de tarjetas de Ventas y Gastos
    const salesLabelEl = document.querySelector('#btn-show-sales-detail > small');
    const expLabelEl   = document.querySelector('#btn-show-expenses-detail > small');
    const periodCardLabels = { today: 'Ventas de Hoy', month: 'Ventas del Mes', year: 'Ventas del Año', total: 'Ventas Totales' };
    const expCardLabels    = { today: 'Gastos de Hoy',  month: 'Gastos del Mes',  year: 'Gastos del Año',  total: 'Gastos Totales'  };
    if (salesLabelEl) salesLabelEl.textContent = periodCardLabels[period] || 'Total Ventas';
    if (expLabelEl)   expLabelEl.textContent   = expCardLabels[period]   || 'Total Gastos';

    // Mostrar loader
    if (loader) loader.classList.remove('hidden');
    if (content) content.classList.add('hidden');

    try {
        const response = await fetch(`/api/statistics/get-cash-balance-v2.php?period=${encodeURIComponent(period)}&_ts=${Date.now()}`);
        const result = await response.json();

        if (result.success) {
            const data = result.data;
            const fmt = (v) => new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(v);

            // 1. Datos Principales
            document.getElementById('mobile-m-sales').textContent = fmt(data.income);
            document.getElementById('mobile-m-expenses').textContent = fmt(data.expenses);
            document.getElementById('mobile-m-balance').textContent = fmt(data.balance);
            document.getElementById('mobile-m-count').textContent = data.operationCount || 0;
            document.getElementById('mobile-m-valuation').textContent = fmt(data.valuation || 0);

            const avg = data.sales_count > 0 ? data.income / data.sales_count : 0;
            document.getElementById('mobile-m-avg').textContent = fmt(avg);

            // Estilo balance
            const balanceIconBox = document.getElementById('mobile-m-balance-icon');
            const scalesIcon = document.getElementById('mobile-m-scales-icon');
            const balanceArrow = document.getElementById('mobile-m-balance-arrow');
            
            if (balanceIconBox && scalesIcon && balanceArrow) {
                if (data.balance > 0) {
                    balanceIconBox.style.background = 'var(--accent-green-20)';
                    balanceIconBox.style.borderColor = 'var(--accent-green)';
                    scalesIcon.style.color = 'var(--accent-green)';
                    balanceArrow.innerHTML = '<i class="ph ph-arrow-up-right" style="color: var(--accent-green); font-size: 1.2rem;"></i>';
                } else if (data.balance < 0) {
                    balanceIconBox.style.background = 'var(--accent-red-20)';
                    balanceIconBox.style.borderColor = 'var(--accent-red)';
                    scalesIcon.style.color = 'var(--accent-red)';
                    balanceArrow.innerHTML = '<i class="ph ph-arrow-down-right" style="color: var(--accent-red); font-size: 1.2rem;"></i>';
                } else {
                    balanceIconBox.style.background = 'var(--accent-yellow-20)';
                    balanceIconBox.style.borderColor = 'var(--accent-yellow)';
                    scalesIcon.style.color = 'var(--accent-yellow)';
                    balanceArrow.innerHTML = '<i class="ph ph-arrows-horizontal" style="color: var(--accent-yellow); font-size: 1.2rem;"></i>';
                }
            }

            // 2. Click para detalles
            const transListTitle = { today: 'de Hoy', month: 'del Mes', year: 'del Año', total: 'Histórico' };
            const suffix = transListTitle[period] || '';
            const btnSales = document.getElementById('btn-show-sales-detail');
            const btnExp = document.getElementById('btn-show-expenses-detail');
            if (btnSales) btnSales.onclick = () => window.showMobileTransactionList(`Ventas ${suffix}`, data.recent_sales, 'sale');
            if (btnExp) btnExp.onclick = () => window.showMobileTransactionList(`Gastos ${suffix}`, data.recent_purchases, 'purchase');

            // 3. Rankings
            const renderRanking = (id, list, key, labelKey) => {
                const container = document.getElementById(id);
                if (!container) return;
                if (!list || list.length === 0) {
                    container.innerHTML = '<p style="font-size:0.85rem; color:#888; font-style:italic;">¡Aún no hay movimientos registrados para este ranking!</p>';
                    return;
                }
                if (list.length < 2) {
                    container.innerHTML += '<p style="font-size:0.75rem; color:#aaa; margin-top:5px;">(Solo hay un registro destacado por ahora)</p>';
                }
                const max = Math.max(...list.map(i => parseFloat(i[key])));
                container.innerHTML = list.map(item => {
                    const val = parseFloat(item[key]);
                    const pct = max > 0 ? (val / max) * 100 : 0;
                    return `
                        <div style="margin-bottom:10px;">
                            <div style="display:flex; justify-content:space-between; font-size:0.85rem; margin-bottom:4px;">
                                <span style="font-weight:700;">${item[labelKey] || 'N/A'}</span>
                                <span style="font-weight:900; color:var(--accent-color);">${key === 'total' ? fmt(val) : val + ' un.'}</span>
                            </div>
                            <div style="height:6px; background:#eee; border-radius:3px; overflow:hidden;">
                                <div style="height:100%; background:var(--accent-color); width:${pct}%;"></div>
                            </div>
                        </div>
                    `;
                }).join('');
            };

            renderRanking('mobile-m-top-products', data.top_products, 'qty', 'name');
            renderRanking('mobile-m-top-clients', data.top_clients, 'total', 'name');

            if (loader) loader.classList.add('hidden');
            if (content) content.classList.remove('hidden');
        } else {
            throw new Error(result.message || "Error desconocido en el servidor");
        }
    } catch (e) {
        console.error("[METRICS ERROR]", e);
        if (loader) loader.classList.add('hidden');
        if (modal) modal.classList.add('hidden');
        pop_ups.error("Error al cargar métricas: " + e.message);
    }
};

window.showMobileTransactionList = function(title, list, type) {
    const modal = document.getElementById('mobile-transaction-list-modal');
    const body = document.getElementById('m-trans-body');
    document.getElementById('m-trans-title').textContent = title;
    
    modal.style.setProperty('--accent-color', type === 'sale' ? 'var(--accent-green)' : 'var(--accent-red)');
    modal.classList.remove('hidden');

    if(!list || list.length === 0) {
        body.innerHTML = '<div style="text-align:center; padding:40px; color:#999;">No hay registros para mostrar.</div>';
        return;
    }

    const fmt = (v) => new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(v);

    body.innerHTML = list.map((item, index) => {
        const date = new Date(item.sale_date.replace(/-/g, '/'));
        const time = date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', hour12: true });
        const orgId = list.length - index;
        
        return `
            <div style="background:#fff; border:2px solid #1b1b1b; border-radius:12px; padding:12px; margin-bottom:10px; display:flex; justify-content:space-between; align-items:center;">
                <div>
                    <div style="font-weight:900; font-size:1rem;">Operación #${orgId}</div>
                    <div style="font-size:0.8rem; color:#666;">${time}</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-weight:900; color:var(--accent-color); font-size:1.1rem;">${fmt(item.total_amount)}</div>
                    <button class="btn-ticket-push" onclick="window.viewTransactionDetail(${item.id}, '${type}')" style="margin-top:5px; padding:6px 12px; font-size:0.75rem; background: #fff; color: var(--color-black, #1b1b1b); border: 2px solid var(--color-black, #1b1b1b); border-radius: 8px; font-weight: 900; cursor: pointer; text-transform: uppercase; box-shadow: 2px 2px 0px var(--color-black, #1b1b1b);">
                        VER TICKET
                    </button>
                </div>
            </div>
        `;
    }).join('');
};

window.viewTransactionDetail = function(id, type) {
    if (type === 'sale') {
        const module = window.salesModuleInstance;
        if (module && typeof module.viewSaleDetail === 'function') {
            module.viewSaleDetail(id);
        } else {
            pop_ups.error("El módulo de ventas no está listo.");
        }
    } else if (type === 'purchase') {
        const module = window.purchasesModule || window.purchaseModuleInstance;
        if (module && typeof module.showDetails === 'function') {
            module.showDetails(id);
        } else {
            pop_ups.error("El módulo de compras no está listo.");
        }
    } else {
        pop_ups.info("Detalle de transacción próximamente.");
    }
};

window.closeMobileHistory = function() {
    const modal = document.getElementById('mobile-history-modal');
    if (!modal) return;
    const sheet = modal.querySelector('.m-history-sheet');
    if (sheet) {
        sheet.classList.add('closing');
        let done = false;
        const finish = () => {
            if (done) return;
            done = true;
            modal.classList.add('hidden');
            sheet.classList.remove('closing');
            document.body.style.overflow = '';
        };
        sheet.addEventListener('animationend', finish, { once: true });
        setTimeout(finish, 350);
    } else {
        modal.classList.add('hidden');
        document.body.style.overflow = '';
    }
};

window.openMobileHistory = async function() {
    const modal = document.getElementById('mobile-history-modal');
    if (!modal) return;
    modal.classList.remove('hidden');

    // Reset filter tabs to "all"
    document.querySelectorAll('.m-history-tab').forEach(t => t.classList.remove('active'));
    const allTab = document.getElementById('m-hist-tab-all');
    if (allTab) allTab.classList.add('active');
    window._mHistCurrentFilter = 'all';

    await window.loadMobileHistoryData('all');
};

window.filterMobileHistory = async function(filter) {
    window._mHistCurrentFilter = filter;

    // Update tabs
    document.querySelectorAll('.m-history-tab').forEach(t => t.classList.remove('active'));
    const tab = document.getElementById(`m-hist-tab-${filter}`);
    if (tab) tab.classList.add('active');

    await window.loadMobileHistoryData(filter);
};

window.loadMobileHistoryData = async function(filter) {
    const body  = document.getElementById('mobile-history-body');
    const refreshBtn = document.getElementById('m-history-refresh-btn');
    if (!body) return;

    // Loading state
    body.innerHTML = `<div class="m-history-loading"><i class="ph ph-spinner ph-spin"></i><span>Cargando movimientos...</span></div>`;
    if (refreshBtn) refreshBtn.querySelector('i')?.classList.add('ph-spin');

    try {
        const params = new URLSearchParams();
        if (filter !== 'all') {
            params.set('type', filter);
        }
        const response = await fetch(`/api/history/get.php?${params}`);
        const res = await response.json();

        if (refreshBtn) refreshBtn.querySelector('i')?.classList.remove('ph-spin');

        if (res.success) {
            window._renderMobileHistory(res.logs || []);
        } else {
            body.innerHTML = `<div class="m-history-error"><i class="ph ph-warning-circle"></i><p>${res.message || 'Error al obtener el historial.'}</p></div>`;
        }
    } catch (e) {
        console.error('[HISTORY ERROR]', e);
        if (refreshBtn) refreshBtn.querySelector('i')?.classList.remove('ph-spin');
        body.innerHTML = `<div class="m-history-error"><i class="ph ph-warning-circle"></i><p>Error al cargar datos.</p></div>`;
    }
};

window._renderMobileHistory = function(logs) {
    const body = document.getElementById('mobile-history-body');
    if (!body) return;

    if (!logs || logs.length === 0) {
        body.innerHTML = `<div class="m-history-empty"><i class="ph ph-clock-counter-clockwise"></i><p>No hay movimientos en esta sección.</p></div>`;
        return;
    }

    const actionTitles = {
        create: 'Creó',
        update: 'Actualizó',
        delete: 'Eliminó',
        login:  'Ingresó',
    };
    const entityLabels = {
        product:      'Productos',
        sale:         'Ventas',
        purchase:     'Compras / Gastos',
        collaborator: 'Colaboradores',
        expense:      'Gastos',
        config:       'Configuración',
        delivery:     'Envíos',
        client:       'Clientes',
        supplier:     'Proveedores',
        customer:     'Clientes',
        provider:     'Proveedores',
        employee:     'Empleados',
        payment_method: 'Métodos Pago',
        table_preference: 'Configuración',
        column:       'Columna',
        analytic:     'Analíticas',
        role_permissions: 'Permisos',
        role_settings: 'Permisos',
        inventory:    'Inventario',
        collaborator_debt: 'Deuda Colabs.'
    };
    const entityIcons = {
        product:      'ph-package',
        sale:         'ph-shopping-cart',
        purchase:     'ph-truck',
        collaborator: 'ph-users-three',
        expense:      'ph-lightning',
        config:       'ph-gear',
        delivery:     'ph-package',
        client:       'ph-user',
        supplier:     'ph-factory',
        customer:     'ph-user',
        provider:     'ph-factory',
        employee:     'ph-identification-card',
        payment_method: 'ph-credit-card',
        table_preference: 'ph-gear',
        column:       'ph-columns',
        analytic:     'ph-chart-line',
        role_permissions: 'ph-shield-check',
        role_settings: 'ph-shield-check',
        inventory:    'ph-database',
        collaborator_debt: 'ph-credit-card'
    };
    const actionColors = {
        create: { bg: 'var(--accent-green-20, #dcfce7)',   border: '#1b1b1b',   icon: 'var(--accent-green, #22c55e)',   iconBg: 'var(--accent-green-20, #dcfce7)'   },
        update: { bg: 'var(--accent-yellow-20, #EBCB8B33)', border: '#1b1b1b',   icon: 'var(--accent-yellow, #EBCB8B)',  iconBg: 'var(--accent-yellow-20, #EBCB8B33)' },
        delete: { bg: 'var(--accent-red-20, #fee2e2)',     border: '#1b1b1b',   icon: 'var(--accent-red, #ef4444)',     iconBg: 'var(--accent-red-20, #fee2e2)'     },
        login:  { bg: 'var(--accent-color-quat-opacity)',  border: '#1b1b1b',   icon: 'var(--accent-color)',             iconBg: 'var(--accent-color-quat-opacity)'  },
    };
    const actionIcons = {
        create: 'ph-plus-circle',
        update: 'ph-pencil-simple',
        delete: 'ph-trash',
        login:  'ph-sign-in',
    };

    body.innerHTML = logs.map(item => {
        const date = new Date((item.created_at || '').replace(/-/g, '/'));
        const time = isNaN(date) ? '' : date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const day  = isNaN(date) ? '' : date.toLocaleDateString('es-AR', { day: '2-digit', month: '2-digit', year: '2-digit' });

        const actionKey  = (item.action || 'update').toLowerCase();
        const entityKey  = (item.entity_type || '').toLowerCase();
        const actionVerb = actionTitles[actionKey] || item.action || '';
        const entityName = entityLabels[entityKey] || item.entity_type || '';
        const moduleIcon = entityIcons[entityKey] || 'ph-activity';
        const colors     = actionColors[actionKey] || actionColors.update;
        const actionIcon = actionIcons[actionKey] || 'ph-activity';

        const title   = `${actionVerb} ${entityName}`.trim();
        const actor   = item.full_name || item.username || 'Sistema';
        const rawDesc = item.description || '';
        const desc    = rawDesc ? `${rawDesc.substring(0, 65)}${rawDesc.length > 65 ? '…' : ''}` : '';
        const message = desc ? desc : `por ${actor}`;

        return `
            <div class="m-history-card" style="border: 2px solid #1b1b1b;">
                <div class="m-history-card-left">
                    <div class="m-history-action-icon" style="background:${colors.iconBg}; color:${colors.icon}; border: 2px solid #1b1b1b;">
                        <i class="ph ${actionIcon}"></i>
                    </div>
                </div>
                <div class="m-history-card-right">
                    <div class="m-history-card-top">
                        <span class="m-history-module-badge" style="background:${colors.iconBg}; color:${colors.icon}; border: 1.5px solid #1b1b1b;">
                            <i class="ph ${moduleIcon}"></i> ${entityName}
                        </span>
                        <span class="m-history-date">${day} ${time}</span>
                    </div>
                    <div class="m-history-card-title">${title}</div>
                    <div class="m-history-card-desc">${message}</div>
                    ${actor !== 'Sistema' ? `<div class="m-history-actor"><i class="ph ph-user-circle"></i> ${actor}</div>` : ''}
                </div>
            </div>
        `;
    }).join('');
};


async function ensureCheckerDataLoaded() {
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

    const map = window.columnMapping || {};
    const required = [];
    if (!map.name) required.push('Nombre');
    if (!map.stock) required.push('Stock');
    if (!map.sale_price) required.push('Precio de Venta');
    if (required.length) {
        throw new Error(`Faltan columnas obligatorias en Configuraciones: ${required.join(', ')}`);
    }


    if (!window.allData || !Array.isArray(window.allData) || window.allData.length === 0) {
        const table = await api.getTableData();
        if (!table || !table.success) throw new Error(table?.message || "No se pudieron cargar productos del inventario");
        window.allData = table.data || [];
        window.activeInventoryId = table.inventoryId || table.inventory_id || null;
    }

    return true;
}

async function updateMobileInventoryName() {
    const nameEl = document.getElementById('mobile-current-inv-name');
    try {
        const result = await api.getUserVerifiedTables();
        
        console.log("📱 [MOBILE SESSION CHECK]", result);

        if (result.success) {
            const inventories = result.verifiedInventories || result.inventories || result.data || [];
            const active = inventories.find(inv => inv.is_active) || inventories[0];

            if (active && nameEl) {
                nameEl.textContent = active.name;
                window.currentInventoryId = active.id;
            }
        }
    } catch (e) { 
        console.error("Error al obtener nombre de inventario:", e);
    }
}

window.openMobileInventorySelector = async function() {
    const modal = document.getElementById('mobile-inventory-selector-modal');
    const container = document.getElementById('m-inv-list-container');
    if (!modal || !container) return;
    
    modal.classList.remove('hidden');
    container.innerHTML = '<div style="text-align:center; padding:20px;"><i class="ph ph-spinner ph-spin"></i> Cargando tus negocios...</div>';

    try {
        const result = await api.getUserVerifiedTables();

        if (result.success) {
            const inventories = result.verifiedInventories || result.inventories || result.data || [];
            container.innerHTML = inventories.map(inv => `
                <div onclick="window.switchMobileInventory(${inv.id}, '${inv.name}')" 
                     style="padding:18px; background:${inv.id == window.currentInventoryId ? '#f0faff' : '#fff'}; border:2px solid ${inv.id == window.currentInventoryId ? 'var(--accent-color)' : '#1b1b1b'}; border-radius:15px; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <div style="font-weight:900; font-size:1.05rem;">${inv.name}</div>
                        <small style="color:#888;">ID: #${inv.id}</small>
                    </div>
                    ${inv.id == window.currentInventoryId ? '<i class="ph-bold ph-check-circle" style="color:var(--accent-color); font-size:1.5rem;"></i>' : '<i class="ph ph-caret-right" style="color:#ccc;"></i>'}
                </div>
            `).join('');
        }
    } catch (e) {
        container.innerHTML = '<p style="color:red; text-align:center;">Error al cargar inventarios.</p>';
    }
};

window.switchMobileInventory = async function(id, name) {
    if (id == window.currentInventoryId) {
        document.getElementById('mobile-inventory-selector-modal').classList.add('hidden');
        return;
    }

    try {
        // En tu sistema, el cambio de inventario se hace vía /api/database/select.php
        const response = await fetch('/api/database/select.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ inventoryId: id })
        });
        const result = await response.json();

        if (result.success) {
            window.currentInventoryId = id;
            const nameEl = document.getElementById('mobile-current-inv-name');
            if (nameEl) nameEl.textContent = name;
            document.getElementById('mobile-inventory-selector-modal').classList.add('hidden');
            location.reload(); 
        } else {
            pop_ups.error("No se pudo cambiar de inventario");
        }
    } catch (e) {
        console.error(e);
    }
};

// ============================================================
// ENVÍOS MÓVIL — Implementación nativa
// ============================================================

let _mDelivCurrentFilter = 'pending'; // 'pending' | 'completed'

window.openMobileDeliveries = async function() {
    const modal = document.getElementById('mobile-deliveries-modal');
    if (!modal) return;

    _mDelivCurrentFilter = 'pending';
    modal.classList.remove('hidden');
    window.switchMobileDelivTab('pending');
    await window._loadMobileDeliveriesData();
};

window.closeMobileDeliveries = function() {
    const modal = document.getElementById('mobile-deliveries-modal');
    if (modal) modal.classList.add('hidden');
};

window.switchMobileDelivTab = function(filter) {
    _mDelivCurrentFilter = filter;
    const tabPending   = document.getElementById('m-deliv-tab-pending');
    const tabCompleted = document.getElementById('m-deliv-tab-completed');

    if (filter === 'pending') {
        tabPending?.classList.add('active');
        tabCompleted?.classList.remove('active');
    } else {
        tabPending?.classList.remove('active');
        tabCompleted?.classList.add('active');
    }
    window._loadMobileDeliveriesData();
};

window._loadMobileDeliveriesData = async function() {
    const panel = document.getElementById('m-deliv-list-panel');
    const badge = document.getElementById('m-deliv-count-badge');
    const refreshBtn = document.getElementById('m-deliv-refresh-btn');
    if (!panel) return;

    // Loading state
    panel.innerHTML = `<div class="m-deliv-loading"><i class="ph ph-spinner ph-spin"></i><span>Cargando envíos...</span></div>`;
    if (badge) badge.innerHTML = `<i class="ph ph-spinner ph-spin"></i>`;
    if (refreshBtn) refreshBtn.querySelector('i')?.classList.add('ph-spin');

    try {
        const response = await fetch(`/api/deliveries/get-all.php?status=${_mDelivCurrentFilter}`);
        const data = await response.json();

        if (refreshBtn) refreshBtn.querySelector('i')?.classList.remove('ph-spin');

        if (!data.success) {
            panel.innerHTML = `<div class="m-deliv-empty"><i class="ph ph-warning-circle"></i><p>${data.message || 'Error al cargar envíos.'}</p></div>`;
            return;
        }

        const deliveries = data.deliveries || [];
        const isRepartidor = data.is_repartidor === true;

        // Sync with deliveries module if available
        if (window.deliveriesModuleInstance) {
            window.deliveriesModuleInstance.allDeliveries = deliveries;
            window.deliveriesModuleInstance.isRepartidor  = isRepartidor;
            window.deliveriesModuleInstance.currentFilter = _mDelivCurrentFilter;
        }

        // Badge
        if (badge) {
            const count = deliveries.length;
            badge.className = `m-deliv-count-badge${count === 0 ? ' zero' : ''}`;
            const filterLabel = _mDelivCurrentFilter === 'pending' ? 'Pendientes' : 'Finalizados';
            badge.innerHTML = `<i class="ph ph-${_mDelivCurrentFilter === 'pending' ? 'clock' : 'check-circle'}"></i> ${count} ${filterLabel}`;
        }

        // Tabs visibility: repartidores only see pending, so hide tabs
        const tabs = document.getElementById('m-deliv-tabs');
        if (tabs) tabs.style.display = isRepartidor ? 'none' : 'flex';

        // Render
        if (deliveries.length === 0) {
            const emptyMsg = _mDelivCurrentFilter === 'pending'
                ? (isRepartidor ? '¡Todo al día! No tenés envíos pendientes.' : 'No hay envíos pendientes.')
                : 'No hay envíos finalizados.';
            panel.innerHTML = `
                <div class="m-deliv-empty">
                    <i class="ph ${_mDelivCurrentFilter === 'pending' ? 'ph-package' : 'ph-check-circle'}"></i>
                    <p>${emptyMsg}</p>
                </div>`;
            return;
        }

        panel.innerHTML = isRepartidor
            ? _renderMobileDelivRepartidor(deliveries)
            : _renderMobileDelivAdmin(deliveries);

        // Attach events
        _attachMobileDelivEvents(panel, isRepartidor);

    } catch (e) {
        console.error('[MOBILE DELIVERIES ERROR]', e);
        if (refreshBtn) refreshBtn.querySelector('i')?.classList.remove('ph-spin');
        panel.innerHTML = `<div class="m-deliv-empty"><i class="ph ph-warning-circle"></i><p>Error de conexión.</p></div>`;
    }
};

function _formatMobileDelivDuration(ms) {
    const totalSecs = Math.floor(ms / 1000);
    const hours   = Math.floor(totalSecs / 3600);
    const minutes = Math.floor((totalSecs % 3600) / 60);
    return hours > 0 ? `${hours}h ${minutes}m` : `${minutes} min`;
}

function _renderMobileDelivAdmin(deliveries) {
    return deliveries.map(d => {
        const isPending   = d.status === 'pending';
        const statusClass = isPending ? 'pending' : 'completed';
        const statusLabel = isPending ? 'Pendiente' : 'Entregado';

        const created   = new Date(d.created_at);
        let elapsedStr  = '';
        if (!isPending && d.delivered_at) {
            const delivered = new Date(d.delivered_at);
            elapsedStr = _formatMobileDelivDuration(delivered - created);
        } else if (isPending) {
            elapsedStr = _formatMobileDelivDuration(new Date() - created) + ' activo';
        }

        const fmt = (v) => new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(v);
        const totalFmt = fmt(parseFloat(d.sale_total || 0));
        const repartidor = d.collaborator_name || '<span style="color:#aaa;">Sin asignar</span>';

        const pendingActions = isPending ? `
            <button class="m-deliv-icon-btn complete mobile-complete-deliv" data-id="${d.id}" title="Marcar como entregado">
                <i class="ph ph-check-circle"></i>
            </button>
            <button class="m-deliv-icon-btn mobile-edit-deliv" data-id="${d.id}" title="Editar">
                <i class="ph ph-pencil-simple"></i>
            </button>` : '';

        const ticketImgs = `
            <button class="m-deliv-icon-btn mobile-view-ticket" data-id="${d.id}" title="Ticket Envío">
                <img src="/assets/img/iconos/ticket_envio.png" style="width:18px;height:18px;">
            </button>
            <button class="m-deliv-icon-btn mobile-view-sale" data-sale-id="${d.sale_id}" title="Ticket Venta">
                <img src="/assets/img/iconos/ticket_compra.png" style="width:18px;height:18px;">
            </button>`;

        return `
            <div class="m-deliv-card">
                <div class="m-deliv-card-header">
                    <div class="m-deliv-card-code"><i class="ph ph-tag"></i>${d.ticket_code}</div>
                    <span class="m-deliv-status-pill ${statusClass}">${statusLabel}</span>
                </div>
                <div class="m-deliv-card-body">
                    <div class="m-deliv-row">
                        <i class="ph ph-user"></i>
                        <strong>Cliente</strong>
                        <span>${d.customer_name || 'Consumidor Final'}</span>
                    </div>
                    <div class="m-deliv-row">
                        <i class="ph ph-map-pin"></i>
                        <strong>Dir.</strong>
                        <span>${d.address}</span>
                    </div>
                    <div class="m-deliv-row">
                        <i class="ph ph-person-simple-bike"></i>
                        <strong>Rep.</strong>
                        <span>${repartidor}</span>
                    </div>
                    <div class="m-deliv-row">
                        <i class="ph ph-money"></i>
                        <strong>Total</strong>
                        <span>${totalFmt}</span>
                    </div>
                    ${elapsedStr ? `<div class="m-deliv-time-info"><i class="ph ph-timer"></i> ${elapsedStr}</div>` : ''}
                    ${!isPending && d.delivered_at ? `<div class="m-deliv-time-info" style="color:var(--accent-green);"><i class="ph ph-check-circle"></i> Entregado el ${new Date(d.delivered_at).toLocaleString()}</div>` : ''}
                </div>
                <div class="m-deliv-card-actions">
                    ${ticketImgs}
                    <div style="flex:1;"></div>
                    ${pendingActions}
                    <button class="m-deliv-icon-btn delete mobile-delete-deliv" data-id="${d.id}" title="Eliminar">
                        <i class="ph ph-trash"></i>
                    </button>
                </div>
            </div>`;
    }).join('');
}

function _renderMobileDelivRepartidor(deliveries) {
    return deliveries.map(d => {
        const isPending   = d.status === 'pending';
        const statusClass = isPending ? 'pending' : 'completed';
        const statusLabel = isPending ? 'Pendiente' : 'Entregado';
        const isPaid      = d.is_paid == 1;
        const fmt         = (v) => new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(v);

        const phoneRow = d.customer_phone ? `
            <div class="m-deliv-row">
                <i class="ph ph-phone"></i>
                <strong>Tel.</strong>
                <span>${d.customer_phone}</span>
                <a href="tel:${d.customer_phone}" style="flex-shrink:0; color:var(--accent-color); text-decoration:none; font-size:1.1rem;" title="Llamar"><i class="ph-bold ph-phone-call"></i></a>
            </div>` : '';

        const actions = isPending ? `
            <div class="m-deliv-card-actions">
                <a href="https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(d.address)}" target="_blank" class="m-deliv-action-maps">
                    <i class="ph-bold ph-map-pin"></i> Abrir Mapa
                </a>
                <button class="m-deliv-action-done mobile-complete-deliv" data-id="${d.id}">
                    <i class="ph-bold ph-check"></i> Entregado
                </button>
            </div>` : `
            <div class="m-deliv-card-actions">
                <div class="m-deliv-delivered-label">
                    <i class="ph-bold ph-check-circle"></i>
                    Entregado el ${new Date(d.delivered_at).toLocaleString()}
                </div>
            </div>`;

        return `
            <div class="m-deliv-card">
                <div class="m-deliv-card-header">
                    <div class="m-deliv-card-code"><i class="ph ph-tag"></i>${d.ticket_code}</div>
                    <span class="m-deliv-status-pill ${statusClass}">${statusLabel}</span>
                </div>
                <div class="m-deliv-card-body">
                    <div class="m-deliv-row">
                        <i class="ph ph-user"></i>
                        <strong>Cliente</strong>
                        <span>${d.customer_name || 'Consumidor Final'}</span>
                    </div>
                    ${phoneRow}
                    <div class="m-deliv-row">
                        <i class="ph ph-map-pin"></i>
                        <strong>Dirección</strong>
                        <span>${d.address}</span>
                    </div>
                    <div class="m-deliv-payment ${isPaid ? 'paid' : 'unpaid'}">
                        <i class="ph-bold ${isPaid ? 'ph-check-circle' : 'ph-warning-circle'}"></i>
                        ${isPaid ? 'Pedido ya abonado' : `Cobrar: ${fmt(parseFloat(d.sale_total || 0))}`}
                    </div>
                    ${d.estimated_time ? `<div class="m-deliv-time-info"><i class="ph ph-timer"></i> Tiempo estimado: ${d.estimated_time}</div>` : ''}
                </div>
                ${actions}
            </div>`;
    }).join('');
}

function _attachMobileDelivEvents(panel, isRepartidor) {
    // Complete
    panel.querySelectorAll('.mobile-complete-deliv').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = parseInt(btn.dataset.id);
            const module = window.deliveriesModuleInstance;
            if (module && typeof module.completeDelivery === 'function') {
                await module.completeDelivery(id);
                await window._loadMobileDeliveriesData();
            } else {
                pop_ups.error("Módulo de envíos no cargado.");
            }
        });
    });

    // Edit (admin only)
    panel.querySelectorAll('.mobile-edit-deliv').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = parseInt(btn.dataset.id);
            const module = window.deliveriesModuleInstance;
            if (module && typeof module.openEditModal === 'function') {
                window.closeMobileDeliveries();
                await module.init();
                setTimeout(() => module.openEditModal(id), 200);
            }
        });
    });

    // Delete (admin only)
    panel.querySelectorAll('.mobile-delete-deliv').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = parseInt(btn.dataset.id);
            const module = window.deliveriesModuleInstance;
            if (module && typeof module.deleteDelivery === 'function') {
                await module.deleteDelivery(id);
                await window._loadMobileDeliveriesData();
            }
        });
    });

    // View ticket
    panel.querySelectorAll('.mobile-view-ticket').forEach(btn => {
        btn.addEventListener('click', async () => {
            const id = parseInt(btn.dataset.id);
            const module = window.deliveriesModuleInstance;
            if (module && typeof module.showTicket === 'function') {
                await module.showTicket(id);
            }
        });
    });

    // View sale ticket
    panel.querySelectorAll('.mobile-view-sale').forEach(btn => {
        btn.addEventListener('click', () => {
            const saleId = parseInt(btn.dataset.saleId);
            const salesModule = window.salesModuleInstance;
            if (salesModule && typeof salesModule.viewSaleDetail === 'function') {
                salesModule.viewSaleDetail(saleId);
            } else if (salesModule && typeof salesModule.showDetails === 'function') {
                salesModule.showDetails(saleId);
            } else {
                pop_ups.error("El módulo de ventas no está cargado.");
            }
        });
    });
}

// ============================================================
// COLABORADORES MÓVIL — Implementación nativa
// ============================================================


const MOBILE_COLLAB_SECTIONS = [
    { key: 'can_view_data',          label: 'Ver Datos',         icon: 'ph-table' },
    { key: 'can_view_analytics',     label: 'Analíticas',        icon: 'ph-chart-line' },
    { key: 'can_view_history',       label: 'Historial',         icon: 'ph-clock-counter-clockwise' },
    { key: 'can_view_config',        label: 'Config.',           icon: 'ph-gear' },
    { key: 'can_view_customers',     label: 'Clientes',          icon: 'ph-user-focus' },
    { key: 'can_view_providers',     label: 'Proveedores',       icon: 'ph-van' },
    { key: 'can_view_employees',     label: 'Trabajadores',      icon: 'ph-identification-badge' },
    { key: 'can_view_payments',      label: 'Pagos',             icon: 'ph-wallet' },
    { key: 'can_view_notifications', label: 'Notificaciones',    icon: 'ph-bell' },
    { key: 'can_view_deliveries',    label: 'Envíos',            icon: 'ph-truck' },
    { key: 'can_view_sales',         label: 'Ingreso',           icon: 'ph-money' },
    { key: 'can_view_receipts',      label: 'Egreso',            icon: 'ph-stack' },
];

let _mCollabPermissionsState = { 2: {}, 3: {} };
let _mCollabActiveRoleTab = 2;

window.openMobileCollaborators = async function() {
    const modal = document.getElementById('mobile-collaborators-modal');
    if (!modal) return;

    // Show modal immediately
    modal.classList.remove('hidden');

    // Switch to list tab by default
    window.switchMobileCollabTabNative('list');

    // Determine if user is owner — use confirmed state or fallback to API
    let isOwner = false;
    if (window.usersModuleInstance?.isOwner === true) {
        isOwner = true;
    } else {
        try {
            const roleRes = await fetch('/api/users/get-role-settings.php');
            const roleData = await roleRes.json();
            isOwner = roleData.success && roleData.mode === 'owner';
            if (window.usersModuleInstance) window.usersModuleInstance.isOwner = isOwner;
        } catch (e) { isOwner = false; }
    }

    // Setup invite button
    const inviteBtn = document.getElementById('m-collab-invite-btn');
    if (inviteBtn) {
        if (isOwner) {
            inviteBtn.style.display = 'flex';
            inviteBtn.onclick = () => {
                window.closeMobileCollaborators();
                setTimeout(() => {
                    // Trigger desktop invite modal
                    document.getElementById('invite-collaborator-btn')?.click();
                }, 200);
            };
        } else {
            inviteBtn.style.display = 'none';
        }
    }

    // Show/hide permissions tab based on ownership
    const permsTab = document.getElementById('m-tab-permissions');
    if (permsTab) {
        permsTab.style.display = isOwner ? 'flex' : 'none';
    }

    // Load collaborators list
    await _loadMobileCollaboratorsList();

    // Load quota badge
    if (isOwner) {
        await _loadMobileCollabQuota();
        await _loadMobileCollabDebts();
        await _loadMobileCollabPermissions();
    } else {
        // Hide quota badge for non-owners
        const badge = document.getElementById('m-collab-quota-badge');
        if (badge) badge.style.display = 'none';
    }
};

async function _loadMobileCollaboratorsList() {
    const panel = document.getElementById('m-collab-list-panel');
    if (!panel) return;

    panel.innerHTML = `<div class="m-collab-loading"><i class="ph ph-spinner ph-spin"></i><span>Cargando equipo...</span></div>`;

    try {
        const response = await fetch('/api/users/list.php');
        const result = await response.json();

        if (!result.success) {
            panel.innerHTML = `<div class="m-collab-empty"><i class="ph ph-warning-circle"></i><p>${result.message || 'Error al cargar.'}</p></div>`;
            return;
        }

        const collaborators = result.collaborators || [];
        const isOwner = window.usersModuleInstance?.isOwner ?? false;

        if (collaborators.length === 0) {
            panel.innerHTML = `
                <div class="m-collab-empty">
                    <i class="ph ph-users-three"></i>
                    <p>¡Sos el único en este inventario!<br>Invitá a tu equipo.</p>
                </div>`;
            return;
        }

        let html = '';
        collaborators.forEach(c => {
            const name = c.full_name || c.username || 'Desconocido';
            const initials = name.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
            const isActive = c.status === 'active';
            const canDelete = isOwner && c.role_name !== 'Owner';

            let roleClass = 'employee';
            let roleLabel = 'Empleado';
            if (c.role_name === 'Owner') { roleClass = 'owner'; roleLabel = 'Propietario'; }
            else if (c.role_name === 'Admin') { roleClass = 'admin'; roleLabel = 'Admin'; }

            html += `
                <div class="m-collab-card">
                    <div class="m-collab-card-top">
                        <div class="m-collab-avatar">${initials}</div>
                        <div class="m-collab-info">
                            <div class="m-collab-name">${name}</div>
                            <div class="m-collab-email"><i class="ph ph-envelope"></i> ${c.email}</div>
                        </div>
                        <span class="m-collab-role-badge ${roleClass}">${roleLabel}</span>
                    </div>
                    <div class="m-collab-card-footer">
                        <div class="m-collab-status">
                            <span class="m-collab-status-dot ${isActive ? 'active' : 'pending'}"></span>
                            <span style="color: ${isActive ? 'var(--accent-green)' : '#888'};">${isActive ? 'Activo' : 'Pendiente'}</span>
                        </div>
                        ${canDelete ? `
                            <button class="m-collab-delete-btn" onclick="window._mRemoveCollaborator(${c.collaborator_id})">
                                <i class="ph ph-trash"></i> Eliminar
                            </button>
                        ` : ''}
                    </div>
                </div>`;
        });

        panel.innerHTML = html;

    } catch (e) {
        panel.innerHTML = `<div class="m-collab-empty"><i class="ph ph-warning-circle"></i><p>Error de conexión.</p></div>`;
    }
}

window._mRemoveCollaborator = async function(id) {
    const confirmed = await pop_ups.confirm("Revocar acceso", "\u00bfSeguro que quer\u00e9s eliminar a este colaborador?");
    if (!confirmed) return;

    try {
        const response = await fetch('/api/users/remove.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ collaborator_id: id })
        });
        const result = await response.json();

        if (result.success) {
            pop_ups.success(result.message);
            await _loadMobileCollaboratorsList();
            if (window.usersModuleInstance?.isOwner) {
                await _loadMobileCollabQuota();
            }
        } else {
            pop_ups.error(result.message);
        }
    } catch (e) {
        pop_ups.error("Error al revocar acceso");
    }
};

async function _loadMobileCollabQuota() {
    const badge = document.getElementById('m-collab-quota-badge');
    if (!badge) return;

    try {
        const res = await fetch('/api/users/get-collaborator-quota.php');
        const data = await res.json();
        if (!data.success) { badge.style.display = 'none'; return; }

        badge.style.display = 'inline-flex';

        if (data.locked) {
            badge.innerHTML = `<i class="ph ph-lock"></i> Solo uso personal`;
            badge.style.color = '#94a3b8';
            badge.style.borderColor = '#e2e8f0';
            badge.style.background = '#f1f5f9';
        } else if (data.max === null) {
            badge.innerHTML = `<i class="ph ph-infinity"></i> Ilimitado · ${data.plan_name}`;
        } else {
            const atLimit = data.remaining === 0;
            badge.innerHTML = `<i class="ph ph-users"></i> ${data.used}/${data.max} · ${data.plan_name}`;
            if (atLimit) {
                badge.style.color = 'var(--accent-red)';
                badge.style.borderColor = 'var(--accent-red)';
                badge.style.background = 'var(--accent-red-20, #fff0f0)';
            }
        }

        // Invite button state
        const inviteBtn = document.getElementById('m-collab-invite-btn');
        if (inviteBtn) {
            inviteBtn.disabled = data.locked || !data.allowed;
            inviteBtn.style.opacity = (data.locked || !data.allowed) ? '0.45' : '1';
        }

    } catch (e) {
        if (badge) badge.style.display = 'none';
    }
}

async function _loadMobileCollabDebts() {
    try {
        const resp = await fetch('/api/collaborators/get-pending-debts.php');
        if (!resp.ok) return;
        const res = await resp.json();

        const banner = document.getElementById('m-collab-debt-banner');
        const textEl = document.getElementById('m-collab-debt-text');
        const payBtn = document.getElementById('m-collab-pay-btn');

        if (banner && res.success && res.debts && res.debts.length > 0) {
            banner.classList.remove('hidden');
            if (textEl) {
                const total = res.debts.reduce((sum, d) => sum + (parseFloat(d.price_per_slot) * parseInt(d.slots_added)), 0);
                textEl.textContent = `Deuda: $${total.toLocaleString('es-AR')} · Saldá en 48 hs`;
            }
        } else if (banner) {
            banner.classList.add('hidden');
        }
    } catch (e) { /* silently fail */ }
}

async function _loadMobileCollabPermissions() {
    const panel = document.getElementById('m-collab-perms-panel');
    if (!panel) return;

    panel.innerHTML = `<div class="m-collab-loading"><i class="ph ph-spinner ph-spin"></i><span>Cargando permisos...</span></div>`;

    try {
        const res = await fetch('/api/users/get-role-settings.php');
        const data = await res.json();

        if (!data.success || data.mode !== 'owner') {
            panel.innerHTML = `<div class="m-collab-empty"><i class="ph ph-lock"></i><p>Sin acceso a permisos.</p></div>`;
            return;
        }

        const settings = data.settings || {};
        _mCollabPermissionsState = { 2: { ...(settings[2] || {}) }, 3: { ...(settings[3] || {}) } };
        _mCollabActiveRoleTab = 2;

        _renderMobilePermissionsPanel(settings);

    } catch (e) {
        panel.innerHTML = `<div class="m-collab-empty"><i class="ph ph-warning-circle"></i><p>Error al cargar permisos.</p></div>`;
    }
}

function _renderMobilePermissionsPanel(settings) {
    const panel = document.getElementById('m-collab-perms-panel');
    if (!panel) return;

    panel.innerHTML = `
        <p style="font-size:0.8rem; color:#888; margin:0 0 12px;">
            Definí qué secciones puede ver cada rol. El Propietario siempre tiene acceso total.
        </p>
        <div class="m-perms-role-selector">
            <button class="m-perms-role-btn active" id="m-perms-btn-2" onclick="window._switchMobilePermsRole(2)">
                <i class="ph ph-star"></i> Administrador
            </button>
            <button class="m-perms-role-btn" id="m-perms-btn-3" onclick="window._switchMobilePermsRole(3)">
                <i class="ph ph-user"></i> Empleado
            </button>
        </div>
        <div id="m-perms-content"></div>
        <button class="m-perms-save-btn" onclick="window._saveMobileCollabPermissions()">
            <i class="ph ph-floppy-disk"></i> Guardar Cambios
        </button>
    `;

    _renderMobilePermsContent(settings[_mCollabActiveRoleTab] || {});
}

function _renderMobilePermsContent(perms) {
    const container = document.getElementById('m-perms-content');
    if (!container) return;

    const html = `<div class="m-perms-grid">
        ${MOBILE_COLLAB_SECTIONS.map(s => {
            const checked = perms[s.key] !== false;
            return `
                <label class="m-perms-check-item">
                    <input type="checkbox"
                        data-key="${s.key}"
                        data-role="${_mCollabActiveRoleTab}"
                        ${checked ? 'checked' : ''}
                        onchange="window._onMobilePermChange(this)">
                    <i class="ph ${s.icon}"></i>
                    <span>${s.label}</span>
                </label>`;
        }).join('')}
    </div>`;

    container.innerHTML = html;
}

window._switchMobilePermsRole = function(roleId) {
    _mCollabActiveRoleTab = roleId;
    document.querySelectorAll('.m-perms-role-btn').forEach(b => b.classList.remove('active'));
    document.getElementById(`m-perms-btn-${roleId}`)?.classList.add('active');
    _renderMobilePermsContent(_mCollabPermissionsState[roleId] || {});
};

window._onMobilePermChange = function(checkbox) {
    const roleId = parseInt(checkbox.dataset.role);
    const key = checkbox.dataset.key;
    if (!_mCollabPermissionsState[roleId]) _mCollabPermissionsState[roleId] = {};
    _mCollabPermissionsState[roleId][key] = checkbox.checked;
};

window._saveMobileCollabPermissions = async function() {
    const saveBtn = document.querySelector('.m-perms-save-btn');
    if (saveBtn) {
        saveBtn.disabled = true;
        saveBtn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Guardando...';
    }

    try {
        const response = await fetch('/api/users/update_permissions.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ settings: _mCollabPermissionsState, categories: {} })
        });
        const result = await response.json();

        if (result.success) {
            pop_ups.success("Permisos guardados correctamente.");
        } else {
            pop_ups.error(result.message || "Error al guardar.");
        }
    } catch (e) {
        pop_ups.error("Error de conexi\u00f3n.");
    } finally {
        if (saveBtn) {
            saveBtn.disabled = false;
            saveBtn.innerHTML = '<i class="ph ph-floppy-disk"></i> Guardar Cambios';
        }
    }
};

window.closeMobileCollaborators = function() {
    const modal = document.getElementById('mobile-collaborators-modal');
    if (modal) modal.classList.add('hidden');
};

window.switchMobileCollabTabNative = function(tab) {
    const listPanel = document.getElementById('m-collab-list-panel');
    const permsPanel = document.getElementById('m-collab-perms-panel');
    const tabList = document.getElementById('m-tab-list');
    const tabPerms = document.getElementById('m-tab-permissions');

    if (tab === 'list') {
        listPanel?.classList.remove('hidden');
        permsPanel?.classList.add('hidden');
        tabList?.classList.add('active');
        tabPerms?.classList.remove('active');
    } else if (tab === 'permissions') {
        listPanel?.classList.add('hidden');
        permsPanel?.classList.remove('hidden');
        tabList?.classList.remove('active');
        tabPerms?.classList.add('active');
    }
};

// Mantener compatibilidad con código viejo que llame switchMobileCollabTab
window.switchMobileCollabTab = window.switchMobileCollabTabNative;