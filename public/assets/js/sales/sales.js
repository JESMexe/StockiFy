/**
 * public/assets/js/sales/sales.js
 * Version 3.2: Corrección de listas desplegables y botones visibles.
 */
import { getSaleResources, createSale, getSalesHistory, getSaleDetailsNew, updateSaleCustomer } from '../api.js';
import { pop_ups } from '../notifications/pop-up.js';

async function createPaymentMethodApi(name) {
    const res = await fetch('/api/payment-methods/create.php', { method:'POST', body:JSON.stringify({name}) });
    return res.json();
}

export class SalesModule {
    constructor() {
        this.containerId = 'sales';
        this.isInitialized = false;
        this.currentSale = { clientId: null, clientName: null, items: [], subtotal: 0, total: 0, surcharge: 0 };
        this.availableProducts = [];
        this.availableClients = [];
        this.availableEmployees = [];
        this.paymentMethods = [];
        this.viewingSaleId = null;
        this.isEditingClient = false;
    }

    init() {
        if (this.isInitialized) { this.loadSalesHistory(); return; }
        const container = document.getElementById(this.containerId);
        if (!container) return;
        container.innerHTML = this.renderBaseStructure();
        this.attachEvents();
        this.loadSalesHistory();
        this.isInitialized = true;
    }

