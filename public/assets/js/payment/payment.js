/**
 * public/assets/js/payments/payments.js
 * Version 2.0: Tipos, Monedas y Recargos.
 */
import { getAllPaymentMethods, createPaymentMethod, deletePaymentMethod, updatePaymentMethod } from '../api.js';
import { pop_ups } from '../notifications/pop-up.js';

export class PaymentsModule {
    constructor() {
        this.containerId = 'payments';
        this.isInitialized = false;
        this.editingItem = null;
    }

    init() {
        if (this.isInitialized) { this.loadMethods(); return; }
        const container = document.getElementById(this.containerId);
        if (!container) return;
        container.innerHTML = this.renderBaseStructure();
        this.attachEvents();
        this.loadMethods();
        this.isInitialized = true;
    }

    renderBaseStructure() {
        return `
            <div class="payments-layout">
                <div class="table-header">
                    <h2>Métodos de Pago</h2>
                    <div class="table-controls">
                        <button id="pay-create-btn" class="btn btn-primary" >+ Nuevo Método</button>
                    </div>
                </div>

                <div class="table-wrapper" style="flex-grow:1; overflow-y:auto; background: #f9f9f9;">
                    <div id="pay-list-body" class="pay-grid">
                        <p style="grid-column: 1/-1; text-align:center;">Cargando...</p>
                    </div>
                </div>

                <div id="create-payment-modal" class="modal-overlay hidden" style="align-items:center; justify-content:center; display:none; z-index:1000;">
                    <div class="modal-content" style="width: 700px; max-width: 95%;">
                        <div class="modal-header"><h3>Nuevo Método</h3><button class="modal-close-btn" id="close-pay-modal">&times;</button></div>
                        <div class="modal-body" style="padding: 1.5rem;">
                            <form id="create-pay-form">
                                <div style="margin-bottom:15px;">
                                    <label>Nombre</label>
                                    <input type="text" id="pay-name" class="rustic-input" style="width:100%;" required placeholder="Ej: Visa Crédito">
                                </div>
                                <div class="form-row" style="margin-bottom:15px;">
                                    <div class="form-col">
                                        <label>Tipo</label>
                                        <select id="pay-type" class="rustic-select" style="width:100%;">
                                            <option value="Cash">Efectivo</option>
                                            <option value="Card">Tarjeta</option>
                                            <option value="Transfer">Digital/Transferencia</option>
                                            <option value="Crypto">Criptomoneda</option>
                                            <option value="Other" selected>Otro</option>
                                        </select>
                                    </div>
                                    <div class="form-col">
                                        <label>Moneda</label>
                                        <select id="pay-currency" class="rustic-select" style="width:100%;">
                                            <option value="ARS" selected>Pesos (ARS)</option>
                                            <option value="USD">Dólares (USD)</option>
                                            <option value="USDT">USDT</option>
                                        </select>
                                    </div>
                                </div>
                                <div style="margin-bottom:15px;">
                                    <label>Recargo / Comisión (%)</label>
                                    <input type="number" id="pay-surcharge" class="rustic-input" style="width:100%;" value="0" step="0.01" placeholder="Ej: 10 para 10%">
                                    <small style="color:#888;">Si cobrás un extra por usar este método (ej: Tarjeta).</small>
                                </div>
                                <div style="text-align: right;"><button type="submit" id="submit-pay-btn" class="btn btn-primary" >Guardar</button></div>
                            </form>
                        </div>
                    </div>
                </div>

                <div id="edit-payment-modal" class="modal-overlay hidden" style="align-items:center; justify-content:center; display:none; z-index:1000;">
                    <div class="modal-content" style="width: 700px; max-width: 95%;">
                        <div class="modal-header"><h3>Editar Método</h3><button class="modal-close-btn" id="close-edit-pay-modal">&times;</button></div>
                        <div class="modal-body" style="padding: 1.5rem;">
                            <form id="edit-pay-form">
                                <div style="margin-bottom:15px;"><label>Nombre</label><input type="text" id="edit-pay-name" class="rustic-input" style="width:100%;" required></div>
                                <div class="form-row" style="margin-bottom:15px;">
                                    <div class="form-col">
                                        <label>Tipo</label>
                                        <select id="edit-pay-type" class="rustic-select" style="width:100%;">
                                            <option value="Cash">Efectivo</option>
                                            <option value="Card">Tarjeta</option>
                                            <option value="Transfer">Digital/Transferencia</option>
                                            <option value="Crypto">Criptomoneda</option>
                                            <option value="Other">Otro</option>
                                        </select>
                                    </div>
                                    <div class="form-col">
                                        <label>Moneda</label>
                                        <select id="edit-pay-currency" class="rustic-select" style="width:100%;">
                                            <option value="ARS">Pesos (ARS)</option>
                                            <option value="USD">Dólares (USD)</option>
                                            <option value="USDT">USDT</option>
                                        </select>
                                    </div>
                                </div>
                                <div style="margin-bottom:15px;"><label>Recargo (%)</label><input type="number" id="edit-pay-surcharge" class="rustic-input" style="width:100%;" step="0.01"></div>
                                <div style="text-align: right;"><button type="submit" id="update-pay-btn" class="btn btn-primary" >Actualizar</button></div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    attachEvents() {
        // Create
        document.getElementById('pay-create-btn')?.addEventListener('click', () => {
            document.getElementById('create-pay-form').reset();
            const m = document.getElementById('create-payment-modal'); m.classList.remove('hidden'); m.style.display='flex';
        });
        document.getElementById('close-pay-modal')?.addEventListener('click', () => {
            const m = document.getElementById('create-payment-modal'); m.classList.add('hidden'); m.style.display='none';
        });
        document.getElementById('create-pay-form')?.addEventListener('submit', (e) => { e.preventDefault(); this.submitCreate(); });

        // Edit
        document.getElementById('close-edit-pay-modal')?.addEventListener('click', () => {
            const m = document.getElementById('edit-payment-modal'); m.classList.add('hidden'); m.style.display='none';
        });
        document.getElementById('edit-pay-form')?.addEventListener('submit', (e) => { e.preventDefault(); this.submitUpdate(); });
    }

    async loadMethods() {
        const container = document.getElementById('pay-list-body');
        try {
            const res = await getAllPaymentMethods();
            if(!res.success || !res.methods.length) {
                container.innerHTML = '<div style="grid-column: 1/-1; text-align:center; padding:2rem; color:#999;">Sin métodos de pago. Creá uno para comenzar.</div>';
                return;
            }
            container.innerHTML = res.methods.map(m => {
                const surchargeText = parseFloat(m.surcharge) > 0 ? `<span class="badge surcharge">+${parseFloat(m.surcharge)}%</span>` : '';

                // Traducción visual de tipos
                let typeIcon = '<i class="ph ph-question"></i>';
                if(m.type === 'Cash') typeIcon = '<i class="ph ph-money"></i>';
                if(m.type === 'Card') typeIcon = '<i class="ph ph-credit-card"></i>';
                if(m.type === 'Transfer') typeIcon = '<i class="ph ph-bank"></i>';
                if(m.type === 'Crypto') typeIcon = '<i class="ph ph-currency-btc"></i>';

                return `
                <div class="pay-card">
                    <div class="pay-header">
                        <span class="pay-name">${m.name}</span>
                        <div class="pay-actions">
                            <button class="icon-btn-sm edit-trigger" data-id="${m.id}"><i class="ph ph-pencil-simple"></i></button>
                            <button class="icon-btn-sm delete delete-trigger" data-id="${m.id}"><i class="ph ph-trash"></i></button>
                        </div>
                    </div>
                    <div class="pay-tags">
                        <span class="badge type">${typeIcon} ${m.type}</span>
                        <span class="badge currency-${m.currency}">${m.currency}</span>
                        ${surchargeText}
                    </div>
                </div>`;
            }).join('');

            // Bind events (usando find para pasar objeto completo al editar)
            container.querySelectorAll('.edit-trigger').forEach(b => b.addEventListener('click', () => {
                const m = res.methods.find(i => i.id == b.dataset.id);
                this.openEdit(m);
            }));
            container.querySelectorAll('.delete-trigger').forEach(b => b.addEventListener('click', () => {
                const method = res.methods.find(i => i.id == b.dataset.id);
                this.deleteMethod(method.id, method.name);
            }));

        } catch (e) { container.innerHTML = 'Error al cargar'; }
    }

    async submitCreate() {
        const data = {
            name: document.getElementById('pay-name').value,
            type: document.getElementById('pay-type').value,
            currency: document.getElementById('pay-currency').value,
            surcharge: document.getElementById('pay-surcharge').value
        };
        const btn = document.getElementById('submit-pay-btn'); btn.disabled=true;
        try {
            const res = await createPaymentMethod(data); // API espera objeto
            if(res.success) {
                document.getElementById('create-payment-modal').classList.add('hidden');
                document.getElementById('create-payment-modal').style.display='none';
                this.loadMethods();
                pop_ups.success("Método creado");
            } else pop_ups.error("Error al crear");
        } catch(e) { console.error(e); }
        btn.disabled=false;
    }

    openEdit(item) {
        this.editingItem = item;
        document.getElementById('edit-pay-name').value = item.name;
        document.getElementById('edit-pay-type').value = item.type;
        document.getElementById('edit-pay-currency').value = item.currency;
        document.getElementById('edit-pay-surcharge').value = item.surcharge;

        const m = document.getElementById('edit-payment-modal'); m.classList.remove('hidden'); m.style.display='flex';
    }

    async submitUpdate() {
        const data = {
            name: document.getElementById('edit-pay-name').value,
            type: document.getElementById('edit-pay-type').value,
            currency: document.getElementById('edit-pay-currency').value,
            surcharge: document.getElementById('edit-pay-surcharge').value
        };
        try {
            const res = await updatePaymentMethod(this.editingItem.id, data);
            if(res.success) {
                document.getElementById('edit-payment-modal').classList.add('hidden');
                document.getElementById('edit-payment-modal').style.display='none';
                this.loadMethods();
                pop_ups.success("Actualizado");
            } else pop_ups.error("Error al actualizar");
        } catch(e) { console.error(e); }
    }

    async deleteMethod(id, name = '') {
        if (!await pop_ups.confirm("¿Borrar este método?")) return;

        try {
            const res = await deletePaymentMethod(id);

            if (res.success) {
                const label = name ? ` "${name}"` : '';
                pop_ups.info(`Método de pago ${label} eliminado.`, "Eliminado");
                this.loadMethods();
            } else {
                pop_ups.error(res.message || "No se pudo eliminar");
            }
        } catch (e) {
            console.error(e);
            pop_ups.error("Error de conexión");
        }
    }
}

export const paymentsModuleInstance = new PaymentsModule();