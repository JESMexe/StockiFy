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
const fmtMoney = (amount, currency = 'ARS') => {
    if (amount === undefined || amount === null || isNaN(amount)) return '$ 0,00';
    return new Intl.NumberFormat('es-AR', { style: 'currency', currency: currency, minimumFractionDigits: 2 }).format(amount);
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
        this.currentSale = { items: [], payments: [], subtotal_items: 0, total_surcharges: 0, total_final: 0, exchange_rate: 1 };
        this.resources = { products: [], customers: [], paymentMethods: [], employees: [], config: null };
        this.currentSortOrder = 'DESC';

        // ESTADO DE LA UI
        this.activePaymentTab = 'ARS';
        this.rates = { USD: 1, USDT: 1 };
        this.showingCartInUSD = false;
    }

    init() {
        if (this.isInitialized) {
            if(document.getElementById(this.containerId)) this.loadHistory(this.currentSortOrder);
            return;
        }

        const container = document.getElementById(this.containerId);

        if (container) {
            container.innerHTML = this.renderBaseStructure();

            // [FIX MOVIL] Mover modales al body para que se vean
            const modalCreate = document.getElementById('create-sale-modal');
            const modalDetail = document.getElementById('detail-sale-modal');

            if (modalCreate) document.body.appendChild(modalCreate);
            if (modalDetail) document.body.appendChild(modalDetail);

        } else {
            // Fallback si no existe la vista de tabla
            const tempDiv = document.createElement('div');
            tempDiv.innerHTML = this.renderBaseStructure();
            tempDiv.querySelectorAll('.modal-overlay').forEach(m => document.body.appendChild(m));
        }

        this.attachEvents();

        if(container) this.loadHistory(this.currentSortOrder);

        this.isInitialized = true;
    }

    renderBaseStructure() {
        return `
            <div class="sales-layout">
                <div class="table-header">
                    <h2>Gestión de Ventas</h2>
                    <div class="table-controls">
                        <button id="sales-renumber-btn" class="btn btn-secondary hidden" title="Renumerar IDs"><i class="ph ph-list-numbers"></i></button>
                        <button id="sales-sort-btn" class="btn btn-secondary" title="Ordenar por Fecha/ID"><i class="ph ph-sort-ascending" id="sales-sort-icon"></i></button>
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
                    <div class="modal-content" style="width: 95vw; max-width: 2000px;">
                        
                        <div class="modal-header" style="display:flex; justify-content:space-between; align-items:center;">
                            <div style="display:flex; align-items:center; gap:10px;">
                                <button id="mobile-back-to-products" class="btn btn-secondary hidden-desktop" style="display:none; padding:5px 10px;">
                                    <i class="ph-bold ph-arrow-left"></i>
                                </button>
                                <h3><i class="ph-bold ph-shopping-cart"></i> Registrar Venta</h3>
                            </div>
                            <button class="modal-close-btn" id="close-sale-modal">&times;</button>
                        </div>
                        
                        <div id="config-warning-overlay" style="display:none; flex-direction:column; align-items:center; justify-content:center; height:100%; text-align:center; padding:40px; color:#555;">
                            <i class="ph ph-warning-circle" style="font-size: 3rem; color: var(--accent-color); margin-bottom: 20px;"></i>
                            <h3 style="margin-bottom: 15px; font-weight: 800; color: #333;">Falta Configuración de Columnas</h3>
                            <p style="max-width: 500px; margin-bottom: 10px; line-height: 1.5;">Para usar el módulo de ventas, el sistema necesita saber qué columnas de tu tabla corresponden a los datos clave.</p>
                            <button id="go-to-config-btn" class="btn btn-primary">Cerrar Ventana</button>
                        </div>

                        <div class="purchase-modal-body" id="sale-modal-body">
                            
                            <div class="purchase-col" id="step-products" style="flex: 1.2;">
                                <h4>1. Productos</h4>
                                <div style="display:flex; gap:8px; margin-bottom:15px; align-items: center;">
                                    <input type="text" id="sale-search-product" class="rustic-input" placeholder="Buscar o Escanear..." autocomplete="off" style="flex:1; width: 100%;">
                                    <button id="btn-toggle-manual" class="btn btn-secondary" title="Agregar ítem manual" style="color: var(--accent-color); white-space: nowrap;">
                                        <i class="ph-bold ph-hand-pointing"></i> Manual
                                    </button>
                                </div>
                                <div id="manual-item-form" style="display:none; background:#f9f9f9; padding:10px; border:1px dashed var(--accent-color); border-radius:6px; margin-bottom:15px;">
                                    <input type="text" id="manual-name" class="rustic-input" placeholder="Descripción" style="margin-bottom:8px; width:100%;">
                                    <div style="display:flex; gap:8px;">
                                        <input type="number" id="manual-price" class="rustic-input" placeholder="$ Precio" style="flex:1;">
                                        <input type="number" id="manual-qty" class="rustic-input" value="1" style="width:60px;">
                                        <button id="btn-add-manual" class="btn btn-primary"><i class="ph ph-plus"></i></button>
                                    </div>
                                </div>
                                <div id="sale-products-list" class="scrollable-list"></div>
                            </div>

                            <div id="step-checkout" style="display:contents;">
                                
                                <div class="purchase-col" style="flex: 1;">
                                    <h4>2. Carrito</h4>
                                    <div id="sale-cart-items" class="scrollable-list" style="background: #FFF; border-radius: 8px;">
                                        <div style="text-align:center; color:#999; margin-top:50px;">Carrito vacío</div>
                                    </div>
                                    <div style="display:flex; flex-direction:column; gap:10px; padding-top:10px; border-top:2px dashed var(--color-black);">
                                        <div style="display:flex; justify-content:space-between; align-items:flex-end;">
                                            <button id="toggle-cart-currency-btn" class="btn btn-sm btn-secondary" style="padding: 4px 10px; font-size: 0.75rem; width: auto;">
                                                <i class="ph-bold ph-currency-circle-dollar"></i> Ver en USD
                                            </button>
                                            <div style="text-align:right;">
                                                <span style="font-size:0.8rem; color:#666;">Subtotal:</span>
                                                <div id="cart-subtotal-display" style="font-weight:bold; font-size:1.1rem;">$0,00</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="purchase-col" style="flex: 1.1;">
                                    <h4>3. Cierre y Pagos</h4>
                                    <div class="form-section" style="margin-bottom: 15px;">
                                        <label class="form-section-title">Notas de Venta</label>
                                        <textarea id="sale-notes" class="rustic-input" placeholder="Opcional..." style="height:40px; width:100%; resize:none;"></textarea>
                                    </div>
                                    <div class="scrollable-list" style="padding-right: 5px; overflow-x: visible;">
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
                                        <div class="form-section" style="margin-top: 15px; border: 1px solid #ddd; padding: 10px; border-radius: 8px; background: #fff;">
                                            <label class="form-section-title" style="padding-bottom: 0; bottom: 0; margin-bottom: 0">Calculadora </label>
                                            <h6 style="top: 0; margin-bottom: 10px; color: #888888; font-size: 12px ">(Vuelto y control informativo)</h6>
                                            <div class="currency-tabs" style="display:flex; gap:5px; margin-bottom:10px; border-bottom: 2px solid #eee;">
                                                <button class="currency-tab active" data-curr="ARS" style="padding: 5px 15px; border:none; background:none; font-weight:bold; cursor:pointer; border-bottom: 3px solid var(--accent-color);">ARS</button>
                                                <button class="currency-tab" data-curr="USD" style="padding: 5px 15px; border:none; background:none; color:#999; cursor:pointer;">USD</button>
                                                <button class="currency-tab" data-curr="USDT" style="padding: 5px 15px; border:none; background:none; color:#999; cursor:pointer;">USDT</button>
                                            </div>
                                            <div id="rate-display-info" style="font-size:0.8rem; color:var(--accent-color); margin-bottom:5px; display:none;">
                                                Cotización aplicada: $1200.00
                                            </div>
                                            <div class="payment-row">
                                                <div style="flex:1; position:relative;">
                                                    <span id="pay-currency-symbol" style="position:absolute; left:8px; top:8px; color:#666; pointer-events:none;">$</span>
                                                    <input type="number" id="pay-input-amount" class="rustic-input" placeholder="Monto" style="width:100%; padding-left:45px;">
                                                </div>
                                                <select id="pay-method-select" class="rustic-select" style="flex:1.2;"></select>
                                                <button id="btn-add-payment"><i class="ph ph-plus" style="font-weight:bold;"></i></button>
                                            </div>
                                            <div id="payments-list" style="margin-top:10px;"><p style="color:#999; text-align:center; font-size:0.8rem;">Sin pagos registrados</p></div>
                                        </div>
                                    </div> 
                                    
                                    <div class="totals-box">
                                        <div class="flex-row total-line"><span>Total valor Productos:</span> <span id="checkout-subtotal">$0,00</span></div>
                                        <div class="flex-row total-line" style="color:var(--color-gray);"><span>Recargos:</span> <span id="checkout-surcharges">$0,00</span></div>
                                        <div class="flex-row total-line total-final"><span>Total a pagar:</span> <span id="checkout-total-final">$0,00</span></div>
                                        <div class="flex-row total-line" style="font-weight:bold;" id="change-row"><span>Falta para completar:</span> <span id="checkout-diff">$0,00</span></div>
                                    </div>
                                    <button id="confirm-sale-btn" class="btn btn-primary w-full" disabled>Confirmar Venta</button>
                                </div>
                            </div>
                            
                            <div id="mobile-checkout-bar" class="hidden-desktop" style="display:none; position:fixed; bottom:0; left:0; width:100%; background:white; padding:15px; border-top:1px solid #ccc; box-shadow:0 -5px 15px rgba(0,0,0,0.1); z-index:99999;">
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <div>
                                        <div id="mob-bar-count" style="font-size:0.8rem; color:#666;">0 Ítems</div>
                                        <div id="mob-bar-total" style="font-size:1.4rem; font-weight:800; color:var(--accent-color);">$ 0,00</div>
                                    </div>
                                    <button id="btn-go-to-checkout" class="btn btn-primary" style="padding:10px 20px;">
                                        Ir a Pagar <i class="ph-bold ph-arrow-right"></i>
                                    </button>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <div id="detail-sale-modal" class="modal-overlay hidden" style="display:none; z-index:2000;">
                    <div class="modal-content" style="width:400px; max-width:90vw; background:#fff; padding:0; overflow:hidden; display:flex; flex-direction:column;">
                        <div class="modal-header" style="padding:15px; border-bottom:1px solid #eee;">
                            <h3>Ticket de Venta</h3>
                            <button class="modal-close-btn" id="close-detail-modal">&times;</button>
                        </div>
                        <div id="detail-modal-content" style="padding:20px; overflow-y:auto; max-height:80vh; background:#fff;"></div>
                    </div>
                </div>
            </div>
        `;
    }

    // [IMPORTANTE] Este método ahora es parte de la CLASE
    setMobileView(view) {
        const btnCheckout = document.getElementById('btn-go-to-checkout');
        const btnBackHeader = document.getElementById('mobile-back-to-products');
        const stepProd = document.getElementById('step-products');
        const stepCheck = document.getElementById('step-checkout');
        const bar = document.getElementById('mobile-checkout-bar');

        // Solo actuar si estamos en modo móvil (existe la barra)
        if (!bar || window.innerWidth > 768) return;

        if (view === 'checkout') {
            // --- MODO CHECKOUT ---
            if(stepProd) stepProd.style.display = 'none';
            if(stepCheck) stepCheck.style.display = 'contents';

            // Mostrar flecha volver arriba
            if(btnBackHeader) btnBackHeader.style.display = 'flex';

            // Configurar botón de abajo como "Seguir Agregando"
            if(btnCheckout) {
                btnCheckout.innerHTML = '<i class="ph-bold ph-plus"></i> Seguir Agregando';
                btnCheckout.className = 'btn btn-secondary';
                // Al hacer clic, volver a productos
                btnCheckout.onclick = (e) => {
                    e.preventDefault(); e.stopPropagation();
                    this.setMobileView('products');
                };
            }

        } else {
            // --- MODO PRODUCTOS (DEFAULT) ---
            if(stepProd) stepProd.style.display = 'flex';
            if(stepCheck) stepCheck.style.display = 'none';

            // Ocultar flecha volver arriba
            if(btnBackHeader) btnBackHeader.style.display = 'none';

            // Configurar botón de abajo como "Ir a Pagar"
            if(btnCheckout) {
                btnCheckout.innerHTML = 'Ir a Pagar <i class="ph-bold ph-arrow-right"></i>';
                btnCheckout.className = 'btn btn-primary';
                // Al hacer clic, ir a checkout
                btnCheckout.onclick = (e) => {
                    e.preventDefault(); e.stopPropagation();
                    this.setMobileView('checkout');
                };
            }

            bar.style.display = 'block';
        }
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

        document.getElementById('sales-renumber-btn')?.addEventListener('click', async () => {
            if(await pop_ups.confirm("¿Renumerar Historial?", "Se asignarán nuevos IDs...")) {
                try {
                    const res = await fetch('/api/sales/reset-ids.php');
                    const data = await res.json();
                    if(data.success) { pop_ups.info("Historial reorganizado."); await this.loadHistory(this.currentSortOrder); }
                    else { pop_ups.error("Error: " + (data.message || "No se pudo renumerar")); }
                } catch(e) { console.error(e); pop_ups.error("Error de conexión"); }
            }
        });

        document.getElementById('sales-create-btn')?.addEventListener('click', () => this.openCreateModal());
        document.getElementById('close-sale-modal')?.addEventListener('click', () => this.closeModal('create-sale-modal'));
        document.getElementById('close-detail-modal')?.addEventListener('click', () => this.closeModal('detail-sale-modal'));

        const searchInput = document.getElementById('sale-search-product');
        if(searchInput) {
            searchInput.addEventListener('input', (e) => this.filterProducts(e.target.value));
            searchInput.addEventListener('keydown', (e) => { if(e.key === 'Enter') { e.preventDefault(); this.handleScan(e.target.value); }});
        }

        document.getElementById('btn-toggle-manual')?.addEventListener('click', () => {
            const form = document.getElementById('manual-item-form');
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        });
        document.getElementById('btn-add-manual')?.addEventListener('click', () => this.addManualItem());

        document.getElementById('btn-add-payment')?.addEventListener('click', () => this.addPayment());
        document.getElementById('pay-input-amount')?.addEventListener('keypress', (e) => { if(e.key==='Enter') this.addPayment(); });

        document.querySelectorAll('.currency-tab').forEach(tab => {
            tab.addEventListener('click', (e) => this.switchPaymentTab(e.target.dataset.curr));
        });

        document.getElementById('toggle-cart-currency-btn')?.addEventListener('click', () => this.toggleCartCurrency());
        document.getElementById('sale-commission-pct')?.addEventListener('input', () => this.calculateCommission());
        document.getElementById('confirm-sale-btn')?.addEventListener('click', () => this.submitSale());

        // CONFIGURAR EVENTO DE LA FLECHA DE ARRIBA (Volver)
        const btnBackHeader = document.getElementById('mobile-back-to-products');
        if(btnBackHeader) {
            btnBackHeader.addEventListener('click', (e) => {
                e.preventDefault();
                this.setMobileView('products');
            });
        }
    }

    closeModal(id) {
        const el = document.getElementById(id);
        if(el) {
            el.classList.add('hidden');
            el.style.display = 'none';
        }
    }

    async openCreateModal() {
        try {
            const rateData = await api.getExchangeRate();
            let baseRate = 1200;
            if (rateData.avg) baseRate = parseFloat(rateData.avg);
            else if (rateData.sell) baseRate = parseFloat(rateData.sell);
            this.rates.USD = baseRate; this.rates.USDT = baseRate; this.currentSale.exchange_rate = baseRate;
        } catch (e) { console.warn("Error cotización", e); }

        await this.fetchResources();

        // [IMPORTANTE] Resetear la vista móvil al abrir
        this.setMobileView('products');

        const map = this.resources.config;
        if (!map || !map.name || !map.sale_price || !map.stock) {
            const overlay = document.getElementById('config-warning-overlay');
            overlay.style.display = 'flex';
            document.getElementById('sale-modal-body').style.display = 'none';
            document.getElementById('go-to-config-btn').onclick = () => {
                this.closeModal('create-sale-modal');
                const event = new CustomEvent('open-column-config');
                window.dispatchEvent(event);
            };
        } else {
            document.getElementById('config-warning-overlay').style.display = 'none';
            document.getElementById('sale-modal-body').style.display = 'flex';
        }

        this.currentSale = { items: [], payments: [], subtotal_items: 0, total_surcharges: 0, total_final: 0, exchange_rate: this.rates.USD };
        this.activePaymentTab = 'ARS';
        this.showingCartInUSD = false;

        document.getElementById('sale-notes').value = '';
        this.switchPaymentTab('ARS');
        this.renderProducts(this.resources.products);
        this.fillSelect('sale-customer', this.resources.customers, 'id', 'full_name', 'Cliente General');
        this.fillSelect('sale-seller', this.resources.employees, 'id', 'full_name', 'Sin Vendedor');

        this.updateCartUI();
        this.recalcSale();

        const modal = document.getElementById('create-sale-modal');
        modal.classList.remove('hidden');
        modal.style.display = 'flex';

        if(window.innerWidth > 768) {
            setTimeout(() => document.getElementById('sale-search-product').focus(), 100);
        }
    }

    switchPaymentTab(currency) {
        this.activePaymentTab = currency;
        document.querySelectorAll('.currency-tab').forEach(t => {
            if(t.dataset.curr === currency) {
                t.style.borderBottom = '3px solid var(--accent-color)'; t.style.color = '#333'; t.style.fontWeight = 'bold';
            } else {
                t.style.borderBottom = 'none'; t.style.color = '#999'; t.style.fontWeight = 'normal';
            }
        });
        const rateInfo = document.getElementById('rate-display-info');
        const symbol = document.getElementById('pay-currency-symbol');
        if (currency === 'ARS') {
            rateInfo.style.display = 'none'; symbol.textContent = '$';
        } else {
            rateInfo.style.display = 'block';
            const rate = this.rates[currency];
            rateInfo.textContent = `Cotización aplicada: $${rate.toFixed(2)}`;
            symbol.textContent = currency === 'USD' ? 'U$S' : '₮';
        }
        const filteredMethods = this.resources.paymentMethods.filter(pm => {
            const pmCurr = pm.currency || 'ARS'; return pmCurr === currency;
        });
        this.fillSelect('pay-method-select', filteredMethods, 'id', 'name', null);
    }

    renderProducts(list) {
        const c = document.getElementById('sale-products-list');
        c.innerHTML = list.map(p => {
            const stock = parseFloat(p.stock);
            const style = stock > 0 ? '' : 'opacity:0.6; pointer-events:none; filter:grayscale(1);';
            const badgeStock = stock > 0 ? `<span style="font-size:0.75rem; color:#666;">Stock: ${stock}</span>` : `<span style="font-size:0.75rem; color:red; font-weight:bold;">AGOTADO</span>`;
            let displayPrice = parseFloat(p.price);
            let badgeCurrency = '';
            if (p.currency === 'USD') {
                displayPrice = displayPrice * this.rates.USD;
                badgeCurrency = `<span class="usd-badge" style="background:var(--accent-color-quat-opacity); color:var(--color-black); font-size:0.7rem; padding:1px 4px; border-radius:3px; margin-left:5px;">Orig: U$S ${parseFloat(p.price).toFixed(2)}</span>`;
            }
            return `<div class="resource-item prod-trigger" data-id="${p.id}" style="${style}">
                <div style="flex:1; overflow:hidden; padding-right:10px;">
                    <div style="font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;">${p.name}</div>
                    <div style="display:flex; align-items:center;">${badgeStock}</div>
                </div>
                <div style="text-align:right;">
                    <div style="font-weight:700; color:var(--sale-green); font-size:1rem;">${fmtMoney(displayPrice)}</div>${badgeCurrency}
                </div>
            </div>`;
        }).join('');
        c.querySelectorAll('.prod-trigger').forEach(b => {
            b.addEventListener('click', () => {
                const p = this.resources.products.find(x => x.id == b.dataset.id);
                let finalPriceARS = parseFloat(p.price);
                if (p.currency === 'USD') finalPriceARS = finalPriceARS * this.rates.USD;
                this.addToCart(p, finalPriceARS);
            });
        });
    }

    addToCart(p, priceInArs) {
        const exist = this.currentSale.items.find(i => i.id == p.id);
        if (exist) {
            if (exist.cantidad >= p.stock) {
                pop_ups.warning("Atención: Estás vendiendo por encima del stock disponible.");
            }
            exist.cantidad++;
        } else {
            this.currentSale.items.push({
                id: p.id, nombre: p.name, cantidad: 1, precio: priceInArs, original_currency: p.currency, max_stock: parseFloat(p.stock)
            });
        }
        this.recalcSale();
    }

    addPayment() {
        const amtInput = document.getElementById('pay-input-amount');
        const methodSelect = document.getElementById('pay-method-select');
        let amountInputVal = parseFloat(amtInput.value);
        const methodId = methodSelect.value;
        if (isNaN(amountInputVal) || amountInputVal <= 0) return pop_ups.warning("Ingresá un monto válido");
        if (!methodId) return pop_ups.warning("Elegí un método de pago");
        const methodObj = this.resources.paymentMethods.find(m => m.id == methodId);
        const surchargePct = parseFloat(methodObj.surcharge) || 0;
        let amountInArs = amountInputVal; let originalAmount = amountInputVal; let appliedRate = 1; let currencyId = this.activePaymentTab;
        if (this.activePaymentTab !== 'ARS') { appliedRate = this.rates[this.activePaymentTab]; amountInArs = amountInputVal * appliedRate; }
        const surchargeVal = amountInArs * (surchargePct / 100);
        this.currentSale.payments.push({
            method_id: methodId, name: methodObj.name, amount: amountInArs, surcharge_percent: surchargePct, surcharge_val: surchargeVal, currency_id: currencyId, original_amount: originalAmount, exchange_rate: appliedRate
        });
        amtInput.value = ''; this.recalcSale();
    }

    updatePaymentUI() {
        const c = document.getElementById('payments-list');
        if (this.currentSale.payments.length === 0) { c.innerHTML = '<p style="color:#999; text-align:center; font-size:0.8rem; margin-top:10px;">Sin pagos</p>'; }
        else {
            c.innerHTML = this.currentSale.payments.map((p, idx) => {
                let displayAmount = fmtMoney(p.amount);
                if (p.currency_id && p.currency_id !== 'ARS') {
                    const symbol = p.currency_id === 'USD' ? 'U$S' : '₮';
                    displayAmount = `<b>${symbol} ${parseFloat(p.original_amount).toFixed(2)}</b> <span style="font-size:0.8rem; color:#666;">(${fmtMoney(p.amount)})</span>`;
                }
                return `<div style="background:#FFF; border:1px solid #ddd; border-radius:4px; padding:8px; margin-bottom:5px; display:flex; justify-content:space-between; align-items:center;">
                    <div><div style="font-size:0.9rem;">${displayAmount}</div><div style="font-size:0.75rem; color:#555;">${p.name}</div>${p.surcharge_val > 0 ? `<div style="font-size:0.7rem; color:var(--accent-color);">+ Recargo: ${fmtMoney(p.surcharge_val)}</div>` : ''}</div>
                    <span style="cursor:pointer; color:var(--accent-red);" class="rm-pay" data-idx="${idx}"><i class="ph ph-x-circle" style="font-size:1.2rem;"></i></span></div>`;
            }).join('');
            c.querySelectorAll('.rm-pay').forEach(b => b.addEventListener('click', () => { this.currentSale.payments.splice(b.dataset.idx, 1); this.recalcSale(); }));
        }
    }

    toggleCartCurrency() {
        this.showingCartInUSD = !this.showingCartInUSD;
        const btn = document.getElementById('toggle-cart-currency-btn');
        if(this.showingCartInUSD) { btn.innerHTML = '<i class="ph ph-currency-circle-dollar"></i> Ver en ARS'; btn.classList.replace('btn-secondary', 'btn-primary'); }
        else { btn.innerHTML = '<i class="ph ph-currency-circle-dollar"></i> Ver en USD'; btn.classList.replace('btn-primary', 'btn-secondary'); }
        this.updateCartSubtotalDisplay();
    }

    updateCartSubtotalDisplay() {
        const displayEl = document.getElementById('cart-subtotal-display');
        const valARS = this.currentSale.subtotal_items;
        if (this.showingCartInUSD) {
            const valUSD = valARS / this.rates.USD; displayEl.textContent = `U$S ${valUSD.toFixed(2)}`; displayEl.style.color = 'var(--accent-color)';
        } else { displayEl.textContent = fmtMoney(valARS); displayEl.style.color = 'inherit'; }
    }

    recalcSale() {
        const round = (num) => Math.round((num + Number.EPSILON) * 100) / 100;
        const rawSubtotal = this.currentSale.items.reduce((sum, i) => sum + (i.precio * i.cantidad), 0);
        const rawSurcharges = this.currentSale.payments.reduce((sum, p) => sum + p.surcharge_val, 0);
        const rawPaid = this.currentSale.payments.reduce((sum, p) => sum + p.amount + p.surcharge_val, 0);
        this.currentSale.subtotal_items = rawSubtotal;
        this.currentSale.total_surcharges = rawSurcharges;
        this.currentSale.total_final = round(rawSubtotal + rawSurcharges);
        const totalPaid = round(rawPaid);
        const diff = round(totalPaid - this.currentSale.total_final);
        document.getElementById('checkout-subtotal').textContent = fmtMoney(this.currentSale.subtotal_items);
        document.getElementById('checkout-surcharges').textContent = fmtMoney(this.currentSale.total_surcharges);
        document.getElementById('checkout-total-final').textContent = fmtMoney(this.currentSale.total_final);
        this.updateCartSubtotalDisplay();
        const diffEl = document.getElementById('checkout-diff');
        const rowDiff = document.getElementById('change-row');
        if (diff >= -0.01) { rowDiff.style.color = 'var(--accent-color)'; rowDiff.querySelector('span:first-child').textContent = "Vuelto a dar:"; diffEl.textContent = fmtMoney(Math.abs(diff)); }
        else { rowDiff.style.color = 'var(--accent-red)'; rowDiff.querySelector('span:first-child').textContent = "Falta por completar:"; diffEl.textContent = fmtMoney(Math.abs(diff)); }

        // ACTUALIZAR BARRA MÓVIL
        const mobTotal = document.getElementById('mob-bar-total');
        const mobCount = document.getElementById('mob-bar-count');
        if(mobTotal && mobCount) {
            mobTotal.textContent = fmtMoney(this.currentSale.total_final);
            const count = this.currentSale.items.reduce((s, i) => s + i.cantidad, 0);
            mobCount.textContent = `${count} Ítems`;
        }

        document.getElementById('confirm-sale-btn').disabled = (this.currentSale.items.length === 0);
        this.updateCartUI(); this.updatePaymentUI(); this.calculateCommission();
    }

    updateCartUI() {
        const c = document.getElementById('sale-cart-items');
        if(this.currentSale.items.length === 0) { c.innerHTML = '<div style="text-align:center; color:#999; margin-top:50px;">Carrito vacío</div>'; }
        else {
            c.innerHTML = this.currentSale.items.map((item, idx) => `
                <div class="cart-card">
                    <div class="cart-row-top"><div class="cart-name">${item.nombre}</div><div class="cart-unit-price">${fmtMoney(item.precio)} c/u</div></div>
                    <div class="cart-row-bottom"><div class="cart-total">${fmtMoney(item.precio * item.cantidad)}</div>
                    <div class="cart-controls-wrapper"><button class="ctrl-btn sub" data-idx="${idx}">-</button><div class="qty-val">${item.cantidad}</div><button class="ctrl-btn add" data-idx="${idx}">+</button><button class="del-btn del" data-idx="${idx}" title="Eliminar"><i class="ph ph-trash"></i></button></div></div>
                </div>`).join('');
            c.querySelectorAll('.add').forEach(b => b.addEventListener('click', () => { 
                const item = this.currentSale.items[b.dataset.idx]; 
                if (item.cantidad >= item.max_stock) {
                    pop_ups.warning("Atención: Operando en stock negativo.");
                }
                item.cantidad++; 
                this.recalcSale(); 
            }));
            c.querySelectorAll('.sub').forEach(b => b.addEventListener('click', () => { const item = this.currentSale.items[b.dataset.idx]; item.cantidad--; if(item.cantidad < 1) this.currentSale.items.splice(b.dataset.idx, 1); this.recalcSale(); }));
            c.querySelectorAll('.del').forEach(b => b.addEventListener('click', () => { this.currentSale.items.splice(b.dataset.idx, 1); this.recalcSale(); }));
        }
        this.updateCartSubtotalDisplay();
    }

    calculateCommission() {
        const pct = parseFloat(document.getElementById('sale-commission-pct').value) || 0;
        const commVal = this.currentSale.subtotal_items * (pct / 100);
        const disp = document.getElementById('commission-display');
        if(disp) disp.textContent = `Comisión: ${fmtMoney(commVal)}`;
    }

    async fetchResources() {
        try {
            const [prefRes, data, empRes] = await Promise.all([ getCurrentInventoryPreferences(), getSaleResources(), getEmployeeList() ]);
            this.resources.config = prefRes.mapping || {};
            if(data.success) {
                this.resources.products = data.products || [];
                this.resources.customers = data.customers || [];
                this.resources.paymentMethods = data.payment_methods || [];
            }
            if(empRes.success) this.resources.employees = empRes.employees || [];
        } catch(e) { console.error(e); }
    }

    fillSelect(id, list, valKey, textKey, placeholder) {
        const s = document.getElementById(id); s.innerHTML = placeholder ? `<option value="">${placeholder}</option>` : '';
        list.forEach(i => { const op = document.createElement('option'); op.value = i[valKey]; let txt = i[textKey]; if(id === 'pay-method-select' && i.surcharge > 0) txt += ` (+${parseFloat(i.surcharge)}%)`; op.textContent = txt; s.appendChild(op); });
    }

    async submitSale() {
        const btn = document.getElementById('confirm-sale-btn'); btn.disabled = true; btn.textContent = 'Procesando Venta...';
        const pct = parseFloat(document.getElementById('sale-commission-pct').value) || 0;
        const payload = {
            customer_id: document.getElementById('sale-customer').value || null,
            seller_id: document.getElementById('sale-seller').value || null,
            commission_amount: this.currentSale.subtotal_items * (pct / 100),
            total_final: this.currentSale.total_final,
            notes: document.getElementById('sale-notes').value,
            exchange_rate_snapshot: this.currentSale.exchange_rate,
            items: this.currentSale.items.map(i => ({ id: i.id || null, nombre: i.nombre, cantidad: i.cantidad, precio: i.precio, subtotal: i.precio * i.cantidad })),
            payments: this.currentSale.payments
        };
        try { 
            const res = await createSale(payload); 
            if (res.success) { 
                this.closeModal('create-sale-modal'); 
                await this.loadHistory(this.currentSortOrder); 
                pop_ups.success("Venta Exitosa"); 
                
                // Procesar Alertas
                if (res.alerts && res.alerts.length > 0) {
                    // Mostrar alertas visuales amarillas rápidas en la UI
                    res.alerts.forEach(a => {
                        if (a.type === 'low_stock') {
                            pop_ups.warning(`Stock mínimo alcanzado en ${a.product_name}. Actual: ${a.current_stock}. Min: ${a.min_stock}`, 'Bajo Stock');
                        } else if (a.type === 'negative_profit') {
                            pop_ups.error(`¡Alerta de Rentabilidad en ${a.product_name}! Venta a $${a.sale_price}, Costo $${a.cost_price}`, 'Rentabilidad Crítica');
                        }
                    });
                    
                    // Mostrar popup de espera
                    btn.textContent = 'Enviando Correos...'; 
                    pop_ups.info("Espere. Generando reportes y enviándolos al mail...");
                    try {
                        const emailRes = await fetch('/api/sales/send-queued-emails.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ alerts: res.alerts })
                        });
                        const emailData = await emailRes.json();
                        if (emailData.success) {
                            if (emailData.sent > 0) {
                                pop_ups.success(`¡Reportes (${emailData.sent}) enviados al mail con éxito!`);
                            }
                        }
                    } catch (err) {
                        console.error("Error al enviar correos diferidos:", err);
                    }
                }
            } else { 
                pop_ups.error(res.message); 
            } 
        } catch(e) { 
            console.error(e); 
            pop_ups.error("Error de conexión"); 
        }
        btn.disabled = false; btn.textContent = 'Confirmar Venta';
    }

    async loadHistory(order='desc') {
        const b = document.getElementById('sales-list-body');
        b.innerHTML = '<tr><td colspan="5" style="text-align:center; padding:20px;">Cargando...</td></tr>';
        try {
            const data = await getSalesHistory(order);
            if(!data.success || !data.sales || data.sales.length === 0) { b.innerHTML='<tr><td colspan="4" style="text-align:center; padding:20px; color:#999;">No hay ventas registradas</td></tr>'; return; }
            b.innerHTML = data.sales.map(s => {
                const dateStr = s.created_at; const total = s.total; const seller = s.seller_name; const comm = s.commission;
                let payBadge = '';
                if (s.payments && s.payments.length > 0) {
                    const first = s.payments[0]; const extra = s.payments.length - 1; const plus = extra > 0 ? ` <b style="color:var(--accent-color);">+${extra}</b>` : '';
                    payBadge = `<div style="margin-top:4px;"><span style="color:#555; font-size:0.75rem; background:#f4f4f4; padding:2px 6px; border-radius:4px;">${first}${plus}</span></div>`;
                } else { payBadge = `<div style="margin-top:4px;"><span style="color:#eee; font-size:0.75rem;">-</span></div>`; }
                let sellerHtml = '<span style="color:#ccc;">No asignado</span>';
                if (seller && seller !== '-' && seller !== 'No asignado') {
                    sellerHtml = `<div style="line-height:1.2;"><div style="font-weight:600; color:#333;"><i class="ph ph-identification-badge" style="color:var(--accent-color); font-size:1.1rem; padding-right: 6px"></i>${seller}</div>${comm > 0 ? `<div style="font-size:0.75rem; color:var(--sale-green); font-weight:600;">Com: ${fmtMoney(comm)}</div>` : ''}</div>`;
                }
                return `<tr style="border-bottom:1px solid #eee;"><td style="padding:10px 15px;">${fmtDate(dateStr)}<div style="font-size:0.75rem; color:#999;">#${s.id}</div></td><td style="padding:10px 15px;"><div style="font-weight:600; color:#444;"><i class="ph ph-user-focus" style="color:var(--accent-color); font-size:1.1rem; padding-right: 6px"></i>${s.customer_name}</div></td><td style="padding:10px 15px;">${sellerHtml}</td><td style="padding:10px 15px; text-align:right;"><div style="font-weight:800; color:var(--sale-green); font-size:1.1rem; line-height:1.2;">${fmtMoney(total)}</div>${payBadge}</td><td style="padding:10px 15px; text-align:center;"><div class="btn-icon-group" style="justify-content:center;"><button class="action-btn view" title="Ver Ticket" onclick="window.salesModuleInstance.showDetails('${s.id}')"><i class="ph ph-receipt"></i></button><button class="action-btn edit" title="Editar" onclick="window.salesModuleInstance.editSale('${s.id}')"><i class="ph ph-pencil-simple"></i></button><button class="action-btn delete" title="Eliminar" onclick="window.salesModuleInstance.deleteSale('${s.id}')"><i class="ph ph-trash"></i></button></div></td></tr>`;
            }).join('');
        } catch(e) { console.error(e); b.innerHTML='<tr><td colspan="5" style="text-align:center; color:red;">Error de visualización</td></tr>'; }
    }

    async addManualItem() {
        const nameInput = document.getElementById('manual-name'); const priceInput = document.getElementById('manual-price'); const qtyInput = document.getElementById('manual-qty');
        const name = nameInput.value.trim(); const price = parseFloat(priceInput.value); const qty = parseFloat(qtyInput.value) || 1;
        if (!name) return pop_ups.warning("Ingresá una descripción"); if (isNaN(price) || price <= 0) return pop_ups.warning("Ingresá un precio válido");
        this.currentSale.items.push({ id: null, fake_id: 'man_' + Date.now(), nombre: name, cantidad: qty, precio: price, max_stock: 999999 });
        nameInput.value = ''; priceInput.value = ''; qtyInput.value = '1'; document.getElementById('manual-item-form').style.display = 'none'; pop_ups.success("Ítem agregado"); this.recalcSale();
    }

    filterProducts(t) {
        const term = t.toLowerCase().trim(); if(!term) { this.renderProducts(this.resources.products); return; }
        const filtered = this.resources.products.filter(p => (p.name && p.name.toLowerCase().includes(term)) || Object.values(p).some(val => val && String(val).toLowerCase().includes(term)));
        this.renderProducts(filtered);
    }

    handleScan(t) {
        const term = t.toLowerCase().trim(); if(!term) return;
        let match = this.resources.products.find(p => Object.values(p).some(val => val && String(val).toLowerCase() === term));
        if (!match) {
            const visualMatches = this.resources.products.filter(p => (p.name && p.name.toLowerCase().includes(term)) || Object.values(p).some(val => val && String(val).toLowerCase().includes(term)));
            if (visualMatches.length === 1) match = visualMatches[0];
        }
        if (match) {
            let finalPriceARS = parseFloat(match.price);
            if (match.currency === 'USD') finalPriceARS = finalPriceARS * this.rates.USD;
            this.addToCart(match, finalPriceARS);
            const input = document.getElementById('sale-search-product'); input.value = ''; input.focus(); this.filterProducts('');
            pop_ups.success(`Agregado: ${match.name}`);
        } else { pop_ups.warning("Producto no encontrado"); }
    }

    async showDetails(id) {
        try {
            const res = await getSaleDetails(id); if(!res.success) throw new Error(res.message || "Error al cargar detalles");
            const s = res.sale;
            const bodyContainer = document.getElementById('detail-modal-content');
            let html = `<div style="text-align:center; margin-bottom:20px; border-bottom:2px dashed var(--ticket-color); padding-bottom:15px;"><div style="font-size:1.3rem; font-weight:900; letter-spacing:1px;">TICKET #${s.id}</div><div style="font-size:0.9rem; margin-top:5px;">${fmtDate(s.created_at)}</div></div>`;
            html += `<div class="ticket-row" style="margin-bottom:10px;"><div style="display:flex; flex-direction:column; width:100%;"><span style="font-size:0.7rem; text-transform:uppercase; color:#666; font-weight:bold;">CLIENTE:</span><span style="font-size:1rem; font-weight:800;">${s.customer_name || 'Consumidor Final'}</span></div></div>`;
            let sellerDisplay = "No especificado"; let commissionDisplay = "";
            if (s.seller_name && s.seller_name !== '-') {
                sellerDisplay = s.seller_name; const comm = s.commission_amount ? parseFloat(s.commission_amount) : 0;
                commissionDisplay = `<span style="font-size:0.8rem; background:#eee; padding:2px 6px; border-radius:4px; margin-left: auto;">Com: ${fmtMoney(comm)}</span>`;
            }
            html += `<div class="ticket-row" style="margin-bottom:15px; border-bottom:1px dotted #ccc; padding-bottom:10px;"><div style="display:flex; flex-direction:column; width:100%;"><span style="font-size:0.7rem; text-transform:uppercase; color:#666; font-weight:bold;">ATENDIDO POR:</span><div style="display:flex; justify-content:space-between; align-items:center;"><span style="font-size:1rem; font-weight:800;">${sellerDisplay}</span>${commissionDisplay}</div></div></div>`;
            bodyContainer.innerHTML = html;
            bodyContainer.insertAdjacentHTML('beforeend', '<h4>PRODUCTOS</h4>');
            const productsTable = document.createElement('table'); productsTable.innerHTML = `<thead><tr><th style="text-align:left;">DESCRIPCIÓN</th><th style="text-align:center; width:40px;">CANT</th><th style="text-align:right;">TOTAL</th></tr></thead><tbody id="detail-items-list"></tbody>`;
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
}

const salesModuleInstance = new SalesModule();
export { salesModuleInstance };
window.salesModuleInstance = salesModuleInstance;
console.log("✅ SalesModule registrado globalmente.");