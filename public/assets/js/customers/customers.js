/**
 */
import * as api from '../api.js';
import { pop_ups } from '../notifications/pop-up.js';

export class CustomerModule {
    constructor() {
        this.containerId = 'customers';
        this.isInitialized = false;
        this.editingId = null;
        this.currentSortOrder = 'DESC';
        this.allCustomers = [];
    }

    init() {
        if (this.isInitialized) {
            this.loadCustomers(this.currentSortOrder);
            return;
        }
        const container = document.getElementById(this.containerId);
        if (!container) return;

        container.innerHTML = this.renderBaseStructure();
        this.attachEvents();
        this.loadCustomers(this.currentSortOrder);
        this.isInitialized = true;
    }

    renderBaseStructure() {
        return `
            <style>
                .customers-layout { display: flex; flex-direction: column; height: 100%; gap: 1rem; }
                #create-customer-modal .modal-content, 
                #detail-customer-modal .modal-content {
                    background-color: #ffffff !important;
                    box-shadow: 8px 8px 0 rgba(0,0,0,0.3);
                    border: 1px solid #ddd;
                    border-radius: 8px;
                }
                #detail-customer-modal .modal-content:hover {
                    box-shadow: 8px 8px 0 var(--accent-color);
                }
                .form-group { margin-bottom: 15px; }
                .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #555; font-size: 0.9rem; }
                .form-input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; }
                .form-row { display: flex; gap: 15px; }
                .form-col { flex: 1; }
                
                .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
                .detail-item { 
                    background-color: rgba(0,0,0,0.1); 
                    padding: 10px; 
                    border-radius: 4px; 
                    border: 1px solid rgba(0,0,0); 
                }
                .detail-item:hover { 
                    background-color: var(--accent-color-quat-opacity); 
                    border: 1px solid var(--accent-color); 
                }
                .detail-label { 
                    display: block; 
                    font-size: 0.8rem; 
                    color: var(--accent-color); 
                    margin-bottom: 2px; 
                }
                .detail-value { font-weight: 600; color: #333; }
                
                .btn-icon-group { display: flex; gap: 8px; justify-content: center; }
            </style>

            <div class="customers-layout">
                <div class="table-header">
                    <h2>Clientes</h2>
                    <div class="table-controls" style="display:flex; align-items:center; gap:10px;">
                        <input type="text" id="cust-search-input" placeholder="Buscar por nombre, DNI o email..."
                            style="padding:8px 12px; border:2px solid #1b1b1b; border-radius:8px; font-family:'Satoshi',sans-serif; font-weight:500; font-size:0.95rem; outline:none; width:280px; box-shadow:2px 2px 0px rgba(0,0,0,0.1); transition:all 0.2s; align-self:stretch;">
                        <button id="customers-sort-btn" class="btn btn-secondary" title="Ordenar por ID (Mayor/Menor)">
                            <i class="ph ph-sort-ascending" id="cust-sort-icon"></i>
                        </button>
                        <button id="customers-create-btn" class="btn btn-primary">+ Nuevo Cliente</button>
                    </div>
                </div>
                <div class="table-wrapper" style="flex-grow:1; overflow-y:auto;">
                    <table id="customers-table" style="width:100%; border-collapse:collapse;">
                        <thead style="position:sticky; top:0; background:white; z-index:10; box-shadow:0 1px 2px var(--accent-color);">
                            <tr>
                                <th style="padding:12px; text-align:left;">Nombre</th>
                                <th style="padding:12px; text-align:left;">Contacto</th>
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
                            <h3 id="modal-cust-title">Nuevo Cliente</h3>
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
                            <h3><i class="ph-bold ph-user-focus"></i>  Ficha de Cliente</h3>
                            <button class="modal-close-btn" id="close-detail-cust-modal">&times;</button>
                        </div>
                        <div class="modal-body" id="detail-cust-content" style="padding: 1.5rem;"></div>
                    </div>
                </div>
            </div>
        `;
    }

