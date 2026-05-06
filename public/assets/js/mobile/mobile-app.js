/**
 */
import { pop_ups } from "../notifications/pop-up.js?v=3.0";
import * as api from "../api.js";

export async function initMobileApp() {
    console.log("📱 StockiFy Mobile Mode Iniciado");
    updateMobileInventoryName();

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

window.openMobileMetrics = async function() {
    const modal = document.getElementById('mobile-metrics-modal');
    if(!modal) return;
    modal.classList.remove('hidden');
    
    const loader = document.getElementById('metrics-loader');
    const content = document.getElementById('metrics-content');
    loader.classList.remove('hidden');
    content.classList.add('hidden');

    try {
        const response = await fetch(`/api/statistics/get-cash-balance-v2.php?period=today&_ts=${Date.now()}`);
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
            const btnSales = document.getElementById('btn-show-sales-detail');
            const btnExp = document.getElementById('btn-show-expenses-detail');
            if (btnSales) btnSales.onclick = () => window.showMobileTransactionList('Ventas de Hoy', data.recent_sales, 'sale');
            if (btnExp) btnExp.onclick = () => window.showMobileTransactionList('Gastos de Hoy', data.recent_purchases, 'purchase');

            // 3. Rankings
            const renderRanking = (id, list, key, labelKey) => {
                const container = document.getElementById(id);
                if (!container) return;
                if (!list || list.length === 0) {
                    container.innerHTML = '<p style="font-size:0.85rem; color:#888; font-style:italic;">¡Aún no hay movimientos registrados para este ranking hoy!</p>';
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

            loader.classList.add('hidden');
            content.classList.remove('hidden');
        } else {
            throw new Error(result.message || "Error desconocido en el servidor");
        }
    } catch (e) {
        console.error("[METRICS ERROR]", e);
        loader.classList.add('hidden');
        modal.classList.add('hidden'); // Cerramos para que no quede el modal vacío
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
                    <button class="btn-brutal-mini" onclick="window.viewTransactionDetail(${item.id}, '${type}')" style="margin-top:5px; padding:4px 8px; font-size:0.7rem;">
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
    } else {
        pop_ups.info("Detalle de compra próximamente.");
    }
};

window.openMobileHistory = async function() {
    const modal = document.getElementById('mobile-history-modal');
    const body = document.getElementById('mobile-history-body');
    if(!modal) return;
    modal.classList.remove('hidden');
    body.innerHTML = '<div style="text-align: center; padding: 40px; color: #999;"><i class="ph ph-spinner ph-spin" style="font-size:2rem;"></i><br>Cargando movimientos...</div>';

    try {
        const response = await fetch('/api/history/get.php');
        const res = await response.json();

        if (res.success) {
            const logs = res.notifications.filter(n => n.type !== 'error').slice(0, 20);
            if(logs.length === 0) {
                body.innerHTML = '<p style="text-align:center; padding:40px; color:#999;">No hay movimientos registrados.</p>';
                return;
            }

            body.innerHTML = logs.map(item => {
                const date = new Date(item.created_at.replace(/-/g, '/'));
                const time = date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
                const day = date.toLocaleDateString();
                
                let icon = 'ph-info';
                let color = 'var(--accent-blue)';
                if (item.type === 'success') { icon = 'ph-check-circle'; color = 'var(--accent-green)'; }
                if (item.type === 'warning') { icon = 'ph-warning'; color = 'var(--accent-yellow)'; }

                return `
                    <div style="background:#fff; border:2px solid #1b1b1b; border-radius:15px; padding:15px; margin-bottom:12px; display:flex; gap:12px; align-items:start;">
                        <div style="background:${color}; color:#fff; width:40px; height:40px; border-radius:10px; display:flex; align-items:center; justify-content:center; flex-shrink:0;">
                            <i class="ph ${icon}" style="font-size:1.4rem;"></i>
                        </div>
                        <div style="flex-grow:1;">
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
                                <span style="font-size:0.75rem; font-weight:800; color:#888;">${day} - ${time}</span>
                            </div>
                            <div style="font-weight:800; font-size:1rem; color:#1b1b1b; line-height:1.2;">${item.title}</div>
                            <div style="font-size:0.85rem; color:#666; margin-top:4px;">${item.message}</div>
                        </div>
                    </div>
                `;
            }).join('');
        }
    } catch (e) {
        console.error(e);
        body.innerHTML = '<p style="text-align:center; padding:20px; color:red;">Error al cargar datos.</p>';
    }
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
        const response = await fetch('/api/database/get-verified-tables.php', { method: 'POST' });
        const result = await response.json();
        
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
        const response = await fetch('/api/database/get-verified-tables.php', { method: 'POST' });
        const result = await response.json();

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