/**
 * public/assets/js/purchases/purchases.js
 * Módulo de Compras (Final: Cards + Lógica USD Robusta)
 */
import { pop_ups } from '../notifications/pop-up.js';
import * as api from "../api.js";

const fmtMoney = (amount, currency = 'ARS') => {
    if (amount === undefined || amount === null || isNaN(amount)) return '$ 0,00';
    return new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: currency,
        minimumFractionDigits: 2
    }).format(amount);
};

const fmtDate = (dateString) => {
    if (!dateString) return '-';
    const safeDate = dateString.replace(/-/g, '/');
    const d = new Date(safeDate);
    if (isNaN(d.getTime())) return dateString;
    return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
};

export class PurchaseModule {
    constructor() {
        this.containerId = 'receipts';
        this.isInitialized = false;

        this.currentPurchase = { providerId: null, providerName: null, items: [], total: 0 };
        this.availableProducts = [];
        this.availableProviders = [];
        this.config = {};

        // Estado para Cotización
        this.rates = { USD: 1, USDT: 1 };

        this.editingId = null;
        this.currentSortOrder = 'DESC';
    }

    init() {
        if (this.isInitialized) { this.loadHistory(this.currentSortOrder); return; }
        const container = document.getElementById(this.containerId);
        if (!container) return;
        container.innerHTML = this.renderBaseStructure();
        this.attachEvents();
        this.loadHistory(this.currentSortOrder);
        this.isInitialized = true;
    }

    // --- CARGA DE HISTORIAL ---
    async loadHistory(order = 'desc') {
        const tableBody = document.getElementById('purchases-list-body');
        tableBody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:20px;">Cargando...</td></tr>';

        try {
            const res = await api.getPurchasesHistory(order);

            if (!res.success || !res.purchases || res.purchases.length === 0) {
                tableBody.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:20px; color:#999;">No hay compras registradas</td></tr>';
                return;
            }

            tableBody.innerHTML = res.purchases.map(p => {
                const dateHtml = `${fmtDate(p.created_at)} <div style="font-size:0.75rem; color:#999;">#${p.id}</div>`;
                const isInventory = !p.category;
                const icon = isInventory ? '<i class="ph ph-truck" style="color:var(--accent-color);"></i>' : '<i class="ph ph-lightning" style="color:var(--accent-color);"></i>';
                const title = p.provider_name !== '-' ? p.provider_name : (p.category || 'Gasto General');
                const subtitle = isInventory ? 'Compra de Inventario' : 'Gasto / Servicio';

                const infoHtml = `
                    <div style="display:flex; align-items:center; gap:10px;">
                        <div style="font-size:1.2rem; opacity:0.7;">${icon}</div>
                        <div style="line-height:1.2;">
                            <div style="font-weight:600; color:#333;">${title}</div>
                            <div style="font-size:0.75rem; color:#888;">${subtitle}</div>
                        </div>
                    </div>
                `;

                const totalHtml = `<div style="font-weight:800; color:var(--accent-color); font-size:1.1rem;">${fmtMoney(parseFloat(p.total))}</div>`;

                return `
                    <tr style="border-bottom:1px solid #eee;">
                        <td style="padding:12px;">${dateHtml}</td>
                        <td style="padding:12px;">${infoHtml}</td>
                        <td style="padding:12px; text-align:right;">${totalHtml}</td>
                        <td style="padding:12px; text-align:center;">
                            <div class="btn-icon-group" style="justify-content:center;">
                                <button class="action-btn view" onclick="window.purchasesModule.showDetails('${p.id}')" title="Ver Detalle"><i class="ph ph-receipt"></i></button>
                                <button class="action-btn edit" onclick="window.purchasesModule.editPurchase('${p.id}')" title="Editar"><i class="ph ph-pencil-simple"></i></button>
                                <button class="action-btn delete" onclick="window.purchasesModule.deletePurchase('${p.id}')" title="Eliminar"><i class="ph ph-trash"></i></button>
                            </div>
                        </td>
                    </tr>
                `;
            }).join('');

        } catch (error) {
            console.error(error);
            tableBody.innerHTML = '<tr><td colspan="4" style="text-align:center; color:red; padding: 15px;">Error al cargar historial</td></tr>';
        }
    }

