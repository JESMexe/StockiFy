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

/* --- UTILIDAD: FORMATO DE MONEDA ARGENTINA --- */
const fmtMoney = (amount) => {
    return new Intl.NumberFormat('es-AR', {
        style: 'currency',
        currency: 'ARS',
        minimumFractionDigits: 2
    }).format(amount);
};

const fmtDate = (dateString) => {
    if (!dateString) return '-';
    const d = new Date(dateString);
    if (isNaN(d.getTime())) return '-'; // Previene Invalid Date
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
                        <button id="sales-sort-btn" class="btn btn-secondary" title="Ordenar"><i class="ph ph-sort-ascending"></i></button>
                        <button id="sales-create-btn" class="btn btn-primary">+ Nueva Venta</button>
                    </div>
                </div>

                <div class="table-wrapper" style="flex-grow:1; overflow-y:auto;">
                    <table id="sales-table" style="width:100%; border-collapse:collapse;">
                        <thead style="position:sticky; top:0; background:white; z-index:10; box-shadow:0 1px 2px rgba(0,0,0,0.1);">
                            <tr>
                                <th style="padding:12px;">Fecha</th>
                                <th style="padding:12px;">Cliente / Vendedor</th>
                                <th style="padding:12px; text-align:right;">Total</th>
                                <th style="padding:12px; text-align:center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="sales-list-body"></tbody>
                    </table>
                </div>

                <div id="create-sale-modal" class="modal-overlay hidden" style="display:none; z-index:1000;">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h3><i class="ph ph-shopping-cart"></i> Registrar Venta</h3>
                            <button class="modal-close-btn" id="close-sale-modal">&times;</button>
                        </div>
                        
                        <div id="config-warning-overlay" style="display:none; padding:20px; text-align:center;">
                            <p>Falta configuración de columnas.</p>
                            <a href="/dashboard.php" class="btn btn-sm btn-secondary">Ir a Configuración</a>
                        </div>

                        <div class="purchase-modal-body" id="sale-modal-body">
                            
                            <div class="purchase-col" style="flex: 1.2;">
                                <h4>1. Productos</h4>
                                <input type="text" id="sale-search-product" class="rustic-input" placeholder="Buscar o Escanear..." autocomplete="off" style="margin-bottom:15px;">
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
                                        <label class="form-section-title">Notas de Venta</label>
                                        <textarea id="sale-notes" class="rustic-input" placeholder="Comentarios opcionales..." style="height:50px;"></textarea>
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
                                        <label class="form-section-title">Agregar Pago</label>
                                        <div class="payment-row">
                                            <input type="number" id="pay-input-amount" class="rustic-input" placeholder="Monto" style="flex:1;">
                                            <select id="pay-method-select" class="rustic-select" style="flex:1.2;"></select>
                                            <button id="btn-add-payment">
                                                <i class="ph ph-plus" style="font-weight:bold; font-size: 1.1rem;"></i>
                                            </button>
                                        </div>
                                        
                                        <div id="payments-list" style="margin-top:10px;">
                                            <p style="color:#999; text-align:center; font-size:0.8rem;">Sin pagos registrados</p>
                                        </div>
                                    </div>

                                </div> 

                                <div class="totals-box">
                                    <div class="flex-row total-line"><span>Total Productos:</span> <span id="checkout-subtotal">$0,00</span></div>
                                    <div class="flex-row total-line" style="color:var(--accent-color);"><span>Total Recargos:</span> <span id="checkout-surcharges">$0,00</span></div>
                                    
                                    <div class="flex-row total-line total-final">
                                        <span>Total a Pagar:</span> <span id="checkout-total-final">$0,00</span>
                                    </div>
                                    
                                    <div class="flex-row total-line" style="margin-top:10px; color:var(--sale-green);">
                                        <span>Pagado:</span> <span id="checkout-paid">$0,00</span>
                                    </div>
                                    <div class="flex-row total-line" style="font-weight:bold;" id="change-row">
                                        <span>Falta:</span> <span id="checkout-diff">$0,00</span>
                                    </div>
                                </div>

                                <button id="confirm-sale-btn" disabled>CONFIRMAR VENTA</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="detail-sale-modal" class="modal-overlay hidden" style="display:none; z-index:1050;">
                    <div class="modal-content" style="height:auto; max-height:90vh;">
                        <div class="modal-header">
                            <h3><i class="ph ph-receipt"></i> Detalle de Venta #<span id="detail-id"></span></h3>
                            <button class="modal-close-btn" id="close-detail-modal">&times;</button>
                        </div>
                        <div class="purchase-modal-body" style="flex-direction:column; overflow-y:auto;">
                            <div style="display:flex; justify-content:space-between; margin-bottom:15px; border-bottom:2px dashed #000; padding-bottom:10px;">
                                <div>
                                    <div style="font-weight:bold; font-size:1.1rem;" id="detail-date"></div>
                                    <div style="color:#666;" id="detail-customer"></div>
                                </div>
                                <div style="text-align:right;">
                                    <div style="font-weight:800; font-size:1.5rem; color:var(--sale-green);" id="detail-total"></div>
                                    <div style="color:#666;" id="detail-seller"></div>
                                </div>
                            </div>
                            
                            <h4 style="font-size:0.9rem; text-transform:uppercase; border-bottom:1px solid #ccc;">Productos</h4>
                            <table style="width:100%; border-collapse:collapse; margin-bottom:20px;">
                                <thead><tr style="text-align:left; color:#666; font-size:0.85rem;"><th>Producto</th><th style="text-align:center;">Cant</th><th style="text-align:right;">Precio</th><th style="text-align:right;">Subtotal</th></tr></thead>
                                <tbody id="detail-items-list"></tbody>
                            </table>

                            <h4 style="font-size:0.9rem; text-transform:uppercase; border-bottom:1px solid #ccc;">Pagos</h4>
                            <table style="width:100%; border-collapse:collapse;">
                                <tbody id="detail-payments-list"></tbody>
                            </table>
                            
                            <div id="detail-notes" style="margin-top:20px; font-style:italic; color:#666; background:#eee; padding:10px; border-radius:4px; display:none;"></div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    attachEvents() {
        document.getElementById('sales-create-btn')?.addEventListener('click', () => this.openCreateModal());
        document.getElementById('close-sale-modal')?.addEventListener('click', () => this.closeModal('create-sale-modal'));
        document.getElementById('close-detail-modal')?.addEventListener('click', () => this.closeModal('detail-sale-modal'));

        // --- LÓGICA DE BÚSQUEDA Y ESCÁNER ---
        const searchInput = document.getElementById('sale-search-product');
        if(searchInput) {
            // Filtrado visual mientras escriben
            searchInput.addEventListener('input', (e) => this.filterProducts(e.target.value));

            // DETECTOR DE "ENTER" (Escáner de código de barras)
            searchInput.addEventListener('keydown', (e) => {
                if(e.key === 'Enter') {
                    e.preventDefault(); // Evitar submit si hubiera form
                    this.handleScan(e.target.value);
                }
            });
        }

        document.getElementById('btn-add-payment')?.addEventListener('click', () => this.addPayment());
        document.getElementById('pay-input-amount')?.addEventListener('keypress', (e) => { if(e.key==='Enter') this.addPayment(); });
        document.getElementById('sale-commission-pct')?.addEventListener('input', () => this.calculateCommission());
        document.getElementById('confirm-sale-btn')?.addEventListener('click', () => this.submitSale());
    }

    closeModal(id) { document.getElementById(id).classList.add('hidden'); document.getElementById(id).style.display='none'; }

    async fetchResources() {
        try {
            const [prefRes, data, empRes] = await Promise.all([
                getCurrentInventoryPreferences(),
                getSaleResources(),
                getEmployeeList()
            ]);

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
        this.renderProducts(this.resources.products);
        this.fillSelect('sale-customer', this.resources.customers, 'id', 'full_name', 'Cliente General');
        this.fillSelect('sale-seller', this.resources.employees, 'id', 'full_name', 'Sin Vendedor');
        this.fillSelect('pay-method-select', this.resources.paymentMethods, 'id', 'name', null);

        // Enfocar buscador automáticamente al abrir para escanear rápido
        setTimeout(() => document.getElementById('sale-search-product').focus(), 100);

        this.updateCartUI();
        this.recalcSale();

        const modal = document.getElementById('create-sale-modal');
        modal.classList.remove('hidden');
        modal.style.display = 'flex';
    }

    /* --- REEMPLAZAR ESTA FUNCIÓN EN SALES.JS --- */
    async showDetails(id) {
        try {
            const res = await getSaleDetails(id);
            if(!res.success) throw new Error(res.message || "Error al cargar detalles");
            const s = res.sale;

            const bodyContainer = document.querySelector('#detail-sale-modal .purchase-modal-body');

            // --- A. Cabecera ---
            let html = `
                <div style="text-align:center; margin-bottom:20px; border-bottom:2px dashed var(--ticket-color); padding-bottom:15px;">
                    <div style="font-size:1.3rem; font-weight:900; letter-spacing:1px;">TICKET #${s.id}</div>
                    <div style="font-size:0.9rem; margin-top:5px;">${fmtDate(s.created_at)}</div>
                </div>
            `;

            // --- B. Cliente ---
            html += `
                <div class="ticket-row" style="margin-bottom:10px;">
                    <div style="display:flex; flex-direction:column; width:100%;">
                        <span style="font-size:0.7rem; text-transform:uppercase; color:#666; font-weight:bold;">CLIENTE:</span>
                        <span style="font-size:1rem; font-weight:800;">${s.customer_name || 'Consumidor Final'}</span>
                    </div>
                </div>
            `;

            // --- C. Vendedor (LÓGICA CORREGIDA) ---
            // Definimos qué mostrar
            let sellerDisplay = "No especificado";
            let commissionDisplay = ""; // Por defecto oculto

            if (s.seller_name && s.seller_name !== '-') {
                sellerDisplay = s.seller_name;
                const comm = s.commission_amount ? parseFloat(s.commission_amount) : 0;
                // Solo mostramos la etiqueta de comisión si hay vendedor real
                commissionDisplay = `
                    <span style="font-size:0.8rem; background:#eee; padding:2px 6px; border-radius:4px; margin-left: auto;">
                        Com: ${fmtMoney(comm)}
                    </span>`;
            }

            html += `
                <div class="ticket-row" style="margin-bottom:15px; border-bottom:1px dotted #ccc; padding-bottom:10px;">
                    <div style="display:flex; flex-direction:column; width:100%;">
                        <span style="font-size:0.7rem; text-transform:uppercase; color:#666; font-weight:bold;">ATENDIDO POR:</span>
                        <div style="display:flex; justify-content:space-between; align-items:center;">
                            <span style="font-size:1rem; font-weight:800;">${sellerDisplay}</span>
                            ${commissionDisplay}
                        </div>
                    </div>
                </div>
            `;

            bodyContainer.innerHTML = html;

            // --- D. Productos ---
            bodyContainer.insertAdjacentHTML('beforeend', '<h4>PRODUCTOS</h4>');
            const productsTable = document.createElement('table');
            productsTable.innerHTML = `
                <thead>
                    <tr>
                        <th style="text-align:left;">DESCRIPCIÓN</th>
                        <th style="text-align:center; width:40px;">CANT</th>
                        <th style="text-align:right;">TOTAL</th>
                    </tr>
                </thead>
                <tbody id="detail-items-list"></tbody>
            `;
            bodyContainer.appendChild(productsTable);

            document.getElementById('detail-items-list').innerHTML = s.items.map(i => `
                <tr>
                    <td style="text-align:left; line-height:1.2;">
                        ${i.product_name}
                        <div style="font-size:0.75rem; color:#666;">Unit: ${fmtMoney(i.price)}</div>
                    </td>
                    <td style="text-align:center; font-weight:bold; vertical-align:top;">${parseFloat(i.quantity)}</td>
                    <td style="text-align:right; font-weight:800; vertical-align:top;">${fmtMoney(i.subtotal)}</td>
                </tr>`).join('');

            // --- E. Total ---
            bodyContainer.insertAdjacentHTML('beforeend', `
                <div id="detail-total-section">
                    <span id="detail-total-label">TOTAL A PAGAR</span>
                    <span id="detail-total">${fmtMoney(s.total_final)}</span>
                </div>
            `);

            // --- F. Pagos ---
            bodyContainer.insertAdjacentHTML('beforeend', '<h4>FORMA DE PAGO</h4>');
            const paymentsTable = document.createElement('table');
            paymentsTable.innerHTML = '<tbody id="detail-payments-list"></tbody>';
            bodyContainer.appendChild(paymentsTable);
            document.getElementById('detail-payments-list').innerHTML = s.payments.map(p => `
                <tr class="ticket-row" style="border:none;">
                    <td style="text-align:left; font-weight:600;">${p.payment_method_name}</td>
                    <td style="text-align:right; font-weight:800;">${fmtMoney(p.amount)}</td>
                </tr>`).join('');

            // --- G. Notas ---
            if(s.notes) {
                bodyContainer.insertAdjacentHTML('beforeend', `<div id="detail-notes"><strong>NOTAS:</strong><br>${s.notes}</div>`);
            }

            const modal = document.getElementById('detail-sale-modal');
            modal.classList.remove('hidden'); modal.style.display = 'flex';

        } catch(e) {
            pop_ups.error("Error visualizando ticket: " + e.message);
        }
    }

    // NUEVA FUNCIÓN: Editar (Re-abrir)
    async editSale(id) {
        if(!await pop_ups.confirm("¿Editar Venta?", "Esto ELIMINARÁ la venta actual, devolverá el stock y cargará los productos en el carrito.")) return;

        try {
            // 1. Obtener datos de la venta a editar
            const res = await getSaleDetails(id);
            if(!res.success) throw new Error("No se pudo leer la venta");
            const oldSale = res.sale;

            // 2. Eliminar la venta antigua (revertir stock)
            await fetch('/api/sales/delete.php', {
                method: 'POST',
                body: JSON.stringify({id})
            });

            // 3. Abrir el modal de creación (limpio)
            await this.openCreateModal();

            // 4. Llenar el formulario con los datos viejos
            if(oldSale.customer_id) document.getElementById('sale-customer').value = oldSale.customer_id;
            if(oldSale.seller_id) document.getElementById('sale-seller').value = oldSale.seller_id;
            document.getElementById('sale-notes').value = oldSale.notes || '';

            // 5. Reconstruir el carrito
            this.currentSale.items = oldSale.items.map(i => ({
                id: i.product_id,
                nombre: i.product_name,
                cantidad: parseFloat(i.quantity),
                precio: parseFloat(i.price), // Precio histórico
                max_stock: 9999 // Asumimos stock disponible ya que acabamos de devolverlo
            }));

            // 6. Recalcular todo
            this.updateCartUI();
            this.recalcSale();

            pop_ups.info("Venta cargada para edición");

        } catch(e) {
            pop_ups.error("Error al editar: " + e.message);
        }
    }

    renderProducts(list) {
        const c = document.getElementById('sale-products-list');
        c.innerHTML = list.map(p => {
            const stock = parseFloat(p.stock);
            const style = stock > 0 ? '' : 'opacity:0.6; pointer-events:none; filter:grayscale(1);';
            const badge = stock > 0
                ? `<span style="font-size:0.75rem; color:#666;">Stock: ${stock}</span>`
                : `<span style="font-size:0.75rem; color:red; font-weight:bold;">AGOTADO</span>`;

            return `
            <div class="resource-item prod-trigger" data-id="${p.id}" style="${style}">
                <div style="flex:1; overflow:hidden; padding-right:10px;">
                    <div style="font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${p.name}</div>
                    ${badge}
                </div>
                <div style="font-weight:700; color:var(--sale-green); font-size:1rem;">
                    ${fmtMoney(p.price)}
                </div>
            </div>`;
        }).join('');

        c.querySelectorAll('.prod-trigger').forEach(b =>
            b.addEventListener('click', () => this.addToCart(this.resources.products.find(x=>x.id==b.dataset.id)))
        );
    }

    /* --- NUEVA LÓGICA DE BÚSQUEDA PROFUNDA Y ESCÁNER --- */

    // Filtro visual (Input event)
    filterProducts(term) {
        const t = term.toLowerCase().trim();
        if(!t) {
            this.renderProducts(this.resources.products);
            return;
        }

        const filtered = this.resources.products.filter(p => {
            // Buscamos en el nombre
            if (p.name && p.name.toLowerCase().includes(t)) return true;

            // Buscamos en CUALQUIER otra propiedad (Barcode, SKU, ID, etc.)
            // Esto es agnóstico a la base de datos, si la API trae la columna, la busca.
            return Object.values(p).some(val =>
                val && String(val).toLowerCase().includes(t)
            );
        });

        this.renderProducts(filtered);
    }

    // Acción al presionar ENTER (Lector de barras)
    handleScan(term) {
        const t = term.toLowerCase().trim();
        if(!t) return;

        // 1. Buscar coincidencia EXACTA primero (prioridad código de barras)
        let match = this.resources.products.find(p => {
            // Verificamos coincidencia exacta en cualquier valor del objeto (barcode, codigo, nombre exacto)
            return Object.values(p).some(val => val && String(val).toLowerCase() === t);
        });

        // 2. Si no hay exacta, mirar si el filtro visual arrojó UN solo resultado
        if (!match) {
            const visualMatches = this.resources.products.filter(p => {
                if (p.name && p.name.toLowerCase().includes(t)) return true;
                return Object.values(p).some(val => val && String(val).toLowerCase().includes(t));
            });
            if (visualMatches.length === 1) {
                match = visualMatches[0];
            }
        }

        // 3. Acción
        if (match) {
            this.addToCart(match);

            // UX: Limpiar input y reiniciar lista para el siguiente escaneo
            const input = document.getElementById('sale-search-product');
            input.value = '';
            input.focus(); // Mantener foco para escanear en ráfaga
            this.filterProducts(''); // Restaurar lista completa

            // Feedback sutil
            pop_ups.success(`Agregado: ${match.name}`);
        } else {
            pop_ups.warning("Producto no encontrado");
        }
    }

    addToCart(p) {
        const exist = this.currentSale.items.find(i => i.id == p.id);
        if (exist) {
            if (exist.cantidad >= p.stock) return pop_ups.warning("Stock máximo alcanzado");
            exist.cantidad++;
        } else {
            this.currentSale.items.push({ id: p.id, nombre: p.name, cantidad: 1, precio: parseFloat(p.price), max_stock: parseFloat(p.stock) });
        }
        this.recalcSale();
    }

    updateCartUI() {
        const c = document.getElementById('sale-cart-items');
        if(this.currentSale.items.length === 0) {
            c.innerHTML = '<div style="text-align:center; color:#999; margin-top:50px;">Carrito vacío</div>';
        } else {
            c.innerHTML = this.currentSale.items.map((item, idx) => `
                <div class="cart-item">
                    <div class="cart-left">
                        <div class="cart-title">${item.nombre}</div>
                        <div class="cart-total-price">${fmtMoney(item.precio * item.cantidad)}</div>
                    </div>
                    
                    <div class="cart-right">
                        <div class="cart-unit-price">${fmtMoney(item.precio)} c/u</div>
                        <div class="cart-controls">
                            <button class="btn-xs sub" data-idx="${idx}">-</button>
                            <span style="font-weight:600; font-size:0.9rem; min-width:24px; text-align:center;">${item.cantidad}</span>
                            <button class="btn-xs add" data-idx="${idx}">+</button>
                            <button class="btn-xs del" data-idx="${idx}" style="margin-left:4px;"><i class="ph ph-trash"></i></button>
                        </div>
                    </div>
                </div>
            `).join('');

            c.querySelectorAll('.add').forEach(b => b.addEventListener('click', () => {
                const item = this.currentSale.items[b.dataset.idx];
                if (item.cantidad >= item.max_stock) return pop_ups.warning("Stock insuficiente");
                item.cantidad++; this.recalcSale();
            }));
            c.querySelectorAll('.sub').forEach(b => b.addEventListener('click', () => {
                const item = this.currentSale.items[b.dataset.idx];
                item.cantidad--; if(item.cantidad < 1) this.currentSale.items.splice(b.dataset.idx, 1);
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
        const surchargeVal = amount * (surchargePct / 100);

        this.currentSale.payments.push({
            method_id: methodId,
            name: methodObj.name,
            amount: amount,
            surcharge_percent: surchargePct,
            surcharge_val: surchargeVal
        });

        amtInput.value = '';
        this.recalcSale();
    }

    removePayment(idx) {
        this.currentSale.payments.splice(idx, 1);
        this.recalcSale();
    }

    updatePaymentUI() {
        const c = document.getElementById('payments-list');
        if (this.currentSale.payments.length === 0) {
            c.innerHTML = '<p style="color:#999; text-align:center; font-size:0.8rem; margin-top:10px;">Sin pagos registrados</p>';
        } else {
            c.innerHTML = this.currentSale.payments.map((p, idx) => `
                <div style="background:#FFF; border:1px solid var(--color-black); border-radius:4px; padding:8px; margin-bottom:5px; display:flex; justify-content:space-between; align-items:center;">
                    <div>
                        <div style="font-weight:600;">${fmtMoney(p.amount)} <span style="font-weight:normal; font-size:0.85rem; color:#555;">(${p.name})</span></div>
                        ${p.surcharge_val > 0 ? `<div style="font-size:0.75rem; color:var(--accent-color);">+ Recargo: ${fmtMoney(p.surcharge_val)}</div>` : ''}
                    </div>
                    <span style="cursor:pointer; color:var(--accent-red);" class="rm-pay" data-idx="${idx}"><i class="ph ph-x-circle" style="font-size:1.2rem;"></i></span>
                </div>
            `).join('');
            c.querySelectorAll('.rm-pay').forEach(b => b.addEventListener('click', () => this.removePayment(b.dataset.idx)));
        }
    }

    recalcSale() {
        this.currentSale.subtotal_items = this.currentSale.items.reduce((sum, i) => sum + (i.precio * i.cantidad), 0);
        this.currentSale.total_surcharges = this.currentSale.payments.reduce((sum, p) => sum + p.surcharge_val, 0);
        this.currentSale.total_final = this.currentSale.subtotal_items + this.currentSale.total_surcharges;
        const totalPaid = this.currentSale.payments.reduce((sum, p) => sum + p.amount + p.surcharge_val, 0);
        const diff = totalPaid - this.currentSale.total_final;

        document.getElementById('checkout-subtotal').textContent = fmtMoney(this.currentSale.subtotal_items);
        document.getElementById('checkout-surcharges').textContent = fmtMoney(this.currentSale.total_surcharges);
        document.getElementById('checkout-total-final').textContent = fmtMoney(this.currentSale.total_final);
        document.getElementById('checkout-paid').textContent = fmtMoney(totalPaid);

        const diffEl = document.getElementById('checkout-diff');
        const rowDiff = document.getElementById('change-row');

        if (diff >= 0) {
            rowDiff.style.color = 'var(--color-gray)';
            rowDiff.querySelector('span:first-child').textContent = "Vuelto:";
            diffEl.textContent = fmtMoney(diff);
            document.getElementById('confirm-sale-btn').disabled = (this.currentSale.items.length === 0);
        } else {
            rowDiff.style.color = 'var(--accent-red)';
            rowDiff.querySelector('span:first-child').textContent = "Falta:";
            diffEl.textContent = fmtMoney(Math.abs(diff));
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
        const s = document.getElementById(id);
        s.innerHTML = placeholder ? `<option value="">${placeholder}</option>` : '';
        list.forEach(i => {
            const op = document.createElement('option');
            op.value = i[valKey];
            let txt = i[textKey];
            if(id === 'pay-method-select' && i.surcharge > 0) txt += ` (+${parseFloat(i.surcharge)}%)`;
            op.textContent = txt;
            s.appendChild(op);
        });
    }

    async submitSale() {
        const btn = document.getElementById('confirm-sale-btn');
        btn.disabled = true; btn.textContent = 'Procesando...';

        const pct = parseFloat(document.getElementById('sale-commission-pct').value) || 0;
        const commAmount = this.currentSale.subtotal_items * (pct / 100);

        const payload = {
            customer_id: document.getElementById('sale-customer').value || null,
            seller_id: document.getElementById('sale-seller').value || null,
            commission_amount: commAmount,
            total_final: this.currentSale.total_final,
            notes: document.getElementById('sale-notes').value,
            items: this.currentSale.items.map(i => ({
                id: i.id, nombre: i.nombre, cantidad: i.cantidad, precio: i.precio, subtotal: i.precio * i.cantidad
            })),
            payments: this.currentSale.payments
        };

        try {
            const res = await createSale(payload);
            if (res.success) {
                this.closeModal('create-sale-modal');
                await this.fetchResources();
                await this.loadHistory(this.currentSortOrder);
                pop_ups.success("Venta Exitosa");
            } else {
                pop_ups.error(res.message || "Error al procesar venta");
            }
        } catch(e) {
            console.error(e);
            pop_ups.error("Error de conexión");
        }
        btn.disabled = false; btn.textContent = 'CONFIRMAR VENTA';
    }

    /* --- HISTORIAL (Visualización actualizada) --- */
    async loadHistory(order='desc') {
        const b = document.getElementById('sales-list-body');
        b.innerHTML = '<tr><td colspan="4" style="text-align:center; padding:20px;">Cargando...</td></tr>';
        try {
            const data = await getSalesHistory(order);
            if(!data.success || !data.sales || data.sales.length === 0) {
                b.innerHTML='<tr><td colspan="4" style="text-align:center; padding:20px;">Sin ventas registradas</td></tr>';
                return;
            }
            // AQUI ESTA EL CAMBIO PRINCIPAL EN EL HTML GENERADO:
            b.innerHTML = data.sales.map(s => ` 
                <tr style="border-bottom:1px solid #eee;"> 
                    <td style="padding:12px;">
                        ${fmtDate(s.created_at)}
                        <div style="font-size:0.75rem; color:#999;">#${s.id}</div>
                    </td> 
                    <td style="padding:12px;"> 
                        <div style="font-weight:600;">${s.customer_name || 'Cliente General'}</div> 
                    </td> 
                    <td style="padding:12px; text-align:right; vertical-align:top;">
                        <div style="font-weight:800; color:var(--sale-green); font-size:1.1rem;">${fmtMoney(s.total)}</div>
                        ${s.seller_name && s.seller_name !== '-' ?
                `<div style="font-size:0.8rem; color:#666; margin-top:4px; line-height:1.2;">
                                <i class="ph ph-user-circle"></i> ${s.seller_name} <br>
                                <span style="color:var(--accent-color); font-weight:600;">(Com: ${fmtMoney(s.commission)})</span>
                             </div>`
                : ''} 
                    </td> 
                    <td style="padding:12px; text-align:center;"> 
                        <div class="btn-icon-group" style="justify-content:center;"> 
                            <button class="action-btn view" title="Ver Ticket" onclick="window.salesModuleInstance.showDetails('${s.id}')"><i class="ph ph-receipt"></i></button> 
                            <button class="action-btn edit" title="Editar (Reabrir en Carrito)" onclick="window.salesModuleInstance.editSale('${s.id}')"><i class="ph ph-pencil-simple"></i></button> 
                            <button class="action-btn delete" title="Eliminar" onclick="window.salesModuleInstance.deleteSale('${s.id}')"><i class="ph ph-trash"></i></button> 
                        </div> 
                    </td> 
                </tr>`).join('');
        } catch(e) { console.error(e); }
    }

    async deleteSale(id) {
        if(!await pop_ups.confirm("Eliminar Venta", "Se devolverá el stock y anulará el registro. ¿Seguro?")) return;
        try {
            await fetch('/api/sales/delete.php', {method:'POST', body:JSON.stringify({id})});
            this.loadHistory();
            pop_ups.info("Venta eliminada.");
        } catch(e){ pop_ups.error("Error al eliminar."); }
    }
}

window.salesModuleInstance = new SalesModule();
export const salesModuleInstance = window.salesModuleInstance;