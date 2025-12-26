/**
 * public/assets/js/providers/providers.js
 * Módulo de Gestión de Proveedores.
 */
import { getProviderList, createProviderNew, getProviderDetails } from '../api.js';
import { pop_ups } from '../notifications/pop-up.js';

export class ProviderModule {
    constructor() {
        this.containerId = 'providers';
        this.isInitialized = false;
    }

    init() {
        if (this.isInitialized) {
            this.loadProviders();
            return;
        }
        const container = document.getElementById(this.containerId);
        if (!container) return;

        container.innerHTML = this.renderBaseStructure();
        this.attachEvents();
        this.loadProviders();
        this.isInitialized = true;
    }

    renderBaseStructure() {
        return `
            <style>
                .providers-layout { display: flex; flex-direction: column; height: 100%; gap: 1rem; }
                
                #create-provider-modal .modal-content, 
                #detail-provider-modal .modal-content {
                    background-color: #ffffff !important;
                    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
                    border: 1px solid #ddd;
                    border-radius: 8px;
                }

                .prov-col-accent { color: #f57c00; font-weight: bold; } /* Orange accent */

                .form-group { margin-bottom: 15px; }
                .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #555; font-size: 0.9rem; }
                .form-input { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; }
                .form-row { display: flex; gap: 15px; }
                .form-col { flex: 1; }

                .detail-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
                .detail-item { background: #fff3e0; padding: 10px; border-radius: 4px; border: 1px solid #ffe0b2; }
                .detail-label { display: block; font-size: 0.8rem; color: #e65100; margin-bottom: 2px; }
                .detail-value { font-weight: 600; color: #333; }
            </style>

            <div class="providers-layout">
                <div class="table-header">
                    <h2>Proveedores</h2>
                    <div class="table-controls">
                        <button id="prov-sort-btn" class="btn btn-secondary"><i class="ph ph-sort-ascending"></i></button>
                        <button id="prov-create-btn" class="btn btn-primary">+ Nuevo Proveedor</button>
                    </div>
                </div>

                <div class="table-wrapper" style="flex-grow:1; overflow-y:auto;">
                    <table id="prov-table" style="width:100%; border-collapse:collapse;">
                        <thead style="position:sticky; top:0; background:white; z-index:10; box-shadow:0 1px 2px rgba(0,0,0,0.1);">
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
                            <h3>Nuevo Proveedor</h3>
                            <button class="modal-close-btn" id="close-prov-modal">&times;</button>
                        </div>
                        <div class="modal-body" style="padding: 1.5rem;">
                            <form id="create-prov-form">
                                <div class="form-group">
                                    <label>Nombre / Razón Social (Obligatorio)</label>
                                    <input type="text" id="prov-name" class="form-input" required>
                                </div>
                                <div class="form-row">
                                    <div class="form-col form-group">
                                        <label>Whatsapp / Tel</label>
                                        <input type="text" id="prov-phone" class="form-input">
                                    </div>
                                    <div class="form-col form-group">
                                        <label>CUIT / Tax ID</label>
                                        <input type="text" id="prov-tax" class="form-input">
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Email</label>
                                    <input type="email" id="prov-email" class="form-input">
                                </div>
                                <div class="form-group">
                                    <label>Dirección</label>
                                    <input type="text" id="prov-address" class="form-input">
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
        document.getElementById('prov-create-btn')?.addEventListener('click', () => {
            document.getElementById('create-prov-form').reset();
            const m = document.getElementById('create-provider-modal'); m.classList.remove('hidden'); m.style.display='flex';
        });
        document.getElementById('close-prov-modal')?.addEventListener('click', () => {
            const m = document.getElementById('create-provider-modal'); m.classList.add('hidden'); m.style.display='none';
        });
        document.getElementById('create-prov-form')?.addEventListener('submit', (e) => { e.preventDefault(); this.submitProvider(); });
        document.getElementById('close-detail-prov-modal')?.addEventListener('click', () => {
            const m = document.getElementById('detail-provider-modal'); m.classList.add('hidden'); m.style.display='none';
        });
        const sortBtn = document.getElementById('prov-sort-btn');
        if(sortBtn) sortBtn.addEventListener('click', () => this.loadProviders());
    }

    async submitProvider() {
        const btn = document.getElementById('submit-prov-btn'); btn.disabled = true; btn.textContent = "Guardando...";
        const data = {
            name: document.getElementById('prov-name').value,
            phone: document.getElementById('prov-phone').value,
            tax_id: document.getElementById('prov-tax').value,
            email: document.getElementById('prov-email').value,
            address: document.getElementById('prov-address').value
        };
        try {
            const response = await createProviderNew(data);
            if (response.success) {
                document.getElementById('create-provider-modal').classList.add('hidden');
                document.getElementById('create-provider-modal').style.display='none';
                await this.loadProviders();
                pop_ups.success('Proveedor creado');
            }
        } catch (e) { console.error(e); }
        finally { btn.disabled = false; btn.textContent = "Guardar Proveedor"; }
    }

    async loadProviders(order='desc') {
        const tbody = document.getElementById('prov-list-body');
        if(!tbody) return;
        tbody.innerHTML = '<tr><td colspan="4" class="text-center">Cargando...</td></tr>';
        try {
            const data = await getProviderList(order);
            if (!data.success || !data.providers.length) { tbody.innerHTML = '<tr><td colspan="4" class="text-center" style="padding:2rem; color:#999;">Sin proveedores</td></tr>'; return; }

            tbody.innerHTML = data.providers.map(p => `
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:12px;">
                        <div style="font-weight:600;">${p.full_name}</div>
                        <small style="color:#888;">ID: ${p.id}</small>
                    </td>
                    <td style="padding:12px;">
                        ${p.phone ? `<div><i class="ph ph-whatsapp-logo" style="color:green;"></i> ${p.phone}</div>` : ''}
                        ${p.email ? `<small style="color:#666;">${p.email}</small>` : ''}
                    </td>
                    <td style="padding:12px;">${p.address || '-'}</td>
                    <td style="padding:12px; text-align:center;">
                        <button class="btn btn-secondary btn-sm view-prov" data-id="${p.id}"><i class="ph ph-eye"></i></button>
                    </td>
                </tr>
            `).join('');
            tbody.querySelectorAll('.view-prov').forEach(b => b.addEventListener('click', () => this.showDetails(b.dataset.id)));
        } catch (e) { console.error(e); tbody.innerHTML = '<tr><td colspan="4" class="text-center" style="color:red">Error</td></tr>'; }
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
                        <div style="width:60px; height:60px; background:#fff3e0; color:#e65100; border-radius:50%; display:flex; align-items:center; justify-content:center; font-size:1.5rem; margin:0 auto 10px auto;">
                            <i class="ph ph-truck"></i>
                        </div>
                        <h2 style="margin:0;">${p.full_name}</h2>
                        <small style="color:#888;">Proveedor #${p.id}</small>
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