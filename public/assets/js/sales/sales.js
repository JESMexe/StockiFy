/**
 * public/assets/js/sales/sales.js
 * Version 3.3: Corrección de carga de recursos y mapeo dinámico de columnas.
 */
// 1. IMPORTAMOS LAS FUNCIONES QUE SÍ EXISTEN EN TU API.JS
import { getAllProducts, getAllClients, getAllProviders, createSale, getSalesHistory, getSaleDetailsNew, updateSaleCustomer } from '../api.js';
import { pop_ups } from '../notifications/pop-up.js';

// Función auxiliar para métodos de pago (puedes moverla a api.js si prefieres)
async function createPaymentMethodApi(name) {
    // Nota: Verifica que esta ruta exista, no estaba en tu lista de archivos subidos.
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
        this.paymentMethods = [
            { id: 'efectivo', name: 'Efectivo', surcharge: 0 },
            { id: 'tarjeta', name: 'Tarjeta', surcharge: 10 },
            { id: 'transferencia', name: 'Transferencia', surcharge: 0 }
        ];
        this.viewingSaleId = null;
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

    // ... (renderBaseStructure SE MANTIENE IGUAL, no es necesario cambiarlo) ...
    renderBaseStructure() {
        return `
            <div class="sales-layout">
                <div class="table-header">
                    <h2>Ventas</h2>
                    <div class="table-controls" style="gap:10px;">
                        <button id="sales-sort-btn" class="btn btn-secondary"><i class="ph ph-arrows-clockwise"></i></button>
                        <button id="quick-sale-btn" class="btn btn-secondary" style="border: 2px solid var(--accent-color); color: var(--accent-color);"> <i class="ph ph-lightning"></i> Venta Rápida</button>
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
                                    <input type="text" id="sale-search-product" class="big-amount-input" style="font-size:1.2rem; text-align:left; border:1px solid #ccc; border-radius:8px;" placeholder="Buscar producto...">
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
                                        <label class="quick-label">Vendedor</label>
                                        <select id="sale-employee-select" class="rustic-select" style="width:100%;"><option value="">-- Yo --</option></select>
                                    </div>
                                </div>

                                <div style="background:white; padding:15px; border-radius:8px; border:2px solid #1b1b1b;">
                                    <h4 style="margin:0 0 10px 0; border:none; font-size:0.9rem;">Pago</h4>
                                    <div style="margin-bottom:10px;">
                                        <label class="quick-label">Método de Pago</label>
                                        <select id="sale-pay-method" class="rustic-select" style="width:100%;"></select>
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
                                            <label class="quick-label">Nota</label>
                                            <input type="text" id="sale-notes" class="rustic-input" placeholder="Opcional...">
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-auto">
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
                                    <select id="quick-sale-client" class="rustic-select" style="width:100%; margin-bottom:5px;"><option value="">-- Consumidor Final --</option></select>
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

        document.getElementById('sale-tendered')?.addEventListener('input', () => this.updateChangeCalc());
        document.getElementById('sale-pay-method')?.addEventListener('change', () => this.calcTotal());

        document.getElementById('quick-sale-method')?.addEventListener('change', () => this.calcQuickTotal());
        document.getElementById('quick-sale-amount')?.addEventListener('input', () => this.calcQuickTotal());

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

    async fetchResources() {
        try {
            const [productsRes, clientsRes] = await Promise.all([
                getAllProducts().catch(e => ({ success: false })),
                getAllClients().catch(e => ({ success: false }))
            ]);

            if(clientsRes.success || Array.isArray(clientsRes)) {
                this.availableClients = clientsRes.clients || clientsRes || [];
            }

            if(productsRes.success) {
                this.availableProducts = productsRes.productList || [];
                this.renderProducts(this.availableProducts);
            }

            this.fillSelects();
        } catch(e){ console.error("Error cargando recursos:", e); }
    }

    fillSelects() {
        const fill = (id, list, defaultText) => {
            const sel = document.getElementById(id);
            if(!sel) return;
            sel.innerHTML = `<option value="">${defaultText}</option>`;
            list.forEach(item => {
                const opt = document.createElement('option');
                opt.value = item.id;
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

        fill('sale-employee-select', this.availableEmployees, '-- Yo --');
        fill('sale-pay-method', this.paymentMethods, '-- Seleccionar --');
        fill('quick-sale-method', this.paymentMethods, '-- Seleccionar --');
    }

    // --- VENTA RÁPIDA (Se mantiene lógica, solo update de select de cliente) ---
    async openQuickModal() {
        document.getElementById('quick-sale-form').reset();
        document.getElementById('quick-final-total').textContent = '$0.00';
        document.getElementById('quick-surcharge-msg').style.display = 'none';
        const m = document.getElementById('quick-sale-modal'); m.classList.remove('hidden'); m.style.display='flex';

        // Si no se cargaron productos, intentamos cargar recursos igual
        if(this.availableClients.length === 0) await this.fetchResources();

        this.fillClientSelect('quick-sale-client');
        this.fillSelects(); // Asegurar métodos de pago
        document.getElementById('quick-sale-amount').focus();
    }

    fillClientSelect(id) {
        const s = document.getElementById(id);
        s.innerHTML='<option value="">-- Consumidor Final --</option>';
        this.availableClients.forEach(c => {
            const o = document.createElement('option');
            o.value=c.id;
            // Manejamos posible diferencia de nombres de propiedades en clientes
            o.textContent = c.full_name || c.nombre || c.name || 'Cliente sin nombre';
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
        document.getElementById('quick-final-total').textContent = `$${(baseAmount + surcharge).toFixed(2)}`;
        const msg = document.getElementById('quick-surcharge-msg');
        if(surchargePercent > 0) { msg.style.display='block'; msg.textContent=`+${surchargePercent}% Recargo`; }
        else msg.style.display='none';
    }

    async submitQuickSale(e) {
        e.preventDefault();
        const btn = document.getElementById('btn-confirm-quick');
        btn.disabled=true; btn.textContent='Procesando...';

        const rawBase = document.getElementById('quick-sale-amount').value.replace(/\./g, '').replace(',', '.');
        const baseAmount = parseFloat(rawBase);

        if(isNaN(baseAmount) || baseAmount <= 0) { pop_ups.warning("Monto inválido"); btn.disabled=false; btn.textContent='Registrar'; return; }

        const sel = document.getElementById('quick-sale-method');
        const methodId = sel.value;
        const selectedOpt = sel.options[sel.selectedIndex];
        const surchargePercent = selectedOpt ? parseFloat(selectedOpt.dataset.surcharge || 0) : 0;
        const total = baseAmount * (1 + surchargePercent/100);

        const payload = {
            total: total,
            client_id: document.getElementById('quick-sale-client').value || null,
            employee_id: null,
            payment_method_id: methodId || 'efectivo',
            items: [] // Venta rápida no tiene items
        };

        try {
            const res = await createSale(payload);
            if(res.success) { this.closeModal('quick-sale-modal'); this.loadSalesHistory(); pop_ups.success('Venta registrada'); }
            else pop_ups.error(res.message);
        } catch(err) { console.error(err); }
        btn.disabled=false; btn.textContent='Registrar';
    }

    // --- VENTA INVENTARIO ---
    async openCreateModal() {
        this.currentSale = { clientId: null, clientName: null, items: [], subtotal: 0, total: 0, surcharge: 0 };
        this.updateCartUI();
        document.getElementById('create-sale-modal').classList.remove('hidden');
        document.getElementById('create-sale-modal').style.display='flex';
        document.getElementById('sale-search-product').focus();
        await this.fetchResources();
    }

    // 5. RENDER PRODUCTOS USANDO EL MAPEO DINÁMICO
    renderProducts(list) {
        const c = document.getElementById('sale-products-list');
        if(list.length===0){c.innerHTML='<p style="padding:10px; color:#999">Sin resultados</p>'; return;}

        c.innerHTML = list.slice(0, 50).map(p => {
            // 1. Verificamos Nombre (Identificado como 'name')
            // Se puede obviar identificación de nombre para VENTA (según tu lógica), pero visualmente se necesita algo.
            // Si el backend no envía 'name', mostramos 'No identificado'.
            const displayName = p.hasOwnProperty('name') ? p.name : '<span style="color:red;">Nombre No Identificado</span>';

            // 2. Verificamos Stock (Identificado como 'stock')
            const displayStock = p.hasOwnProperty('stock') ? p.stock : '<span style="color:red;">?</span>';

            // 3. Verificamos Precio de Venta (Identificado como 'sale_price')
            // ESTO ES CRÍTICO. Si no hay sale_price identificado, no podemos vender.
            const isPriceIdentified = p.hasOwnProperty('sale_price');
            const displayPrice = isPriceIdentified ? `$${parseFloat(p.sale_price).toFixed(2)}` : '<span style="color:red; font-size:0.8rem;">Precio No Identificado</span>';

            // El botón solo funciona si tenemos los datos mínimos (especialmente precio)
            // Si prefieres bloquear totalmente el item si falta nombre, agrega la condición p.hasOwnProperty('name')
            const canAdd = isPriceIdentified;
            const itemClass = canAdd ? 'resource-item pr-trig' : 'resource-item disabled-item';
            const cursorStyle = canAdd ? 'cursor:pointer;' : 'cursor:not-allowed; opacity:0.6;';

            return `
            <div class="${itemClass}" data-id="${p.pID}" style="${cursorStyle}">
                <div>
                    <b style="font-size:1.1rem;">${displayName}</b><br>
                    <small style="color:#666;">Stock: ${displayStock}</small>
                </div>
                <div class="text-right">
                    <b class="text-green" style="font-size:1.2rem;">${displayPrice}</b>
                </div>
            </div>`;
        }).join('');

        // Solo agregamos evento click a los elementos válidos
        c.querySelectorAll('.pr-trig').forEach(b => {
            b.addEventListener('click', () => {
                const prod = this.availableProducts.find(x => x.pID == b.dataset.id);
                // Doble chequeo antes de agregar
                if (prod && prod.hasOwnProperty('sale_price')) {
                    this.addToCart(prod);
                } else {
                    pop_ups.warning("Este producto no tiene Precio de Venta identificado.");
                }
            });
        });
    }

    renderClients(l) {
        document.getElementById('sale-clients-list').innerHTML = l.map(c =>
            `<div class="resource-item cl-trig" data-id="${c.id}"><b>${c.full_name || c.nombre || 'Cliente'}</b></div>`
        ).join('');
        document.querySelectorAll('.cl-trig').forEach(b =>
            b.addEventListener('click',()=>this.selectClient(this.availableClients.find(x=>x.id==b.dataset.id)))
        );
    }

    filterProducts(t) {
        const term = t.toLowerCase();
        const f = this.availableProducts.filter(p => {
            // Solo filtramos si existe la propiedad 'name'. Si no, no se puede buscar por nombre.
            if (!p.hasOwnProperty('name')) return false;
            return p.name.toLowerCase().includes(term);
        });
        this.renderProducts(f);
    }

    filterClients(t) {
        this.renderClients(this.availableClients.filter(c => (c.full_name||'').toLowerCase().includes(t.toLowerCase())));
    }

    selectClient(c) {
        this.currentSale.clientId=c.id;
        document.getElementById('selected-client-display').innerHTML=`Cliente: <b>${c.full_name || c.nombre}</b>`;
        document.getElementById('selected-client-display').style.display='block';
        document.getElementById('sale-clients-list').style.display='none';
    }

    addToCart(p) {
        // Normalizamos el item al agregarlo al carrito para que tenga name y price fijos internamente
        const itemData = {
            id: p[this.colMap.id] || p.id,
            name: p[this.colMap.name] || 'Item',
            price: parseFloat(p[this.colMap.price]) || 0,
            quantity: 1
        };

        const ex = this.currentSale.items.find(i => i.id == itemData.id);
        if(ex) ex.quantity++;
        else this.currentSale.items.push(itemData);

        this.calcTotal();
    }

    calcTotal() {
        this.currentSale.subtotal = this.currentSale.items.reduce((s,i)=>s+(i.price*i.quantity),0);
        const sel = document.getElementById('sale-pay-method');
        const selectedOpt = sel.options[sel.selectedIndex];
        const surchargePercent = selectedOpt ? parseFloat(selectedOpt.dataset.surcharge || 0) : 0;

        this.currentSale.surcharge = this.currentSale.subtotal * (surchargePercent / 100);
        this.currentSale.total = this.currentSale.subtotal + this.currentSale.surcharge;

        document.getElementById('sale-total-display').textContent=`$${this.currentSale.total.toFixed(2)}`;


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
        el.textContent = tendered > 0 ? `$${change.toFixed(2)}` : '$0.00';
    }

    async submitSale() {
        const btn = document.getElementById('confirm-sale-btn'); btn.textContent = 'Procesando...'; btn.disabled = true;

        const payload = {
            total: this.currentSale.total,
            client_id: this.currentSale.clientId,
            employee_id: document.getElementById('sale-employee-select').value,
            payment_method_id: document.getElementById('sale-pay-method').value,
            items: this.currentSale.items.map(i=>({ id: i.id, nombre: i.name, cantidad: i.quantity, precio: i.price, subtotal: i.price*i.quantity })),
            notes: document.getElementById('sale-notes').value
        };

        try {
            const res = await createSale(payload);
            if (res.success) { this.closeModal('create-sale-modal'); this.loadSalesHistory(); pop_ups.success('Venta registrada con éxito'); }
            else { pop_ups.error(res.message); }
        } catch(e) { console.error(e); pop_ups.error('Error de conexión'); }
        btn.textContent = 'CONFIRMAR VENTA'; btn.disabled = false;
    }

    async loadSalesHistory() {
        // ... (Esta parte se ve bien, si tienes la función importada)
        const b = document.getElementById('sales-list-body');
        b.innerHTML = '<tr><td colspan="5" class="text-center">Cargando...</td></tr>';
        try {
            // Nota: getSalesHistory debe estar en api.js o importada
            const data = await getSalesHistory();
            if(!data || !data.sales || !data.sales.length) { b.innerHTML='<tr><td colspan="5" class="text-center" style="padding:2rem; color:#999;">Sin movimientos</td></tr>'; return; }
            b.innerHTML = data.sales.map(s => {
                return `<tr style="border-bottom:1px solid #eee;">
                    <td style="padding:10px;">${new Date(s.fecha_hora).toLocaleDateString()}</td>
                    <td style="padding:10px;">Venta #${s.id}</td>
                    <td style="padding:10px;">${s.nombre_empleado || '-'}</td>
                    <td style="padding:10px; text-align:right;"><b>$${parseFloat(s.total).toFixed(2)}</b></td>
                    <td style="padding:10px; text-align:center;"><button class="btn btn-secondary btn-sm"><i class="ph ph-eye"></i></button></td>
                </tr>`;
            }).join('');
        } catch(e){ b.innerHTML='<tr><td colspan="5" class="text-center">Error cargando historial</td></tr>'; }
    }
}

export const salesModuleInstance = new SalesModule();