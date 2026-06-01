import { pop_ups } from '../notifications/pop-up.js?v=3.0';

// Mapa completo de entity_type a etiqueta visual y color de sección
const ENTITY_MAP = {
    product:           { label: 'Producto',      colorVar: 'blue' },
    sale:              { label: 'Venta',         colorVar: 'green' },
    purchase:          { label: 'Compra',        colorVar: 'green' },
    collaborator:      { label: 'Colaborador',   colorVar: 'violet' },
    customer:          { label: 'Cliente',       colorVar: 'yellow' },
    provider:          { label: 'Proveedor',     colorVar: 'yellow' },
    employee:          { label: 'Empleado',      colorVar: 'yellow' },
    employee_category: { label: 'Categoría Emp', colorVar: 'yellow' },
    payment_method:    { label: 'Método Pago',   colorVar: 'violet' },
    invitation:        { label: 'Invitación',    colorVar: 'violet' },
    table_preference:  { label: 'Configuración', colorVar: 'blue' },
    column:            { label: 'Columna',       colorVar: 'blue' },
    analytic:          { label: 'Analíticas',    colorVar: 'violet' },
    role_permissions:  { label: 'Permisos de Roles', colorVar: 'violet' },
    role_settings:     { label: 'Permisos de Roles', colorVar: 'violet' },
    inventory:         { label: 'Inventario',    colorVar: 'blue' },
};

// Mapa de acciones a verbos legibles
const ACTION_MAP = {
    create: 'Creó',
    update: 'Modificó',
    delete: 'Eliminó',
    add: 'Agregó',
    remove: 'Eliminó',
    accept_invite: 'Aceptó Invitación',
    access: 'Accedió a',
    export: 'Exportó',
    import: 'Importó',
    convert: 'Conversión',
    config: 'Configuró',
    send: 'Envió',
    update_permissions: 'Configuró',
};

// Traductor de roles a español (excepto Admin)
const translateRole = (role) => {
    if (!role || role === '—') return '—';
    const r = role.trim().toUpperCase();
    if (r === 'OWNER' || r === 'PROPIETARIO') return 'Director';
    if (r === 'EMPLOYEE' || r === 'EMPLEADO') return 'Empleado';
    if (r === 'SUPERVISOR' || r === 'JEFE') return 'Jefe';
    if (r === 'SUPERIOR') return 'Superior';
    if (r === 'ADMIN' || r === 'ADMINISTRADOR') return 'Admin';
    if (r === 'SYSTEM') return 'Sistema';
    if (r === 'GUEST') return 'Invitado';
    return role;
};

// Asigna un color consistente basado en el hash del nombre
const getUserAvatarStyles = (username) => {
    const name = (username || 'Sistema').trim();
    let hash = 0;
    for (let i = 0; i < name.length; i++) {
        hash = name.charCodeAt(i) + ((hash << 5) - hash);
    }
    const colors = ['blue', 'green', 'yellow', 'violet', 'red'];
    const colorKey = colors[Math.abs(hash) % colors.length];
    return {
        bg: `var(--accent-${colorKey}-20)`,
        border: `var(--accent-${colorKey}-50)`,
        text: `var(--accent-${colorKey})`
    };
};

// Formatea los indicadores de AM/PM a un formato visualmente limpio
const formatPrettyTime = (timeStr) => {
    if (!timeStr) return '';
    return timeStr
        .replace(/\s*a\.\s*m\./gi, ' AM')
        .replace(/\s*p\.\s*m\./gi, ' PM')
        .replace(/\s*am/gi, ' AM')
        .replace(/\s*pm/gi, ' PM');
};

export class HistoryModule {
    constructor() {
        this.containerId    = 'history-log';
        this.isInitialized  = false;
        this.currentPage    = 1;
        this.totalPages     = 1;
        this.currentFilter  = null;
        this.currentLogs    = [];
    }

