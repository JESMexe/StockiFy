import { pop_ups } from '../notifications/pop-up.js?v=3.0';

// Secciones configurables del dashboard por el Owner
const CONFIGURABLE_SECTIONS = [
    { key: 'can_view_analytics',     label: 'Analíticas',           icon: 'ph-chart-line' },
    { key: 'can_view_history',       label: 'Historial',            icon: 'ph-clock-counter-clockwise' },
    { key: 'can_view_config',        label: 'Configurar Tabla',     icon: 'ph-gear' },
    { key: 'can_view_customers',     label: 'Clientes',             icon: 'ph-user-focus' },
    { key: 'can_view_providers',     label: 'Proveedores',          icon: 'ph-van' },
    { key: 'can_view_employees',     label: 'Trabajadores',         icon: 'ph-identification-badge' },
    { key: 'can_view_payments',      label: 'Métodos de Pago',      icon: 'ph-wallet' },
    { key: 'can_view_notifications', label: 'Notificaciones',       icon: 'ph-bell' },
];

export const usersModuleInstance = {
    isOwner: false,
    currentSettings: {},

    async init() {
        await this.detectRole();
        this.setupEvents();
        this.loadCollaborators();

        if (this.isOwner) {
            this.showPermissionsPanel();
            this.loadPermissions();
            await this.loadQuota(); // Cargar y renderizar cupos del plan
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
            'can_view_analytics':     'analysis',
            'can_view_history':       'history-log',
            'can_view_config':        'config-db',
            'can_view_customers':     'customers',
            'can_view_providers':     'providers',
            'can_view_employees':     'employees',
            'can_view_payments':      'payments',
            'can_view_notifications': 'notifications',
        };

        Object.entries(sectionMap).forEach(([permKey, viewId]) => {
            // Ocultar si explícitamente está en false (ausente = permitido)
            if (permissions[permKey] === false) {
                const btn = document.querySelector(`[data-target-view="${viewId}"]`);
                btn?.closest('li')?.classList.add('hidden');
            }
        });

        // El botón "Colaboradores" solo es visible para el Owner
        const colabBtn = document.querySelector('[data-target-view="users-manage"]');
        colabBtn?.closest('li')?.classList.add('hidden');
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
                // El Owner ve todo, incluyendo Colaboradores — no hay nada que ocultar
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
        } catch (e) {
            console.warn('[Quota] No se pudo cargar la cuota de colaboradores:', e.message);
        }
    },

    /**
     * Renderiza el badge de cupos junto al título de la sección.
     * Se inserta en el contenedor del título del panel de colaboradores.
     */
    renderQuotaBadge(quota) {
        // Limpiar badge anterior si existe
        document.getElementById('collab-quota-badge')?.remove();

        const titleEl = document.querySelector('#users-manage h2');
        if (!titleEl) return;

        let badgeHtml;

        if (quota.locked) {
            // Plan Básico: sin colaboradores
            badgeHtml = `
                <span id="collab-quota-badge" style="
                    display: inline-flex; align-items: center; gap: 5px;
                    background: #f1f5f9; color: #94a3b8;
                    padding: 3px 10px; border-radius: 20px;
                    font-size: 0.75rem; font-weight: 600;
                    border: 1px solid #e2e8f0; margin-left: 10px;
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
                    padding: 3px 10px; border-radius: 20px;
                    font-size: 0.75rem; font-weight: 600;
                    border: 1px solid color-mix(in srgb, var(--accent-color) 30%, transparent);
                    margin-left: 10px;
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
                    padding: 3px 10px; border-radius: 20px;
                    font-size: 0.75rem; font-weight: 600;
                    border: 1px solid ${borderColor}; margin-left: 10px;
                ">
                    <i class="ph ph-users"></i>
                    ${quota.used}/${quota.max} colaboradores — Plan ${quota.plan_name}
                </span>`;
        }

        titleEl.insertAdjacentHTML('beforeend', badgeHtml);
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

        let html = `
            <table style="width: 100%; border-collapse: collapse; margin-top: 10px;">
                <thead>
                    <tr style="border-bottom: 2px solid #eee;">
                        <th style="text-align: left; padding: 10px; font-size: 0.8rem; text-transform: uppercase; color: #888;">Usuario</th>
                        <th style="text-align: left; padding: 10px; font-size: 0.8rem; text-transform: uppercase; color: #888;">Email</th>
                        <th style="text-align: left; padding: 10px; font-size: 0.8rem; text-transform: uppercase; color: #888;">Rol</th>
                        <th style="text-align: left; padding: 10px; font-size: 0.8rem; text-transform: uppercase; color: #888;">Estado</th>
                        ${this.isOwner ? '<th style="text-align: center; padding: 10px; font-size: 0.8rem; text-transform: uppercase; color: #888;">Acciones</th>' : ''}
                    </tr>
                </thead>
                <tbody>
        `;

        collaborators.forEach(c => {
            let roleBadge;
            if (c.role_name === 'Owner') {
                roleBadge = `<span style="background: var(--accent-green-20); color: var(--accent-green); padding: 3px 8px; border-radius: 4px; font-weight: 900; font-size: 0.75rem; text-transform: uppercase;">Propietario</span>`;
            } else if (c.role_name === 'Admin') {
                roleBadge = `<span style="background: color-mix(in srgb, var(--accent-color) 15%, transparent); color: var(--accent-color); padding: 3px 8px; border-radius: 4px; font-weight: 900; font-size: 0.75rem; text-transform: uppercase;">Administrador</span>`;
            } else {
                roleBadge = `<span style="background: #f1f5f9; color: #475569; padding: 3px 8px; border-radius: 4px; font-weight: 900; font-size: 0.75rem; text-transform: uppercase;">Empleado</span>`;
            }

            const isActive = c.status === 'active';
            // Solo el Owner puede eliminar colaboradores (y no puede eliminarse a sí mismo)
            const canDelete = this.isOwner && c.role_name !== 'Owner';

            html += `
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 12px 10px;"><strong>${c.full_name || c.username}</strong></td>
                    <td style="padding: 12px 10px; font-size: 0.9rem; color: #555;">${c.email}</td>
                    <td style="padding: 12px 10px;">${roleBadge}</td>
                    <td style="padding: 12px 10px;">
                        <span style="font-size: 0.85rem; ${isActive ? 'color: var(--accent-green);' : 'color: #888;'}">
                            ${isActive ? '● Activo' : '● Pendiente'}
                        </span>
                    </td>
                    ${this.isOwner ? `<td style="padding: 12px 10px; text-align: center;">
                        ${canDelete
                            ? `<button class="btn btn-secondary" style="color: var(--accent-red); border-color: var(--accent-red); padding: 5px 10px; width: auto; margin: 0;" onclick="window.usersModuleInstance.removeCollaborator(${c.collaborator_id})">
                                <i class="ph ph-trash"></i>
                               </button>`
                            : '<span style="color: #ccc; font-size: 0.85rem;">—</span>'
                        }
                    </td>` : ''}
                </tr>
            `;
        });

        html += `</tbody></table>`;
        container.innerHTML = html;
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
        submitBtn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Enviando...';

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
            } else if (response.status === 422) {
                // Condición esperada (usuario no registrado, ya colaborador, etc.)
                pop_ups.warning(result.message);
            } else {
                pop_ups.error(result.message);
            }
        } catch (e) {
            pop_ups.error("Error de conexión al enviar la invitación.");
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
                this.renderPermissionsGrid(data.settings);
            }
        } catch (e) {
            console.error('Error cargando permisos:', e);
        }
    },

    renderPermissionsGrid(settings) {
        const grid = document.getElementById('permissions-grid');
        if (!grid) return;

        // settings = { "2": { can_view_analytics: true, ... }, "3": { ... } }
        const adminPerms    = settings[2] ?? {};
        const employeePerms = settings[3] ?? {};

        grid.innerHTML = `
            <div>
                <h4 style="margin: 0 0 1rem; display: flex; align-items: center; gap: 8px; color: var(--accent-color);">
                    <i class="ph ph-star" style="font-size: 1.1rem; color: var(--accent-color);"></i> Administrador
                </h4>
                ${CONFIGURABLE_SECTIONS.map(s => this.renderCheckbox(s, 2, adminPerms[s.key] !== false)).join('')}
            </div>
            <div>
                <h4 style="margin: 0 0 1rem; display: flex; align-items: center; gap: 8px; color: #888;">
                    <i class="ph-fill ph-user" style="font-size: 1.1rem;"></i> Empleado
                </h4>
                ${CONFIGURABLE_SECTIONS.map(s => this.renderCheckbox(s, 3, employeePerms[s.key] !== false)).join('')}
            </div>
        `;

        // Guardar permisos al hacer click en checkboxes
        grid.querySelectorAll('input[type="checkbox"]').forEach(cb => {
            cb.addEventListener('change', () => this.collectPermissions());
        });
    },

    renderCheckbox(section, roleId, isChecked) {
        return `
            <label style="display: flex; align-items: center; gap: 10px; padding: 8px 0; cursor: pointer; border-bottom: 1px solid #f0f0f0;">
                <input type="checkbox" 
                    data-role="${roleId}" 
                    data-key="${section.key}"
                    ${isChecked ? 'checked' : ''}
                    style="width: 16px; height: 16px; accent-color: var(--accent-violet); cursor: pointer;">
                <i class="ph ${section.icon}" style="color: #888; font-size: 1rem;"></i>
                <span style="font-size: 0.9rem; color: #333;">${section.label}</span>
            </label>
        `;
    },

    collectPermissions() {
        // Actualizar currentSettings según el estado actual de checkboxes
        const checkboxes = document.querySelectorAll('#permissions-grid input[type="checkbox"]');
        this.currentSettings = { 2: {}, 3: {} };

        checkboxes.forEach(cb => {
            const roleId = parseInt(cb.dataset.role);
            const key    = cb.dataset.key;
            this.currentSettings[roleId][key] = cb.checked;
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
                body:    JSON.stringify({ settings: this.currentSettings })
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
            // Siempre restaurar al estado original con texto e ícono fijos
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="ph ph-floppy-disk"></i> Guardar Configuración';
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
