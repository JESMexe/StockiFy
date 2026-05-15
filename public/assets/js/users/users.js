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
        }
    },

    /**
     * Consulta el rol del usuario actual para este inventario.
     * Esconde el botón de invitar si no es Owner ni Admin.
     */
    async detectRole() {
        try {
            const res = await fetch('/api/users/get-role-settings.php');
            const data = await res.json();
            if (data.success) {
                this.isOwner = data.mode === 'owner';
                if (data.mode === 'collaborator') {
                    // No-Owner: aplicar restricciones de sidebar según permisos
                    this.applyRoleRestrictions(data.permissions);
                    // Ocultar botón de invitar si es Employee (role_id 3)
                    if (data.role_id === 3) {
                        document.getElementById('invite-collaborator-btn')?.classList.add('hidden');
                    }
                }
            }
        } catch (e) {
            console.error('Error al detectar rol:', e);
        }
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
            // Solo ocultar si explícitamente está en false (ausente = permitido)
            if (permissions[permKey] === false) {
                const btn = document.querySelector(`[data-target-view="${viewId}"]`);
                btn?.closest('li')?.classList.add('hidden');
            }
        });
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
                roleBadge = `<span style="background: var(--accent-green-20); color: var(--accent-green); padding: 3px 8px; border-radius: 4px; font-weight: 900; font-size: 0.75rem; text-transform: uppercase;">Owner</span>`;
            } else if (c.role_name === 'Admin') {
                roleBadge = `<span style="background: #e0f2fe; color: #0284c7; padding: 3px 8px; border-radius: 4px; font-weight: 900; font-size: 0.75rem; text-transform: uppercase;">Admin</span>`;
            } else {
                roleBadge = `<span style="background: #f1f5f9; color: #475569; padding: 3px 8px; border-radius: 4px; font-weight: 900; font-size: 0.75rem; text-transform: uppercase;">Employee</span>`;
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
                <h4 style="margin: 0 0 1rem; display: flex; align-items: center; gap: 8px; color: #0284c7;">
                    <i class="ph-fill ph-shield-check" style="font-size: 1.1rem;"></i> Administrador
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
        const original = saveBtn?.innerHTML;
        if (saveBtn) { saveBtn.disabled = true; saveBtn.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Guardando...'; }

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
            if (saveBtn) { saveBtn.disabled = false; saveBtn.innerHTML = original; }
        }
    },
};

window.usersModuleInstance = usersModuleInstance;

// Escuchar el botón de guardar permisos
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('save-permissions-btn')
        ?.addEventListener('click', () => usersModuleInstance.savePermissions());
});