    renderBaseStructure() {
        return `
            <div class="sales-layout">
                <div class="table-header">
                    <h2>Ventas</h2>
                    <div class="table-controls" style="gap:10px;">
                        <button id="sales-sort-btn" class="btn btn-secondary"><i class="ph ph-arrows-clockwise"></i></button>
                        <button id="quick-sale-btn" class="btn btn-secondary" style="border: 2px solid #28a745; color: #28a745;"> <i class="ph ph-lightning"></i> Venta Rápida</button>
                        <button id="sales-create-btn" class="btn btn-primary">+ Nueva Venta</button>
                    </div>
                </div>

                <div class="table-wrapper" style="flex-grow:1; overflow-y:auto;">
                    <table id="sales-table" style="width:100%; border-collapse:collapse;">
                        <thead style="position:sticky; top:0; background:white; z-index:10; box-shadow:0 1px 2px rgba(0,0,0,0.1);">
                            <tr>
                                <th style="padding:12px;">Fecha</th>
                                <th style="padding:12px;">Detalle</th>
                                <th style="padding:12px;">Vendedor</th>
                                <th style="padding:12px; text-align:right;">Total</th>
                                <th style="padding:12px; text-align:center;"></th>
                            </tr>
                        </thead>
                        <tbody id="sales-list-body"></tbody>
                    </table>
                </div>

                <div id="create-sale-modal" class="modal-overlay hidden" style="display:none; z-index:1000;">
                    <div class="modal-content" style="width: 98%; max-width: 1300px; height: 90vh;">
                        <div class="modal-header"><h3><i class="ph ph-shopping-cart"></i> Nueva Venta</h3><button class="modal-close-btn" id="close-sale-modal">&times;</button></div>
                        <div class="sale-modal-body" style="display:grid; grid-template-columns: 1.5fr 1fr; gap:0;">
                            
                            <div class="sale-col" style="border-right:1px solid #eee;">
                                <div style="margin-bottom:15px;">
                                    <input type="text" id="sale-search-product" class="big-amount-input" style="font-size:1.2rem; text-align:left; border:1px solid #ccc; border-radius:8px;" placeholder="🔍 Buscar por Nombre, SKU, Código...">
                                </div>
                                <div id="sale-products-list" class="resource-list" style="height: calc(100% - 60px);"></div>
                            </div>

                            <div class="sale-col" style="background:#f9f9f9; display:flex; flex-direction:column; gap:10px; overflow-y:auto;">
                                <div id="sale-cart-items" class="resource-list" style="max-height: 200px; min-height:100px; border:1px solid #ddd;"></div>
                                
                                <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px;">
                                    <div>
                                        <label class="quick-label">Cliente</label>
                                        <input type="text" id="sale-search-client" class="rustic-input" placeholder="Buscar..." style="margin-bottom:5px;">
                                        <div id="selected-client-display" style="padding:5px; background:#e3f2fd; border-radius:4px; font-size:0.8rem; display:none;"></div>
                                        <div id="sale-clients-list" class="resource-list" style="max-height:100px; display:none; position:absolute; z-index:100; background:white; border:1px solid #ccc; width:200px;"></div>
                                    </div>
                                    <div>
                                        <label class="quick-label">Vendedor / Comisión</label>
                                        <div style="display:flex; gap:5px;">
                                            <select id="sale-employee-select" class="rustic-select" style="flex:1;"><option value="">-- Yo --</option></select>
                                            <input type="number" id="sale-commission" class="rustic-input" placeholder="$0" style="width:70px;">
                                        </div>
                                    </div>
                                </div>

                                <div style="background:white; padding:15px; border-radius:8px; border:1px solid #e0e0e0;">
                                    <h4 style="margin:0 0 10px 0; border:none; font-size:0.9rem;">Pago</h4>
                                    
                                    <div style="margin-bottom:10px;">
                                        <label class="quick-label">Método de Pago <button id="add-pay-method" style="border:none; background:none; color:blue; cursor:pointer;">(+)</button></label>
                                        <select id="sale-pay-method" class="rustic-select" style="width:100%;"></select>
                                        <small id="sale-surcharge-info" style="color:#e65100; font-weight:bold; display:none; margin-top:2px;">Aplicando recargo...</small>
                                    </div>

                                    <div style="display:flex; gap:10px; margin-bottom:10px;">
                                        <div style="flex:1;">
                                            <label class="quick-label">Paga con ($)</label>
                                            <input type="text" id="sale-tendered" class="rustic-input" placeholder="0.00" style="font-weight:bold;">
                                        </div>
                                        <div style="flex:1; background:#e8f5e9; border-radius:4px; padding:5px; text-align:center;">
                                            <span style="display:block; font-size:0.8rem; color:#2e7d32;">Vuelto</span>
                                            <span id="sale-change-display" style="font-weight:bold; font-size:1.2rem; color:#2e7d32;">$0.00</span>
                                        </div>
                                    </div>

                                    <div style="display:flex; gap:10px;">
                                        <div style="flex:1;">
                                            <label class="quick-label">Comprobante</label>
                                            <input type="file" id="sale-file" class="rustic-input" accept="image/*,.pdf">
                                        </div>
                                        <div style="flex:1;">
                                            <label class="quick-label">Nota</label>
                                            <input type="text" id="sale-notes" class="rustic-input" placeholder="Opcional...">
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-auto">
                                    <div id="sale-subtotal-row" style="display:none; text-align:right; font-size:0.9rem; color:#666;"></div>
                                    <div class="flex-row justify-between" style="font-size:1.8rem; font-weight:bold; margin-bottom:1rem; color:#007bff;">
                                        <span>Total:</span><span id="sale-total-display">$0.00</span>
                                    </div>
                                    <button id="confirm-sale-btn" class="btn btn-primary w-full" style="padding:15px; font-size:1.2rem;" disabled>CONFIRMAR VENTA</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="quick-sale-modal" class="modal-overlay hidden" style="align-items:center; justify-content:center; display:none; z-index:1000;">
                    <div class="modal-content" style="width: 400px; max-width: 90%;">
                        <div class="modal-header" style="background: #f1f8ff;">
                            <h3 style="color: #007bff;"><i class="ph ph-lightning"></i> Venta Rápida</h3>
                            <button class="modal-close-btn" id="close-quick-sale-modal">&times;</button>
                        </div>
                        <div class="modal-body" style="padding: 1.5rem;">
                            <form id="quick-sale-form">
                                <div style="margin-bottom: 10px; text-align: center;">
                                    <label class="quick-label">Monto Base ($)</label>
                                    <input type="text" id="quick-sale-amount" class="big-amount-input" placeholder="0" required autocomplete="off">
                                </div>
                                
                                <div class="quick-form-group">
                                    <label class="quick-label">Método de Pago</label>
                                    <select id="quick-sale-method" class="rustic-select" style="width:100%;"></select>
                                    <small id="quick-surcharge-msg" style="color:#e65100; display:none; font-weight:bold; text-align:right; margin-top:5px; display:block;">+0% Recargo</small>
                                </div>

                                <div class="quick-form-group" style="text-align:right; margin-bottom:20px; border-top:1px solid #eee; padding-top:10px;">
                                    <span style="font-size:1.4rem; font-weight:bold; color:#007bff;">Total: <span id="quick-final-total">$0.00</span></span>
                                </div>

                                <div class="quick-form-group">
                                    <label class="quick-label">Detalles (Opcional)</label>
                                    <select id="quick-sale-employee" class="rustic-select" style="width:100%; margin-bottom:5px;"><option value="">-- Sin Vendedor --</option></select>
                                    <select id="quick-sale-client" class="rustic-select" style="width:100%; margin-bottom:5px;"><option value="">-- Consumidor Final --</option></select>
                                    <select id="quick-sale-category" class="rustic-select" style="width:100%; margin-bottom:5px;">
                                        <option value="Servicio">Servicio</option>
                                        <option value="Varios">Varios</option>
                                        <option value="Envío">Envío</option>
                                    </select>
                                    <textarea id="quick-sale-note" class="rustic-input" style="width:100%; height:50px;" placeholder="Nota..."></textarea>
                                </div>
                                <div style="text-align:right;">
                                    <button type="submit" id="btn-confirm-quick" class="btn btn-primary w-full" style="padding:12px;">Registrar</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div id="detail-sale-modal" class="modal-overlay hidden" style="align-items:center; justify-content:center; display:none; z-index:1001;">
                    <div class="modal-content" style="width: 500px; max-width: 90%;"><div class="modal-header"><h3>Detalle de Venta</h3><button class="modal-close-btn" id="close-detail-sale-modal">&times;</button></div><div class="modal-body" id="detail-sale-content" style="padding: 1.5rem;"></div></div>
                </div>
            </div>
        `;
    }