    renderBaseStructure() {
        return `
            <div class="purchases-layout">
                <style>
                    /* Estilos Generales */
                    .action-btn { width: 32px; height: 32px; border: none; border-radius: 6px; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: all 0.2s ease; font-size: 1.1rem; }
                    .action-btn:hover { transform: translateY(-2px); }
                    .btn-icon-group { display: flex; gap: 8px; justify-content: center; }
                    
                    /* --- ESTILOS DE TARJETAS (CARDS) --- */
                    .product-card {
                        background: white; border: 1px solid #e0e0e0; border-radius: 8px; padding: 10px 12px; margin-bottom: 8px;
                        display: flex; justify-content: space-between; align-items: center; cursor: pointer; transition: all 0.2s ease;
                    }
                    .product-card:hover { border-color: var(--accent-color); transform: translateY(-1px); box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
                    .product-card.disabled { opacity: 0.6; pointer-events: none; background: #f9f9f9; }
                    
                    .prod-info { flex: 1; overflow: hidden; }
                    .prod-name { font-weight: 600; color: #333; font-size: 0.95rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
                    .prod-meta { font-size: 0.75rem; color: #777; display: flex; align-items: center; gap: 8px; margin-top: 2px; }
                    
                    .prod-pricing { text-align: right; display: flex; flex-direction: column; align-items: flex-end; justify-content: center; }
                    .main-price { font-weight: 700; color: var(--sale-green); font-size: 1.1rem; }
                    
                    /* Badge Orig: USD */
                    .badge-usd { 
                        font-size: 0.7rem; background-color: var(--accent-color-quat-opacity); color: var(--color-black); 
                        padding: 2px 4px; border-radius: 4px; font-weight: 500; margin-top: 2px; white-space: nowrap;
                    }
                    
                    .stock-tag { background: #f0f0f0; padding: 1px 5px; border-radius: 3px; font-size: 0.7rem; color: #555; }
                    .stock-tag.warning { background: #fff3cd; color: #856404; }

                    /* --- ESTILOS DEL CARRITO (TIPO IMAGEN) --- */
                    .cart-card {
                        background: #fff; border: 1px solid #333; border-radius: 8px; padding: 12px; margin-bottom: 10px;
                        display: flex; flex-direction: column; gap: 12px; position: relative;
                    }
                    
                    .cart-row-top { display: flex; justify-content: space-between; align-items: flex-start; }
                    .cart-name { font-weight: 700; font-size: 1rem; color: #000; line-height: 1.2; flex: 1; padding-right: 10px; }
                    .cart-unit-price { font-size: 0.85rem; color: #666; white-space: nowrap; text-align: right; }

                    .cart-row-bottom { display: flex; justify-content: space-between; align-items: center; }
                    
                    /* Total Grande Azul */
                    .cart-total { 
                        font-weight: 800; font-size: 1.3rem; color: var(--accent-color); letter-spacing: -0.5px; 
                    }
                    
                    /* Botonera Unificada */
                    .cart-controls-wrapper {
                        display: flex; align-items: center; gap: 4px;
                        background: #f9f9f9; padding: 2px; border-radius: 6px; border: 1px solid #ddd;
                    }
                    
                    .ctrl-btn {
                        width: 28px; height: 28px; border: 1px solid #ccc; background: #fff; border-radius: 4px;
                        cursor: pointer; display: flex; align-items: center; justify-content: center;
                        font-weight: bold; color: #444; font-size: 1rem; transition: background 0.2s;
                    }
                    .ctrl-btn:hover { background: #eee; }
                    
                    .qty-val {
                        min-width: 24px; text-align: center; font-weight: 700; font-size: 1rem;
                        line-height: 28px; cursor: default;
                    }
                    
                    .del-btn {
                        width: 28px; height: 28px; border: 1px solid #ffcccc; background: #fff5f5; border-radius: 4px;
                        cursor: pointer; display: flex; align-items: center; justify-content: center;
                        color: #d65b5b; transition: background 0.2s; margin-left: 4px;
                    }
                    .del-btn:hover { background: #ffe6e6; border-color: #ffb3b3; }

                    /* Tooltips */
                    .tooltip-container { position: relative; display: inline-block; vertical-align: middle; margin-left: 5px; }
                    .tooltip-trigger { color: var(--accent-color); cursor: help; font-size: 1.2rem; transition: transform 0.2s; }
                    .tooltip-trigger:hover { transform: scale(1.2); }
                    .tooltip-content {
                        visibility: hidden; width: 280px; background-color: #2c2c2c; color: #fff; text-align: left;
                        border-radius: 6px; padding: 15px; position: absolute; z-index: 1000;
                        top: 140%; left: 50%; transform: translateX(-50%); opacity: 0; transition: opacity 0.3s, top 0.3s;
                        font-size: 0.85rem; font-weight: normal; line-height: 1.5; box-shadow: 0 5px 15px var(--accent-color); pointer-events: none;
                    }
                    .tooltip-content::after { content: ""; position: absolute; bottom: 100%; left: 50%; margin-left: -6px; border-width: 6px; border-style: solid; border-color: transparent transparent #2c2c2c transparent; }
                    .tooltip-container:hover .tooltip-content { visibility: visible; opacity: 1; top: 125%; }
                </style>

                <div class="table-header">
                    <h2>Gestión de Compras y Gastos</h2>
                    <div class="table-controls" style="gap:10px;">
                        <button id="purchases-renumber-btn" class="btn btn-secondary" title="Renumerar IDs"><i class="ph ph-list-numbers"></i></button>
                        <button id="purchases-sort-btn" class="btn btn-secondary" title="Ordenar por Fecha"><i class="ph ph-sort-ascending" id="purch-sort-icon"></i></button>
                        <button id="quick-expense-btn" class="btn btn-secondary" style="border: 2px solid var(--accent-color); color: var(--accent-color);"> <i class="ph-bold ph-lightning"></i> Nuevo Gasto</button>
                        <button id="purchases-create-btn" class="btn btn-primary">+ Compra Inventario</button>
                    </div>
                </div>

                <div class="table-wrapper" style="flex-grow:1; overflow-y:auto;">
                    <table id="purchases-table" style="width:100%; border-collapse:collapse;">
                        <thead style="position:sticky; top:0; background:white; z-index:10; box-shadow:0 1px 2px var(--accent-color);">
                            <tr>
                                <th style="width: 15%; text-align:left;">Fecha</th>
                                <th style="width: 25%; text-align:left;">Tipo / Proveedor</th>
                                <th style="width: 20%; text-align:right;">Total</th>
                                <th style="width: 15%; text-align:center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="purchases-list-body"></tbody>
                    </table>
                </div>

                <div id="create-purchase-modal" class="modal-overlay hidden" style="display:none; z-index:1000;">
                    <div class="modal-content" style="width: 95%; max-width: 2000px; height: 85vh;">
                        <div class="modal-header"><h3><i class="ph ph-truck"></i> Nueva Compra de Inventario</h3><button class="modal-close-btn" id="close-purchase-modal">&times;</button></div>
                        
                        <div id="purchase-config-warning" style="display:none; flex-direction:column; align-items:center; justify-content:center; height:100%; text-align:center; padding:40px; color:#555;">
                            <i class="ph ph-warning-circle" style="font-size: 3rem; color: var(--accent-color); margin-bottom: 20px;"></i>
                            <h3 style="margin-bottom: 15px; font-weight: 800; color: #333;">Falta Configuración de Columnas</h3>
                            <p style="max-width: 500px; margin-bottom: 10px; line-height: 1.5;">Para usar el módulo de compras, el sistema necesita saber qué columnas corresponden a los datos clave.</p>
                            <div style="margin-top:50px; background: var(--accent-color-quat-opacity); color: var(--accent-color); padding: 10px 20px; border-radius: 6px; border: 1px solid var(--accent-color-medium-opacity); margin-bottom: 25px; font-size: 0.9rem;">
                                <i class="ph-bold ph-info"></i> Columnas requeridas: <strong>Nombre, Stock y Precio de Compra</strong>.
                            </div>
                            <button id="close-purchase-warning-btn" class="btn btn-primary" style="padding: 10px 30px; max-width: 800px">Cerrar Ventana</button>
                        </div>

                        <div class="purchase-modal-body" id="purchase-modal-body">
                            <div class="purchase-col" style="width: 25%;"><h4>1. Proveedor</h4><input type="text" id="purch-search-provider" class="rustic-input" placeholder="Buscar..." style="margin-bottom:10px;"><div id="purch-providers-list" class="resource-list"></div><div id="selected-provider-display" style="margin-top:10px; padding:10px; background:var(--accent-color-quat-opacity); border-radius:4px; font-size:0.9rem; color:var(--accent-color); display:none;"></div></div>
                            <div class="purchase-col" style="width: 45%;">
                                <h4>2. Productos (Costo)</h4>
                                <input type="text" id="purch-search-product" class="rustic-input" placeholder="Buscar..." style="margin-bottom:10px;">
                                <div id="purch-products-list" class="resource-list"></div>
                            </div>
                            <div class="purchase-col" style="width: 30%;">
                                <h4>3. Resumen</h4>
                                <div id="purch-cart-items" class="resource-list" style="border:none; background:transparent;"></div>
                                <div class="mt-auto" style="border-top:2px solid var(--accent-color-medium-opacity); padding-top:1rem;">
                                    <div class="flex-row justify-between" style="font-size:1.4rem; font-weight:bold; margin-bottom:1rem;"><span>Total:</span><span id="purch-total-display">$0.00</span></div>
                                    <button id="confirm-purchase-btn" class="btn btn-primary w-full" disabled>Confirmar Compra</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="quick-expense-modal" class="modal-overlay hidden" style="align-items:center; justify-content:center; display:none; z-index:1000;">
                    <div class="modal-content" style="width: 400px; max-width: 90%;">
                        <div class="modal-header" style="background: #f9fafb;"><h3 style="color: var(--accent-color);" id="quick-modal-title"><i class="ph-bold ph-lightning"></i> Gasto Rápido</h3><button class="modal-close-btn" id="close-quick-modal">&times;</button></div>
                        <div class="modal-body" style="padding: 1.5rem;">
                            <form id="quick-expense-form">
                                <div style="margin-bottom: 15px;"><label class="quick-label">Fecha</label><input type="date" id="quick-date" class="rustic-input" style="width:100%;"></div>
                                <div style="margin-bottom: 25px; text-align: center;"><label style="font-size: 0.9rem; color: #666; display: block;">Monto Total ($)</label><input type="number" id="quick-amount" class="big-amount-input" placeholder="0.00" step="0.01" required></div>
                                <div class="quick-form-group"><label class="quick-label">Proveedor</label><select id="quick-provider" class="rustic-select" style="width:100%;"><option value="">Desconocido / Ninguno / Vario</option></select></div>
                                <div class="quick-form-group"><label class="quick-label">Categoría</label><select id="quick-category" class="rustic-select" style="width:100%;"><option value="General">General</option><option value="Servicios">Servicios</option><option value="Viáticos">Viáticos</option><option value="Alquiler">Alquiler</option><option value="Mantenimiento">Mantenimiento</option><option value="Otros">Otros</option></select></div>
                                <div class="quick-form-group"><label class="quick-label">Nota</label><textarea id="quick-note" class="rustic-input" style="width:100%; height: 60px; resize: none;" placeholder="Descripción..."></textarea></div>
                                <div style="text-align: right; margin-top: 20px;"><button type="submit" id="submit-quick-btn" class="btn btn-primary w-full">Registrar Gasto</button></div>
                            </form>
                        </div>
                    </div>
                </div>

                <div id="edit-inventory-modal" class="modal-overlay hidden" style="align-items:center; justify-content:center; display:none; z-index:1000;">
                    <div class="modal-content" style="width: 450px; max-width: 90%;">
                        <div class="modal-header"><h3>Editar Datos de Compra</h3><button class="modal-close-btn" id="close-edit-inv-modal">&times;</button></div>
                        <div class="modal-body" style="padding: 1.5rem;">
                            <div style="background:var(--accent-color-quat-opacity); color:var(--accent-color); padding:12px; border-radius:6px; font-size:0.9rem; margin-bottom:20px; border:1px solid var(--accent-color);"><i class="ph ph-warning"></i> <b>Edición Limitada:</b> Solo podés editar datos de cabecera.</div>
                            <form id="edit-inventory-form">
                                <div class="quick-form-group"><label class="quick-label">Fecha</label><input type="date" id="edit-inv-date" class="rustic-input" style="width:100%;"></div>
                                <div class="quick-form-group"><label class="quick-label">Proveedor</label><select id="edit-inv-provider" class="rustic-select" style="width:100%;"></select></div>
                                <div class="quick-form-group"><label class="quick-label">Nota</label><textarea id="edit-inv-note" class="rustic-input" style="width:100%; height:80px;"></textarea></div>
                                <div style="text-align: right; margin-top: 20px;"><button type="submit" id="submit-edit-inv-btn" class="btn btn-primary">Guardar Cambios</button></div>
                            </form>
                        </div>
                    </div>
                </div>

                <div id="detail-purchase-modal" class="modal-overlay hidden" style="align-items:center; justify-content:center; display:none; z-index:1001;">
                    <div class="modal-content" style="width: 500px; max-width: 90%;">
                        <div class="modal-header"><h3>Detalle de Movimiento</h3><button class="modal-close-btn" id="close-detail-purch-modal">&times;</button></div>
                        <div class="modal-body" id="detail-purch-content" style="padding: 1.5rem;"><div class="purchase-modal-body"></div></div>
                    </div>
                </div>
            </div>
        `;
    }

