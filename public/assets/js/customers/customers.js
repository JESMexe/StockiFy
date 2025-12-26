/**
 * public/assets/js/customers/customers.js
 * Módulo Refactorizado: Usa api.js
 */
import * as api from '../api.js';
import { pop_ups } from '../notifications/pop-up.js';

export class CustomerModule {
    constructor() {
        this.containerId = 'customers';
        this.isInitialized = false;
    }

    init() {
        if (this.isInitialized) {
            this.loadCustomers();
            return;
        }
        const container = document.getElementById(this.containerId);
        if (!container) return;

        container.innerHTML = this.renderBaseStructure();
        this.attachEvents();
        this.loadCustomers();
        this.isInitialized = true;
    }

    renderBaseStructure() {
        return `
            <style>
                .customers-layout { display: flex; flex-direction: column; height: 100%; gap: 1rem; }
                #create-customer-modal .modal-content, 
                #detail-customer-modal .modal-content {
                    background-color: #ffffff !important;
                    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
                    border: 1px solid #ddd;
                    border-radius: 8px;
                }
                .form-group { margin-bottom: 15px; }
                .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #555; font-size: 0.9rem; }
                .form-input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; }
                .form-row { display: flex; gap: 15px; }
                .form-col { flex: 1; }
                .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
                .detail-item { background: #f9fafb; padding: 10px; border-radius: 4px; border: 1px solid #eee; }
                .detail-label { display: block; font-size: 0.8rem; color: #888; margin-bottom: 2px; }
                .detail-value { font-weight: 600; color: #333; }
            </style>

            <div class="customers-layout">
                <div class="table-header">
                    <h2>Clientes</h2>
                    <div class="table-controls">
                        <button id="customers-sort-btn" class="btn btn-secondary" title="Ordenar"><i class="ph ph-sort-ascending"></i></button>
                        <button id="customers-create-btn" class="btn btn-primary">+ Nuevo Cliente</button>
                    </div>
                </div>
                <div class="table-wrapper" style="flex-grow:1; overflow-y:auto;">
                    <table id="customers-table" style="width:100%; border-collapse:collapse;">
                        <thead style="position:sticky; top:0; background:white; z-index:10; box-shadow:0 1px 2px rgba(0,0,0,0.1);">
                            <tr>
                                <th style="padding:12px; text-align:left;">Nombre</th>
                                <th style="padding:12px; text-align:left;">Whatsapp / Email</th>
                                <th style="padding:12px; text-align:left;">Ubicación</th>
                                <th style="padding:12px; text-align:center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="customers-list-body"></tbody>
                    </table>
                </div>
                <div id="create-customer-modal" class="modal-overlay hidden" style="align-items:center; justify-content:center; display:none; z-index:1000;">
                    <div class="modal-content" style="width: 600px; max-width: 95%;">
                        <div class="modal-header">
                            <h3>Nuevo Cliente</h3>
                            <button class="modal-close-btn" id="close-customer-modal">&times;</button>
                        </div>
                        <div class="modal-body" style="padding: 1.5rem;">
                            <form id="create-customer-form">
                                <div class="form-group">
                                    <label>Nombre Completo (Obligatorio)</label>
                                    <input type="text" id="cust-name" class="form-input" placeholder="Ej: Juan Pérez" required>
                                </div>
                                <div class="form-row">
                                    <div class="form-col form-group">
                                        <label>Whatsapp</label>
                                        <input type="text" id="cust-phone" class="form-input" placeholder="+54 9 11...">
                                    </div>
                                    <div class="form-col form-group">
                                        <label>DNI / Tax ID</label>
                                        <input type="text" id="cust-dni" class="form-input" placeholder="12345678">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" id="cust-email" class="form-input" placeholder="email@ejemplo.com">
                                </div>
                                <div class="form-group">
                                    <label>Dirección</label>
                                    <input type="text" id="cust-address" class="form-input" placeholder="Calle Falsa 123">
                                </div>
                                <div class="form-group">
                                    <label>Fecha de Nacimiento</label>
                                    <input type="date" id="cust-birth" class="form-input">
                                </div>
                                <div style="margin-top: 2rem; text-align: right;">
                                    <button type="submit" id="submit-customer-btn" class="btn btn-primary">Guardar Cliente</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
                <div id="detail-customer-modal" class="modal-overlay hidden" style="align-items:center; justify-content:center; display:none; z-index:1001;">
                    <div class="modal-content" style="width: 500px; max-width: 90%;">
                        <div class="modal-header">
                            <h3>Ficha de Cliente</h3>
                            <button class="modal-close-btn" id="close-detail-cust-modal">&times;</button>
                        </div>
                        <div class="modal-body" id="detail-cust-content" style="padding: 1.5rem;"></div>
                    </div>
                </div>
            </div>
        `;
    }