    attachEvents() {
        document.getElementById('sales-create-btn')?.addEventListener('click', () => this.openCreateModal());
        document.getElementById('quick-sale-btn')?.addEventListener('click', () => this.openQuickModal());
        document.getElementById('sales-sort-btn')?.addEventListener('click', () => this.loadSalesHistory());

        document.getElementById('close-sale-modal')?.addEventListener('click', () => this.closeModal('create-sale-modal'));
        document.getElementById('close-quick-sale-modal')?.addEventListener('click', () => this.closeModal('quick-sale-modal'));
        document.getElementById('close-detail-sale-modal')?.addEventListener('click', () => this.closeModal('detail-sale-modal'));

        document.getElementById('sale-search-product')?.addEventListener('input', (e) => this.filterProducts(e.target.value));

        const clientInput = document.getElementById('sale-search-client');
        clientInput.addEventListener('input', (e) => {
            this.filterClients(e.target.value);
            document.getElementById('sale-clients-list').style.display = 'block';
        });
        document.addEventListener('click', (e) => {
            if (!clientInput.contains(e.target)) document.getElementById('sale-clients-list').style.display = 'none';
        });

        // CALCULADORA
        document.getElementById('sale-tendered')?.addEventListener('input', () => this.updateChangeCalc());
        document.getElementById('sale-pay-method')?.addEventListener('change', () => this.calcTotal());

        // VENTA RÁPIDA
        document.getElementById('quick-sale-method')?.addEventListener('change', () => this.calcQuickTotal());
        document.getElementById('quick-sale-amount')?.addEventListener('input', () => this.calcQuickTotal());

        document.getElementById('add-pay-method')?.addEventListener('click', async () => {
            const name = prompt("Nombre del nuevo método:");
            if (name) {
                const res = await createPaymentMethodApi(name);
                if (res.success) { await this.fetchResources(); pop_ups.success("Método agregado"); }
            }
        });

        document.getElementById('confirm-sale-btn')?.addEventListener('click', () => this.submitSale());
        document.getElementById('quick-sale-form')?.addEventListener('submit', (e) => this.submitQuickSale(e));

        this.setupCurrencyInput('quick-sale-amount');
        this.setupCurrencyInput('sale-tendered');
    }