    attachEvents() {
        const sortBtn = document.getElementById('purchases-sort-btn');
        if(sortBtn) sortBtn.addEventListener('click', () => {
            this.currentSortOrder = (this.currentSortOrder === 'DESC') ? 'ASC' : 'DESC';
            const icon = document.getElementById('purch-sort-icon');
            if(this.currentSortOrder === 'ASC') icon.classList.replace('ph-sort-ascending', 'ph-sort-descending');
            else icon.classList.replace('ph-sort-descending', 'ph-sort-ascending');
            this.loadHistory(this.currentSortOrder);
        });

        document.getElementById('purchases-create-btn')?.addEventListener('click', () => this.openCreateModal());
        document.getElementById('quick-expense-btn')?.addEventListener('click', () => this.openQuickModal());

        document.getElementById('close-purchase-modal')?.addEventListener('click', () => this.closeModal('create-purchase-modal'));
        document.getElementById('close-quick-modal')?.addEventListener('click', () => this.closeModal('quick-expense-modal'));
        document.getElementById('close-detail-purch-modal')?.addEventListener('click', () => this.closeModal('detail-purchase-modal'));
        document.getElementById('close-edit-inv-modal')?.addEventListener('click', () => this.closeModal('edit-inventory-modal'));

        document.getElementById('purch-search-provider')?.addEventListener('input', (e) => this.filterProviders(e.target.value));
        document.getElementById('purch-search-product')?.addEventListener('input', (e) => this.filterProducts(e.target.value));

        document.getElementById('confirm-purchase-btn')?.addEventListener('click', () => this.submitPurchase());
        document.getElementById('quick-expense-form')?.addEventListener('submit', (e) => this.submitQuickExpense(e));
        document.getElementById('edit-inventory-form')?.addEventListener('submit', (e) => this.submitInventoryEdit(e));

        document.getElementById('purchases-renumber-btn')?.addEventListener('click', async () => {
            const confirm = await pop_ups.confirm("¿Renumerar Historial?", "Se asignarán nuevos IDs consecutivos.");
            if(!confirm) return;
            try {
                const res = await fetch('/api/purchases/reset-ids.php');
                const data = await res.json();
                if(data.success) { pop_ups.info("Historial reorganizado."); await this.loadHistory(this.currentSortOrder); }
                else { pop_ups.error("Error: " + (data.message || "No se pudo renumerar")); }
            } catch(e) { console.error(e); pop_ups.error("Error de conexión"); }
        });
    }

