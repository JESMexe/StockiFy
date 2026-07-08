import { pop_ups } from '../notifications/pop-up.js?v=3.0';

// Secciones configurables del dashboard por el Owner
const CONFIGURABLE_SECTIONS = [
    { key: 'can_view_data',          label: 'Ver Datos',            icon: 'ph-table' },
    { key: 'can_view_analytics',     label: 'Analíticas',           icon: 'ph-chart-line' },
    { key: 'can_view_history',       label: 'Historial',            icon: 'ph-clock-counter-clockwise' },
    { key: 'can_view_config',        label: 'Configurar Tabla',     icon: 'ph-gear' },
    { key: 'can_view_customers',     label: 'Clientes',             icon: 'ph-user-focus' },
    { key: 'can_view_providers',     label: 'Proveedores',          icon: 'ph-van' },
    { key: 'can_view_employees',     label: 'Trabajadores',         icon: 'ph-identification-badge' },
    { key: 'can_view_payments',      label: 'Métodos de Pago',      icon: 'ph-wallet' },
    { key: 'can_view_notifications', label: 'Notificaciones',       icon: 'ph-bell' },
    { key: 'can_view_deliveries',    label: 'Envíos',               icon: 'ph-truck' },
    { key: 'can_view_sales',         label: 'Registrar Ingreso',    icon: 'ph-money' },
    { key: 'can_view_receipts',      label: 'Registrar Egreso',     icon: 'ph-stack' },
];