    setupCurrencyInput(id) {
        const input = document.getElementById(id); if(!input) return;
        input.addEventListener('input', (e) => {
            let raw = e.target.value.replace(/\D/g, '');
            if (raw.length > 9) raw = raw.substring(0, 9);
            e.target.value = raw ? new Intl.NumberFormat('es-AR').format(raw) : '';
        });
    }

    closeModal(id) { document.getElementById(id).classList.add('hidden'); document.getElementById(id).style.display='none'; }

    // --- CARGA DE RECURSOS (Y LLENADO DE SELECTS) ---
    async fetchResources() {
        try {
            const d = await getSaleResources();
            if(d.success){
                this.availableClients = d.clients||[];
                this.availableProducts = d.products||[];
                this.availableEmployees = d.employees||[];
                this.paymentMethods = d.payment_methods||[];

                this.renderProducts(this.availableProducts);
                this.fillSelects(); // AHORA LLENA TODOS
            }
        } catch(e){ console.error(e); }
    }

    fillSelects() {
        // --- CORRECCIÓN: Llenamos los 4 selectores (Venta Normal y Venta Rápida) ---
        const fill = (id, list, defaultText) => {
            const sel = document.getElementById(id);
            if(!sel) return;
            sel.innerHTML = `<option value="">${defaultText}</option>`;

            list.forEach(item => {
                const opt = document.createElement('option');
                opt.value = item.id;
                // Detectar si es empleado (full_name) o método de pago (name + surcharge)
                if(item.surcharge !== undefined) {
                    const sur = parseFloat(item.surcharge);
                    opt.textContent = `${item.name} ${sur>0 ? `(+${sur}%)` : ''}`;
                    opt.dataset.surcharge = sur;
                } else {
                    opt.textContent = item.full_name || item.name;
                }
                sel.appendChild(opt);
            });
        };

        // 1. Empleados (Normal y Rápido)
        fill('sale-employee-select', this.availableEmployees, '-- Sin asignar --');
        fill('quick-sale-employee', this.availableEmployees, '-- Sin asignar --');

        // 2. Métodos de Pago (Normal y Rápido)
        fill('sale-pay-method', this.paymentMethods, '-- Efectivo (Default) --');
        fill('quick-sale-method', this.paymentMethods, '-- Efectivo (Default) --');
    }

    // --- VENTA RÁPIDA ---
    async openQuickModal() {
        document.getElementById('quick-sale-form').reset();
        document.getElementById('quick-final-total').textContent = '$0.00';
        document.getElementById('quick-surcharge-msg').style.display = 'none';
        const m = document.getElementById('quick-sale-modal'); m.classList.remove('hidden'); m.style.display='flex';
        document.getElementById('quick-sale-amount').focus();

        // Cargar recursos si no están
        if(this.paymentMethods.length === 0) await this.fetchResources();
        else this.fillSelects(); // Asegurar que se vean los empleados

        this.fillClientSelect('quick-sale-client');
    }

    fillClientSelect(id) {
        const s = document.getElementById(id); s.innerHTML='<option value="">-- Consumidor Final --</option>';
        this.availableClients.forEach(c => {
            const o = document.createElement('option'); o.value=c.id; o.textContent=c.full_name;
            s.appendChild(o);
        });
    }