    closeModal(id) {
        document.getElementById(id).classList.add('hidden');
        document.getElementById(id).style.display='none';
        this.editingId = null;
    }

    // --- LOGICA DE APERTURA: COTIZACIÓN + RECURSOS ---
    async openCreateModal() {
        // 1. Obtener Cotización Dólar (Igual que en Sales)
        try {
            const rateRes = await fetch('/api/table/get-rate.php');
            const rateData = await rateRes.json();

            let baseRate = 1200;
            if (rateData.avg) baseRate = parseFloat(rateData.avg);
            else if (rateData.sell) baseRate = parseFloat(rateData.sell);

            this.rates.USD = baseRate;
        } catch (e) { console.warn("Error cotización", e); }

        await this.fetchResources();

        const map = this.config;
        const overlay = document.getElementById('purchase-config-warning');
        const body = document.getElementById('purchase-modal-body');

        const hasCost = map && (map.receipt_price || map.buy_price || map.cost);

        if (!map || !map.name || !map.stock || !hasCost) {
            if(overlay) overlay.style.display = 'flex';
            if(body) body.style.display = 'none';
            const closeBtn = document.getElementById('close-purchase-warning-btn');
            if(closeBtn) closeBtn.onclick = () => this.closeModal('create-purchase-modal');
        } else {
            if(overlay) overlay.style.display = 'none';
            if(body) body.style.display = 'flex';
            this.currentPurchase = { providerId: null, providerName: null, items: [], total: 0 };
            this.updateCartUI();
            this.updateProviderUI();
        }

        const m = document.getElementById('create-purchase-modal');
        m.classList.remove('hidden');
        m.style.display='flex';
    }

