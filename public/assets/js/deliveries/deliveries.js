import { pop_ups } from '../notifications/pop-up.js?v=3.0';

export class DeliveriesModule {
    constructor() {
        this.containerId = 'deliveries';
        this.isInitialized = false;
        this.currentFilter = 'pending'; // 'pending' or 'completed'
        this.allDeliveries = [];
        this.isRepartidor = false;
    }

    init() {
        const container = document.getElementById(this.containerId);
        if (!container) return;

        if (!this.isInitialized) {
            container.innerHTML = this.renderBaseStructure();
            this.attachEvents();
            this.isInitialized = true;
        }

        this.loadDeliveries();
    }

    renderBaseStructure() {
        return `
            <style>
                .deliveries-layout { display: flex; flex-direction: column; height: 100%; gap: 1rem; }
                .delivery-status-pending { background-color: var(--accent-red-20); color: var(--accent-red); padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 0.85rem; }
                .delivery-status-completed { background-color: var(--accent-green-20); color: var(--accent-green); padding: 4px 8px; border-radius: 4px; font-weight: bold; font-size: 0.85rem; }
                
                /* Mobile first cards for drivers */
                .driver-view-container { padding: 10px; display: flex; flex-direction: column; gap: 15px; }
                .delivery-card {
                    background: white;
                    border: 2px solid #1b1b1b;
                    box-shadow: 4px 4px 0px #1b1b1b;
                    border-radius: 12px;
                    padding: 0;
                    display: flex;
                    flex-direction: column;
                    overflow: hidden;
                }
                .delivery-card-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px dashed #ccc; padding-bottom: 8px; }
                .delivery-card-code { font-weight: 800; font-size: 1.2rem; color: var(--accent-color); }
                .delivery-card-body { font-size: 0.95rem; line-height: 1.4; display: flex; flex-direction: column; gap: 6px; }
                .delivery-card-actions { display: flex; gap: 10px; margin-top: 5px; }
                .btn-mobile { flex: 1; padding: 12px; font-size: 1rem; border-radius: 8px; font-weight: bold; display: flex; align-items: center; justify-content: center; gap: 8px; }
                
                /* Modal custom styling */
                #create-delivery-modal .modal-content {
                    background-color: #ffffff !important;
                    box-shadow: 8px 8px 0 rgba(0,0,0,0.3);
                    border: 2px solid #1b1b1b;
                    border-radius: 12px;
                }
                .form-group { margin-bottom: 15px; }
                .form-group label { display: block; margin-bottom: 5px; font-weight: 600; color: #333; font-size: 0.9rem; }
                .form-input, .form-select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem; box-sizing: border-box; }
            </style>

            <div class="deliveries-layout" id="deliveries-view-container">
                <!-- Content will be injected dynamically depending on user role (Admin vs Repartidor) -->
                <div style="text-align: center; padding: 2rem;">Cargando módulo de envíos...</div>
            </div>

            <!-- Create/Edit Delivery Modal -->
            <div id="create-delivery-modal" class="modal-overlay hidden" style="align-items:center; justify-content:center; display:none; z-index:1100;">
                <div class="modal-content" style="width: 500px; max-width: 95%;">
                    <div class="modal-header">
                        <h3 id="modal-delivery-title">Nuevo Envío</h3>
                        <button class="modal-close-btn" id="close-delivery-modal">&times;</button>
                    </div>
                    <div class="modal-body" style="padding: 1.5rem;">
                        <form id="create-delivery-form">
                            <input type="hidden" id="deliv-id">
                            <input type="hidden" id="deliv-sale-id">
                            
                            <div class="form-group" id="deliv-sale-selector-group">
                                <label>Venta Relacionada</label>
                                <input type="text" id="deliv-sale-display" class="rustic-input" readonly placeholder="Cargando venta...">
                            </div>
                            
                            <div class="form-group">
                                <label>Repartidor Asignado</label>
                                <select id="deliv-collaborator-id" class="rustic-select" required>
                                    <option value="">Seleccionar Repartidor</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label>Dirección de Entrega</label>
                                <input type="text" id="deliv-address" class="rustic-input" required placeholder="Ej: Av. Santa Fe 1234, CABA">
                            </div>
                            
                            <div class="form-group" id="deliv-phone-group" style="display:none;">
                                <label>Teléfono del Cliente (Opcional)</label>
                                <input type="text" id="deliv-customer-phone" class="rustic-input" placeholder="Ej: +5491122334455">
                            </div>
                            <div class="form-group" id="deliv-email-group" style="display:none;">
                                <label>Email del Cliente (Opcional)</label>
                                <input type="email" id="deliv-customer-email" class="rustic-input" placeholder="Ej: cliente@correo.com">
                            </div>
                            
                            <div class="form-group" style="margin-top: 1rem; border-top: 1px dashed #ccc; padding-top: 1rem;">
                                <label style="font-weight: 800; font-size: 0.95rem; display: block; margin-bottom: 8px;">¿El pago ya se ha realizado?</label>
                                <div style="display: flex; gap: 1rem;">
                                    <label style="cursor:pointer; display:flex; align-items:center; gap:6px; font-weight:bold;"><input type="radio" name="deliv-is-paid" value="1" checked style="accent-color:var(--accent-color); width:18px; height:18px;"> Sí, ya está pago</label>
                                    <label style="cursor:pointer; display:flex; align-items:center; gap:6px; font-weight:bold;"><input type="radio" name="deliv-is-paid" value="0" style="accent-color:var(--accent-color); width:18px; height:18px;"> No, se debe cobrar</label>
                                </div>
                            </div>
                            
                            <div style="margin-top: 2rem; text-align: right;">
                                <button type="submit" id="submit-delivery-btn" class="btn btn-primary">Guardar Envío</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `;
    }