    calcQuickTotal() {
        const rawAmount = document.getElementById('quick-sale-amount').value.replace(/\./g, '').replace(',', '.');
        const baseAmount = parseFloat(rawAmount) || 0;

        const sel = document.getElementById('quick-sale-method');
        const selectedOpt = sel.options[sel.selectedIndex];
        const surchargePercent = selectedOpt ? parseFloat(selectedOpt.dataset.surcharge || 0) : 0;

        const surcharge = baseAmount * (surchargePercent / 100);
        const finalTotal = baseAmount + surcharge;

        document.getElementById('quick-final-total').textContent = `$${finalTotal.toFixed(2)}`;

        const msg = document.getElementById('quick-surcharge-msg');
        if(surchargePercent > 0) {
            msg.style.display = 'block';
            msg.textContent = `+${surchargePercent}% Recargo ($${surcharge.toFixed(2)})`;
        } else {
            msg.style.display = 'none';
        }
    }

    async submitQuickSale(e) {
        e.preventDefault();
        const btn = document.getElementById('btn-confirm-quick');
        btn.disabled=true; btn.textContent='Procesando...';

        const rawBase = document.getElementById('quick-sale-amount').value.replace(/\./g, '').replace(',', '.');
        const baseAmount = parseFloat(rawBase);

        if(isNaN(baseAmount) || baseAmount <= 0) { pop_ups.warning("Monto inválido"); btn.disabled=false; btn.textContent='Registrar'; return; }

        const sel = document.getElementById('quick-sale-method');
        const selectedOpt = sel.options[sel.selectedIndex];
        const surchargePercent = selectedOpt ? parseFloat(selectedOpt.dataset.surcharge || 0) : 0;
        const total = baseAmount * (1 + surchargePercent/100);

        const payload = {
            total: total,
            client_id: document.getElementById('quick-sale-client').value || null,
            employee_id: document.getElementById('quick-sale-employee').value || null,
            payment_method_id: sel.value || null,
            category: document.getElementById('quick-sale-category').value || 'Servicio',
            notes: document.getElementById('quick-sale-note').value || null,
            items: []
        };

        try {
            const res = await createSale(payload);
            if(res.success) { this.closeModal('quick-sale-modal'); this.loadSalesHistory(); pop_ups.success('Venta registrada'); }
            else pop_ups.error(res.message);
        } catch(err) { console.error(err); }
        btn.disabled=false; btn.textContent='Registrar';
    }

    // --- VENTA INVENTARIO (Lógica existente) ---
    async openCreateModal() {
        this.currentSale = { clientId: null, clientName: null, items: [], subtotal: 0, total: 0, surcharge: 0 };
        this.updateCartUI();
        document.getElementById('create-sale-modal').classList.remove('hidden');
        document.getElementById('create-sale-modal').style.display='flex';
        document.getElementById('sale-search-product').focus();
        await this.fetchResources();
    }

    // ... (El resto de funciones: render, filter, selectClient... se mantienen igual) ...
    renderProducts(list) { const c = document.getElementById('sale-products-list'); if(list.length===0){c.innerHTML='<p style="padding:10px; color:#999">Sin resultados</p>'; return;} c.innerHTML = list.slice(0, 50).map(p => `<div class="resource-item pr-trig" data-id="${p.id}"><div><b style="font-size:1.1rem;">${p.name}</b><br><small style="color:#666;">Stock: ${p.stock}</small></div><div class="text-right"><b class="text-green" style="font-size:1.2rem;">$${parseFloat(p.price).toFixed(2)}</b></div></div>`).join(''); c.querySelectorAll('.pr-trig').forEach(b=>b.addEventListener('click',()=>this.addToCart(this.availableProducts.find(x=>x.id==b.dataset.id)))); }
    renderClients(l) { document.getElementById('sale-clients-list').innerHTML = l.map(c=>`<div class="resource-item cl-trig" data-id="${c.id}"><b>${c.full_name}</b></div>`).join(''); document.querySelectorAll('.cl-trig').forEach(b=>b.addEventListener('click',()=>this.selectClient(this.availableClients.find(x=>x.id==b.dataset.id)))); }
    filterProducts(t) { const term = t.toLowerCase(); const f = this.availableProducts.filter(p => p.search_data && p.search_data.includes(term)); this.renderProducts(f); }
    filterClients(t) { this.renderClients(this.availableClients.filter(c=>c.full_name.toLowerCase().includes(t.toLowerCase()))); }
    selectClient(c) { this.currentSale.clientId=c.id; document.getElementById('selected-client-display').innerHTML=`Cliente: <b>${c.full_name}</b>`; document.getElementById('selected-client-display').style.display='block'; document.getElementById('sale-clients-list').style.display='none'; }