    async fetchResources() {
        try {
            const [resData, prefData] = await Promise.all([
                api.getPurchaseResources(),
                api.getCurrentInventoryPreferences()
            ]);
            if(resData.success) {
                this.availableProviders = resData.providers || [];
                this.availableProducts = resData.products || [];
                this.renderProviders(this.availableProviders);
                this.renderProducts(this.availableProducts);
            }
            this.config = (prefData && prefData.success && prefData.mapping) ? prefData.mapping : {};
        } catch(e){ console.error(e); }
    }

    renderProviders(list) { const c = document.getElementById('purch-providers-list'); c.innerHTML = list.map(p => `<div class="resource-item prov-trigger" data-id="${p.id}"><b>${p.full_name}</b> <i class="ph ph-plus-circle" style="color:var(--accent-color);"></i></div>`).join(''); c.querySelectorAll('.prov-trigger').forEach(b => b.addEventListener('click', () => this.selectProvider(this.availableProviders.find(x=>x.id==b.dataset.id)))); }

    // --- RENDERIZADO PRODUCTOS CON LÓGICA DE DÓLAR (_meta_currency_buy) ---
    renderProducts(list) {
        const c = document.getElementById('purch-products-list');
        if(list.length===0){c.innerHTML='<p class="text-center" style="padding:1rem; color:#999">Sin resultados</p>';return;}

        c.innerHTML = list.map(p => {
            const disabledClass = p.can_buy ? '' : 'disabled';

            // Lógica de Moneda: Convertir si es USD
            let displayPrice = parseFloat(p.price);
            let badgeCurrency = '';

            // Verificamos si _meta_currency_buy dice "USD"
            const isUSD = (p._meta_currency_buy === 'USD');

            if (isUSD) {
                displayPrice = displayPrice * this.rates.USD;
                badgeCurrency = `<span class="badge-usd">Orig: U$S ${parseFloat(p.price).toFixed(2)}</span>`;
            }

            let priceHtml = p.can_buy
                ? `<div class="main-price">${fmtMoney(displayPrice)}</div>${badgeCurrency}`
                : `<span class="error-text" style="font-size:0.8rem;"><i class="ph ph-warning"></i> Sin Costo</span>`;

            const stockDisplay = p.stock_warning
                ? `<span class="warning-badge" title="No se detectó columna de stock">Stock ?</span>`
                : `<span class="stock-tag">Stock: ${p.stock}</span>`;

            return `
            <div class="product-card prod-trigger ${disabledClass}" data-id="${p.id}">
                <div class="prod-info">
                    <div class="prod-name">${p.name}</div>
                    <div class="prod-meta">
                        ${stockDisplay}
                    </div>
                </div>
                <div class="prod-pricing">
                    ${priceHtml}
                </div>
            </div>`;
        }).join('');

        c.querySelectorAll('.prod-trigger').forEach(b => {
            if(b.classList.contains('disabled')) return;
            b.addEventListener('click', () => {
                const p = this.availableProducts.find(x => x.id == b.dataset.id);

                // Conversión al agregar al carrito (Matemática interna)
                let finalPriceARS = parseFloat(p.price);
                if (p._meta_currency_buy === 'USD') {
                    finalPriceARS = finalPriceARS * this.rates.USD;
                }

                this.addToCart(p, finalPriceARS);
            });
        });
    }