    attachEvents() {
        document.getElementById('close-delivery-modal')?.addEventListener('click', () => {
            this.closeModal();
        });

        document.getElementById('create-delivery-form')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.handleFormSubmit();
        });
    }

    closeModal() {
        const m = document.getElementById('create-delivery-modal');
        m.classList.add('hidden');
        m.style.display = 'none';
    }

    async loadDeliveries() {
        try {
            const response = await fetch(`/api/deliveries/get-all.php?status=${this.currentFilter}`);
            const data = await response.json();

            if (!data.success) {
                pop_ups.error(data.message || 'Error al cargar envíos');
                return;
            }

            this.isRepartidor = data.is_repartidor;
            this.allDeliveries = data.deliveries;

            const viewContainer = document.getElementById('deliveries-view-container');
            if (!viewContainer) return;

            if (this.isRepartidor) {
                this.renderRepartidorView(viewContainer);
            } else {
                this.renderAdminView(viewContainer);
            }

        } catch (e) {
            console.error(e);
            const viewContainer = document.getElementById('deliveries-view-container');
            if (viewContainer) {
                viewContainer.innerHTML = `<div style="text-align:center;color:red;padding:2rem;">Error de conexión al cargar envíos</div>`;
            }
        }
    }
    renderAdminView(container) {
        const showTimeCol = this.currentFilter === 'completed';
        container.innerHTML = `
            <div class="table-header" style="display:flex; justify-content:space-between; align-items:center; border-bottom: 2px solid #1b1b1b; padding-bottom: 10px; margin-bottom: 10px;">
                <h2 style="margin:0;">Gestión de Envíos</h2>
                <div class="table-controls" style="display:flex; align-items:center; gap:10px; flex-wrap:wrap;">
                    <button class="btn btn-secondary" onclick="window.deliveriesModuleInstance.loadDeliveries()" title="Recargar Envíos" style="margin:0; padding:8px 12px; font-weight:800; width: auto; display: inline-flex; align-items: center; justify-content: center; height: 38px;">
                        <i class="ph-bold ph-arrows-clockwise" style="margin:0;"></i>
                    </button>
                    <button class="btn btn-secondary" onclick="window.location.href='settings.php?tab=remito'" title="Configurar Remito" style="margin:0; padding:8px 12px; font-weight:800; width: auto; display: inline-flex; align-items: center; justify-content: center; height: 38px; gap: 6px;">
                        <i class="ph-bold ph-receipt" style="margin:0;"></i> Configurar Remito
                    </button>
                    <div class="deliveries-tabs" style="margin:0; display:flex; gap:10px;">
                        <button class="tab-btn ${this.currentFilter === 'pending' ? 'active' : ''}" 
                                id="filter-pending-btn" style="height:38px; display:inline-flex; align-items:center; justify-content:center; gap:6px;">
                            <i class="ph-bold ph-clock"></i> Pendientes
                        </button>
                        <button class="tab-btn ${this.currentFilter === 'completed' ? 'active' : ''}" 
                                id="filter-completed-btn" style="height:38px; display:inline-flex; align-items:center; justify-content:center; gap:6px;">
                            <i class="ph-bold ph-check-circle"></i> Finalizados
                        </button>
                    </div>
                </div>
            </div>
            
            <div class="table-wrapper admin-desktop-view" style="flex-grow:1; overflow-y:auto;">
                <table style="width:100%; border-collapse:collapse;">
                    <thead style="position:sticky; top:0; background:white; z-index:10; box-shadow:0 1px 2px var(--accent-color);">
                        <tr>
                            <th style="padding:12px; text-align:left;">Código</th>
                            <th style="padding:12px; text-align:left;">Cliente / Venta</th>
                            <th style="padding:12px; text-align:left;">Dirección</th>
                            <th style="padding:12px; text-align:left;">Repartidor</th>
                            ${showTimeCol ? '<th style="padding:12px; text-align:left;">Tiempo</th>' : ''}
                            <th style="padding:12px; text-align:center;">Estado</th>
                            <th style="padding:12px; text-align:center;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody id="deliveries-list-body">
                        ${this.renderAdminRows()}
                    </tbody>
                </table>
            </div>

            <div class="admin-mobile-view" style="display:none; padding:10px; flex-grow:1; overflow-y:auto;">
                <div style="display:flex; flex-direction:column; gap:16px;">
                    ${this.renderAdminMobileCards()}
                </div>
            </div>
        `;

        // Attach filter toggles
        document.getElementById('filter-pending-btn')?.addEventListener('click', () => {
            this.currentFilter = 'pending';
            this.loadDeliveries();
        });
        document.getElementById('filter-completed-btn')?.addEventListener('click', () => {
            this.currentFilter = 'completed';
            this.loadDeliveries();
        });

        // Add action buttons event listeners to container directly (handles both desktop table & mobile cards)
        container.addEventListener('click', (e) => {
            const editBtn = e.target.closest('.edit-delivery');
            const deleteBtn = e.target.closest('.delete-delivery');
            const completeBtn = e.target.closest('.complete-delivery');
            const ticketBtn = e.target.closest('.view-ticket');
            const viewSaleBtn = e.target.closest('.view-sale-ticket');

            if (editBtn) {
                this.openEditModal(parseInt(editBtn.dataset.id));
            } else if (deleteBtn) {
                this.deleteDelivery(parseInt(deleteBtn.dataset.id));
            } else if (completeBtn) {
                this.completeDelivery(parseInt(completeBtn.dataset.id));
            } else if (ticketBtn) {
                this.showTicket(parseInt(ticketBtn.dataset.id));
            } else if (viewSaleBtn) {
                const saleId = parseInt(viewSaleBtn.dataset.saleId);
                if (window.salesModuleInstance) {
                    window.salesModuleInstance.showDetails(saleId);
                } else {
                    pop_ups.error("El módulo de ventas no está cargado.");
                }
            }
        });
    }

    renderAdminRows() {
        const showTimeCol = this.currentFilter === 'completed';
        if (!this.allDeliveries.length) {
            return `<tr><td colspan="${showTimeCol ? 7 : 6}" style="text-align:center; padding:3rem; color:#888;">No hay envíos registrados en esta sección.</td></tr>`;
        }

        return this.allDeliveries.map(d => {
            // Calculate elapsed time if completed, or current time elapsed
            let elapsedStr = '-';
            const created = new Date(d.created_at);
            if (d.status === 'completed' && d.delivered_at) {
                const delivered = new Date(d.delivered_at);
                const diffMs = delivered - created;
                elapsedStr = this.formatDuration(diffMs);
            } else {
                const diffMs = new Date() - created;
                elapsedStr = this.formatDuration(diffMs) + ' (activo)';
            }

            const stateClass = d.status === 'pending' ? 'delivery-status-pending' : 'delivery-status-completed';
            const stateLabel = d.status === 'pending' ? 'Pendiente' : 'Entregado';

            return `
                <tr style="border-bottom:1px solid #eee;">
                    <td style="padding:12px; font-weight:bold; color:var(--accent-color);">${d.ticket_code}</td>
                    <td style="padding:12px;">
                        <div style="font-weight:600;">${d.customer_name || 'Consumidor Final'}</div>
                        <small style="color:#666;">Total: $${parseFloat(d.sale_total || 0).toLocaleString()}</small>
                    </td>
                    <td style="padding:12px;">${d.address}</td>
                    <td style="padding:12px; font-weight:500;">${d.collaborator_name || '<span style="color:#aaa;">No asignado</span>'}</td>
                    ${showTimeCol ? `
                    <td style="padding:12px;">
                        <div style="font-size:0.85rem; color:#333;">Registrado: ${new Date(d.created_at).toLocaleString()}</div>
                        ${d.delivered_at ? `<div style="font-size:0.85rem; color:var(--accent-green);">Entregado: ${new Date(d.delivered_at).toLocaleString()}</div>` : ''}
                        <div style="font-size:0.85rem; font-weight:bold; margin-top:2px;">Transcurrido: ${elapsedStr}</div>
                    </td>
                    ` : ''}
                    <td style="padding:12px; text-align:center;"><span class="${stateClass}">${stateLabel}</span></td>
                    <td style="padding:12px; text-align:center;">
                        <div style="display:flex; gap:8px; justify-content:center; align-items:center;">
                            <button class="action-btn view-ticket" data-id="${d.id}" title="Ver Ticket de Envío" style="background:none; border:none; padding:4px; box-shadow:none;"><img src="/assets/img/iconos/ticket_envio.png" style="width:22px; height:22px; vertical-align:middle;"></button>
                            <button class="action-btn view-sale-ticket" data-sale-id="${d.sale_id}" title="Ver Ticket de Venta" style="background:none; border:none; padding:4px; box-shadow:none;"><img src="/assets/img/iconos/ticket_compra.png" style="width:22px; height:22px; vertical-align:middle;"></button>
                            ${d.status === 'pending' ? `
                                <button class="action-btn complete-delivery" data-id="${d.id}" title="Marcar como Entregado" style="color:var(--accent-green);"><i class="ph ph-check-circle" style="font-size:1.3rem;"></i></button>
                                <button class="action-btn edit-delivery" data-id="${d.id}" title="Editar"><i class="ph ph-pencil-simple" style="font-size:1.3rem;"></i></button>
                            ` : ''}
                            <button class="action-btn delete-delivery" data-id="${d.id}" title="Eliminar" style="color:var(--accent-red);"><i class="ph ph-trash" style="font-size:1.3rem;"></i></button>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    renderRepartidorView(container) {
        container.innerHTML = `
            <div class="driver-view-container">
                <div style="border-bottom: 2px solid #1b1b1b; padding-bottom: 10px; margin-bottom: 10px; display: flex; justify-content: space-between; align-items: center;">
                    <div>
                        <h2 style="margin:0;"><i class="ph-bold ph-truck" style="color:var(--accent-color);"></i> Mis Repartos</h2>
                        <p style="margin: 5px 0 0 0; font-size:0.9rem; color:#666;">Envíos asignados a tu usuario.</p>
                    </div>
                    <button class="btn btn-secondary" onclick="window.deliveriesModuleInstance.loadDeliveries()" title="Recargar Envíos" style="margin:0; padding:8px 12px; font-weight:800; width: auto; display: inline-flex; align-items: center; justify-content: center; height: 38px;">
                        <i class="ph-bold ph-arrows-clockwise" style="margin:0;"></i>
                    </button>
                </div>
                
                <div class="deliveries-tabs" style="margin-bottom:15px; display:flex; gap:10px;">
                    <button class="tab-btn ${this.currentFilter === 'pending' ? 'active' : ''}" onclick="window.deliveriesModuleInstance.currentFilter='pending'; window.deliveriesModuleInstance.loadDeliveries();">Pendientes</button>
                    <button class="tab-btn ${this.currentFilter === 'completed' ? 'active' : ''}" onclick="window.deliveriesModuleInstance.currentFilter='completed'; window.deliveriesModuleInstance.loadDeliveries();">Finalizados</button>
                </div>
                
                <div style="display:flex; flex-direction:column; gap:16px;" id="driver-cards-container">
                    ${this.renderRepartidorCards()}
                </div>
            </div>
        `;

        const completeBtns = container.querySelectorAll('.driver-complete-btn');
        if (completeBtns) {
            completeBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    const id = btn.dataset.id;
                    this.completeDelivery(id);
                });
            });
        }

        const mapsBtns = container.querySelectorAll('.driver-maps-btn');
        if (mapsBtns) {
            mapsBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    const addr = btn.dataset.address;
                    window.open(`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(addr)}`, '_blank');
                });
            });
        }
    }

    renderRepartidorCards() {
        if (!this.allDeliveries.length) {
            return `
                <div style="text-align:center; padding:3rem; color:#888; background:white; border:2px dashed #ccc; border-radius:12px;">
                    <i class="ph ph-package" style="font-size:3rem; margin-bottom:10px; color:#aaa;"></i>
                    <p style="margin:0; font-weight:bold; font-size:1.1rem;">¡Todo al día!</p>
                    <p style="margin:5px 0 0 0; font-size:0.9rem;">No tienes ningún envío pendiente asignado.</p>
                </div>
            `;
        }

        return this.allDeliveries.map(d => {
            return `
                <div class="delivery-card" style="background:#fff; border:2px solid #1b1b1b; border-radius:12px; overflow:hidden; display:flex; flex-direction:column;">
                    <div class="delivery-card-header" style="background:#fff; border-bottom:1px solid #eee; padding:12px 15px; display:flex; justify-content:space-between; align-items:center;">
                        <span class="delivery-card-code" style="font-weight:900; font-size:1.1rem; color:var(--accent-color);">${d.ticket_code}</span>
                        <span class="${d.status === 'pending' ? 'delivery-status-pending' : 'delivery-status-completed'}">${d.status === 'pending' ? 'Pendiente' : 'Entregado'}</span>
                    </div>
                    <div class="delivery-card-body" style="padding:15px; font-size:0.95rem; line-height:1.5;">
                        <div style="margin-bottom:6px;"><strong>Cliente:</strong> ${d.customer_name || 'Consumidor Final'}</div>
                        ${d.customer_phone ? `<div style="margin-bottom:6px; display:flex; align-items:center;"><strong>Teléfono:</strong> <span style="margin-left:6px;">${d.customer_phone}</span><a href="tel:${d.customer_phone}" class="btn btn-secondary" style="padding: 0; width:32px; height:32px; margin:0 0 0 10px; font-size:0.9rem; text-decoration:none; display:inline-flex; align-items:center; justify-content:center; border-radius:8px; flex-shrink:0;"><i class="ph-fill ph-phone-call" style="margin:0;"></i></a></div>` : ''}
                        <div style="margin-bottom:6px;"><strong>Dirección:</strong> ${d.address}</div>
                        <div style="margin-bottom:6px; font-weight:800; font-size:1.05rem; display:flex; align-items:center; gap:6px;">
                            ${(d.is_paid !== undefined && d.is_paid != 1) ?
                    `<i class="ph-bold ph-warning-circle" style="color:var(--accent-yellow); font-size:1.2rem;"></i> <span style="color:var(--accent-yellow);">Cobrar: $${parseFloat(d.sale_total || 0).toLocaleString('es-AR', { minimumFractionDigits: 2 })}</span>` :
                    `<i class="ph-bold ph-check-circle" style="color:var(--accent-green); font-size:1.2rem;"></i> <span style="color:var(--accent-green);">Pedido ya abonado</span>`
                }
                        </div>
                        ${d.estimated_time ? `<div style="margin-bottom:6px;"><strong>Tiempo Estimado:</strong> ${d.estimated_time}</div>` : ''}
                        <div style="font-size:0.8rem; color:#666; margin-top:10px;"><strong>Registrado:</strong> ${new Date(d.created_at).toLocaleString()}</div>
                    </div>
                    ${d.status === 'pending' ? `
                    <div class="delivery-card-actions" style="padding:12px; border-top:2px solid #1b1b1b; display:flex; gap:12px; justify-content:center; background:#fafafa;">
                        <button class="btn btn-secondary driver-maps-btn" data-address="${d.address}" style="flex: 0 1 150px; margin:0; padding:10px; font-size:0.9rem; font-weight:700; display:inline-flex; align-items:center; justify-content:center; gap:6px; border:2px solid #1b1b1b; box-shadow:2px 2px 0px #1b1b1b; border-radius:6px; height:42px;">
                            <i class="ph-bold ph-map-pin"></i> Abrir Mapa
                        </button>
                        <button class="btn btn-primary driver-complete-btn" data-id="${d.id}" style="flex: 0 1 150px; margin:0; padding:10px; background-color: var(--accent-green); border:2px solid #1b1b1b; color: white; font-size:0.9rem; font-weight:700; display:inline-flex; align-items:center; justify-content:center; gap:6px; box-shadow:2px 2px 0px #1b1b1b; border-radius:6px; height:42px;">
                            <i class="ph-bold ph-check"></i> Entregado
                        </button>
                    </div>` : `
                    <div class="delivery-card-actions" style="padding:12px; border-top:2px solid #1b1b1b; display:flex; gap:10px; justify-content:center; background:#fafafa; align-items:center;">
                        <div style="font-size:0.9rem; color:var(--accent-green); font-weight:bold; display:inline-flex; align-items:center; gap:6px;"><i class="ph-bold ph-check-circle" style="font-size:1.2rem;"></i> Entregado el ${new Date(d.delivered_at).toLocaleString()}</div>
                    </div>`}
                </div>
            `;
        }).join('');
    }

    renderAdminMobileCards() {
        if (!this.allDeliveries.length) {
            return `<div style="text-align:center; padding:3rem; color:#888; background:white; border:2px dashed #ccc; border-radius:12px;">No hay envíos registrados en esta sección.</div>`;
        }

        return this.allDeliveries.map(d => {
            const stateClass = d.status === 'pending' ? 'delivery-status-pending' : 'delivery-status-completed';
            const stateLabel = d.status === 'pending' ? 'Pendiente' : 'Entregado';

            let elapsedStr = '-';
            const created = new Date(d.created_at);
            if (d.status === 'completed' && d.delivered_at) {
                const delivered = new Date(d.delivered_at);
                const diffMs = delivered - created;
                elapsedStr = this.formatDuration(diffMs);
            } else {
                const diffMs = new Date() - created;
                elapsedStr = this.formatDuration(diffMs) + ' (activo)';
            }

            return `
                <div class="delivery-card" style="background:#fff; border:2px solid #1b1b1b; border-radius:12px; overflow:hidden; display:flex; flex-direction:column; box-shadow: 4px 4px 0px #1b1b1b;">
                    <div class="delivery-card-header" style="background:#fff; border-bottom:2px solid #1b1b1b; padding:12px 15px; display:flex; justify-content:space-between; align-items:center;">
                        <span class="delivery-card-code" style="font-weight:900; font-size:1.1rem; color:var(--accent-color);">${d.ticket_code}</span>
                        <span class="${stateClass}" style="${d.status === 'pending' ? 'background:var(--accent-red); color:white;' : 'background:var(--accent-green); color:white;'} padding:2px 8px; border-radius:4px; font-size:0.75rem; font-weight:bold;">${stateLabel}</span>
                    </div>
                    <div class="delivery-card-body" style="padding:15px; font-size:0.95rem; line-height:1.5;">
                        <div style="margin-bottom:6px;"><strong>Cliente:</strong> ${d.customer_name || 'Consumidor Final'}</div>
                        <div style="margin-bottom:6px;"><strong>Total:</strong> $${parseFloat(d.sale_total || 0).toLocaleString()}</div>
                        <div style="margin-bottom:6px;"><strong>Dirección:</strong> ${d.address}</div>
                        <div style="margin-bottom:6px;"><strong>Repartidor:</strong> ${d.collaborator_name || '<span style="color:#aaa;">No asignado</span>'}</div>
                        <div style="font-size:0.8rem; color:#666; margin-top:10px;">
                            <div><strong>Registrado:</strong> ${new Date(d.created_at).toLocaleString()}</div>
                            ${d.delivered_at ? `<div><strong>Entregado:</strong> ${new Date(d.delivered_at).toLocaleString()}</div>` : ''}
                            <div><strong>Transcurrido:</strong> ${elapsedStr}</div>
                        </div>
                    </div>
                    <div class="delivery-card-actions" style="padding:12px; border-top:2px solid #1b1b1b; display:flex; gap:12px; justify-content:center; background:#fafafa; align-items:center;">
                        <button class="action-btn view-ticket btn btn-secondary" data-id="${d.id}" title="Ver Ticket" style="width:36px; height:36px; padding:0; display:inline-flex; align-items:center; justify-content:center; border:2px solid #1b1b1b; box-shadow:2px 2px 0px #1b1b1b; border-radius:6px; margin:0;"><img src="/assets/img/iconos/ticket_envio.png" style="width:20px; height:20px;"></button>
                        <button class="action-btn view-sale-ticket btn btn-secondary" data-sale-id="${d.sale_id}" title="Ver Compra" style="width:36px; height:36px; padding:0; display:inline-flex; align-items:center; justify-content:center; border:2px solid #1b1b1b; box-shadow:2px 2px 0px #1b1b1b; border-radius:6px; margin:0;"><img src="/assets/img/iconos/ticket_compra.png" style="width:20px; height:20px;"></button>
                        ${d.status === 'pending' ? `
                            <button class="action-btn complete-delivery btn btn-secondary" data-id="${d.id}" title="Entregado" style="color:var(--accent-green); width:36px; height:36px; display:inline-flex; align-items:center; justify-content:center; padding:0; border:2px solid #1b1b1b; border-radius:6px; box-shadow:2px 2px 0px #1b1b1b; margin:0;"><i class="ph-bold ph-check" style="font-size:1.2rem;"></i></button>
                            <button class="action-btn edit-delivery btn btn-secondary" data-id="${d.id}" title="Editar" style="width:36px; height:36px; display:inline-flex; align-items:center; justify-content:center; padding:0; border:2px solid #1b1b1b; border-radius:6px; box-shadow:2px 2px 0px #1b1b1b; margin:0;"><i class="ph-bold ph-pencil-simple" style="font-size:1.2rem;"></i></button>
                        ` : ''}
                        <button class="action-btn delete-delivery btn btn-secondary" data-id="${d.id}" title="Eliminar" style="color:var(--accent-red); width:36px; height:36px; display:inline-flex; align-items:center; justify-content:center; padding:0; border:2px solid #1b1b1b; border-radius:6px; box-shadow:2px 2px 0px #1b1b1b; margin:0;"><i class="ph-bold ph-trash" style="font-size:1.2rem;"></i></button>
                    </div>
                </div>
            `;
        }).join('');
    }

    formatDuration(ms) {
        const totalSecs = Math.floor(ms / 1000);
        const hours = Math.floor(totalSecs / 3600);
        const minutes = Math.floor((totalSecs % 3600) / 60);

        if (hours > 0) {
            return `${hours}h ${minutes}m`;
        }
        return `${minutes} min`;
    }

    async openCreateModal(saleId, address = '', customerName = '', totalAmount = 0, customerPhone = '', customerEmail = '') {
        this.closeModal();

        document.getElementById('modal-delivery-title').textContent = "Crear Envío";
        document.getElementById('submit-delivery-btn').textContent = "Crear Envío";
        document.getElementById('create-delivery-form').reset();
        document.getElementById('deliv-id').value = '';
        document.getElementById('deliv-sale-id').value = saleId;

        const dateTimeStr = new Date().toLocaleString();
        document.getElementById('deliv-sale-display').value = `${dateTimeStr} - ${customerName || 'Consumidor Final'} ($${parseFloat(totalAmount).toLocaleString()})`;
        document.getElementById('deliv-address').value = address;

        // Fetch sale details to check customer phone/email
        this.currentCustomerId = null;
        this.currentCustomerPhone = customerPhone || '';
        this.currentCustomerEmail = customerEmail || '';

        try {
            const saleDetailsRes = await fetch(`/api/sales/get-details.php?id=${saleId}`);
            const saleDetails = await saleDetailsRes.json();
            if (saleDetails.success && saleDetails.sale) {
                this.currentCustomerId = saleDetails.sale.customer_id;
                this.currentCustomerPhone = saleDetails.sale.customer_phone || customerPhone || '';
                this.currentCustomerEmail = saleDetails.sale.customer_email || customerEmail || '';
                if (document.getElementById('deliv-is-paid')) {
                    document.getElementById('deliv-is-paid-1').checked = (parseInt(saleDetails.sale.is_paid) === 1);
                    document.getElementById('deliv-is-paid-0').checked = (parseInt(saleDetails.sale.is_paid) !== 1);
                }
            }
        } catch (e) {
            console.error("Error fetching sale details in openCreateModal:", e);
        }

        // Always show these input fields to allow customization
        document.getElementById('deliv-phone-group').style.display = 'block';
        document.getElementById('deliv-email-group').style.display = 'block';
        document.getElementById('deliv-customer-phone').value = this.currentCustomerPhone;
        document.getElementById('deliv-customer-email').value = this.currentCustomerEmail;

        await this.loadCollaboratorsDropdown();

        const m = document.getElementById('create-delivery-modal');
        m.classList.remove('hidden');
        m.style.display = 'flex';
    }

    async openEditModal(id) {
        const d = this.allDeliveries.find(x => x.id === id);
        if (!d) return;

        document.getElementById('modal-delivery-title').textContent = "Editar Envío";
        document.getElementById('submit-delivery-btn').textContent = "Guardar Cambios";
        document.getElementById('create-delivery-form').reset();

        document.getElementById('deliv-id').value = d.id;
        document.getElementById('deliv-sale-id').value = d.sale_id;

        const dateTimeStr = new Date(d.created_at).toLocaleString();
        document.getElementById('deliv-sale-display').value = `${dateTimeStr} - ${d.customer_name || 'Consumidor Final'} ($${parseFloat(d.sale_total || 0).toLocaleString()})`;
        document.getElementById('deliv-address').value = d.address;

        // Always show the phone and email fields so the user can easily update them on the delivery level
        document.getElementById('deliv-phone-group').style.display = 'block';
        document.getElementById('deliv-email-group').style.display = 'block';
        document.getElementById('deliv-customer-phone').value = d.phone || d.customer_phone || '';
        document.getElementById('deliv-customer-email').value = d.email || d.customer_email || '';

        if (document.getElementById('deliv-is-paid')) {
            document.getElementById('deliv-is-paid-1').checked = (parseInt(d.is_paid) === 1);
            document.getElementById('deliv-is-paid-0').checked = (parseInt(d.is_paid) !== 1);
        }

        await this.loadCollaboratorsDropdown(d.collaborator_id);

        const m = document.getElementById('create-delivery-modal');
        m.classList.remove('hidden');
        m.style.display = 'flex';
    }

    async loadCollaboratorsDropdown(selectedId = null) {
        const select = document.getElementById('deliv-collaborator-id');
        if (!select) return;
        select.innerHTML = '<option value="">Cargando repartidores...</option>';

        try {
            const response = await fetch('/api/employees/get-all.php');
            const data = await response.json();

            if (data.success && data.employees) {
                // Filter to only include employees with "Repartidor" category AND that are collaborators
                const repartidores = data.employees.filter(emp => emp.category_name === 'Repartidor' && parseInt(emp.is_collaborator) === 1);

                if (repartidores.length === 0) {
                    select.innerHTML = '<option value="">No hay repartidores colaboradores disponibles</option>';
                    return;
                }

                let options = '<option value="">Seleccionar Repartidor</option>';
                repartidores.forEach(emp => {
                    const sel = (selectedId && parseInt(emp.id) === parseInt(selectedId)) ? 'selected' : '';
                    options += `<option value="${emp.id}" ${sel}>${emp.full_name}</option>`;
                });
                select.innerHTML = options;
            } else {
                select.innerHTML = '<option value="">Error al cargar empleados</option>';
            }
        } catch (e) {
            select.innerHTML = '<option value="">Error de conexión</option>';
        }
    }

    async handleFormSubmit() {
        const btn = document.getElementById('submit-delivery-btn');
        btn.disabled = true;
        const originalText = btn.textContent;
        btn.textContent = "Procesando...";

        const newPhone = document.getElementById('deliv-customer-phone').value.trim();
        const newEmail = document.getElementById('deliv-customer-email').value.trim();

        const id = document.getElementById('deliv-id').value;
        const data = {
            id: id ? parseInt(id) : null,
            sale_id: parseInt(document.getElementById('deliv-sale-id').value),
            collaborator_id: parseInt(document.getElementById('deliv-collaborator-id').value),
            address: document.getElementById('deliv-address').value,
            phone: newPhone || null,
            email: newEmail || null,
            is_paid: parseInt(document.querySelector('input[name="deliv-is-paid"]:checked').value),
            estimated_time: null
        };

        const endpoint = id ? '/api/deliveries/update.php' : '/api/deliveries/create.php';

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data)
            });
            const result = await response.json();

            if (result.success) {
                this.closeModal();
                this.loadDeliveries();
                pop_ups.success(id ? 'Envío actualizado' : 'Envío creado');

                // If it's a new delivery, open ticket view immediately
                if (!id && result.id) {
                    this.showTicket(result.id);
                }
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

    async completeDelivery(id) {
        const confirm = await pop_ups.confirm("Finalizar Envío", "¿Confirmas que el pedido fue entregado correctamente?");
        if (!confirm) return;

        try {
            const response = await fetch('/api/deliveries/complete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            });
            const res = await response.json();
            if (res.success) {
                pop_ups.success("Envío marcado como entregado");
                this.loadDeliveries();
            } else {
                pop_ups.error(res.message || "Error al completar");
            }
        } catch (e) { pop_ups.error("Error de conexión"); }
    }

    async deleteDelivery(id) {
        const confirm = await pop_ups.confirm("Eliminar Envío", "¿Estás seguro de que deseas eliminar este envío? Esto no afectará la venta.");
        if (!confirm) return;

        try {
            const response = await fetch('/api/deliveries/delete.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: id })
            });
            const res = await response.json();
            if (res.success) {
                pop_ups.info("Envío eliminado");
                this.loadDeliveries();
            } else {
                pop_ups.error(res.message || "Error al eliminar");
            }
        } catch (e) { pop_ups.error("Error de conexión"); }
    }

    async showTicket(id) {
        try {
            const response = await fetch('/api/deliveries/get-all.php');
            const data = await response.json();
            if (!data.success) return;

            this.allDeliveries = data.deliveries;
            const d = data.deliveries.find(x => parseInt(x.id) === parseInt(id));
            if (!d) {
                pop_ups.error("No se encontraron detalles del envío");
                return;
            }

            const customerEmail = d.customer_email || '';

            const ticketHTML = `
                <div style="text-align:center; margin-bottom:20px; border-bottom:2px dashed #000; padding-bottom:15px;">
                    <div style="font-size:1.15rem; font-weight:900; color:#000; letter-spacing:0.5px;">ENVÍO: ${d.ticket_code}</div>
                    <div style="font-size:0.85rem; margin-top:5px; color:#555;">${new Date(d.created_at).toLocaleString()}</div>
                </div>
                
                <div class="ticket-row" style="margin-bottom:12px; display:flex; flex-direction:column; width:100%; border-bottom:1px dotted #ccc; padding-bottom:8px;">
                    <span style="font-size:0.75rem; text-transform:uppercase; color:#666; font-weight:bold; letter-spacing:0.5px;">CLIENTE:</span>
                    <span style="font-size:1.05rem; font-weight:800; color:#000; margin-top:2px;">${d.customer_name || 'Consumidor Final'}</span>
                    ${d.customer_phone ? `<span style="font-size:0.9rem; font-weight:600; color:#555; margin-top:3px;"><i class="ph ph-phone" style="vertical-align:middle; font-size:1.1rem;"></i> ${d.customer_phone}</span>` : ''}
                </div>

                <div class="ticket-row" style="margin-bottom:12px; display:flex; flex-direction:column; width:100%; border-bottom:1px dotted #ccc; padding-bottom:8px;">
                    <span style="font-size:0.75rem; text-transform:uppercase; color:#666; font-weight:bold; letter-spacing:0.5px;">DIRECCIÓN DE ENTREGA:</span>
                    <span style="font-size:1.05rem; font-weight:800; color:var(--accent-color); margin-top:4px; line-height:1.5; display:block;"><i class="ph ph-map-pin" style="vertical-align:middle; font-size:1.1rem;"></i> ${d.address}</span>
                </div>

                <div class="ticket-row" style="margin-bottom:12px; display:flex; flex-direction:column; width:100%; border-bottom:1px dotted #ccc; padding-bottom:8px;">
                    <span style="font-size:0.75rem; text-transform:uppercase; color:#666; font-weight:bold; letter-spacing:0.5px;">REPARTIDOR ASIGNADO:</span>
                    <span style="font-size:1.05rem; font-weight:800; color:#000; margin-top:2px;">${d.collaborator_name || 'No asignado'}</span>
                </div>

                <div class="ticket-row" style="margin-bottom:12px; display:flex; flex-direction:column; width:100%; border-bottom:1px dotted #ccc; padding-bottom:8px;">
                    <span style="font-size:0.75rem; text-transform:uppercase; color:#666; font-weight:bold; letter-spacing:0.5px;">VENTA RELACIONADA:</span>
                    <span style="font-size:0.95rem; font-weight:700; color:#333; margin-top:2px;">Fecha/Hora: ${new Date(d.sale_date || d.created_at).toLocaleString()}</span>
                    <span style="font-size:0.95rem; font-weight:700; color:#333; margin-top:2px;">Cliente: ${d.customer_name || 'Consumidor Final'}</span>
                    <span style="font-size:0.95rem; font-weight:800; color:var(--sale-green); margin-top:2px;">Monto Total: $${parseFloat(d.sale_total || 0).toLocaleString()}</span>
                </div>

                <div style="text-align:center; margin-top:20px; font-weight:bold; border-top:2px dashed #000; padding-top:15px; letter-spacing:1px; color:#000;">
                    ¡GRACIAS POR SU COMPRA!
                </div>

                <div style="margin-top:25px; display:flex; flex-direction:column; gap:12px; padding:4px;">
                    <div style="display:flex; gap:12px; justify-content:stretch;">
                        <button class="ticket-btn" onclick="window.contactDeliveryWhatsApp('${d.customer_phone || ''}')" style="margin:0; flex:1; background-color:white; border-color:#1b1b1b; color:#1b1b1b;">
                            <i class="ph ph-phone" style="font-size:1.2rem;"></i> Contactar
                        </button>
                        <button class="ticket-btn" onclick="window.shareDeliveryWhatsApp('${d.ticket_code}', '${encodeURIComponent(d.address)}', '${d.customer_phone || ''}')" style="margin:0; flex:1; background-color:var(--accent-green); border-color:#1b1b1b; color:#1b1b1b;">
                            <i class="ph ph-whatsapp-logo" style="font-size:1.2rem;"></i> Notificar
                        </button>
                    </div>
                    <button class="ticket-btn" onclick="window.sendDeliveryEmail(${d.id}, '${customerEmail}')" style="margin:0; width:100%; border-color:#1b1b1b; color:#1b1b1b;">
                        <i class="ph ph-envelope" style="font-size:1.2rem;"></i> Enviar por Email
                    </button>
                    <button class="ticket-btn" onclick="window.printDeliveryRemito(${d.id})" style="margin:0; width:100%; border-color:var(--accent-color); color:var(--accent-color); background-color:#fff;">
                        <i class="ph ph-printer" style="font-size:1.2rem;"></i> Imprimir Remito
                    </button>
                </div>
            `;

            let ticketModal = document.getElementById('delivery-ticket-modal');
            if (!ticketModal) {
                ticketModal = document.createElement('div');
                ticketModal.id = 'delivery-ticket-modal';
                ticketModal.className = 'modal-overlay';
                ticketModal.style.cssText = 'align-items:center; justify-content:center; display:flex; z-index:20000; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); padding:20px; box-sizing:border-box;';
                ticketModal.innerHTML = `
                    <div class="modal-content" style="width: 400px; max-width: 100%; background:#fffdfc; padding:25px; border-radius:0 !important; border:none; border-top:1px solid #e0e0e0; box-shadow:0 10px 30px rgba(0,0,0,0.25); position:relative; font-family:\'Courier New\', Courier, \'Lucida Sans Typewriter\', monospace; overflow:visible; display:flex; flex-direction:column; box-sizing:border-box;">
                        <style>
                            .ticket-btn {
                                border: 2px solid #1b1b1b !important;
                                border-radius: 8px !important;
                                min-height: 40px !important;
                                display: flex !important;
                                align-items: center !important;
                                justify-content: center !important;
                                gap: 6px !important;
                                font-weight: bold !important;
                                font-size: 0.9rem !important;
                                cursor: pointer !important;
                                transition: all 0.1s ease !important;
                                box-shadow: none !important;
                                font-family: 'Satoshi', sans-serif !important;
                                transform: translate(0, 0) !important;
                                background-color: white;
                                box-sizing: border-box !important;
                                padding: 5px 10px !important;
                                white-space: nowrap !important;
                                overflow: hidden !important;
                            }
                            .ticket-btn:hover {
                                transform: translate(-2px, -2px) !important;
                                box-shadow: 2px 2px 0px #1b1b1b !important;
                            }
                        </style>
                        <div class="modal-header" style="display:flex; justify-content:space-between; align-items:center; border-bottom:2px dashed #000; padding-bottom:12px; margin-bottom:15px; background:transparent;">
                            <h3 style="margin:0; font-family:\'Courier New\', Courier, \'Lucida Sans Typewriter\', monospace; font-weight:800; text-transform:uppercase; letter-spacing:1px; font-size:1.2rem; color:#000;">Ticket de Envío</h3>
                            <button onclick="window.closeDeliveryTicket()" style="background:none; border:none; font-size:1.5rem; cursor:pointer; color:#999; line-height:1; padding:0;">&times;</button>
                        </div>
                        <div class="modal-body ticket-scroll" id="delivery-ticket-body" style="background:#fffdfc; overflow-y:auto; max-height:calc(100vh - 220px); box-sizing:border-box; padding: 4px;"></div>
                        <div style="content:\'\'; position:absolute; left:0; bottom:-14px; width:100%; height:15px; background-image:url(\'data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 20 15%22%3E%3Cpolygon points=%220,0 10,15 20,0%22 fill=%22%23fffdfc%22/%3E%3C/svg%3E\'); background-size:20px 15px; background-repeat:repeat-x; z-index:10; display:block;"></div>
                    </div>
                `;
                document.body.appendChild(ticketModal);
            } else {
                ticketModal.style.display = 'flex';
                ticketModal.classList.remove('hidden');
            }

            document.getElementById('delivery-ticket-body').innerHTML = ticketHTML;

            window.closeDeliveryTicket = () => {
                ticketModal.style.display = 'none';
                ticketModal.classList.add('hidden');
            };

            window.contactDeliveryWhatsApp = (phone) => {
                let cleaned = phone ? phone.replace(/\D/g, '') : '';
                if (cleaned && !cleaned.startsWith('54') && cleaned.length >= 10) {
                    if (cleaned.length === 10) {
                        cleaned = '549' + cleaned;
                    } else if (cleaned.length === 11 && cleaned.startsWith('15')) {
                        cleaned = '549' + cleaned.substring(2);
                    }
                }
                const link = `https://wa.me/${cleaned}`;
                window.open(link, '_blank');
            };

            window.shareDeliveryWhatsApp = (code, addr, phone) => {
                let cleaned = phone ? phone.replace(/\D/g, '') : '';
                if (cleaned && !cleaned.startsWith('54') && cleaned.length >= 10) {
                    if (cleaned.length === 10) {
                        cleaned = '549' + cleaned;
                    } else if (cleaned.length === 11 && cleaned.startsWith('15')) {
                        cleaned = '549' + cleaned.substring(2);
                    }
                }
                const text = `Hola! Te notificamos que hemos recibido satisfactoriamente tu pedido y el envío está en gestión.\nTu envío con código *${code}* está registrado con la dirección: _${decodeURIComponent(addr)}_.`;
                const link = `https://wa.me/${cleaned}?text=${encodeURIComponent(text)}`;
                window.open(link, '_blank');
            };

            window.sendDeliveryEmail = async (deliveryId, currentEmail) => {
                const d = window.deliveriesModuleInstance.allDeliveries.find(x => x.id === deliveryId);
                if (!d) {
                    pop_ups.error("No se encontró el envío.");
                    return;
                }

                // Close the ticket modal first so the prompt is not hidden behind it
                window.closeDeliveryTicket();

                const email = await pop_ups.prompt(
                    'Enviar Ticket de Envío',
                    'Escribí la dirección de correo electrónico del cliente para enviarle el ticket:',
                    'ejemplo@correo.com',
                    currentEmail || ''
                );

                if (email) {
                    pop_ups.info('Enviando correo...');

                    try {
                        const emailContent = `
                            <div style="font-family:'Courier New', Courier, monospace; max-width:400px; margin:0 auto; padding:20px; background:#fffdfc; border:1px solid #ddd; box-sizing:border-box;">
                                <div style="text-align:center; margin-bottom:20px; border-bottom:2px dashed #000; padding-bottom:15px;">
                                    <h2 style="margin:0; font-size:1.3rem; font-weight:900; text-transform:uppercase; letter-spacing:1px; color:#000;">TICKET DE ENVÍO</h2>
                                    <div style="font-size:1.1rem; font-weight:bold; margin-top:5px; color:#1b1b1b;">CÓDIGO: ${d.ticket_code}</div>
                                    <div style="font-size:0.85rem; margin-top:5px; color:#555;">${new Date(d.created_at).toLocaleString()}</div>
                                </div>
                                <div style="margin-bottom:12px; border-bottom:1px dotted #ccc; padding-bottom:8px;">
                                    <span style="font-size:0.75rem; text-transform:uppercase; color:#666; font-weight:bold; display:block;">CLIENTE:</span>
                                    <span style="font-size:1rem; font-weight:bold; color:#000;">${d.customer_name || 'Consumidor Final'}</span>
                                </div>
                                <div style="margin-bottom:12px; border-bottom:1px dotted #ccc; padding-bottom:8px;">
                                    <span style="font-size:0.75rem; text-transform:uppercase; color:#666; font-weight:bold; display:block;">DIRECCIÓN DE ENTREGA:</span>
                                    <span style="font-size:1rem; font-weight:bold; color:#000; line-height:1.4;">${d.address}</span>
                                </div>
                                <div style="margin-bottom:12px; border-bottom:1px dotted #ccc; padding-bottom:8px;">
                                    <span style="font-size:0.75rem; text-transform:uppercase; color:#666; font-weight:bold; display:block;">REPARTIDOR ASIGNADO:</span>
                                    <span style="font-size:1rem; font-weight:bold; color:#000;">${d.collaborator_name || 'No asignado'}</span>
                                </div>
                                <div style="margin-bottom:12px; border-bottom:1px dotted #ccc; padding-bottom:8px;">
                                    <span style="font-size:0.75rem; text-transform:uppercase; color:#666; font-weight:bold; display:block;">VENTA RELACIONADA:</span>
                                    <span style="font-size:0.95rem; font-weight:bold; color:#333;">Fecha/Hora: ${new Date(d.sale_date || d.created_at).toLocaleString()}</span><br>
                                    <span style="font-size:0.95rem; font-weight:bold; color:#333;">Cliente: ${d.customer_name || 'Consumidor Final'}</span><br>
                                    <span style="font-size:0.95rem; font-weight:bold; color:#2e7d32;">Monto Total: $${parseFloat(d.sale_total || 0).toLocaleString()}</span>
                                </div>
                                <div style="text-align:center; margin-top:20px; font-weight:bold; border-top:2px dashed #000; padding-top:15px; color:#000;">
                                    ¡GRACIAS POR SU COMPRA!
                                </div>
                            </div>
                        `;

                        const response = await fetch('/api/sales/send-email.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                emailInfo: {
                                    to: email,
                                    subject: `Ticket de Envío ${d.ticket_code} - Stockify`,
                                    from: 'no_reply@stockify.com.ar',
                                    fromName: 'Stockify',
                                    html: emailContent
                                }
                            })
                        });

                        const resData = await response.json();
                        if (resData.success) {
                            pop_ups.success(`El ticket fue enviado correctamente a ${email}`, '¡Enviado!');
                        } else {
                            throw new Error(resData.error || 'Error al enviar');
                        }
                    } catch (err) {
                        pop_ups.error(err.message, 'Error');
                    }
                }
            };

            window.printDeliveryRemito = async (deliveryId) => {
                const d = window.deliveriesModuleInstance.allDeliveries.find(x => x.id === deliveryId);
                if (!d) return;

                let saleItems = [];
                try {
                    const saleRes = await fetch(`/api/sales/get-details.php?id=${d.sale_id}`);
                    const saleData = await saleRes.json();
                    if (saleData && saleData.success && saleData.sale && saleData.sale.items) {
                        saleItems = saleData.sale.items;
                    }
                } catch (e) { console.error("Error fetching sale items for remito:", e); }

                let itemsHtml = '';
                if (saleItems.length > 0) {
                    itemsHtml = saleItems.map(item => `
                        <tr>
                            <td>${item.product_code || item.product_id}</td>
                            <td>${item.product_name} x${item.quantity}</td>
                            <td class="text-right">$${parseFloat(item.subtotal || 0).toLocaleString('es-AR', { minimumFractionDigits: 2 })}</td>
                        </tr>
                    `).join('');
                } else {
                    itemsHtml = `
                        <tr>
                            <td>${d.ticket_code}</td>
                            <td>Envío asociado a Venta del ${new Date(d.sale_date || d.created_at).toLocaleString('es-AR')}</td>
                            <td class="text-right">$${parseFloat(d.sale_total || 0).toLocaleString('es-AR', { minimumFractionDigits: 2 })}</td>
                        </tr>
                    `;
                }

                const win = window.open('', '_blank');
                win.document.write(`
                    <html>
                    <head>
                        <title>Remito - ${d.ticket_code}</title>
                        <style>
                            @page { margin: 10mm; size: A4 portrait; }
                            body { font-family: 'Arial', sans-serif; padding: 0; margin: 0; color: #000; font-size: 13px; }
                            .remito-container { max-width: 100%; border: 1px solid #000; box-sizing: border-box; }
                            
                            /* Header Area */
                            .header { display: flex; border-bottom: 1px solid #000; }
                            .header-left { flex: 1; padding: 20px; text-align: center; }
                            .header-middle { width: 60px; border-left: 1px solid #000; border-right: 1px solid #000; display: flex; flex-direction: column; align-items: center; justify-content: flex-start; padding-top: 10px; }
                            .header-right { flex: 1; padding: 20px 20px 20px 30px; }
                            
                            .logo-img { max-width: 180px; height: auto; margin-bottom: 15px; }
                            
                            /* The "X" Box */
                            .doc-letter-box { width: 40px; height: 40px; border: 1px solid #000; display: flex; align-items: center; justify-content: center; font-size: 28px; font-weight: bold; margin-bottom: 5px; }
                            .doc-code { font-size: 10px; font-weight: bold; }
                            
                            .header-title { font-size: 24px; font-weight: 900; letter-spacing: 2px; margin-bottom: 15px; text-transform: uppercase; }
                            .header-info { font-size: 13px; margin-bottom: 5px; font-weight: bold; }
                            
                            .company-info { font-size: 12px; line-height: 1.4; text-align: left; }
                            
                            /* Customer Area */
                            .customer-area { border-bottom: 1px solid #000; padding: 15px 20px; background: #fafafa; display: flex; flex-wrap: wrap; }
                            .customer-row { width: 100%; display: flex; margin-bottom: 8px; }
                            .customer-col { flex: 1; }
                            .c-label { font-weight: bold; font-size: 12px; }
                            
                            /* Table */
                            .details-table { width: 100%; border-collapse: collapse; }
                            .details-table th { border-bottom: 1px solid #000; border-right: 1px solid #000; text-align: left; padding: 10px; font-size: 12px; background: #eee; }
                            .details-table th:last-child { border-right: none; }
                            .details-table td { border-right: 1px solid #000; padding: 10px; font-size: 13px; vertical-align: top; }
                            .details-table td:last-child { border-right: none; }
                            .table-container { min-height: 250px; }
                            
                            /* Footer / Signatures */
                            .footer-area { border-top: 1px solid #000; display: flex; padding: 0; }
                            .footer-box { flex: 1; padding: 20px; border-right: 1px solid #000; }
                            .footer-box:last-child { border-right: none; }
                            
                            .signature-line { margin-top: 60px; border-top: 1px dashed #000; text-align: center; padding-top: 5px; font-weight: bold; width: 80%; margin-left: auto; margin-right: auto; }
                            
                            .text-center { text-align: center; }
                            .text-right { text-align: right; }
                        </style>
                    </head>
                    <body onload="setTimeout(function(){ window.print(); window.close(); }, 500);">
                        <div class="remito-container">
                            
                            <div class="header">
                                <div class="header-left">
                                    ${d.remito_logo_path ? `<img src="${d.remito_logo_path}" alt="Logo" class="logo-img">` : ''}
                                    <div class="company-info">
                                        <b>Comprobante no válido como factura.</b><br><br>
                                        ${d.remito_description ? `${d.remito_description}<br>` : ''}
                                        ${d.remito_url ? `${d.remito_url}` : ''}
                                    </div>
                                </div>
                                <div class="header-middle">
                                    <div class="doc-letter-box">X</div>
                                    <div class="doc-code">COD. 00</div>
                                </div>
                                <div class="header-right">
                                    <div class="header-title">REMITO</div>
                                    <div class="header-info">Nº ${d.ticket_code}</div>
                                    <div class="header-info">FECHA: ${new Date(d.created_at).toLocaleDateString('es-AR')}</div>
                                    <div style="margin-top:20px;" class="company-info">
                                        <b>C.U.I.T.:</b> 00-00000000-0<br>
                                        <b>Ingresos Brutos:</b> 00-00000000-0<br>
                                        <b>Inicio de Actividades:</b> 01/01/2024
                                    </div>
                                </div>
                            </div>
                            
                            <div class="customer-area">
                                <div class="customer-row">
                                    <div class="customer-col">
                                        <span class="c-label">Señor(es):</span> ${d.customer_name || 'Consumidor Final'}
                                    </div>
                                    <div class="customer-col">
                                        <span class="c-label">Teléfono:</span> ${d.customer_phone || '-'}
                                    </div>
                                </div>
                                <div class="customer-row">
                                    <div class="customer-col">
                                        <span class="c-label">Domicilio:</span> ${d.address}
                                    </div>
                                </div>
                                <div class="customer-row">
                                    <div class="customer-col">
                                        <span class="c-label">Repartidor Asignado:</span> ${d.collaborator_name || 'No asignado'}
                                    </div>
                                    <div class="customer-col">
                                        <span class="c-label">Venta Asoc.:</span> Fecha ${new Date(d.sale_date || d.created_at).toLocaleString('es-AR')}
                                    </div>
                                </div>
                            </div>
                            
                            <div class="table-container">
                                <table class="details-table">
                                    <thead>
                                        <tr>
                                            <th style="width:15%">CÓDIGO</th>
                                            <th style="width:65%">DESCRIPCIÓN</th>
                                            <th style="width:20%" class="text-right">VALOR DECLARADO</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${itemsHtml}
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="footer-area">
                                <div class="footer-box">
                                    <div style="font-size:11px; color:#555; text-align:center;">
                                        Firma del Repartidor:<br>
                                        Certifica que la mercadería fue entregada en perfectas condiciones.
                                    </div>
                                    <div class="signature-line">Aclaración y Firma</div>
                                </div>
                                <div class="footer-box">
                                    <div style="font-size:11px; color:#555; text-align:center;">
                                        Firma del Cliente:<br>
                                        Recibí Conforme.
                                    </div>
                                    <div class="signature-line">Aclaración y Firma</div>
                                </div>
                            </div>
                            
                        </div>
                    </body>
                    </html>
                `);
                win.document.close();
            };
        } catch (e) {
            pop_ups.error("Error al generar ticket");
        }
    }
}

export const deliveriesModuleInstance = new DeliveriesModule();
window.deliveriesModuleInstance = deliveriesModuleInstance;