    attachEvents() {
        document.getElementById('customers-create-btn')?.addEventListener('click', () => {
            document.getElementById('create-customer-form').reset();
            const m = document.getElementById('create-customer-modal');
            m.classList.remove('hidden'); m.style.display='flex';
        });

        document.getElementById('close-customer-modal')?.addEventListener('click', () => {
            const m = document.getElementById('create-customer-modal');
            m.classList.add('hidden'); m.style.display='none';
        });

        document.getElementById('create-customer-form')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.submitCustomer();
        });

        document.getElementById('close-detail-cust-modal')?.addEventListener('click', () => {
            const m = document.getElementById('detail-customer-modal');
            m.classList.add('hidden'); m.style.display='none';
        });

        const sortBtn = document.getElementById('customers-sort-btn');
        if(sortBtn) sortBtn.addEventListener('click', () => this.loadCustomers());
    }

    async submitCustomer() {
        const btn = document.getElementById('submit-customer-btn');
        btn.disabled = true;
        btn.textContent = "Guardando...";

        const data = {
            name: document.getElementById('cust-name').value,
            phone: document.getElementById('cust-phone').value,
            dni: document.getElementById('cust-dni').value,
            email: document.getElementById('cust-email').value,
            address: document.getElementById('cust-address').value,
            birth_date: document.getElementById('cust-birth').value
        };

        try {
            // USAMOS LA API CENTRALIZADA
            const response = await api.createCustomerNew(data);

            if (response.success) {
                document.getElementById('create-customer-modal').classList.add('hidden');
                document.getElementById('create-customer-modal').style.display='none';
                await this.loadCustomers();
                pop_ups.success('Cliente creado correctamente');
            }
        } catch (e) {
            console.error(e); // El error ya lo maneja api.js (pop_ups) pero lo logueamos
        } finally {
            btn.disabled = false;
            btn.textContent = "Guardar Cliente";
        }
    }

    async loadCustomers(order='desc') {
        const tbody = document.getElementById('customers-list-body');
        if(!tbody) return;
        tbody.innerHTML = '<tr><td colspan="4" class="text-center">Cargando...</td></tr>';

        try {
            // USAMOS LA API CENTRALIZADA
            const data = await api.getCustomerList(order);

            if (!data.success || !data.customers.length) {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center" style="padding:2rem; color:#999;">No hay clientes registrados</td></tr>';
                return;
            }

            tbody.innerHTML = data.customers.map(c => `
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:12px;">
                        <div style="font-weight:600;">${c.full_name}</div>
                        <small style="color:#888;">ID: ${c.id}</small>
                    </td>
                    <td style="padding:12px;">
                        ${c.phone ? `<div><i class="ph ph-whatsapp-logo" style="color:green;"></i> ${c.phone}</div>` : ''}
                        ${c.email ? `<small style="color:#666;">${c.email}</small>` : ''}
                        ${!c.phone && !c.email ? '<span style="color:#ccc;">-</span>' : ''}
                    </td>
                    <td style="padding:12px;">${c.address || '<span style="color:#ccc;">-</span>'}</td>
                    <td style="padding:12px; text-align:center;">
                        <button class="btn btn-secondary btn-sm view-cust" data-id="${c.id}"><i class="ph ph-eye"></i></button>
                    </td>
                </tr>
            `).join('');

            tbody.querySelectorAll('.view-cust').forEach(b => {
                b.addEventListener('click', () => this.showDetails(b.dataset.id));
            });

        } catch (e) {
            console.error(e);
            tbody.innerHTML = '<tr><td colspan="4" class="text-center" style="color:red">Error al cargar</td></tr>';
        }
    }

    async showDetails(id) {
        const m = document.getElementById('detail-customer-modal');
        const c = document.getElementById('detail-cust-content');
        m.classList.remove('hidden'); m.style.display='flex';
        c.innerHTML = 'Cargando...';

        try {
            // USAMOS LA API CENTRALIZADA
            const data = await api.getCustomerDetails(id);

            if(data.success) {
                const cust = data.customer;
                c.innerHTML = `
                    <div style="text-align:center; margin-bottom:1.5rem;">
                        <div style="width:60px; height:60px; background:#e3f2fd; color:#1976d2; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.5rem; margin:0 auto 10px auto;">
                            ${cust.full_name.charAt(0).toUpperCase()}
                        </div>
                        <h2 style="margin:0;">${cust.full_name}</h2>
                        <small style="color:#888;">Cliente #${cust.id}</small>
                    </div>
                    
                    <div class="detail-grid">
                        <div class="detail-item">
                            <span class="detail-label">Whatsapp</span>
                            <span class="detail-value">${cust.phone || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Email</span>
                            <span class="detail-value">${cust.email || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">DNI / Tax ID</span>
                            <span class="detail-value">${cust.tax_id || '-'}</span>
                        </div>
                        <div class="detail-item">
                            <span class="detail-label">Fecha Nacimiento</span>
                            <span class="detail-value">${cust.birth_date ? new Date(cust.birth_date).toLocaleDateString() : '-'}</span>
                        </div>
                        <div class="detail-item" style="grid-column: 1 / -1;">
                            <span class="detail-label">Dirección</span>
                            <span class="detail-value">${cust.address || '-'}</span>
                        </div>
                    </div>
                `;
            }
        } catch(e) { c.innerHTML = 'Error al cargar'; }
    }
}

export const customerModuleInstance = new CustomerModule();