    selectProvider(p) { this.currentPurchase.providerId = p.id; this.currentPurchase.providerName = p.full_name; this.updateProviderUI(); }
    updateProviderUI() { const d = document.getElementById('selected-provider-display'); if(this.currentPurchase.providerId) { d.style.display='block'; d.innerHTML = `Proveedor: <b>${this.currentPurchase.providerName}</b> <span id="rm-prov" style="float:right; cursor:pointer;">&times;</span>`; document.getElementById('rm-prov').addEventListener('click', (e)=>{ e.stopPropagation(); this.selectProvider({id:null, full_name:null}); }); } else d.style.display='none'; }

    // --- AGREGAR CARRITO (Con precio ya convertido) ---
    addToCart(p, overridePrice = null) {
        const exist = this.currentPurchase.items.find(i => i.id == p.id);
        const priceToUse = overridePrice !== null ? overridePrice : parseFloat(p.price);

        if(exist) {
            exist.quantity++;
            exist.price = priceToUse;
        } else {
            this.currentPurchase.items.push({...p, quantity:1, price: priceToUse});
        }
        this.calcTotal();
    }

    calcTotal() {
        this.currentPurchase.total = this.currentPurchase.items.reduce((s,i)=>s+(i.price*i.quantity),0);
        document.getElementById('purch-total-display').textContent = `${fmtMoney(this.currentPurchase.total)}`;
        this.updateCartUI();
    }

    // --- CARRITO REDISEÑADO CON BOTONERA IMAGEN ---
    updateCartUI() {
        const c = document.getElementById('purch-cart-items');
        if(!this.currentPurchase.items.length) {
            c.innerHTML='<div style="text-align:center; color:#999; margin-top:50px;">Carrito vacío</div>';
            document.getElementById('confirm-purchase-btn').disabled=true;
            return;
        }
        document.getElementById('confirm-purchase-btn').disabled=false;

        c.innerHTML = this.currentPurchase.items.map((i,idx) => `
            <div class="cart-card">
                <div class="cart-row-top">
                    <div class="cart-name">${i.name}</div>
                    <div class="cart-unit-price">${fmtMoney(i.price)} c/u</div>
                </div>
                
                <div class="cart-row-bottom">
                    <div class="cart-total">${fmtMoney(i.price * i.quantity)}</div>
                    
                    <div class="cart-controls-wrapper">
                        <button class="ctrl-btn sub" data-idx="${idx}">-</button>
                        <div class="qty-val">${i.quantity}</div>
                        <button class="ctrl-btn add" data-idx="${idx}">+</button>
                        <button class="del-btn del" data-idx="${idx}" title="Eliminar">
                            <i class="ph ph-trash"></i>
                        </button>
                    </div>
                </div>
            </div>`
        ).join('');

        // Listeners
        c.querySelectorAll('.sub').forEach(b => b.addEventListener('click', ()=>{
            const idx = parseInt(b.dataset.idx);
            const item = this.currentPurchase.items[idx];
            item.quantity--;
            if(item.quantity < 1) this.currentPurchase.items.splice(idx, 1);
            this.calcTotal();
        }));
        c.querySelectorAll('.add').forEach(b => b.addEventListener('click', ()=>{
            const idx = parseInt(b.dataset.idx);
            this.currentPurchase.items[idx].quantity++;
            this.calcTotal();
        }));
        c.querySelectorAll('.del').forEach(b => b.addEventListener('click', ()=>{
            const idx = parseInt(b.dataset.idx);
            this.currentPurchase.items.splice(idx, 1);
            this.calcTotal();
        }));
    }