    addToCart(p) { const ex=this.currentSale.items.find(i=>i.id==p.id); if(ex) ex.quantity++; else this.currentSale.items.push({...p, quantity:1}); this.calcTotal(); }

    calcTotal() {
        this.currentSale.subtotal = this.currentSale.items.reduce((s,i)=>s+(i.price*i.quantity),0);
        const sel = document.getElementById('sale-pay-method');
        const selectedOpt = sel.options[sel.selectedIndex];
        const surchargePercent = selectedOpt ? parseFloat(selectedOpt.dataset.surcharge || 0) : 0;

        this.currentSale.surcharge = this.currentSale.subtotal * (surchargePercent / 100);
        this.currentSale.total = this.currentSale.subtotal + this.currentSale.surcharge;

        document.getElementById('sale-total-display').textContent=`$${this.currentSale.total.toFixed(2)}`;

        const subRow = document.getElementById('sale-subtotal-row');
        const infoMsg = document.getElementById('sale-surcharge-info');
        if (surchargePercent > 0) {
            subRow.style.display = 'block';
            subRow.innerHTML = `Subtotal: $${this.currentSale.subtotal.toFixed(2)} + Recargo: $${this.currentSale.surcharge.toFixed(2)} (${surchargePercent}%)`;
            infoMsg.style.display = 'block';
            infoMsg.textContent = `Se aplica un recargo del ${surchargePercent}%`;
        } else {
            subRow.style.display = 'none';
            infoMsg.style.display = 'none';
        }
        this.updateCartUI();
        this.updateChangeCalc();
    }

    updateCartUI() {
        const c=document.getElementById('sale-cart-items');
        if(!this.currentSale.items.length){ c.innerHTML='<p class="text-center" style="color:#999; margin-top:2rem;">Vacío</p>'; document.getElementById('confirm-sale-btn').disabled=true; return; }
        document.getElementById('confirm-sale-btn').disabled=false;
        c.innerHTML=this.currentSale.items.map((i,x)=>`<div class="cart-item"><div class="flex-row justify-between"><b>${i.name}</b> <span>$${(i.price*i.quantity).toFixed(2)}</span></div><div class="flex-row justify-between align-center" style="margin-top:5px;"><small>$${i.price}</small><div class="cart-controls"><button class="qty-btn sub" data-x="${x}">-</button><span>${i.quantity}</span><button class="qty-btn add" data-x="${x}">+</button></div></div></div>`).join('');
        c.querySelectorAll('.sub').forEach(b=>b.addEventListener('click',()=>{const i=this.currentSale.items[b.dataset.x]; i.quantity--; if(i.quantity<1)this.currentSale.items.splice(b.dataset.x,1); this.calcTotal();}));
        c.querySelectorAll('.add').forEach(b=>b.addEventListener('click',()=>{this.currentSale.items[b.dataset.x].quantity++; this.calcTotal();}));
    }

    updateChangeCalc() {
        const rawTendered = document.getElementById('sale-tendered').value.replace(/\./g, '').replace(',', '.');
        const tendered = parseFloat(rawTendered) || 0;
        const total = this.currentSale.total;
        const change = tendered - total;
        const el = document.getElementById('sale-change-display');

        if (tendered > 0) {
            el.textContent = `$${change.toFixed(2)}`;
            el.style.color = change >= 0 ? '#28a745' : '#dc3545';
        } else { el.textContent = '$0.00'; }
    }