    init() {
        if (this.isInitialized) {
            this.loadHistory();
            return;
        }

        const container = document.getElementById(this.containerId);
        if (!container) return;

        container.innerHTML = this.renderBaseStructure();
        this.attachEvents();
        this.loadHistory();
        this.isInitialized = true;
    }

    renderBaseStructure() {
        return `
            <div class="table-container" style="height: calc(100vh - 120px); display: flex; flex-direction: column; overflow: hidden; margin-bottom: 0;">
                <div class="table-header" style="display: flex; justify-content: space-between; align-items: center; padding: 1.5rem 2rem; border-bottom: 2px solid #1b1b1b; flex-wrap: wrap; gap: 15px; background: #fafafa;">
                    <div>
                        <h2 style="margin: 0; font-size: 1.5rem; display: flex; align-items: center; gap: 10px; color: #1b1b1b; font-family: 'Satoshi', sans-serif;">
                            <i class="ph-bold ph-notebook" style="color: var(--accent-color);"></i> Historial de Auditoría
                        </h2>
                        <p style="color: #666; margin: 4px 0 0 0; font-size: 0.85rem; font-weight: 500;">
                            Registro permanente e inmutable de la actividad del inventario.
                        </p>
                    </div>
                    <div class="table-controls" style="display: flex; gap: 10px; flex-wrap: nowrap !important; align-items: center; margin: 0; height: auto !important;">
                        <select id="history-filter" class="rustic-select" style="height: 38px !important; min-width: 160px; padding: 0 30px 0 12px !important; border: 2px solid #1b1b1b; border-radius: 8px; font-weight: 700; cursor: pointer; margin: 0 !important; box-sizing: border-box !important; line-height: 34px !important; align-self: center !important; transform: none !important; box-shadow: none !important;">
                            <option value="">Todos los Módulos</option>
                            <option value="product">Productos</option>
                            <option value="sale">Ventas</option>
                            <option value="purchase">Compras / Gastos</option>
                            <option value="collaborator">Colaboradores</option>
                            <option value="customer">Clientes</option>
                            <option value="provider">Proveedores</option>
                            <option value="employee">Empleados</option>
                            <option value="payment_method">Métodos de Pago</option>
                        </select>
                        <button id="refresh-history-btn" class="btn btn-secondary" title="Actualizar Historial" style="height: 38px !important; width: 38px !important; padding: 0 !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; border: 2px solid #1b1b1b; border-radius: 8px; margin: 0 !important; box-sizing: border-box !important; align-self: center !important; transform: none !important; box-shadow: none !important;">
                            <i class="ph-bold ph-arrows-clockwise" style="font-size: 1.2rem; display: inline-block;"></i>
                        </button>
                    </div>
                </div>

                <div id="history-content-area" style="flex-grow: 1; overflow: hidden; display: flex; flex-direction: column; background: #fff;">
                    <div class="table-wrapper" style="overflow-y: auto; flex-grow: 1; height: 100%;">
                        <table id="history-table" style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                            <thead style="position: sticky; top: 0; background: #f9f9f9; z-index: 10; border-bottom: 2px solid #1b1b1b;">
                                <tr>
                                    <th style="padding: 14px 20px; text-align: left; width: 180px; font-size: 0.8rem; text-transform: uppercase; color: #666; font-weight: 800; border-bottom: 2px solid #1b1b1b;">Fecha y Hora</th>
                                    <th style="padding: 14px 20px; text-align: left; width: 140px; font-size: 0.8rem; text-transform: uppercase; color: #666; font-weight: 800; border-bottom: 2px solid #1b1b1b;">Módulo</th>
                                    <th style="padding: 14px 20px; text-align: left; font-size: 0.8rem; text-transform: uppercase; color: #666; font-weight: 800; border-bottom: 2px solid #1b1b1b;">Descripción</th>
                                    <th style="padding: 14px 20px; text-align: left; width: 220px; font-size: 0.8rem; text-transform: uppercase; color: #666; font-weight: 800; border-bottom: 2px solid #1b1b1b;">Usuario / Rol</th>
                                </tr>
                            </thead>
                            <tbody id="history-body">
                                <tr><td colspan="4" style="text-align: center; padding: 40px; color: #999; font-weight: 500;">Cargando registros...</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Paginación -->
                    <div id="history-pagination" style="display: flex; justify-content: space-between; align-items: center; padding: 15px 20px; border-top: 2px solid #1b1b1b; background: #fafafa;">
                        <span id="history-total-label" style="font-size: 0.85rem; color: #666; font-weight: 600;"></span>
                        <div style="display: flex; gap: 10px;">
                            <button id="history-prev-btn" class="btn btn-secondary" style="padding: 0 16px !important; height: 38px !important; width: auto !important; min-width: 110px; font-size: 0.85rem; font-weight: 700; border: 2px solid #1b1b1b; border-radius: 8px; margin: 0 !important; box-sizing: border-box !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; gap: 6px !important; white-space: nowrap !important; transform: none !important; box-shadow: none !important;" disabled>
                                <i class="ph-bold ph-caret-left" style="font-size: 1rem; display: inline-block;"></i> Anterior
                            </button>
                            <button id="history-next-btn" class="btn btn-secondary" style="padding: 0 16px !important; height: 38px !important; width: auto !important; min-width: 110px; font-size: 0.85rem; font-weight: 700; border: 2px solid #1b1b1b; border-radius: 8px; margin: 0 !important; box-sizing: border-box !important; display: inline-flex !important; align-items: center !important; justify-content: center !important; gap: 6px !important; white-space: nowrap !important; transform: none !important; box-shadow: none !important;" disabled>
                                Siguiente <i class="ph-bold ph-caret-right" style="font-size: 1rem; display: inline-block;"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    attachEvents() {
        document.getElementById('refresh-history-btn')
            ?.addEventListener('click', () => {
                this.currentPage = 1;
                this.loadHistory();
            });

        document.getElementById('history-filter')
            ?.addEventListener('change', (e) => {
                this.currentFilter = e.target.value || null;
                this.currentPage   = 1;
                this.loadHistory();
            });

        document.getElementById('history-prev-btn')
            ?.addEventListener('click', () => {
                if (this.currentPage > 1) {
                    this.currentPage--;
                    this.loadHistory();
                }
            });

        document.getElementById('history-next-btn')
            ?.addEventListener('click', () => {
                if (this.currentPage < this.totalPages) {
                    this.currentPage++;
                    this.loadHistory();
                }
            });

        // Click delegation on history table rows
        const historyBody = document.getElementById('history-body');
        if (historyBody) {
            historyBody.addEventListener('click', (e) => {
                const row = e.target.closest('.history-row');
                if (row) {
                    const idx = row.getAttribute('data-log-index');
                    if (idx !== null && this.currentLogs && this.currentLogs[idx]) {
                        this.openDetailModal(this.currentLogs[idx]);
                    }
                }
            });
        }

        // Close modal handlers
        const modal = document.getElementById('history-detail-modal');
        const closeBtnX = document.getElementById('close-history-detail-modal');
        const closeBtnFooter = document.getElementById('history-detail-close-btn');

        const closeModal = () => {
            modal?.classList.add('hidden');
        };

        closeBtnX?.addEventListener('click', closeModal);
        closeBtnFooter?.addEventListener('click', closeModal);
        modal?.addEventListener('click', (e) => {
            if (e.target === modal) {
                closeModal();
            }
        });
    }

    async loadHistory() {
        const body = document.getElementById('history-body');
        if (body) {
            body.innerHTML = `<tr><td colspan="4" style="text-align:center;padding:40px;color:#999;">
                <i class="ph ph-spinner" style="animation: spin 1s linear infinite; display:inline-block;"></i> Cargando...
            </td></tr>`;
        }

        try {
            const params = new URLSearchParams({ page: this.currentPage });
            if (this.currentFilter) params.set('type', this.currentFilter);

            const response = await fetch(`/api/history/get.php?${params}`);
            const res      = await response.json();

            if (res.success) {
                this.currentLogs = res.logs ?? [];
                this.totalPages = res.pages ?? 1;
                this.renderLogs(res.logs, res.total);
                this.updatePagination(res.total);
            } else {
                if (body) body.innerHTML = `<tr><td colspan="4" style="text-align:center;padding:40px;color:var(--accent-red);">${res.message}</td></tr>`;
            }
        } catch (error) {
            console.error('Error cargando historial:', error);
            if (body) body.innerHTML = `<tr><td colspan="4" style="text-align:center;padding:40px;color:var(--accent-red);">Error de conexión.</td></tr>`;
        }
    }

    renderLogs(logs, total) {
        const body = document.getElementById('history-body');
        if (!body) return;

        if (!logs || logs.length === 0) {
            body.innerHTML = `<tr><td colspan="4" style="text-align:center;padding:40px;color:#999;">No hay registros de actividad para este inventario.</td></tr>`;
            return;
        }

        this.injectStyles();

        body.innerHTML = logs.map((log, index) => {
            const date      = new Date(log.created_at.replace(/-/g, '/'));
            const datePart  = date.toLocaleDateString('es-AR');
            const timePart  = formatPrettyTime(date.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' }));
            const dateStr   = `${datePart} ${timePart}`;

            const entityKey = log.entity_type ? log.entity_type.toLowerCase() : '';
            const entity = ENTITY_MAP[entityKey] ?? { 
                label: log.entity_type ? (log.entity_type.charAt(0).toUpperCase() + log.entity_type.slice(1).toLowerCase()) : 'Sistema', 
                colorVar: 'blue' 
            };
            const actionTxt = ACTION_MAP[log.action] ?? log.action;

            const userName  = log.full_name || log.username || 'Sistema';
            const rawRole   = log.role_name || '—';
            const roleName  = translateRole(rawRole);

            // Inicial del avatar y estilos hash
            const initial = userName.charAt(0).toUpperCase();
            const avatarStyles = getUserAvatarStyles(userName);

            const roleColor = 'var(--accent-color)';

            return `
                <tr style="border-bottom: 1px solid #f0f0f0; cursor: pointer;" class="history-row" data-log-index="${index}">
                    <td style="padding: 13px 20px; font-size: 0.82rem; font-weight: 600; color: #555; white-space: nowrap;">${dateStr}</td>
                    <td style="padding: 13px 20px;">
                        <span class="history-badge" style="background: var(--accent-${entity.colorVar}-20); color: var(--accent-${entity.colorVar}); border: 1px solid var(--accent-${entity.colorVar}-50);">
                            ${entity.label}
                        </span>
                    </td>
                    <td style="padding: 13px 20px;">
                        <div style="font-weight: 700; color: #1b1b1b; font-size: 0.9rem;">${actionTxt} ${entity.label.toLowerCase()}</div>
                        <div style="font-size: 0.83rem; color: #666; margin-top: 3px; display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; text-overflow: ellipsis; word-break: break-word;">${log.description || '—'}</div>
                    </td>
                    <td style="padding: 13px 20px;">
                        <div style="display: flex; align-items: center; gap: 10px;">
                            <div style="width: 32px; height: 32px; background: ${avatarStyles.bg}; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; font-weight: 900; border: 1.5px solid ${avatarStyles.border}; flex-shrink: 0; color: ${avatarStyles.text};">
                                ${initial}
                            </div>
                            <div>
                                <div style="font-size: 0.85rem; font-weight: 700; color: #1b1b1b; line-height: 1.2;">${userName}</div>
                                <div style="font-size: 0.72rem; font-weight: 600; color: ${roleColor}; letter-spacing: 0.5px; margin-top: 1px;">${roleName}</div>
                            </div>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
    }