    filterProviders(t) { this.renderProviders(this.availableProviders.filter(p=>p.full_name.toLowerCase().includes(t.toLowerCase()))); }
    filterProducts(t) { this.renderProducts(this.availableProducts.filter(p=>p.name.toLowerCase().includes(t.toLowerCase()))); }

    async submitPurchase() {
        const btn = document.getElementById('confirm-purchase-btn');
        btn.textContent = 'Procesando...';
        btn.disabled=true;
        try {
            const payload = {
                provider_id: this.currentPurchase.providerId,
                total: this.currentPurchase.total,
                items: this.currentPurchase.items.map(i=>({
                    id:i.id,
                    nombre_producto:i.name,
                    cantidad:i.quantity,
                    precio_unitario:i.price,
                    subtotal:i.price*i.quantity
                }))
            };
            const res = await api.createPurchase(payload);
            if(res.success) {
                this.closeModal('create-purchase-modal');
                await this.loadHistory();
                pop_ups.success('Compra registrada');
            }
        } catch(e){ console.error(e); }
        btn.textContent = 'Confirmar Compra';
        btn.disabled=false;
    }

    async showDetails(id) {
        try {
            const res = await api.getPurchaseDetails(id);
            if (!res.success) throw new Error(res.message || "Error al cargar detalles");
            const p = res.purchase; const items = res.items || [];
            const modal = document.getElementById('detail-purchase-modal');
            if(!modal) { alert("Error: No se encontró el modal #detail-purchase-modal"); return; }
            const bodyContainer = modal.querySelector('.purchase-modal-body');
            let html = `<div style="text-align:center; margin-bottom:20px; border-bottom:2px dashed var(--ticket-color); padding-bottom:15px;"><div style="font-size:1.3rem; font-weight:900; letter-spacing:1px; color:var(--ticket-color);">COMPRA #${p.id}</div><div style="font-size:0.9rem; margin-top:5px;">${fmtDate(p.created_at)}</div></div>`;
            const provName = p.provider_name || p.provider_real_name || '-'; const concept = (provName !== '-') ? provName : (p.category || 'Gasto General');
            html += `<div class="ticket-row" style="margin-bottom:15px;"><div style="display:flex; flex-direction:column; width:100%;"><span style="font-size:0.7rem; text-transform:uppercase; color:#666; font-weight:bold;">PROVEEDOR / CONCEPTO:</span><span style="font-size:1.1rem; font-weight:800; color:var(--ticket-color);">${concept}</span></div></div>`;
            if (items.length > 0) {
                html += '<h4>PRODUCTOS INGRESADOS</h4>';
                html += `<table style="width:100%; border-collapse:collapse; margin-bottom:15px;"><thead style="border-bottom:1px dashed var(--ticket-color);"><tr><th style="text-align:left; padding:5px;">Prod</th><th style="text-align:center; padding:5px;">Cant</th><th style="text-align:right; padding:5px;">Costo</th></tr></thead><tbody>`;
                html += items.map(i => `
                    <tr>
                        <td style="padding:8px 5px; font-size:0.9rem;">
                            <b>${i.product_name}</b>
                            <div style="font-size:0.75rem; color:#888;">ID: ${i.product_id}</div>
                        </td>
                        <td style="padding:8px 5px; text-align:center; font-weight:bold;">${parseFloat(i.quantity)}</td>
                        <td style="padding:8px 5px; text-align:right;">${fmtMoney(i.unit_price)}</td>
                    </tr>`).join('');
                html += `</tbody></table>`;
            } else { html += `<div style="padding:15px; text-align:center; font-style:italic; color:#666; margin-bottom:15px; border: 1px dashed #ccc;">Gasto sin ítems de inventario</div>`; }
            html += `<div id="detail-total-section"><span>TOTAL PAGADO</span><span>${fmtMoney(p.total || p.total_amount)}</span></div>`;
            if(p.notes) { html += `<div id="detail-notes"><strong>NOTAS:</strong><br>${p.notes}</div>`; }
            bodyContainer.innerHTML = html; modal.classList.remove('hidden'); modal.style.display = 'flex';
        } catch (e) { pop_ups.error("Error: " + e.message); }
    }