    async submitSale() {
        const btn = document.getElementById('confirm-sale-btn'); btn.textContent = 'Procesando...'; btn.disabled = true;
        const formData = new FormData();
        formData.append('total', this.currentSale.total);
        formData.append('client_id', this.currentSale.clientId || '');
        formData.append('employee_id', document.getElementById('sale-employee-select').value);
        formData.append('commission_amount', document.getElementById('sale-commission').value || 0);
        formData.append('payment_method_id', document.getElementById('sale-pay-method').value);

        const rawTendered = document.getElementById('sale-tendered').value.replace(/\./g, '').replace(',', '.');
        const tendered = parseFloat(rawTendered) || 0;
        formData.append('amount_tendered', tendered);
        formData.append('change_returned', tendered > 0 ? (tendered - this.currentSale.total).toFixed(2) : 0);
        formData.append('notes', document.getElementById('sale-notes').value);

        const fileInput = document.getElementById('sale-file');
        if (fileInput.files[0]) formData.append('proof_file', fileInput.files[0]);

        const items = this.currentSale.items.map(i=>({ id: i.id, nombre_producto: i.name, cantidad: i.quantity, precio_unitario: i.price, subtotal: i.price*i.quantity }));
        formData.append('items', JSON.stringify(items));

        try {
            const res = await fetch('/api/sales/create.php', { method: 'POST', body: formData });
            const data = await res.json();
            if (data.success) { this.closeModal('create-sale-modal'); this.loadSalesHistory(); pop_ups.success('Venta registrada con éxito'); }
            else { pop_ups.error(data.message); }
        } catch(e) { console.error(e); pop_ups.error('Error de conexión'); }
        btn.textContent = 'CONFIRMAR VENTA'; btn.disabled = false;
    }

    async loadSalesHistory(order='desc') {
        const b = document.getElementById('sales-list-body'); b.innerHTML = '<tr><td colspan="5" class="text-center">Cargando...</td></tr>';
        try {
            const data = await getSalesHistory(order);
            if(!data.success || !data.sales.length) { b.innerHTML='<tr><td colspan="5" class="text-center" style="padding:2rem; color:#999;">Sin movimientos</td></tr>'; return; }
            b.innerHTML = data.sales.map(s => {
                let icon = '<i class="ph ph-shopping-bag" style="color:#007bff;"></i>';
                let main = s.nombre_cliente || 'Consumidor Final';
                if(s.category) { icon='<i class="ph ph-lightning" style="color:#28a745;"></i>'; main=`${s.category} <small class="text-muted">(${main})</small>`; }
                const empName = s.nombre_empleado ? `<span style="font-size:0.85rem; background:#f0f0f0; padding:2px 6px; border-radius:4px;">${s.nombre_empleado}</span>` : '<span style="color:#ccc;">-</span>';
                return `<tr style="border-bottom:1px solid #eee;"><td style="padding:10px;">${new Date(s.fecha_hora).toLocaleDateString()}</td><td style="padding:10px; display:flex; align-items:center; gap:8px;">${icon} <span>${main}</span></td><td style="padding:10px;">${empName}</td><td style="padding:10px; text-align:right;"><b>$${parseFloat(s.total).toFixed(2)}</b></td><td style="padding:10px; text-align:center;"><button class="btn btn-secondary btn-sm view-det" data-id="${s.id}"><i class="ph ph-eye"></i></button></td></tr>`;
            }).join('');
            b.querySelectorAll('.view-det').forEach(b=>b.addEventListener('click',()=>this.showDetails(b.dataset.id)));
        } catch(e){ console.error(e); }
    }

