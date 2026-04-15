/**
 */
import { getProviderList, getProviderDetails } from '../api.js';
import { pop_ups } from '../notifications/pop-up.js';

export class ProviderModule {
    constructor() {
        this.containerId = 'providers';
        this.isInitialized = false;
        this.editingId = null;
        this.currentSortOrder = 'DESC';
        this.allProviders = [];
    }

    init() {
        if (this.isInitialized) {
            this.loadProviders(this.currentSortOrder);
            return;
        }
        const container = document.getElementById(this.containerId);
        if (!container) return;

        container.innerHTML = this.renderBaseStructure();
        this.attachEvents();
        this.loadProviders(this.currentSortOrder);
        this.isInitialized = true;
    }

    renderBaseStructure() {
        return `
            <style>
                .providers-layout { display: flex; flex-direction: column; height: 100%; gap: 1rem; }
                
                #create-provider-modal .modal-content, 
                #detail-provider-modal .modal-content {
                    background-color: #ffffff !important;
                    box-shadow: 8px 8px 0 rgba(0,0,0,0.3);
                    border: 1px solid #ddd;
                    border-radius: 8px;
                }
                #detail-provider-modal .modal-content:hover {
                    box-shadow: 8px 8px 0 var(--accent-color);
                }

                .form-group { margin-bottom: 15px; }
                .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #555; font-size: 0.9rem; }
                .form-input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; }
                .form-row { display: flex; gap: 15px; }
                .form-col { flex: 1; }

                .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
                .detail-item { background: rgba(0,0,0,0.1); padding: 10px; border-radius: 4px; border: 1px solid rgba(0,0,0); }
                .detail-item:hover { background: var(--accent-color-quat-opacity); padding: 10px; border-radius: 4px; border: 1px solid var(--accent-color); }
                .detail-label { display: block; font-size: 0.8rem; color: var(--accent-color); margin-bottom: 2px; }
                .detail-value { font-weight: 600; color: #333; }
                
                .btn-icon-group { display: flex; gap: 8px; justify-content: center; }
            </style>

            <div class="providers-layout">
                <div class="table-header">
                    <h2>Proveedores</h2>
                    <div class="table-controls" style="display:flex; align-items:center; gap:10px;">
                        <input type="text" id="prov-search-input" placeholder="Buscar por nombre, CUIT o email..."
                            style="padding:8px 12px; border:2px solid #1b1b1b; border-radius:8px; font-family:'Satoshi',sans-serif; font-weight:500; font-size:0.95rem; outline:none; width:280px; box-shadow:2px 2px 0px rgba(0,0,0,0.1); transition:all 0.2s; align-self:stretch;">
                        <button id="prov-sort-btn" class="btn btn-secondary" title="Ordenar por ID (Mayor/Menor)">
                            <i class="ph ph-sort-ascending" id="sort-icon"></i>
                        </button>
                        <button id="prov-create-btn" class="btn btn-primary">+ Nuevo Proveedor</button>
                    </div>
                </div>

                <div class="table-wrapper" style="flex-grow:1; overflow-y:auto;">
                    <table id="prov-table" style="width:100%; border-collapse:collapse;">
                        <thead style="position:sticky; top:0; background:white; z-index:10; box-shadow:0 1px 2px var(--accent-color);">
                            <tr>
                                <th style="padding:12px; text-align:left;">Nombre</th>
                                <th style="padding:12px; text-align:left;">Contacto</th>
                                <th style="padding:12px; text-align:left;">Ubicación</th>
                                <th style="padding:12px; text-align:center;">Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="prov-list-body"></tbody>
                    </table>
                </div>

                <div id="create-provider-modal" class="modal-overlay hidden" style="align-items:center; justify-content:center; display:none; z-index:1000;">
                    <div class="modal-content" style="width: 600px; max-width: 95%;">
                        <div class="modal-header">
                            <h3 id="modal-prov-title">Nuevo Proveedor</h3>
                            <button class="modal-close-btn" id="close-prov-modal">&times;</button>
                        </div>
                        <div class="modal-body" style="padding: 1.5rem;">
                            <form id="create-prov-form">
                                <div class="form-group">
                                    <label>Nombre / Razón Social (Obligatorio)</label>
                                    <input type="text" id="prov-name" class="form-input" required placeholder="Ej: Distribuidora Central S.A.">
                                </div>
                                <div class="form-row">
                                    <div class="form-col form-group">
                                        <label>Whatsapp / Tel</label>
                                        <input type="text" id="prov-phone" class="form-input" placeholder="Ej: 11 5555 1234">
                                    </div>
                                    <div class="form-col form-group">
                                        <label>CUIT / Tax ID</label>
                                        <input type="text" id="prov-tax" class="form-input" placeholder="Ej: 20-12345678-9">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" id="prov-email" class="form-input" placeholder="ventas@proveedor.com">
                                </div>
                                <div class="form-group">
                                    <label>Dirección</label>
                                    <input type="text" id="prov-address" class="form-input" placeholder="Ej: Av. Corrientes 1234, CABA">
                                </div>
                                <div style="margin-top: 2rem; text-align: right;">
                                    <button type="submit" id="submit-prov-btn" class="btn btn-primary">Guardar Proveedor</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>

                <div id="detail-provider-modal" class="modal-overlay hidden" style="align-items:center; justify-content:center; display:none; z-index:1001;">
                    <div class="modal-content" style="width: 500px; max-width: 90%;">
                        <div class="modal-header">
                            <h3>Ficha de Proveedor</h3>
                            <button class="modal-close-btn" id="close-detail-prov-modal">&times;</button>
                        </div>
                        <div class="modal-body" id="detail-prov-content" style="padding: 1.5rem;"></div>
                    </div>
                </div>
            </div>
        `;
    }