    attachEvents() {
        const sortBtn = document.getElementById('customers-sort-btn');
        if(sortBtn) {
            sortBtn.addEventListener('click', () => {
                this.currentSortOrder = (this.currentSortOrder === 'DESC') ? 'ASC' : 'DESC';
                const icon = document.getElementById('cust-sort-icon');
                if(this.currentSortOrder === 'ASC') {
                    icon.classList.replace('ph-sort-ascending', 'ph-sort-descending');
                } else {
                    icon.classList.replace('ph-sort-descending', 'ph-sort-ascending');
                }
                this.loadCustomers(this.currentSortOrder);
            });
        }

        document.getElementById('customers-create-btn')?.addEventListener('click', () => {
            this.editingId = null;
            document.getElementById('modal-cust-title').textContent = "Nuevo Cliente";
            document.getElementById('submit-customer-btn').textContent = "Guardar Cliente";
            document.getElementById('create-customer-form').reset();
            const m = document.getElementById('create-customer-modal');
            m.classList.remove('hidden'); m.style.display='flex';
        });

        document.getElementById('close-customer-modal')?.addEventListener('click', () => {
            const m = document.getElementById('create-customer-modal');
            m.classList.add('hidden'); m.style.display='none';
        });

        document.getElementById('close-detail-cust-modal')?.addEventListener('click', () => {
            const m = document.getElementById('detail-customer-modal');
            m.classList.add('hidden'); m.style.display='none';
        });

        document.getElementById('create-customer-form')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleFormSubmit();
        });

        document.getElementById('cust-search-input')?.addEventListener('input', (e) => {
            this.filterCustomers(e.target.value);
        });
    }

    async handleFormSubmit() {
        const btn = document.getElementById('submit-customer-btn');
        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = "Procesando...";

        const data = {
            id: this.editingId,
            name: document.getElementById('cust-name').value,
            phone: document.getElementById('cust-phone').value,
            dni: document.getElementById('cust-dni').value,
            email: document.getElementById('cust-email').value,
            address: document.getElementById('cust-address').value,
            birth_date: document.getElementById('cust-birth').value,
        };

        const endpoint = this.editingId ? '/api/customers/update.php' : '/api/customers/create.php';

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();

            if (result.success) {
                document.getElementById('create-customer-modal').classList.add('hidden');
                document.getElementById('create-customer-modal').style.display='none';
                await this.loadCustomers(this.currentSortOrder);
                pop_ups.success(this.editingId ? 'Cliente actualizado' : 'Cliente creado');
            } else {
                pop_ups.error(result.message || 'Error en la operación');
            }
        } catch (e) {
            console.error(e);
            pop_ups.error('Error de conexión');
        } finally {
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }

    async loadCustomers(order) {
        const tbody = document.getElementById('customers-list-body');
        if(!tbody) return;
        tbody.innerHTML = '<tr><td colspan="4" class="text-center">Cargando...</td></tr>';

        // Limpiar búsqueda al recargar
        const searchInput = document.getElementById('cust-search-input');
        if (searchInput) searchInput.value = '';

        try {
            const response = await fetch('/api/customers/get-all?order=' + order);
            const data = await response.json();

            if (!data.success || !data.customers.length) {
                this.allCustomers = [];
                tbody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center" style="padding:3rem; color:#888;">
                            <i class="ph ph-user-focus" style="font-size:2rem; margin-bottom:10px;"></i>
                            <p>No hay clientes registrados.</p>
                        </td>
                    </tr>`;
                return;
            }

            this.allCustomers = data.customers;
            this.renderCustomerRows(this.allCustomers);

        } catch (e) {
            console.error(e);
            tbody.innerHTML = '<tr><td colspan="4" class="text-center" style="color:red">Error al cargar</td></tr>';
        }
    }

    renderCustomerRows(customers) {
        const tbody = document.getElementById('customers-list-body');
        if (!tbody) return;

        if (!customers.length) {
            tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; padding:2rem; color:#999;">No se encontraron resultados.</td></tr>`;
            return;
        }

        tbody.innerHTML = customers.map((c, idx) => {
            const total = customers.length;
            const displayNum = this.currentSortOrder === 'DESC' ? total - idx : idx + 1;
            const cJson = JSON.stringify(c).replace(/"/g, '&quot;');
            return `
            <tr style="border-bottom:1px solid #eee;">
                <td style="padding:12px;">
                    <div style="font-weight:600; color:#333;">${c.full_name}</div>
                    <small style="color:#888;">#${displayNum}</small>
                </td>
                <td style="padding:12px;">
                    ${c.phone ? `<div style="display:flex; align-items:center; gap:5px;"><i class="ph-fill ph-chats" style="color:var(--accent-color);"></i> ${c.phone}</div>` : ''}
                    ${c.email ? `<small style="color:#666; display:block; margin-top:2px;">${c.email}</small>` : ''}
                    ${!c.phone && !c.email ? '<span style="color:#ccc;">-</span>' : ''}
                </td>
                <td style="padding:12px;">${c.address || '<span style="color:#ccc;">-</span>'}</td>
                <td style="padding:12px; text-align:center;">
                     <div class="btn-icon-group">
                        <button class="action-btn view" data-id="${c.id}" title="Ver Detalles"><i class="ph ph-eye"></i></button>
                        <button class="action-btn edit" data-customer="${cJson}" title="Editar"><i class="ph ph-pencil-simple"></i></button>
                        <button class="action-btn delete" data-id="${c.id}" title="Eliminar"><i class="ph ph-trash"></i></button>
                    </div>
                </td>
            </tr>
        `}).join('');

        tbody.querySelectorAll('.view').forEach(b => b.addEventListener('click', () => this.showDetails(b.dataset.id)));
        tbody.querySelectorAll('.edit').forEach(b => {
            b.addEventListener('click', () => {
                const data = JSON.parse(b.dataset.customer);
                this.openEditModal(data);
            });
        });
        tbody.querySelectorAll('.delete').forEach(b => b.addEventListener('click', () => this.deleteCustomer(b.dataset.id)));
    }

    filterCustomers(term) {
        const normalize = (str) => (str || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
        const stripNonDigits = (str) => (str || '').replace(/\D/g, '');
        const q = normalize(term);
        const qDigits = stripNonDigits(term);
        if (!q && !qDigits) {
            this.renderCustomerRows(this.allCustomers);
            return;
        }
        const filtered = this.allCustomers.filter(c => {
            const name = normalize(c.full_name);
            const dni = normalize(c.tax_id);
            const email = normalize(c.email);
            const phone = stripNonDigits(c.phone);
            // Text match (accent-insensitive)
            if (q && (name.includes(q) || dni.includes(q) || email.includes(q))) return true;
            // Digit-only match for phone/DNI
            if (qDigits && (phone.includes(qDigits) || stripNonDigits(c.tax_id).includes(qDigits))) return true;
            return false;
        });
        this.renderCustomerRows(filtered);
    }

    openEditModal(c) {
        this.editingId = c.id;
        document.getElementById('modal-cust-title').textContent = "Editar Cliente";
        document.getElementById('submit-customer-btn').textContent = "Guardar Cambios";

        document.getElementById('cust-name').value = c.full_name || '';
        document.getElementById('cust-phone').value = c.phone || '';
        document.getElementById('cust-dni').value = c.tax_id || '';
        document.getElementById('cust-email').value = c.email || '';
        document.getElementById('cust-address').value = c.address || '';
        document.getElementById('cust-birth').value = c.birth_date || '';

        const m = document.getElementById('create-customer-modal');
        m.classList.remove('hidden');
        m.style.display='flex';
    }

    async deleteCustomer(id) {
        const confirm = await pop_ups.confirm("Eliminar Cliente", "¿Estás seguro? Si tiene ventas registradas, no se podrá borrar.");
        if(!confirm) return;

        try {
            const response = await fetch('/api/customers/delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            });
            const res = await response.json();
            if(res.success) {
                pop_ups.success("Cliente eliminado");
                this.loadCustomers(this.currentSortOrder);
            } else {
                pop_ups.error(res.message || "No se pudo eliminar");
            }
        } catch(e) { pop_ups.error("Error de conexión"); }
    }

    async showDetails(id) {
        const m = document.getElementById('detail-customer-modal');
        const c = document.getElementById('detail-cust-content');
        m.classList.remove('hidden'); m.style.display='flex';
        c.innerHTML = 'Cargando...';

        try {
            const data = await api.getCustomerDetails(id);

            if(data.success) {
                const cust = data.customer;
                c.innerHTML = `
                    <div style="text-align:center; margin-bottom:1.5rem;">
                        <div style="width:60px; height:60px; background:var(--accent-color-quat-opacity); color:var(--accent-color); border-radius:50%; border: 1px solid var(--accent-color); display:flex; align-items:center; justify-content:center; font-size:1.5rem; margin:0 auto 10px auto;">
                            ${cust.full_name.charAt(0).toUpperCase()}
                        </div>
                        <h2 style="margin:0;">${cust.full_name}</h2>
                        <small style="color:#888;">Ficha de Cliente</small>
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