export const usersModuleInstance = {
    isOwner: false,
    currentSettings: {},
    categoriesSettings: {}, // Para guardar permisos de categorías

    async init() {
        await this.detectRole();
        this.setupEvents();
        this.loadCollaborators();

        if (this.isOwner) {
            this.showPermissionsPanel();
            this.loadPermissions();
            await this.loadQuota(); // Cargar y renderizar cupos del plan
            await this.loadPendingDebts(); // Cargar y renderizar deudas pendientes
        }
    },

    /**
     * Consulta el rol del usuario actual para este inventario.
     * Esconde el botón de invitar si no es Owner ni Admin.
     */
    async detectRole() {
        await this.applyStartupRestrictions();
    },

    /**
     * Aplica restricciones de visibilidad en el sidebar para colaboradores.
     */
    applyRoleRestrictions(permissions) {
        const sectionMap = {
            'can_view_data':          'view-db',
            'can_view_analytics':     'analysis',
            'can_view_history':       'history-log',
            'can_view_config':        'config-db',
            'can_view_customers':     'customers',
            'can_view_providers':     'providers',
            'can_view_employees':     'employees',
            'can_view_payments':      'payments',
            'can_view_notifications': 'notifications',
            'can_view_deliveries':    'deliveries',
            'can_view_sales':         'sales',
            'can_view_receipts':      'receipts',
        };

        Object.entries(sectionMap).forEach(([permKey, viewId]) => {
            // Ocultar si explícitamente está en false (ausente = permitido)
            if (permissions[permKey] === false) {
                const btn = document.querySelector(`[data-target-view="${viewId}"]`);
                btn?.closest('li')?.classList.add('hidden');
            }
        });

        // Ocultar tarjetas de la versión Mobile basadas en los permisos del colaborador
        const mobileCardMap = {
            'can_view_data':          '.action-check',
            'can_view_analytics':     '.action-metrics, .action-balance',
            'can_view_history':       '.action-history',
            'can_view_customers':     '.action-customers',
            'can_view_providers':     '.action-providers',
            'can_view_employees':     '.action-employees',
            'can_view_deliveries':    '.action-deliveries',
            'can_view_sales':         '.action-sale',
            'can_view_receipts':      '.action-purchase, .action-expense',
        };
        Object.entries(mobileCardMap).forEach(([permKey, selector]) => {
            if (permissions[permKey] === false) {
                document.querySelectorAll(selector).forEach(card => {
                    card.classList.add('hidden');
                });
            }
        });

        // El botón "Colaboradores" solo es visible para el Owner
        const colabBtn = document.querySelector('[data-target-view="users-manage"]');
        colabBtn?.closest('li')?.classList.add('hidden');
        document.querySelector('.action-collaborators')?.classList.add('hidden');
    },

    /**
     * Detecta el rol y aplica restricciones al sidebar.
     * Llamado en el arranque del dashboard para que el sidebar ya esté correcto
     * antes de que el usuario pueda interactuar.
     */
    async applyStartupRestrictions() {
        try {
            const res  = await fetch('/api/users/get-role-settings.php');
            const data = await res.json();
            if (!data.success) return;

            this.isOwner = data.mode === 'owner';

            if (data.mode === 'owner') {
                window.__rbacPermissions = null;
                // Mostrar y cargar panel de horario laboral
                const workPanel = document.getElementById('work-hours-panel');
                if (workPanel) {
                    workPanel.classList.remove('hidden');
                    const workHours = data.work_hours || { enabled: 0, start: '08:00', end: '20:00' };
                    const enabledCheck = document.getElementById('work-hours-enabled');
                    const startInput = document.getElementById('work-hours-start');
                    const endInput = document.getElementById('work-hours-end');
                    if (enabledCheck) enabledCheck.checked = !!workHours.enabled;
                    if (startInput) startInput.value = workHours.start;
                    if (endInput) endInput.value = workHours.end;
                }
            } else if (data.mode === 'collaborator') {
                window.__rbacPermissions = data.permissions ?? {};
                this.applyRoleRestrictions(data.permissions);
                // Ocultar botón de invitar si es Employee
                if (data.role_id === 3) {
                    document.getElementById('invite-collaborator-btn')?.classList.add('hidden');
                }
            }
        } catch (e) {
            console.error('Error al aplicar restricciones de inicio:', e);
        }
    },

    async loadCollaborators() {
        const container = document.getElementById('collaborators-list-container');
        if (!container) return;

        container.innerHTML = '<p style="color: #666;"><i class="ph ph-spinner ph-spin"></i> Cargando colaboradores...</p>';

        try {
            const response = await fetch('/api/users/list.php');
            const result   = await response.json();

            if (result.success) {
                this.renderList(result.collaborators);
            } else {
                container.innerHTML = `<p style="color: var(--accent-red);">${result.message}</p>`;
            }
        } catch (error) {
            container.innerHTML = `<p style="color: var(--accent-red);">Error de conexión.</p>`;
        }
    },

    /**
     * Carga la cuota de colaboradores desde el backend y actualiza la UI:
     * - Badge de cupos junto al título
     * - Estado del botón de invitación (habilitado / deshabilitado / bloqueado)
     */
    async loadQuota() {
        try {
            const res  = await fetch('/api/users/get-collaborator-quota.php');
            const data = await res.json();
            if (!data.success) return;

            this.renderQuotaBadge(data);
            this.applyQuotaToInviteButton(data);

            // Mostrar botón "Agregar Slots" solo para planes Profesional (2) y Vitalicio (4)
            const addSlotsBtn = document.getElementById('add-slots-btn');
            if (addSlotsBtn) {
                const planInt = parseInt(data.plan);
                if (planInt === 2 || planInt === 4) {
                    addSlotsBtn.classList.remove('hidden');
                    if (data.has_pending_debt || data.has_expired_debt) {
                        addSlotsBtn.disabled = true;
                        addSlotsBtn.style.opacity = '0.5';
                        addSlotsBtn.style.cursor = 'not-allowed';
                        if (data.has_expired_debt) {
                            addSlotsBtn.title = "Tu deuda anterior expiró sin saldarse. Contactá a soporte para habilitar esta opción.";
                        } else {
                            addSlotsBtn.title = "Tenés una deuda de slots pendiente. Saldala para poder agregar más.";
                        }
                    } else {
                        addSlotsBtn.disabled = false;
                        addSlotsBtn.style.opacity = '';
                        addSlotsBtn.style.cursor = '';
                        addSlotsBtn.title = "Agregar slots de colaboradores adicionales";
                    }
                } else {
                    addSlotsBtn.classList.add('hidden');
                }
            }
        } catch (e) {
            console.warn('[Quota] No se pudo cargar la cuota de colaboradores:', e.message);
        }
    },

    async loadPendingDebts() {
        try {
            const resp = await fetch('/api/collaborators/get-pending-debts.php');
            if (!resp.ok) return;
            const res = await resp.json();
            
            const banner = document.getElementById('debt-warning-banner');
            if (res.success && res.debts && res.debts.length > 0) {
                if (banner) {
                    banner.dataset.hasDebt = 'true';
                    const activeTab = document.querySelector('.mobile-collab-tab-btn.active')?.getAttribute('onclick') || '';
                    const isMobile = window.innerWidth <= 768;
                    if (isMobile && activeTab.includes('permissions')) {
                        banner.classList.add('hidden');
                    } else {
                        banner.classList.remove('hidden');
                    }
                    const amountSpan = document.getElementById('debt-warning-text');
                    const payBtn = document.getElementById('pay-debt-btn');
                    const totalDebt = res.debts.reduce((sum, d) => sum + (parseFloat(d.price_per_slot) * parseInt(d.slots_added)), 0);
                    
                    if (amountSpan) {
                        amountSpan.innerText = `Tenés una deuda pendiente de $${totalDebt.toLocaleString('es-AR')} por slots agregados. Plazo restante para saldar: 48 horas o tus colaboradores serán eliminados.`;
                    }
                    
                    if (payBtn && res.debts[0]) {
                        payBtn.dataset.debtId = res.debts[0].id;
                        payBtn.dataset.amount = totalDebt;
                    }
                }
            } else {
                if (banner) {
                    banner.dataset.hasDebt = 'false';
                    banner.classList.add('hidden');
                }
            }
            
            // Definir función global para saldar la deuda
            window.handleDebtPayment = function() {
                const payBtn = document.getElementById('pay-debt-btn');
                if (!payBtn) return;
                const debtId = parseInt(payBtn.dataset.debtId || 0);
                const amount = parseFloat(payBtn.dataset.amount || 0);
                
                if (!debtId || !amount) {
                    pop_ups.error("No se pudo identificar la deuda pendiente.");
                    return;
                }
                
                if (window.paymentsModule) {
                    window.paymentsModule.openSlotsCheckout(debtId, 0, amount);
                } else {
                    pop_ups.error("El módulo de pagos no está inicializado.");
                }
            };
        } catch (e) {
            console.error("Error loading pending debts:", e);
        }
    },

    /**
     * Renderiza el badge de cupos junto al título de la sección o el placeholder móvil.
     */
    renderQuotaBadge(quota) {
        // Limpiar badge anterior si existe
        document.getElementById('collab-quota-badge')?.remove();

        const isMobile = window.innerWidth <= 768;
        let titleEl = isMobile ? document.getElementById('collab-quota-placeholder-mobile') : null;
        if (!titleEl) {
            titleEl = document.querySelector('#users-manage h2');
        }
        if (!titleEl) return;

        let badgeHtml;

        if (quota.locked) {
            // Plan Básico: sin colaboradores
            badgeHtml = `
                <span id="collab-quota-badge" style="
                    display: inline-flex; align-items: center; gap: 5px;
                    background: #f1f5f9; color: #94a3b8;
                    padding: 6px 12px; border-radius: 20px;
                    font-size: 0.8rem; font-weight: 600;
                    border: 1px solid #e2e8f0; margin-left: ${isMobile ? '0' : '10px'};
                ">
                    <i class="ph ph-lock"></i> Solo uso personal — Plan ${quota.plan_name}
                </span>`;
        } else if (quota.max === null) {
            // Plan Vitalicio: ilimitado
            badgeHtml = `
                <span id="collab-quota-badge" style="
                    display: inline-flex; align-items: center; gap: 5px;
                    background: color-mix(in srgb, var(--accent-color) 12%, transparent);
                    color: var(--accent-color);
                    padding: 6px 12px; border-radius: 20px;
                    font-size: 0.8rem; font-weight: 600;
                    border: 1px solid color-mix(in srgb, var(--accent-color) 30%, transparent);
                    margin-left: ${isMobile ? '0' : '10px'};
                ">
                    <i class="ph ph-infinity"></i> Ilimitado — Plan ${quota.plan_name}
                </span>`;
        } else {
            // Planes con límite numérico
            const isAtLimit = quota.remaining === 0;
            const color     = isAtLimit ? 'var(--accent-red)' : 'var(--accent-color)';
            const bgColor   = isAtLimit
                ? 'color-mix(in srgb, var(--accent-red) 12%, transparent)'
                : 'color-mix(in srgb, var(--accent-color) 12%, transparent)';
            const borderColor = isAtLimit
                ? 'color-mix(in srgb, var(--accent-red) 30%, transparent)'
                : 'color-mix(in srgb, var(--accent-color) 30%, transparent)';

            badgeHtml = `
                <span id="collab-quota-badge" style="
                    display: inline-flex; align-items: center; gap: 5px;
                    background: ${bgColor}; color: ${color};
                    padding: 6px 12px; border-radius: 20px;
                    font-size: 0.8rem; font-weight: 600;
                    border: 1px solid ${borderColor}; margin-left: ${isMobile ? '0' : '10px'};
                ">
                    <i class="ph ph-users"></i>
                    ${quota.used}/${quota.max} colaboradores — Plan ${quota.plan_name}
                </span>`;
        }

        if (isMobile) {
            titleEl.style.display = 'flex';
            titleEl.innerHTML = badgeHtml;
        } else {
            titleEl.insertAdjacentHTML('beforeend', badgeHtml);
        }
    },

    /**
     * Habilita o deshabilita el botón de invitación según la cuota disponible.
     */
    applyQuotaToInviteButton(quota) {
        const btn = document.getElementById('invite-collaborator-btn');
        if (!btn) return;

        if (quota.locked) {
            // Plan Básico: bloquear completamente con candado
            btn.disabled = true;
            btn.innerHTML = '<i class="ph ph-lock"></i> Colaboradores bloqueados';
            btn.style.opacity   = '0.55';
            btn.style.cursor    = 'not-allowed';
            btn.title = `El plan ${quota.plan_name} no permite agregar colaboradores.`;
        } else if (!quota.allowed) {
            // Sin cupos restantes
            btn.disabled = true;
            btn.innerHTML = `<i class="ph ph-users"></i> Sin cupos disponibles (${quota.used}/${quota.max})`;
            btn.style.opacity = '0.6';
            btn.style.cursor  = 'not-allowed';
            btn.title = `Alcanzaste el límite de colaboradores de tu plan. Contactá a soporte para ampliar.`;
        } else {
            // Cupos disponibles — asegurarse de que el botón esté activo
            btn.disabled = false;
            btn.innerHTML = '<i class="ph ph-user-plus"></i> Nueva Invitación';
            btn.style.opacity = '';
            btn.style.cursor  = '';
            btn.title = quota.max !== null
                ? `Cupos disponibles: ${quota.remaining} de ${quota.max}`
                : 'Plan ilimitado';
        }
    },

    renderList(collaborators) {
        const container = document.getElementById('collaborators-list-container');
        if (!container) return;

        if (!collaborators || collaborators.length === 0) {
            container.innerHTML = '<p style="color: #666;">No hay colaboradores en este inventario. ¡Sos el único!</p>';
            return;
        }

        // --- VISTA DESKTOP ---
        let desktopHtml = `
            <div class="collaborators-desktop-view">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="border-bottom: 2px solid #eee; background: #fafafa;">
                            <th style="text-align: left; padding: 16px 20px; font-size: 0.8rem; text-transform: uppercase; color: #888;">Usuario</th>
                            <th style="text-align: left; padding: 16px 20px; font-size: 0.8rem; text-transform: uppercase; color: #888;">Email</th>
                            <th style="text-align: left; padding: 16px 20px; font-size: 0.8rem; text-transform: uppercase; color: #888;">Rol</th>
                            <th style="text-align: left; padding: 16px 20px; font-size: 0.8rem; text-transform: uppercase; color: #888;">Estado</th>
                            ${this.isOwner ? '<th style="text-align: center; padding: 16px 20px; font-size: 0.8rem; text-transform: uppercase; color: #888;">Acciones</th>' : ''}
                        </tr>
                    </thead>
                    <tbody>
        `;

        // --- VISTA MOBILE ---
        let mobileHtml = `
            <div class="collaborators-mobile-view" style="display: none; flex-direction: column; gap: 12px; padding: 10px 0;">
        `;

        collaborators.forEach(c => {
            let roleBadge;
            let roleBadgeMobile;
            if (c.role_name === 'Owner') {
                roleBadge = `<span style="background: var(--accent-green-20); color: var(--accent-green); padding: 3px 8px; border-radius: 4px; font-weight: 900; font-size: 0.75rem; text-transform: uppercase;">Propietario</span>`;
                roleBadgeMobile = `<span style="background: var(--accent-green-20); color: var(--accent-green); padding: 4px 10px; border-radius: 6px; font-weight: 900; font-size: 0.7rem; text-transform: uppercase;">Propietario</span>`;
            } else if (c.role_name === 'Admin') {
                roleBadge = `<span style="background: color-mix(in srgb, var(--accent-color) 15%, transparent); color: var(--accent-color); padding: 3px 8px; border-radius: 4px; font-weight: 900; font-size: 0.75rem; text-transform: uppercase;">Administrador</span>`;
                roleBadgeMobile = `<span style="background: color-mix(in srgb, var(--accent-color) 15%, transparent); color: var(--accent-color); padding: 4px 10px; border-radius: 6px; font-weight: 900; font-size: 0.7rem; text-transform: uppercase;">Admin</span>`;
            } else {
                roleBadge = `<span style="background: #f1f5f9; color: #475569; padding: 3px 8px; border-radius: 4px; font-weight: 900; font-size: 0.75rem; text-transform: uppercase;">Empleado</span>`;
                roleBadgeMobile = `<span style="background: #f1f5f9; color: #475569; padding: 4px 10px; border-radius: 6px; font-weight: 900; font-size: 0.7rem; text-transform: uppercase;">Empleado</span>`;
            }

            const isActive = c.status === 'active';
            const canDelete = this.isOwner && c.role_name !== 'Owner';

            // Desktop Row
            desktopHtml += `
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 16px 20px;"><strong>${c.full_name || c.username}</strong></td>
                    <td style="padding: 16px 20px; font-size: 0.9rem; color: #555;">${c.email}</td>
                    <td style="padding: 16px 20px;">${roleBadge}</td>
                    <td style="padding: 16px 20px;">
                        <span style="font-size: 0.85rem; ${isActive ? 'color: var(--accent-green);' : 'color: #888;'}">
                            ${isActive ? '● Activo' : '● Pendiente'}
                        </span>
                    </td>
                    ${this.isOwner ? `<td style="padding: 16px 20px; text-align: center;">
                        ${canDelete
                            ? `<button class="btn btn-secondary" style="color: var(--accent-red); border-color: var(--accent-red); padding: 5px 10px; width: auto; margin: 0;" onclick="window.usersModuleInstance.removeCollaborator(${c.collaborator_id})">
                                <i class="ph ph-trash"></i>
                               </button>`
                            : '<span style="color: #ccc; font-size: 0.85rem;">—</span>'
                        }
                    </td>` : ''}
                </tr>
            `;

            // Mobile Card
            mobileHtml += `
                <div style="border: 2px solid #1b1b1b; border-radius: 12px; padding: 14px; background: white; box-shadow: 3px 3px 0 #1b1b1b; display: flex; flex-direction: column; gap: 8px; position: relative;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; gap: 8px;">
                        <div style="min-width: 0;">
                            <div style="font-weight: bold; font-size: 1rem; color: #1b1b1b; display: flex; align-items: center; gap: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                <i class="ph ph-user-circle" style="font-size: 1.25rem; color: var(--accent-color); flex-shrink: 0;"></i>
                                <span style="overflow: hidden; text-overflow: ellipsis;">${c.full_name || c.username}</span>
                            </div>
                            <div style="font-size: 0.8rem; color: #666; margin-top: 4px; display: flex; align-items: center; gap: 6px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                <i class="ph ph-envelope" style="flex-shrink: 0;"></i>
                                <span style="overflow: hidden; text-overflow: ellipsis;">${c.email}</span>
                            </div>
                        </div>
                        <div style="flex-shrink: 0;">
                            ${roleBadgeMobile}
                        </div>
                    </div>
                    
                    <div style="border-top: 1px dashed #eee; padding-top: 8px; display: flex; justify-content: space-between; align-items: center; margin-top: 2px;">
                        <span style="font-size: 0.8rem; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; ${isActive ? 'color: var(--accent-green);' : 'color: #888;'}">
                            <span style="display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: ${isActive ? 'var(--accent-green)' : '#888'};"></span>
                            ${isActive ? 'Activo' : 'Pendiente'}
                        </span>
                        ${this.isOwner && canDelete ? `
                            <button class="btn btn-secondary" style="color: var(--accent-red); border-color: var(--accent-red); padding: 4px 8px; font-size: 0.75rem; width: auto; margin: 0; display: inline-flex; align-items: center; gap: 4px;" onclick="window.usersModuleInstance.removeCollaborator(${c.collaborator_id})">
                                <i class="ph ph-trash"></i> Eliminar
                            </button>
                        ` : ''}
                    </div>
                </div>
            `;
        });

        desktopHtml += `</tbody></table></div>`;
        mobileHtml += `</div>`;

        container.innerHTML = desktopHtml + mobileHtml;
    },

    async removeCollaborator(id) {
        const confirmed = await pop_ups.confirm("Revocar acceso", "¿Seguro que querés eliminar a este colaborador del inventario?");
        if (!confirmed) return;

        try {
            const response = await fetch('/api/users/remove.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ collaborator_id: id })
            });
            const result = await response.json();

            if (result.success) {
                pop_ups.success(result.message);
                this.loadCollaborators();
                if (this.isOwner) this.loadQuota();
            } else {
                pop_ups.error(result.message);
            }
        } catch (e) {
            pop_ups.error("Error al revocar acceso");
        }
    },

    setupEvents() {
        const inviteBtn = document.getElementById('invite-collaborator-btn');
        const modal     = document.getElementById('invite-collaborator-modal');
        const greyBg    = document.getElementById('grey-background');
        const closeBtn  = document.getElementById('close-invite-modal-btn');
        const form      = document.getElementById('invite-collaborator-form');

        if (inviteBtn && modal && greyBg) {
            inviteBtn.onclick = () => {
                Array.from(greyBg.children).forEach(child => child.classList.add('hidden'));
                modal.classList.remove('hidden');
                greyBg.classList.remove('hidden');
                greyBg.style.display        = 'flex';
                greyBg.style.justifyContent = 'center';
                greyBg.style.alignItems     = 'center';
                document.getElementById('invite-email')?.focus();
            };
        }

        if (closeBtn && modal && greyBg) {
            closeBtn.onclick = () => {
                modal.classList.add('hidden');
                greyBg.classList.add('hidden');
                greyBg.style.display = '';
            };
        }

        if (form) {
            form.onsubmit = async (e) => {
                e.preventDefault();
                await this.submitInviteForm();
            };
        }

        // --- Agregar event listeners para "Agregar Slots" ---
        const addSlotsBtn = document.getElementById('add-slots-btn');
        const addSlotsModal = document.getElementById('add-slots-modal');
        const closeAddSlotsBtn = document.getElementById('close-add-slots-modal-btn');
        const addSlotsForm = document.getElementById('add-slots-form');

        if (addSlotsBtn && addSlotsModal && greyBg) {
            addSlotsBtn.onclick = () => {
                if (addSlotsBtn.disabled) return;
                Array.from(greyBg.children).forEach(child => child.classList.add('hidden'));
                addSlotsModal.classList.remove('hidden');
                greyBg.classList.remove('hidden');
                greyBg.style.display        = 'flex';
                greyBg.style.justifyContent = 'center';
                greyBg.style.alignItems     = 'center';
                document.getElementById('slots-count-input')?.focus();
            };
        }

        const slotsCountInput = document.getElementById('slots-count-input');
        const slotsDebtSummary = document.getElementById('slots-debt-summary');
        if (slotsCountInput && slotsDebtSummary) {
            slotsCountInput.addEventListener('input', (e) => {
                const val = parseInt(e.target.value) || 0;
                const price = window.STOCKIFY_SLOT_PRICE || 20000;
                const total = val * price;
                slotsDebtSummary.innerText = '$' + total.toLocaleString('es-AR');
            });
        }

        if (closeAddSlotsBtn && addSlotsModal && greyBg) {
            closeAddSlotsBtn.onclick = () => {
                addSlotsModal.classList.add('hidden');
                greyBg.classList.add('hidden');
                greyBg.style.display = '';
            };
        }

        if (addSlotsForm) {
            addSlotsForm.onsubmit = async (e) => {
                e.preventDefault();
                await this.submitAddSlotsForm();
            };
        }
    },

    async submitInviteForm() {
        const emailInput = document.getElementById('invite-email');
        const roleInput  = document.getElementById('invite-role');
        const submitBtn  = document.getElementById('send-invite-submit-btn');

        const email  = emailInput?.value.trim();
        const roleId = parseInt(roleInput?.value);

        if (!email) return;

        const originalContent = submitBtn.innerHTML;
        submitBtn.disabled  = true;
        if (!document.getElementById('ph-spin-style')) {
            document.head.insertAdjacentHTML('beforeend', '<style id="ph-spin-style">@keyframes phSpin { 100% { transform: rotate(360deg); } } .ph-spin { animation: phSpin 1s linear infinite; display: inline-block; }</style>');
        }
        submitBtn.innerHTML = '<i class="ph-bold ph-spinner ph-spin"></i> Invitando...';

        try {
            const response = await fetch('/api/invitations/send.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ email, role_id: roleId })
            });
            const result = await response.json();

            if (result.success) {
                pop_ups.success(result.message, "¡Listo!");
                document.getElementById('close-invite-modal-btn')?.click();
                if (emailInput) emailInput.value = '';
                this.loadCollaborators();
                if (this.isOwner) this.loadQuota();
            } else if (response.status === 422) {
                // Condición esperada (usuario no registrado, ya colaborador, etc.)
                pop_ups.warning(result.message);
            } else {
                pop_ups.error(result.message, 'Error', response.status);
            }
        } catch (e) {
            pop_ups.error("Error de conexión al enviar la invitación.");
        } finally {
            submitBtn.disabled  = false;
            submitBtn.innerHTML = originalContent;
        }
    },

    async submitAddSlotsForm() {
        const slotsInput = document.getElementById('slots-count-input');
        const submitBtn  = document.getElementById('add-slots-submit-btn');

        const slotsCount = parseInt(slotsInput?.value);
        if (!slotsCount || slotsCount < 1) return;

        const originalContent = submitBtn.innerHTML;
        submitBtn.disabled  = true;
        submitBtn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Procesando...';

        try {
            const response = await fetch('/api/collaborators/add-slots.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ slots_count: slotsCount })
            });
            const result = await response.json();

            if (result.success) {
                pop_ups.success(result.message, "¡Éxito!");
                document.getElementById('close-add-slots-modal-btn')?.click();
                if (slotsInput) slotsInput.value = '';
                // Recargar cuota y deudas pendientes
                if (this.isOwner) {
                    await this.loadQuota();
                    await this.loadPendingDebts();
                }
            } else {
                pop_ups.error(result.message);
            }
        } catch (e) {
            pop_ups.error("Error de conexión al agregar slots.");
        } finally {
            submitBtn.disabled  = false;
            submitBtn.innerHTML = originalContent;
        }
    },

    // ============================================================
    // PANEL DE PERMISOS — Solo visible para el Owner
    // ============================================================

    showPermissionsPanel() {
        const panel = document.getElementById('role-permissions-panel');
        if (panel) panel.classList.remove('hidden');
    },

    async loadPermissions() {
        try {
            const res  = await fetch('/api/users/get-role-settings.php');
            const data = await res.json();
            if (data.success && data.mode === 'owner') {
                this.currentSettings = data.settings;
                
                this.categoriesSettings = {};
                if (data.categories) {
                    data.categories.forEach(cat => {
                        this.categoriesSettings[cat.id] = {
                            name: cat.name,
                            permissions: cat.permissions
                        };
                    });
                }
                
                this.renderPermissionsGrid(data.settings, this.categoriesSettings);
            }
        } catch (e) {
            console.error('Error cargando permisos:', e);
        }
    },

    renderPermissionsGrid(settings, categories) {
        const grid = document.getElementById('permissions-grid');
        if (!grid) return;

        let tabsHtml = `
            <div class="permissions-tabs-container" style="display: flex; gap: 10px; border-bottom: 2px solid #1b1b1b; padding-bottom: 10px; margin-bottom: 1.5rem; flex-wrap: wrap;">
                <button type="button" class="tab-btn active" data-target-tab="role-2" style="font-family: inherit; font-weight: bold; padding: 8px 16px; border: 2px solid #1b1b1b; background: var(--accent-color); color: white; cursor: pointer; border-radius: 8px; box-shadow: none; transform: translate(2px, 2px); transition: all 0.1s;">
                    <i class="ph ph-star"></i> Administrador
                </button>
                <button type="button" class="tab-btn" data-target-tab="role-3" style="font-family: inherit; font-weight: bold; padding: 8px 16px; border: 2px solid #1b1b1b; background: white; color: #1b1b1b; cursor: pointer; border-radius: 8px; box-shadow: 2px 2px 0 #1b1b1b; transform: translate(0px, 0px); transition: all 0.1s;">
                    <i class="ph ph-user"></i> Empleado
                </button>
        `;

        Object.entries(categories).forEach(([catId, cat]) => {
            tabsHtml += `
                <button type="button" class="tab-btn" data-target-tab="cat-${catId}" style="font-family: inherit; font-weight: bold; padding: 8px 16px; border: 2px solid #1b1b1b; background: white; color: #1b1b1b; cursor: pointer; border-radius: 8px; box-shadow: 2px 2px 0 #1b1b1b; transform: translate(0px, 0px); transition: all 0.1s;">
                    <i class="ph ph-identification-badge"></i> ${cat.name}
                </button>
            `;
        });

        tabsHtml += `</div>`;

        let contentHtml = `<div class="permissions-tab-contents">`;

        // 1. Panel Administrador (role 2)
        const adminPerms = settings[2] ?? {};
        contentHtml += `
            <div class="tab-content-panel" id="tab-role-2">
                <h4 style="margin: 0 0 1rem; color: var(--accent-color);"><i class="ph ph-star"></i> Permisos de Administrador</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px;">
                    ${CONFIGURABLE_SECTIONS.map(s => this.renderCheckbox(s, 'role', 2, adminPerms[s.key] !== false)).join('')}
                </div>
            </div>
        `;

        // 2. Panel Empleado (role 3)
        const employeePerms = settings[3] ?? {};
        contentHtml += `
            <div class="tab-content-panel hidden" id="tab-role-3">
                <h4 style="margin: 0 0 1rem; color: #555;"><i class="ph ph-user"></i> Permisos de Empleado (General)</h4>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px;">
                    ${CONFIGURABLE_SECTIONS.map(s => this.renderCheckbox(s, 'role', 3, employeePerms[s.key] !== false)).join('')}
                </div>
            </div>
        `;

        // 3. Paneles de Categorías
        Object.entries(categories).forEach(([catId, cat]) => {
            const catPerms = cat.permissions ?? {};
            contentHtml += `
                <div class="tab-content-panel hidden" id="tab-cat-${catId}">
                    <h4 style="margin: 0 0 1rem; color: #1b1b1b;"><i class="ph ph-identification-badge"></i> Permisos de Categoría: ${cat.name}</h4>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 12px;">
                        ${CONFIGURABLE_SECTIONS.map(s => this.renderCheckbox(s, 'cat', catId, catPerms[s.key] !== false)).join('')}
                    </div>
                </div>
            `;
        });

        contentHtml += `</div>`;

        grid.style.display = 'block';
        grid.innerHTML = tabsHtml + contentHtml;

        const tabBtns = grid.querySelectorAll('.tab-btn');
        tabBtns.forEach(btn => {
            btn.onclick = () => {
                tabBtns.forEach(b => {
                    b.classList.remove('active');
                    b.style.background = 'white';
                    b.style.color = '#1b1b1b';
                    b.style.boxShadow = '2px 2px 0 #1b1b1b';
                    b.style.transform = 'translate(0px, 0px)';
                });
                btn.classList.add('active');
                btn.style.background = 'var(--accent-color)';
                btn.style.color = 'white';
                btn.style.boxShadow = 'none';
                btn.style.transform = 'translate(2px, 2px)';

                const target = btn.dataset.targetTab;
                grid.querySelectorAll('.tab-content-panel').forEach(panel => {
                    panel.classList.add('hidden');
                });
                const activePanel = document.getElementById(`tab-${target}`);
                if (activePanel) {
                    activePanel.classList.remove('hidden');
                }
            };
        });

        grid.querySelectorAll('input[type="checkbox"]').forEach(cb => {
            cb.addEventListener('change', () => this.collectPermissions());
        });
    },

    renderCheckbox(section, type, targetId, isChecked) {
        return `
            <label style="display: flex; align-items: center; gap: 10px; padding: 10px 12px; cursor: pointer; border: 2px solid #1b1b1b; border-radius: 8px; background: #fafafa; margin-bottom: 5px;">
                <input type="checkbox" 
                    data-type="${type}" 
                    data-target="${targetId}" 
                    data-key="${section.key}"
                    ${isChecked ? 'checked' : ''}
                    style="width: 18px; height: 18px; accent-color: var(--accent-violet); cursor: pointer;">
                <i class="ph ${section.icon}" style="color: #666; font-size: 1.1rem;"></i>
                <span style="font-size: 0.9rem; color: #1b1b1b; font-weight: bold;">${section.label}</span>
            </label>
        `;
    },

    collectPermissions() {
        const checkboxes = document.querySelectorAll('#permissions-grid input[type="checkbox"]');
        this.currentSettings = { 2: {}, 3: {} };
        this.categoriesSettingsToSave = {};

        checkboxes.forEach(cb => {
            const type     = cb.dataset.type;
            const targetId = cb.dataset.target;
            const key      = cb.dataset.key;

            if (type === 'role') {
                const roleId = parseInt(targetId);
                this.currentSettings[roleId][key] = cb.checked;
            } else if (type === 'cat') {
                const catId = parseInt(targetId);
                if (!this.categoriesSettingsToSave[catId]) {
                    this.categoriesSettingsToSave[catId] = {};
                }
                this.categoriesSettingsToSave[catId][key] = cb.checked;
            }
        });
    },

    async savePermissions() {
        this.collectPermissions();
        const saveBtn = document.getElementById('save-permissions-btn');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Guardando...';
        }

        try {
            const response = await fetch('/api/users/update_permissions.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify({ 
                    settings: this.currentSettings,
                    categories: this.categoriesSettingsToSave
                })
            });
            const result = await response.json();

            if (result.success) {
                pop_ups.success("Permisos actualizados correctamente.");
            } else {
                pop_ups.error(result.message ?? "Error al guardar.");
            }
        } catch (e) {
            pop_ups.error("Error de conexión.");
        } finally {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="ph ph-floppy-disk"></i> Guardar Configuración';
            }
        }
    },

    async saveWorkHours() {
        const enabled = document.getElementById('work-hours-enabled')?.checked ? 1 : 0;
        const start = document.getElementById('work-hours-start')?.value || '08:00';
        const end = document.getElementById('work-hours-end')?.value || '20:00';

        const saveBtn = document.getElementById('save-work-hours-btn');
        if (saveBtn) {
            saveBtn.disabled = true;
            saveBtn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Guardando...';
        }

        try {
            const response = await fetch('/api/collaborators/save-work-hours.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ enabled, start, end })
            });
            const result = await response.json();

            if (result.success) {
                pop_ups.success("Horario laboral guardado correctamente.");
            } else {
                pop_ups.error(result.message ?? "Error al guardar.");
            }
        } catch (e) {
            console.error(e);
            pop_ups.error("Error de conexión.");
        } finally {
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="ph ph-floppy-disk"></i> Guardar Horario';
            }
        }
    },
};

window.usersModuleInstance = usersModuleInstance;

// Escuchar el botón de guardar permisos
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('save-permissions-btn')
        ?.addEventListener('click', () => usersModuleInstance.savePermissions());
});