    attachEvents() {
        const sortBtn = document.getElementById('prov-sort-btn');
        if(sortBtn) {
            sortBtn.addEventListener('click', () => {
                this.currentSortOrder = (this.currentSortOrder === 'DESC') ? 'ASC' : 'DESC';

                const icon = document.getElementById('sort-icon');
                if(this.currentSortOrder === 'ASC') {
                    icon.classList.replace('ph-sort-ascending', 'ph-sort-descending');
                } else {
                    icon.classList.replace('ph-sort-descending', 'ph-sort-ascending');
                }

                this.loadProviders(this.currentSortOrder);
            });
        }

        document.getElementById('prov-create-btn')?.addEventListener('click', () => {
            this.editingId = null;
            document.getElementById('modal-prov-title').textContent = "Nuevo Proveedor";
            document.getElementById('submit-prov-btn').textContent = "Guardar Proveedor";
            document.getElementById('create-prov-form').reset();
            const m = document.getElementById('create-provider-modal');
            m.classList.remove('hidden');
            m.style.display='flex';
        });

        document.getElementById('close-prov-modal')?.addEventListener('click', () => {
            const m = document.getElementById('create-provider-modal'); m.classList.add('hidden'); m.style.display='none';
        });
        document.getElementById('close-detail-prov-modal')?.addEventListener('click', () => {
            const m = document.getElementById('detail-provider-modal'); m.classList.add('hidden'); m.style.display='none';
        });

        document.getElementById('create-prov-form')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleFormSubmit();
        });

        document.getElementById('prov-search-input')?.addEventListener('input', (e) => {
            this.filterProviders(e.target.value);
        });
    }

    async handleFormSubmit() {
        const btn = document.getElementById('submit-prov-btn');
        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = "Procesando...";

        const data = {
            id: this.editingId,
            name: document.getElementById('prov-name').value,
            phone: document.getElementById('prov-phone').value,
            tax_id: document.getElementById('prov-tax').value,
            email: document.getElementById('prov-email').value,
            address: document.getElementById('prov-address').value
        };

        const endpoint = this.editingId ? '/api/providers/update.php' : '/api/providers/create.php';

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();

            if (result.success) {
                document.getElementById('create-provider-modal').classList.add('hidden');
                document.getElementById('create-provider-modal').style.display='none';
                await this.loadProviders(this.currentSortOrder); // Mantenemos el orden actual
                pop_ups.success(this.editingId ? 'Proveedor actualizado' : 'Proveedor creado');
            } else {
                pop_ups.error(result.message || "Error en la operación");
            }
        } catch (e) { console.error(e); pop_ups.error("Error de conexión"); }
        finally { btn.disabled = false; btn.textContent = originalText; }
    }

    async loadProviders(order) {
        const tbody = document.getElementById('prov-list-body');
        if(!tbody) return;
        tbody.innerHTML = '<tr><td colspan="4" class="text-center" style="padding:20px;">Cargando...</td></tr>';

        const searchInput = document.getElementById('prov-search-input');
        if (searchInput) searchInput.value = '';

        try {
            const data = await getProviderList(order);
            if (!data.success || !data.providers.length) {
                this.allProviders = [];
                tbody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center" style="padding:3rem; color:#888;">
                            <i class="ph ph-truck" style="font-size:2rem; margin-bottom:10px;"></i>
                            <p>No hay proveedores registrados.</p>
                        </td>
                    </tr>`;
                return;
            }

            this.allProviders = data.providers;
            this.renderProviderRows(this.allProviders);

        } catch (e) { console.error(e); tbody.innerHTML = '<tr><td colspan="4" class="text-center" style="color:red">Error al cargar</td></tr>'; }
    }

    renderProviderRows(providers) {
        const tbody = document.getElementById('prov-list-body');
        if (!tbody) return;

        if (!providers.length) {
            tbody.innerHTML = `<tr><td colspan="4" style="text-align:center; padding:2rem; color:#999;">No se encontraron resultados.</td></tr>`;
            return;
        }

        tbody.innerHTML = providers.map((p, idx) => {
            const total = providers.length;
            const displayNum = this.currentSortOrder === 'DESC' ? total - idx : idx + 1;
            const pJson = JSON.stringify(p).replace(/"/g, '&quot;');
            return `
            <tr style="border-bottom:1px solid #eee;">
                <td style="padding:12px;">
                    <div style="font-weight:600; color:#333;">${p.full_name}</div>
                    <small style="color:#888;">#${displayNum}</small>
                </td>
                <td style="padding:12px;">
                    ${p.phone ? `<div style="display:flex; align-items:center; gap:5px;"><i class="ph-fill ph-chats" style="color:var(--accent-color);"></i> ${p.phone}</div>` : ''}
                    ${p.email ? `<small style="color:#666; display:block; margin-top:2px;">${p.email}</small>` : ''}
                </td>
                <td style="padding:12px; color:#555;">${p.address || '-'}</td>
                <td style="padding:12px; text-align:center;">
                    <div class="btn-icon-group">
                        <button class="action-btn view" data-id="${p.id}" title="Ver Detalles"><i class="ph ph-eye"></i></button>
                        <button class="action-btn edit" data-provider="${pJson}" title="Editar"><i class="ph ph-pencil-simple"></i></button>
                        <button class="action-btn delete" data-id="${p.id}" title="Eliminar"><i class="ph ph-trash"></i></button>
                    </div>
                </td>
            </tr>
        `}).join('');

        tbody.querySelectorAll('.view').forEach(b => b.addEventListener('click', () => this.showDetails(b.dataset.id)));
        tbody.querySelectorAll('.edit').forEach(b => {
            b.addEventListener('click', () => {
                const data = JSON.parse(b.dataset.provider);
                this.openEditModal(data);
            });
        });
        tbody.querySelectorAll('.delete').forEach(b => b.addEventListener('click', () => this.deleteProvider(b.dataset.id)));
    }

    filterProviders(term) {
        const normalize = (str) => (str || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
        const stripNonDigits = (str) => (str || '').replace(/\D/g, '');
        const q = normalize(term);
        const qDigits = stripNonDigits(term);
        if (!q && !qDigits) {
            this.renderProviderRows(this.allProviders);
            return;
        }
        const filtered = this.allProviders.filter(p => {
            const name = normalize(p.full_name);
            const tax = normalize(p.tax_id);
            const email = normalize(p.email);
            const phone = stripNonDigits(p.phone);
            if (q && (name.includes(q) || tax.includes(q) || email.includes(q))) return true;
            if (qDigits && (phone.includes(qDigits) || stripNonDigits(p.tax_id).includes(qDigits))) return true;
            return false;
        });
        this.renderProviderRows(filtered);
    }

    openEditModal(p) {
        this.editingId = p.id;
        document.getElementById('modal-prov-title').textContent = "Editar Proveedor";
        document.getElementById('submit-prov-btn').textContent = "Guardar Cambios";

        document.getElementById('prov-name').value = p.full_name || '';
        document.getElementById('prov-phone').value = p.phone || '';
        document.getElementById('prov-tax').value = p.tax_id || '';
        document.getElementById('prov-email').value = p.email || '';
        document.getElementById('prov-address').value = p.address || '';

        const m = document.getElementById('create-provider-modal');
        m.classList.remove('hidden');
        m.style.display='flex';
    }

    async deleteProvider(id) {
        const confirm = await pop_ups.confirm("Eliminar Proveedor", "¿Estás seguro? Si tiene historial de compras, no se podrá borrar.");
        if(!confirm) return;

        try {
            const response = await fetch('/api/providers/delete', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            });
            const res = await response.json();
            if(res.success) {
                pop_ups.success("Proveedor eliminado");
                this.loadProviders(this.currentSortOrder);
            } else {
                pop_ups.error(res.message || "No se pudo eliminar");
            }
        } catch(e) { pop_ups.error("Error de conexión"); }
    }

    async showDetails(id) {
        const m = document.getElementById('detail-provider-modal');
        const c = document.getElementById('detail-prov-content');
        m.classList.remove('hidden'); m.style.display='flex';
        c.innerHTML = 'Cargando...';
        try {
            const data = await getProviderDetails(id);
            if(data.success) {
                const p = data.provider;
                c.innerHTML = `
                    <div style="text-align:center; margin-bottom:1.5rem;">
                        <div style="width:60px; height:60px; background:var(--accent-color-quat-opacity); color:var(--accent-color); border-radius:50%; border: 1px solid var(--accent-color); display:flex; align-items:center; justify-content:center; font-size:1.5rem; margin:0 auto 10px auto;">
                            <i class="ph ph-truck"></i>
                        </div>
                        <h2 style="margin:0;">${p.full_name}</h2>
                        <small style="color:#888;">Ficha de Proveedor</small>
                    </div>
                    <div class="detail-grid">
                        <div class="detail-item"><span class="detail-label">Teléfono</span><span class="detail-value">${p.phone || '-'}</span></div>
                        <div class="detail-item"><span class="detail-label">Email</span><span class="detail-value">${p.email || '-'}</span></div>
                        <div class="detail-item"><span class="detail-label">CUIT / Tax ID</span><span class="detail-value">${p.tax_id || '-'}</span></div>
                        <div class="detail-item"><span class="detail-label">Registro</span><span class="detail-value">${new Date(p.created_at).toLocaleDateString()}</span></div>
                        <div class="detail-item" style="grid-column: 1 / -1;"><span class="detail-label">Dirección</span><span class="detail-value">${p.address || '-'}</span></div>
                    </div>
                `;
            }
        } catch(e) { c.innerHTML = 'Error'; }
    }
}

export const providerModuleInstance = new ProviderModule();