    openDetailModal(log) {
        const modal = document.getElementById('history-detail-modal');
        if (!modal) return;

        const date      = new Date(log.created_at.replace(/-/g, '/'));
        const datePart  = date.toLocaleDateString('es-AR');
        const timePart  = formatPrettyTime(date.toLocaleTimeString('es-AR'));
        const dateStr   = `${datePart} - ${timePart}`;

        const entityKey = log.entity_type ? log.entity_type.toLowerCase() : '';
        const entity = ENTITY_MAP[entityKey] ?? { 
            label: log.entity_type ? (log.entity_type.charAt(0).toUpperCase() + log.entity_type.slice(1).toLowerCase()) : 'Sistema', 
            colorVar: 'blue' 
        };
        const actionTxt = ACTION_MAP[log.action] ?? log.action;

        const userName  = log.full_name || log.username || 'Sistema';
        const rawRole   = log.role_name || '—';
        const roleName  = translateRole(rawRole);
        const initial = userName.charAt(0).toUpperCase();
        const roleColor = 'var(--accent-color)';
        const avatarStyles = getUserAvatarStyles(userName);

        // Populate avatar card
        const detailAvatar = document.getElementById('history-detail-avatar');
        detailAvatar.textContent = initial;
        detailAvatar.style.background = avatarStyles.bg;
        detailAvatar.style.borderColor = avatarStyles.border;
        detailAvatar.style.color = avatarStyles.text;

        document.getElementById('history-detail-username').textContent = userName;
        
        const roleBadge = document.getElementById('history-detail-role');
        roleBadge.textContent = roleName;
        roleBadge.style.color = roleColor;

        // Populate metadata details
        document.getElementById('history-detail-datetime').textContent = dateStr;
        
        const sectionBadge = document.getElementById('history-detail-section');
        sectionBadge.textContent = (log.section || entity.label).toUpperCase();
        sectionBadge.style.background = `var(--accent-${entity.colorVar}-20)`;
        sectionBadge.style.color = `var(--accent-${entity.colorVar})`;
        sectionBadge.style.borderColor = `var(--accent-${entity.colorVar}-50)`;

        // Description
        document.getElementById('history-detail-description').textContent = log.description || '—';

        // Extra description (optional)
        const extraContainer = document.getElementById('history-detail-extra-container');
        const extraText = document.getElementById('history-detail-extra-description');
        if (log.extra_description && log.extra_description.trim() !== '') {
            extraText.textContent = log.extra_description;
            extraContainer.style.display = 'block';
        } else {
            extraText.textContent = '';
            extraContainer.style.display = 'none';
        }

        modal.classList.remove('hidden');
    }

    updatePagination(total) {
        const label    = document.getElementById('history-total-label');
        const prevBtn  = document.getElementById('history-prev-btn');
        const nextBtn  = document.getElementById('history-next-btn');

        if (label)   label.textContent   = `${total} registro${total !== 1 ? 's' : ''} — Página ${this.currentPage} de ${this.totalPages}`;
        if (prevBtn) prevBtn.disabled    = this.currentPage <= 1;
        if (nextBtn) nextBtn.disabled    = this.currentPage >= this.totalPages;
    }

    injectStyles() {
        if (document.getElementById('history-styles')) return;
        const style = document.createElement('style');
        style.id = 'history-styles';
        style.innerHTML = `
            .history-badge {
                padding: 4px 10px;
                border-radius: 6px;
                font-size: 0.72rem;
                font-weight: 900;
                display: inline-block;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }
            .history-row:hover { background: #fafafa; }
            .no-hover:hover { transform: none !important; box-shadow: none !important; }
            @keyframes spin { from { transform: rotate(0deg); } to { transform: rotate(360deg); } }
        `;
        document.head.appendChild(style);
    }
}

window.HistoryModule = new HistoryModule();
