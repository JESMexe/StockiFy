/* public/assets/js/sales/sales.js */
import {
    getSaleResources,
    createSale,
    getSalesHistory,
    getEmployeeList,
    getCurrentInventoryPreferences,
    getSaleDetails
} from '../api.js';
import { pop_ups } from '../notifications/pop-up.js';

/* --- HELPERS --- */
const fmtMoney = (amount) => {
    if (amount === undefined || amount === null || isNaN(amount)) return '$ 0,00';
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS', minimumFractionDigits: 2 }).format(amount);
};

const fmtDate = (dateString) => {
    if (!dateString) return '-';
    const safeDate = dateString.replace(/-/g, '/');
    const d = new Date(safeDate);
    if (isNaN(d.getTime())) return dateString;
    return d.toLocaleDateString() + ' ' + d.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
};

export class SalesModule {
    constructor() {
        this.containerId = 'sales';
        this.isInitialized = false;
        this.currentSale = { items: [], payments: [], subtotal_items: 0, total_surcharges: 0, total_final: 0 };
        this.resources = { products: [], customers: [], paymentMethods: [], employees: [], config: null };
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

    renderBaseStructure() {
        return `
            <div class="sales-layout">
                <div class="table-header">
                    <h2>Gestión de Ventas</h2>
                    <div class="table-controls">
                        <button id="sales-renumber-btn" class="btn btn-secondary" title="Renumerar IDs (1, 2, 3...) desde el inicio"><i class="ph ph-list-numbers"></i></button>
                        <button id="sales-sort-btn" class="btn btn-secondary" title="Ordenar por Fecha"><i class="ph ph-sort-ascending" id="sales-sort-icon"></i></button>
                        <button id="sales-create-btn" class="btn btn-primary">+ Nueva Venta</button>
                    </div>
                </div>

                <div class="table-wrapper" style="flex-grow:1; overflow-y:auto;">
                    <table id="sales-table">
                        <thead>
                            <tr>
                                <th style="width: 15%;">Fecha</th>
                                <th style="width: 25%;">Cliente</th>
                                <th style="width: 25%;">Vendedor</th>
                                <th style="width: 20%; text-align:right;">Total</th>
                                <th style="width: 15%; text-align:center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="sales-list-body"></tbody>
                    </table>
                </div>

                <div id="create-sale-modal" class="modal-overlay hidden" style="display:none; z-index:1000;">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3><i class="ph-bold ph-shopping-cart"></i> Registrar Venta</h3>
                            <button class="modal-close-btn" id="close-sale-modal">&times;</button>
                        </div>
                        
                        <div id="config-warning-overlay" style="display:none; padding:20px; text-align:center;">
                            <p>Falta configuración de columnas.</p>
                            <a href="/dashboard.php" class="btn btn-sm btn-secondary">Ir a Configuración</a>
                        </div>

                        <div class="purchase-modal-body" id="sale-modal-body">
                            <div class="purchase-col" style="flex: 1.2;">
                                <h4>1. Productos</h4>
                                
                                <div style="display:flex; gap:8px; margin-bottom:15px; align-items: center;">
                                    <input type="text" id="sale-search-product" class="rustic-input" placeholder="Buscar o Escanear..." autocomplete="off" style="flex:1; width: 100%;">
                                    
                                    <button id="btn-toggle-manual" class="btn btn-secondary" title="Agregar ítem manual / servicio" style="color: var(--accent-color); white-space: nowrap;">
                                        <i class="ph-bold ph-hand-pointing"></i> Manual
                                    </button>
                                </div>

                                <div id="manual-item-form" style="display:none; background:#f9f9f9; padding:10px; border:1px dashed var(--accent-color); border-radius:6px; margin-bottom:15px;">
                                    <div style="font-size:0.8rem; font-weight:bold; color:var(--accent-color); margin-bottom:5px;">Ítem Manual / Servicio</div>
                                    
                                    <input type="text" id="manual-name" class="rustic-input" placeholder="Descripción (Ej: Mano de Obra)" style="margin-bottom:8px; height:35px; width:100%;">
                                    
                                    <div style="display:flex; gap:8px; align-items:center;">
                                        <div style="flex: 1;"> <input type="number" id="manual-price" class="rustic-input" placeholder="$ Precio" style="width: 100%; height:35px;">
                                        </div>

                                        <div style="height: 20px; border-left: 1px solid #ddd; margin: 0 2px;"></div>
                                        <span style="font-size: 0.85rem; font-weight: 600; color: #555;">Cant:</span>

                                        <input type="number" id="manual-qty" class="rustic-input" value="1" style="width:50px; height:35px; text-align:center;">
                                        
                                        <button id="btn-add-manual" class="btn btn-primary" style="height:35px; width: 35px; padding: 0; flex-shrink: 0;"><i class="ph ph-plus"></i></button>
                                    </div>
                                </div>

                                <div id="sale-products-list" class="scrollable-list"></div>
                            </div>

                            <div class="purchase-col" style="flex: 1;">
                                <h4>2. Carrito</h4>
                                <div id="sale-cart-items" class="scrollable-list" style="background: #FFF; border-radius: 8px;">
                                    <div style="text-align:center; color:#999; margin-top:50px;">Carrito vacío</div>
                                </div>
                                <div style="text-align:right; padding-top:10px; font-weight:bold; border-top:2px dashed var(--color-black); flex-shrink:0;">
                                    Subtotal: <span id="cart-subtotal-display">$0,00</span>
                                </div>
                            </div>

                            <div class="purchase-col" style="flex: 1.1;">
                                <h4>3. Cierre y Pagos</h4>
                                <div class="scrollable-list" style="padding-right: 5px; overflow-x: visible;">
                                    <div class="form-section">
                                        <label class="form-section-title">Notas</label>
                                        <textarea id="sale-notes" class="rustic-input" placeholder="..." style="height:50px;"></textarea>
                                    </div>
                                    <div class="form-section">
                                        <label class="form-section-title">Cliente</label>
                                        <select id="sale-customer" class="rustic-select"></select>
                                    </div>
                                    <div class="form-section">
                                        <div style="display:flex; gap:10px; align-items: flex-end;">
                                            <div style="flex:1;">
                                                <label class="form-section-title">Vendedor</label>
                                                <select id="sale-seller" class="rustic-select"></select>
                                            </div>
                                            <div style="width: 80px;">
                                                <label class="form-section-title">Comisión %</label>
                                                <input type="number" id="sale-commission-pct" class="rustic-input" value="0">
                                            </div>
                                        </div>
                                        <div id="commission-display" style="text-align:right; font-size:0.75rem; color:#666; margin-top:5px; font-weight:600;">Comisión: $0,00</div>
                                    </div>
                                    <div class="form-section">
                                        <label class="form-section-title">Calculadora de Pagos</label>
                                        <div class="payment-row">
                                            <input type="number" id="pay-input-amount" class="rustic-input" placeholder="Monto" style="flex:1;">
                                            <select id="pay-method-select" class="rustic-select" style="flex:1.2;"></select>
                                            <button id="btn-add-payment"><i class="ph ph-plus" style="font-weight:bold;"></i></button>
                                        </div>
                                        <div id="payments-list" style="margin-top:10px;"><p style="color:#999; text-align:center; font-size:0.8rem;">Sin pagos</p></div>
                                    </div>
                                </div> 
                                <div class="totals-box">
                                    <div class="flex-row total-line"><span>Productos:</span> <span id="checkout-subtotal">$0,00</span></div>
                                    <div class="flex-row total-line" style="color:var(--color-gray);"><span>Recargos:</span> <span id="checkout-surcharges">$0,00</span></div>
                                    <div class="flex-row total-line total-final"><span>Total:</span> <span id="checkout-total-final">$0,00</span></div>
                                    <!--<div class="flex-row total-line" style="margin-top:10px; color:var(--sale-green);"><span>Pagado:</span> <span id="checkout-paid">$0,00</span></div>-->
                                    <div class="flex-row total-line" style="font-weight:bold;" id="change-row"><span>Falta:</span> <span id="checkout-diff">$0,00</span></div>
                                </div>
                                <button id="confirm-sale-btn" disabled>Confirmar Venta</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="detail-sale-modal" class="modal-overlay hidden" style="display:none; z-index:1050;">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3>TICKET DE VENTA</h3>
                            <button class="modal-close-btn" id="close-detail-modal">&times;</button>
                        </div>
                        <div class="purchase-modal-body">
                            </div>
                    </div>
                </div>
            </div>
        `;
    }

    attachEvents() {
        const sortBtn = document.getElementById('sales-sort-btn');
        if(sortBtn) sortBtn.addEventListener('click', () => {
            this.currentSortOrder = (this.currentSortOrder === 'DESC') ? 'ASC' : 'DESC';
            const icon = document.getElementById('sales-sort-icon');
            if(icon) {
                if(this.currentSortOrder === 'ASC') icon.classList.replace('ph-sort-ascending', 'ph-sort-descending');
                else icon.classList.replace('ph-sort-descending', 'ph-sort-ascending');
            }
            this.loadHistory(this.currentSortOrder);
        });

        document.getElementById('sales-create-btn')?.addEventListener('click', () => this.openCreateModal());
        document.getElementById('close-sale-modal')?.addEventListener('click', () => this.closeModal('create-sale-modal'));
        document.getElementById('close-detail-modal')?.addEventListener('click', () => this.closeModal('detail-sale-modal'));

        const searchInput = document.getElementById('sale-search-product');
        if(searchInput) {
            searchInput.addEventListener('input', (e) => this.filterProducts(e.target.value));
            searchInput.addEventListener('keydown', (e) => { if(e.key === 'Enter') { e.preventDefault(); this.handleScan(e.target.value); }});
        }

        // --- LÓGICA VENTA MANUAL ---
        document.getElementById('btn-toggle-manual')?.addEventListener('click', () => {
            const form = document.getElementById('manual-item-form');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
            if(form.style.display === 'block') document.getElementById('manual-name').focus();
        });

        document.getElementById('btn-add-manual')?.addEventListener('click', () => this.addManualItem());
        document.getElementById('manual-price')?.addEventListener('keypress', (e) => { if(e.key==='Enter') this.addManualItem(); });

        document.getElementById('btn-add-payment')?.addEventListener('click', () => this.addPayment());
        document.getElementById('pay-input-amount')?.addEventListener('keypress', (e) => { if(e.key==='Enter') this.addPayment(); });
        document.getElementById('sale-commission-pct')?.addEventListener('input', () => this.calculateCommission());
        document.getElementById('confirm-sale-btn')?.addEventListener('click', () => this.submitSale());

        document.getElementById('sales-renumber-btn')?.addEventListener('click', async () => {
            const confirm = await pop_ups.confirm(
                "¿Renumerar Historial?",
                "Se asignarán nuevos IDs consecutivos (1, 2, 3...) a TODAS las ventas según su fecha. Ideal para limpiar después de pruebas o borrados."

            );
            if(!confirm) return;

            try {
                const res = await fetch('/api/sales/reset-ids.php'); // Asegurate que la ruta exista
                const data = await res.json();

                if(data.success) {
                    pop_ups.info("Historial reorganizado: IDs consecutivos.");
                    await this.loadHistory(this.currentSortOrder); // Recargar la tabla
                } else {
                    pop_ups.error("Error: " + (data.message || "No se pudo renumerar"));
                }
            } catch(e) {
                console.error(e);
                pop_ups.error("Error de conexión");
            }
        });
    }

    closeModal(id) { document.getElementById(id).classList.add('hidden'); document.getElementById(id).style.display='none'; }

    async loadHistory(order='desc') {
        const b = document.getElementById('sales-list-body');
        b.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:20px;">Cargando...</td></tr>';
        try {
            const data = await getSalesHistory(order);
            if(!data.success || !data.sales || data.sales.length === 0) {
                b.innerHTML='<tr><td colspan="5" style="text-align:center; padding:20px;">Sin ventas registradas</td></tr>';
                return;
            }
            b.innerHTML = data.sales.map(s => {
                const dateStr = s.created_at; const total = s.total; const seller = s.seller_name; const comm = s.commission;
                let payBadge = '';
                if (s.payments && s.payments.length > 0) {
                    const first = s.payments[0];
                    const extra = s.payments.length - 1;
                    const plus = extra > 0 ? ` <b style="color:var(--accent-color);">+${extra}</b>` : '';
                    payBadge = `<div style="margin-top:4px;"><span style="color:#555; font-size:0.75rem; background:#f4f4f4; padding:2px 6px; border-radius:4px;">${first}${plus}</span></div>`;
                } else { payBadge = `<div style="margin-top:4px;"><span style="color:#eee; font-size:0.75rem;">-</span></div>`; }
                let sellerHtml = '<span style="color:#ccc;">-</span>';
                if (seller && seller !== '-' && seller !== 'No asignado') {
                    sellerHtml = `<div style="line-height:1.2;"><div style="font-weight:600; color:#333;">${seller}</div>${comm > 0 ? `<div style="font-size:0.75rem; color:var(--sale-green); font-weight:600;">Com: ${fmtMoney(comm)}</div>` : ''}</div>`;
                }
                return `<tr style="border-bottom:1px solid #eee;"><td style="padding:10px 15px;">${fmtDate(dateStr)}<div style="font-size:0.75rem; color:#999;">#${s.id}</div></td><td style="padding:10px 15px;"><div style="font-weight:600; color:#444;">${s.customer_name}</div></td><td style="padding:10px 15px;">${sellerHtml}</td><td style="padding:10px 15px; text-align:right;"><div style="font-weight:800; color:var(--sale-green); font-size:1.1rem; line-height:1.2;">${fmtMoney(total)}</div>${payBadge}</td><td style="padding:10px 15px; text-align:center;"><div class="btn-icon-group" style="justify-content:center;"><button class="action-btn view" title="Ver Ticket" onclick="window.salesModuleInstance.showDetails('${s.id}')"><i class="ph ph-receipt"></i></button><button class="action-btn edit" title="Editar" onclick="window.salesModuleInstance.editSale('${s.id}')"><i class="ph ph-pencil-simple"></i></button><button class="action-btn delete" title="Eliminar" onclick="window.salesModuleInstance.deleteSale('${s.id}')"><i class="ph ph-trash"></i></button></div></td></tr>`;
            }).join('');
        } catch(e) { console.error(e); b.innerHTML='<tr><td colspan="5" style="text-align:center; color:red;">Error de visualización</td></tr>'; }
    }

    async fetchResources() {
        try {
            const [prefRes, data, empRes] = await Promise.all([
                getCurrentInventoryPreferences(), getSaleResources(), getEmployeeList()
            ]);
            this.resources.inventoryId = prefRes.id || prefRes.inventory_id || null;
            this.resources.config = prefRes.mapping || {};
            if(data.success) {
                this.resources.products = data.products || [];
                this.resources.customers = data.customers || [];
                this.resources.paymentMethods = data.payment_methods || [];
            }
            if(empRes.success) this.resources.employees = empRes.employees || [];
        } catch(e) { console.error(e); }
    }

    async openCreateModal() {
        await this.fetchResources();
        const map = this.resources.config;
        if (!map || !map.name || !map.sale_price) {
            document.getElementById('config-warning-overlay').style.display = 'block';
            document.getElementById('sale-modal-body').style.display = 'none';
        } else {
            document.getElementById('config-warning-overlay').style.display = 'none';
            document.getElementById('sale-modal-body').style.display = 'flex';
        }

        this.currentSale = { items: [], payments: [], subtotal_items: 0, total_surcharges: 0, total_final: 0 };
        document.getElementById('sale-notes').value = '';
        // Reset manual form
        document.getElementById('manual-item-form').style.display = 'none';
        document.getElementById('manual-name').value = '';
        document.getElementById('manual-price').value = '';
        document.getElementById('manual-qty').value = '1';

        this.renderProducts(this.resources.products);
        this.fillSelect('sale-customer', this.resources.customers, 'id', 'full_name', 'Cliente General');
        this.fillSelect('sale-seller', this.resources.employees, 'id', 'full_name', 'Sin Vendedor');
        this.fillSelect('pay-method-select', this.resources.paymentMethods, 'id', 'name', null);

        setTimeout(() => document.getElementById('sale-search-product').focus(), 100);
        this.updateCartUI();
        this.recalcSale();

        const modal = document.getElementById('create-sale-modal');
        modal.classList.remove('hidden'); modal.style.display = 'flex';
    }

    async showDetails(id) {
        try {
            const res = await getSaleDetails(id);
            if(!res.success) throw new Error(res.message || "Error al cargar detalles");
            const s = res.sale;
            s.items = res.items || [];
            s.payments = res.payments || [];
            const bodyContainer = document.querySelector('#detail-sale-modal .purchase-modal-body');

            let html = `<div style="text-align:center; margin-bottom:20px; border-bottom:2px dashed var(--ticket-color); padding-bottom:15px;"><div style="font-size:1.3rem; font-weight:900; letter-spacing:1px;">TICKET #${s.id}</div><div style="font-size:0.9rem; margin-top:5px;">${fmtDate(s.created_at)}</div></div>`;
            html += `<div class="ticket-row" style="margin-bottom:10px;"><div style="display:flex; flex-direction:column; width:100%;"><span style="font-size:0.7rem; text-transform:uppercase; color:#666; font-weight:bold;">CLIENTE:</span><span style="font-size:1rem; font-weight:800;">${s.customer_name || 'Consumidor Final'}</span></div></div>`;

            let sellerDisplay = "No especificado"; let commissionDisplay = "";
            if (s.seller_name && s.seller_name !== '-') {
                sellerDisplay = s.seller_name;
                const comm = s.commission_amount ? parseFloat(s.commission_amount) : 0;
                commissionDisplay = `<span style="font-size:0.8rem; background:#eee; padding:2px 6px; border-radius:4px; margin-left: auto;">Com: ${fmtMoney(comm)}</span>`;
            }
            html += `<div class="ticket-row" style="margin-bottom:15px; border-bottom:1px dotted #ccc; padding-bottom:10px;"><div style="display:flex; flex-direction:column; width:100%;"><span style="font-size:0.7rem; text-transform:uppercase; color:#666; font-weight:bold;">ATENDIDO POR:</span><div style="display:flex; justify-content:space-between; align-items:center;"><span style="font-size:1rem; font-weight:800;">${sellerDisplay}</span>${commissionDisplay}</div></div></div>`;

            bodyContainer.innerHTML = html;
            bodyContainer.insertAdjacentHTML('beforeend', '<h4>PRODUCTOS</h4>');
            const productsTable = document.createElement('table');
            productsTable.innerHTML = `<thead><tr><th style="text-align:left;">DESCRIPCIÓN</th><th style="text-align:center; width:40px;">CANT</th><th style="text-align:right;">TOTAL</th></tr></thead><tbody id="detail-items-list"></tbody>`;
            bodyContainer.appendChild(productsTable);

            document.getElementById('detail-items-list').innerHTML = s.items.map(i => `<tr><td style="text-align:left; line-height:1.2;">${i.product_name}<div style="font-size:0.75rem; color:#666;">Unit: ${fmtMoney(i.price)}</div></td><td style="text-align:center; font-weight:bold; vertical-align:top;">${parseFloat(i.quantity)}</td><td style="text-align:right; font-weight:800; vertical-align:top;">${fmtMoney(i.subtotal)}</td></tr>`).join('');

            bodyContainer.insertAdjacentHTML('beforeend', `<div id="detail-total-section"><span id="detail-total-label">TOTAL A PAGAR</span><span id="detail-total">${fmtMoney(s.total_final)}</span></div>`);
            bodyContainer.insertAdjacentHTML('beforeend', '<h4>FORMA DE PAGO</h4>');
            const paymentsTable = document.createElement('table'); paymentsTable.innerHTML = '<tbody id="detail-payments-list"></tbody>';
            bodyContainer.appendChild(paymentsTable);
            document.getElementById('detail-payments-list').innerHTML = s.payments.map(p => `<tr class="ticket-row" style="border:none;"><td style="text-align:left; font-weight:600;">${p.payment_method_name}</td><td style="text-align:right; font-weight:800;">${fmtMoney(p.amount)}</td></tr>`).join('');

            if(s.notes) bodyContainer.insertAdjacentHTML('beforeend', `<div id="detail-notes"><strong>NOTAS:</strong><br>${s.notes}</div>`);

            const modal = document.getElementById('detail-sale-modal'); modal.classList.remove('hidden'); modal.style.display = 'flex';
        } catch(e) { pop_ups.error("Error: " + e.message); }
    }

    async editSale(id) {
        if(!await pop_ups.confirm("¿Editar Venta?", "Esto ELIMINARÁ la venta actual, devolverá el stock y cargará los productos en el carrito.")) return;
        try {
            const res = await getSaleDetails(id); if(!res.success) throw new Error("Error leyendo venta"); const oldSale = res.sale;
            await fetch('/api/sales/delete.php', {method:'POST', body:JSON.stringify({id})});
            await this.openCreateModal();
            document.getElementById('sale-customer').value = oldSale.customer_id || '';
            document.getElementById('sale-seller').value = oldSale.seller_id || '';
            document.getElementById('sale-notes').value = oldSale.notes || '';
            this.currentSale.items = oldSale.items.map(i => ({ id: i.product_id, nombre: i.product_name, cantidad: parseFloat(i.quantity), precio: parseFloat(i.price), max_stock: 9999 }));
            this.updateCartUI(); this.recalcSale();
            pop_ups.info("Venta cargada para edición");
        } catch(e) { pop_ups.error("Error: " + e.message); }
    }

    async deleteSale(id) {
        if(!await pop_ups.confirm("Eliminar Venta", "Se devolverá el stock y anulará el registro. ¿Seguro?")) return;
        try { await fetch('/api/sales/delete.php', {method:'POST', body:JSON.stringify({id})}); this.loadHistory(); pop_ups.info("Venta eliminada"); } catch(e){ pop_ups.error("Error al eliminar"); }
    }

    renderProducts(list) {
        const c = document.getElementById('sale-products-list');
        c.innerHTML = list.map(p => {
            const stock = parseFloat(p.stock);
            const style = stock > 0 ? '' : 'opacity:0.6; pointer-events:none; filter:grayscale(1);';
            const badge = stock > 0 ? `<span style="font-size:0.75rem; color:#666;">Stock: ${stock}</span>` : `<span style="font-size:0.75rem; color:red; font-weight:bold;">AGOTADO</span>`;
            return `<div class="resource-item prod-trigger" data-id="${p.id}" style="${style}"><div style="flex:1; overflow:hidden; padding-right:10px;"><div style="font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${p.name}</div>${badge}</div><div style="font-weight:700; color:var(--sale-green); font-size:1rem;">${fmtMoney(p.price)}</div></div>`;
        }).join('');
        c.querySelectorAll('.prod-trigger').forEach(b => b.addEventListener('click', () => this.addToCart(this.resources.products.find(x=>x.id==b.dataset.id))));
    }

    filterProducts(t) {
        const term = t.toLowerCase().trim();
        if(!term) { this.renderProducts(this.resources.products); return; }
        const filtered = this.resources.products.filter(p => (p.name && p.name.toLowerCase().includes(term)) || Object.values(p).some(val => val && String(val).toLowerCase().includes(term)));
        this.renderProducts(filtered);
    }

    handleScan(t) {
        const term = t.toLowerCase().trim();
        if(!term) return;
        let match = this.resources.products.find(p => Object.values(p).some(val => val && String(val).toLowerCase() === term));
        if (!match) {
            const visualMatches = this.resources.products.filter(p => (p.name && p.name.toLowerCase().includes(term)) || Object.values(p).some(val => val && String(val).toLowerCase().includes(term)));
            if (visualMatches.length === 1) match = visualMatches[0];
        }
        if (match) {
            this.addToCart(match);
            const input = document.getElementById('sale-search-product'); input.value = ''; input.focus(); this.filterProducts('');
            pop_ups.success(`Agregado: ${match.name}`);
        } else { pop_ups.warning("Producto no encontrado"); }
    }

    // --- NUEVA FUNCIÓN: AGREGAR ÍTEM MANUAL ---
    addManualItem() {
        const nameInput = document.getElementById('manual-name');
        const priceInput = document.getElementById('manual-price');
        const qtyInput = document.getElementById('manual-qty');

        const name = nameInput.value.trim();
        const price = parseFloat(priceInput.value);
        const qty = parseFloat(qtyInput.value) || 1;

        if (!name) return pop_ups.warning("Ingresá una descripción");
        if (isNaN(price) || price <= 0) return pop_ups.warning("Ingresá un precio válido");

        // Agregamos al carrito con ID nulo (backend sabrá que es manual)
        // Usamos un 'fake_id' único para poder borrarlo del carrito visualmente
        this.currentSale.items.push({
            id: null,
            fake_id: 'man_' + Date.now(), // Para gestión interna del array
            nombre: name,
            cantidad: qty,
            precio: price,
            max_stock: 999999 // Infinito
        });

        // UX: Limpiar y Feedback
        nameInput.value = '';
        priceInput.value = '';
        qtyInput.value = '1';
        document.getElementById('manual-item-form').style.display = 'none'; // Ocultar form
        pop_ups.success("Ítem agregado");

        this.recalcSale();
    }

    addToCart(p) {
        const exist = this.currentSale.items.find(i => i.id == p.id);
        if (exist) { if (exist.cantidad >= p.stock) return pop_ups.warning("Stock máximo alcanzado"); exist.cantidad++; }
        else { this.currentSale.items.push({ id: p.id, nombre: p.name, cantidad: 1, precio: parseFloat(p.price), max_stock: parseFloat(p.stock) }); }
        this.recalcSale();
    }

    updateCartUI() {
        const c = document.getElementById('sale-cart-items');
        if(this.currentSale.items.length === 0) { c.innerHTML = '<div style="text-align:center; color:#999; margin-top:50px;">Carrito vacío</div>'; }
        else {
            c.innerHTML = this.currentSale.items.map((item, idx) => `
                <div class="cart-item">
                    <div class="cart-left"><div class="cart-title">${item.nombre}</div><div class="cart-total-price">${fmtMoney(item.precio * item.cantidad)}</div></div>
                    <div class="cart-right"><div class="cart-unit-price">${fmtMoney(item.precio)} c/u</div><div class="cart-controls"><button class="btn-xs sub" data-idx="${idx}">-</button><span style="font-weight:600; font-size:0.9rem; min-width:24px; text-align:center;">${item.cantidad}</span><button class="btn-xs add" data-idx="${idx}">+</button><button class="btn-xs del" data-idx="${idx}" style="margin-left:4px;"><i class="ph ph-trash"></i></button></div></div>
                </div>`).join('');

            // Logic para controles
            c.querySelectorAll('.add').forEach(b => b.addEventListener('click', () => {
                const item = this.currentSale.items[b.dataset.idx];
                if (item.cantidad >= item.max_stock) return pop_ups.warning("Stock insuficiente");
                item.cantidad++; this.recalcSale();
            }));

            c.querySelectorAll('.sub').forEach(b => b.addEventListener('click', () => {
                const item = this.currentSale.items[b.dataset.idx];
                item.cantidad--;
                if(item.cantidad < 1) this.currentSale.items.splice(b.dataset.idx, 1);
                this.recalcSale();
            }));

            c.querySelectorAll('.del').forEach(b => b.addEventListener('click', () => {
                this.currentSale.items.splice(b.dataset.idx, 1);
                this.recalcSale();
            }));
        }
        document.getElementById('cart-subtotal-display').textContent = fmtMoney(this.currentSale.subtotal_items);
    }

    addPayment() {
        const amtInput = document.getElementById('pay-input-amount');
        const methodSelect = document.getElementById('pay-method-select');
        const amount = parseFloat(amtInput.value);
        const methodId = methodSelect.value;
        if (isNaN(amount) || amount <= 0) return pop_ups.warning("Ingresá un monto válido");
        if (!methodId) return pop_ups.warning("Elegí un método de pago");
        const methodObj = this.resources.paymentMethods.find(m => m.id == methodId);
        const surchargePct = parseFloat(methodObj.surcharge) || 0;
        this.currentSale.payments.push({ method_id: methodId, name: methodObj.name, amount: amount, surcharge_percent: surchargePct, surcharge_val: amount * (surchargePct / 100) });
        amtInput.value = ''; this.recalcSale();
    }
    removePayment(idx) { this.currentSale.payments.splice(idx, 1); this.recalcSale(); }

    updatePaymentUI() {
        const c = document.getElementById('payments-list');
        if (this.currentSale.payments.length === 0) { c.innerHTML = '<p style="color:#999; text-align:center; font-size:0.8rem; margin-top:10px;">Sin pagos registrados</p>'; }
        else {
            c.innerHTML = this.currentSale.payments.map((p, idx) => `
                <div style="background:#FFF; border:1px solid var(--color-black); border-radius:4px; padding:8px; margin-bottom:5px; display:flex; justify-content:space-between; align-items:center;">
                    <div><div style="font-weight:600;">${fmtMoney(p.amount)} <span style="font-weight:normal; font-size:0.85rem; color:#555;">(${p.name})</span></div>${p.surcharge_val > 0 ? `<div style="font-size:0.75rem; color:var(--accent-color);">+ Recargo: ${fmtMoney(p.surcharge_val)}</div>` : ''}</div>
                    <span style="cursor:pointer; color:var(--accent-red);" class="rm-pay" data-idx="${idx}"><i class="ph ph-x-circle" style="font-size:1.2rem;"></i></span>
                </div>`).join('');
            c.querySelectorAll('.rm-pay').forEach(b => b.addEventListener('click', () => this.removePayment(b.dataset.idx)));
        }
    }

    recalcSale() {
        // HELPER: Redondeo preciso a 2 decimales para evitar el error de "Falta $0.00"
        const round = (num) => Math.round((num + Number.EPSILON) * 100) / 100;

        // 1. Calcular totales crudos
        const rawSubtotal = this.currentSale.items.reduce((sum, i) => sum + (i.precio * i.cantidad), 0);
        const rawSurcharges = this.currentSale.payments.reduce((sum, p) => sum + p.surcharge_val, 0);
        const rawPaid = this.currentSale.payments.reduce((sum, p) => sum + p.amount + p.surcharge_val, 0);

        // 2. Aplicar redondeo para la lógica financiera
        this.currentSale.subtotal_items = rawSubtotal;
        this.currentSale.total_surcharges = rawSurcharges;
        this.currentSale.total_final = round(rawSubtotal + rawSurcharges);

        const totalPaid = round(rawPaid);
        const diff = round(totalPaid - this.currentSale.total_final);

        // 3. Actualizar UI con montos formateados
        document.getElementById('checkout-subtotal').textContent = fmtMoney(this.currentSale.subtotal_items);
        document.getElementById('checkout-surcharges').textContent = fmtMoney(this.currentSale.total_surcharges);
        document.getElementById('checkout-total-final').textContent = fmtMoney(this.currentSale.total_final);
        //document.getElementById('checkout-paid').textContent = fmtMoney(totalPaid);

        const diffEl = document.getElementById('checkout-diff');
        const rowDiff = document.getElementById('change-row');

        // 4. Lógica de Validación (Usando el 'diff' ya redondeado y limpio)
        if (diff >= 0) {
            // SOBRA o ESTÁ JUSTO (Verde)
            rowDiff.style.color = 'var(--accent-color)';
            rowDiff.querySelector('span:first-child').textContent = "Vuelto:";
            diffEl.textContent = fmtMoney(diff);
            // Habilitamos botón si hay items en el carrito
            document.getElementById('confirm-sale-btn').disabled = (this.currentSale.items.length === 0);
        } else {
            // FALTA DINERO (Rojo)
            rowDiff.style.color = 'var(--accent-red)';
            rowDiff.querySelector('span:first-child').textContent = "Falta:";
            diffEl.textContent = fmtMoney(Math.abs(diff));
            // Bloqueamos botón
            document.getElementById('confirm-sale-btn').disabled = true;
        }

        this.updateCartUI();
        this.updatePaymentUI();
        this.calculateCommission();
    }

    calculateCommission() {
        const pct = parseFloat(document.getElementById('sale-commission-pct').value) || 0;
        const commVal = this.currentSale.subtotal_items * (pct / 100);
        document.getElementById('commission-display').textContent = `Comisión: ${fmtMoney(commVal)}`;
    }

    fillSelect(id, list, valKey, textKey, placeholder) {
        const s = document.getElementById(id); s.innerHTML = placeholder ? `<option value="">${placeholder}</option>` : '';
        list.forEach(i => { const op = document.createElement('option'); op.value = i[valKey]; let txt = i[textKey]; if(id === 'pay-method-select' && i.surcharge > 0) txt += ` (+${parseFloat(i.surcharge)}%)`; op.textContent = txt; s.appendChild(op); });
    }

    async submitSale() {
        const btn = document.getElementById('confirm-sale-btn'); btn.disabled = true; btn.textContent = 'Procesando...';
        const pct = parseFloat(document.getElementById('sale-commission-pct').value) || 0;
        const payload = {
            inventory_id: this.resources.inventoryId || null,
            customer_id: document.getElementById('sale-customer').value || null,
            seller_id: document.getElementById('sale-seller').value || null,
            commission_amount: this.currentSale.subtotal_items * (pct / 100),
            total_final: this.currentSale.total_final,
            notes: document.getElementById('sale-notes').value,
            items: this.currentSale.items.map(i => ({
                // ENVIAMOS ID SI EXISTE, SI NO (MANUAL), ENVIAMOS NULL
                id: i.id || null,
                nombre: i.nombre,
                cantidad: i.cantidad,
                precio: i.precio,
                subtotal: i.precio * i.cantidad
            })),
            payments: this.currentSale.payments
        };
        try { const res = await createSale(payload); if (res.success) { this.closeModal('create-sale-modal'); await this.fetchResources(); await this.loadHistory(this.currentSortOrder); pop_ups.success("Venta Exitosa"); } else { pop_ups.error(res.message); } }
        catch(e) { console.error(e); pop_ups.error("Error de conexión"); }
        btn.disabled = false; btn.textContent = 'Confirmar Venta';
    }
}

window.salesModuleInstance = new SalesModule();
export const salesModuleInstance = window.salesModuleInstance;