    async showDetails(id) {
        this.viewingSaleId = id; this.isEditingClient = false;
        const m = document.getElementById('detail-sale-modal'); const c = document.getElementById('detail-sale-content'); m.classList.remove('hidden'); m.style.display='flex'; c.innerHTML = '<p class="text-center">Cargando...</p>';
        if(this.availableClients.length === 0) await this.fetchResources();

        try {
            const data = await getSaleDetailsNew(id);
            if(data.success) {
                const { sale, items } = data;
                let fileLink = sale.proof_file ? `<a href="/${sale.proof_file}" target="_blank" class="btn btn-sm btn-secondary" style="margin-top:10px; display:block; text-align:center;"><i class="ph ph-file-pdf"></i> Ver Comprobante</a>` : '';

                let content = `
                    <div class="detail-relative-container">
                        <button id="edit-client-trigger" class="btn-ghost-edit" title="Editar Cliente"><i class="ph ph-pencil-simple"></i></button>
                        <div style="text-align:center; margin-bottom:1rem;">
                            <h2 style="font-size:2rem; margin:0; color:#007bff;">$${parseFloat(sale.total).toFixed(2)}</h2>
                            <p style="color:#666;">${new Date(sale.fecha_hora).toLocaleString()}</p>
                            <span style="background:#eee; padding:2px 8px; border-radius:4px; font-size:0.8rem;">${sale.metodo_pago || 'Efectivo'}</span>
                        </div>
                        
                        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:10px; margin-bottom:15px;">
                            <div id="client-display-zone" style="background:#f1f8ff; padding:10px; border-radius:8px; border:1px solid #d0e7ff; text-align:center;">
                                <small style="display:block; color:#555;">Cliente</small>
                                <span id="client-name-text" style="font-weight:bold;">${sale.nombre_cliente || 'Consumidor Final'}</span>
                                <div id="client-edit-ui" style="display:none; margin-top:5px;"><select id="detail-client-select" class="rustic-select" style="width:100%; margin-bottom:5px;"></select><div class="edit-controls-wrapper"><button id="cancel-client-btn" class="btn btn-sm btn-secondary">x</button><button id="save-client-btn" class="btn btn-sm btn-primary">Ok</button></div></div>
                            </div>
                            <div style="background:#f9f9f9; padding:10px; border-radius:8px; border:1px solid #eee; text-align:center;">
                                <small style="display:block; color:#555;">Vendedor</small>
                                <span style="font-weight:bold;">${sale.nombre_empleado || '-'}</span>
                            </div>
                        </div>
                        ${sale.notes ? `<div style="margin-bottom:15px;"><label style="font-weight:600;">Nota:</label><p style="background:#eee; padding:10px; border-radius:4px; font-style:italic;">${sale.notes}</p></div>` : ''}
                        ${fileLink}
                    </div>`;

                if(items.length > 0) {
                    content += `<h4>Productos</h4><div style="max-height:200px; overflow-y:auto; border-top:1px solid #eee;">${items.map(i=>`<div style="display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px dotted #eee;"><span>${i.cantidad}x ${i.nombre_producto}</span><b>$${parseFloat(i.subtotal).toFixed(2)}</b></div>`).join('')}</div>`;
                }
                c.innerHTML = content;
                this.fillClientSelect('detail-client-select', sale.id_cliente);
                this.attachDetailEvents(sale.id_cliente);
            }
        } catch(e){ c.innerHTML='Error'; }
    }

    attachDetailEvents(originalId) {
        const trigger = document.getElementById('edit-client-trigger');
        const display = document.getElementById('client-name-text');
        const ui = document.getElementById('client-edit-ui');
        const select = document.getElementById('detail-client-select');
        const save = document.getElementById('save-client-btn');
        const cancel = document.getElementById('cancel-client-btn');

        trigger.addEventListener('click', () => { trigger.style.display='none'; display.style.display='none'; ui.style.display='block'; });
        cancel.addEventListener('click', () => { trigger.style.display='block'; display.style.display='inline'; ui.style.display='none'; select.value=originalId||''; });
        save.addEventListener('click', async () => {
            const newId = select.value || null; save.disabled=true;
            try { const r = await updateSaleCustomer(this.viewingSaleId, newId); if(r.success) { pop_ups.success("Actualizado"); this.loadSalesHistory(); this.showDetails(this.viewingSaleId); } } catch(e){ console.error(e); }
            save.disabled=false;
        });
    }
}

export const salesModuleInstance = new SalesModule();