    fillProviderSelect(selectId, selectedId = null) {
        const sel = document.getElementById(selectId);
        if(!sel) return;
        sel.innerHTML = '<option value="">-- Desconocido / Ninguno --</option>';

        if (this.availableProviders && this.availableProviders.length > 0) {
            this.availableProviders.forEach(p => {
                const opt = document.createElement('option');
                opt.value = p.id;
                opt.textContent = p.full_name;
                if(p.id == selectedId) opt.selected = true;
                sel.appendChild(opt);
            });
        }
    }

    async openQuickModal(d){if(!this.availableProviders.length)await this.fetchResources();this.fillProviderSelect('quick-provider');const m=document.getElementById('quick-expense-modal'),f=document.getElementById('quick-expense-form'),t=document.getElementById('quick-modal-title'),b=document.getElementById('submit-quick-btn');f.reset();document.getElementById('quick-date').valueAsDate=new Date;if(d){this.editingId=d.id;t.innerHTML='<i class="ph ph-pencil-simple"></i> Editar Gasto';b.textContent="Actualizar Gasto";document.getElementById('quick-amount').value=d.total;document.getElementById('quick-provider').value=d.provider_id||"";document.getElementById('quick-category').value=d.category||"General";document.getElementById('quick-note').value=d.notes||"";if(d.created_at)document.getElementById('quick-date').value=d.created_at.split(' ')[0]}else{this.editingId=null;t.innerHTML='<i class="ph-bold ph-lightning"></i> Gasto Rápido';b.textContent="Registrar Gasto"}m.classList.remove('hidden');m.style.display='flex';document.getElementById('quick-amount').focus()}
    async openEditInventoryModal(d){if(!this.availableProviders.length)await this.fetchResources();this.fillProviderSelect('edit-inv-provider');this.editingId=d.id;document.getElementById('edit-inv-provider').value=d.provider_id||"";document.getElementById('edit-inv-note').value=d.notes||"";if(d.created_at)document.getElementById('edit-inv-date').value=d.created_at.split(' ')[0];const m=document.getElementById('edit-inventory-modal');m.classList.remove('hidden');m.style.display='flex'}
    async editPurchase(i){try{const r=await api.getPurchaseDetails(i);if(!r.success)throw new Error("Error");const p=r.purchase;if(p.category)this.openQuickModal(p);else this.openEditInventoryModal(p)}catch(e){pop_ups.error("Error al editar: "+e.message)}}
    async submitQuickExpense(e){e.preventDefault();const b=document.getElementById('submit-quick-btn');b.disabled=true;const ot=b.textContent;b.textContent='Procesando...';const a=parseFloat(document.getElementById('quick-amount').value);if(isNaN(a)||a<=0){pop_ups.warning("Monto inválido");b.disabled=false;b.textContent=ot;return}const p={id:this.editingId,total:a,provider_id:document.getElementById('quick-provider').value||null,category:document.getElementById('quick-category').value||'General',notes:document.getElementById('quick-note').value||null,created_at:document.getElementById('quick-date').value+' '+new Date().toLocaleTimeString(),items:[]};const ep=this.editingId?'/api/purchases/update.php':'/api/purchases/create.php';try{const r=await this.sendRequest(ep,p);if(r.success){this.closeModal('quick-expense-modal');this.loadHistory(this.currentSortOrder);pop_ups.success(this.editingId?'Actualizado':'Registrado')}else pop_ups.error(r.message)}catch(e){pop_ups.error("Error de conexión")}b.disabled=false;b.textContent=ot}
    async submitInventoryEdit(e){e.preventDefault();const b=document.getElementById('submit-edit-inv-btn');b.disabled=true;b.textContent='Guardando...';const p={id:this.editingId,provider_id:document.getElementById('edit-inv-provider').value||null,notes:document.getElementById('edit-inv-note').value||null,created_at:document.getElementById('edit-inv-date').value+' 12:00:00'};try{const r=await this.sendRequest('/api/purchases/update.php',p);if(r.success){this.closeModal('edit-inventory-modal');this.loadHistory(this.currentSortOrder);pop_ups.success('Actualizado')}else pop_ups.error(r.message)}catch(e){pop_ups.error("Error")}b.disabled=false;b.textContent='Guardar Cambios'}
    async deletePurchase(i){if(!await pop_ups.confirm("Eliminar","¿Seguro?"))return;try{const r=await this.sendRequest('/api/purchases/delete.php',{id:i});if(r.success){pop_ups.info("Eliminado");this.loadHistory(this.currentSortOrder)}else pop_ups.error("Error")}catch(e){pop_ups.error("Error")}}
    async sendRequest(u,d){const r=await fetch(u,{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(d)});return await r.json()}
}

window.purchasesModule = new PurchaseModule();
export const purchaseModuleInstance = window.purchasesModule;
export const purchasesModule = window.purchasesModule;