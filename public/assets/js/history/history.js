import { pop_ups } from '../notifications/pop-up.js?v=3.0';

// Mapa de entity_type a etiqueta visual y color
const ENTITY_MAP = {
    product:      { label: 'Producto',      color: 'var(--accent-color)' },
    sale:         { label: 'Venta',         color: 'var(--accent-green)' },
    purchase:     { label: 'Compra',        color: '#88C0D0' },
    collaborator: { label: 'Colaborador',   color: '#B48EAD' },
};

// Mapa de action a etiqueta
const ACTION_MAP = {
    create: 'Creó',
    update: 'Modificó',
    delete: 'Eliminó',
};

export class HistoryModule {
    constructor() {
        this.containerId    = 'history-log';
        this.isInitialized  = false;
        this.currentPage    = 1;
        this.totalPages     = 1;
        this.currentFilter  = null;
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
            <div class="history-layout" style="display: flex; flex-direction: column; gap: 1rem; height: calc(100vh - 120px); overflow: hidden;">
                <div class="table-header" style="display: flex; justify-content: space-between; align-items: flex-end; padding-bottom: 10px; border-bottom: 2px solid #1b1b1b; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <h2 style="margin:0;"><i class="ph ph-notebook"></i> Historial de Auditoría</h2>
                        <p style="color: #666; margin-top: 4px; font-size: 0.9rem;">Registro permanente e inmutable de la actividad del inventario.</p>
                    </div>
                    <div class="table-controls" style="display: flex; gap: 8px; flex-wrap: wrap; align-items: center;">
                        <select id="history-filter" class="rustic-select" style="height: 38px; min-width: 140px;">
                            <option value="">Todo</option>
                            <option value="product">Productos</option>
                            <option value="sale">Ventas</option>
                            <option value="purchase">Compras</option>
                            <option value="collaborator">Colaboradores</option>
                        </select>
                        <button id="refresh-history-btn" class="btn btn-secondary" title="Actualizar" style="height: 38px; padding: 0 12px;">
                            <i class="ph ph-arrows-clockwise"></i>
                        </button>
                    </div>
                </div>

                <div id="history-content-area" style="flex-grow: 1; overflow: hidden; display: flex; flex-direction: column;">
                    <div class="rustic-block no-hover" style="flex-grow: 1; overflow: hidden; display: flex; flex-direction: column; background: #fff; border: 2px solid #1b1b1b; border-radius: 12px; height: 100%; transition: none; transform: none !important;">
                        <div class="table-wrapper" style="overflow-y: auto; flex-grow: 1; height: 100%;">
                            <table id="history-table" style="width: 100%; border-collapse: collapse; table-layout: fixed;">
                                <thead style="position: sticky; top: 0; background: #f9f9f9; z-index: 10; border-bottom: 2px solid #1b1b1b;">
                                    <tr>
                                        <th style="padding: 14px 15px; text-align: left; width: 165px; font-size: 0.82rem; text-transform: uppercase; color: #888;">Fecha y Hora</th>
                                        <th style="padding: 14px 15px; text-align: left; width: 110px; font-size: 0.82rem; text-transform: uppercase; color: #888;">Módulo</th>
                                        <th style="padding: 14px 15px; text-align: left; font-size: 0.82rem; text-transform: uppercase; color: #888;">Descripción</th>
                                        <th style="padding: 14px 15px; text-align: left; width: 170px; font-size: 0.82rem; text-transform: uppercase; color: #888;">Usuario / Rol</th>
                                    </tr>
                                </thead>
                                <tbody id="history-body">
                                    <tr><td colspan="4" style="text-align: center; padding: 40px; color: #999;">Cargando registros...</td></tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Paginación -->
                        <div id="history-pagination" style="display: flex; justify-content: space-between; align-items: center; padding: 12px 15px; border-top: 1px solid #eee; background: #fafafa; border-radius: 0 0 10px 10px;">
                            <span id="history-total-label" style="font-size: 0.85rem; color: #888;"></span>
                            <div style="display: flex; gap: 8px;">
                                <button id="history-prev-btn" class="btn btn-secondary" style="padding: 5px 14px; font-size: 0.85rem;" disabled>← Anterior</button>
                                <button id="history-next-btn" class="btn btn-secondary" style="padding: 5px 14px; font-size: 0.85rem;" disabled>Siguiente →</button>
                            </div>
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

        body.innerHTML = logs.map(log => {
            const date    = new Date(log.created_at.replace(/-/g, '/'));
            const dateStr = date.toLocaleDateString('es-AR') + ' ' + date.toLocaleTimeString('es-AR', { hour: '2-digit', minute: '2-digit' });

            const entity   = ENTITY_MAP[log.entity_type] ?? { label: log.entity_type, color: '#888' };
            const actionTxt = ACTION_MAP[log.action] ?? log.action;

            const userName  = log.full_name || log.username || 'Sistema';
            const roleName  = log.role_name || '—';

            // Inicial del avatar
            const initial = userName.charAt(0).toUpperCase();

            const roleColor = roleName === 'Owner' ? 'var(--accent-green)' : (roleName === 'Admin' ? '#0284c7' : '#888');

            return `
                <tr style="border-bottom: 1px solid #f0f0f0;" class="history-row">
                    <td style="padding: 13px 15px; font-size: 0.82rem; font-weight: 600; color: #555; white-space: nowrap;">${dateStr}</td>
                    <td style="padding: 13px 15px;">
                        <span class="history-badge" style="background: ${entity.color}22; color: ${entity.color}; border: 1px solid ${entity.color}55;">
                            ${entity.label}
                        </span>
                    </td>
                    <td style="padding: 13px 15px;">
                        <div style="font-weight: 700; color: #1b1b1b; font-size: 0.9rem;">${actionTxt} ${entity.label.toLowerCase()}</div>
                        <div style="font-size: 0.83rem; color: #777; margin-top: 2px; word-break: break-word;">${log.description || '—'}</div>
                    </td>
                    <td style="padding: 13px 15px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 28px; height: 28px; background: #f0f0f0; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.75rem; font-weight: 900; border: 1.5px solid #ddd; flex-shrink: 0;">
                                ${initial}
                            </div>
                            <div>
                                <div style="font-size: 0.85rem; font-weight: 700; color: #1b1b1b; line-height: 1.2;">${userName}</div>
                                <div style="font-size: 0.75rem; font-weight: 700; color: ${roleColor}; text-transform: uppercase; letter-spacing: 0.5px;">${roleName}</div>
                            </div>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');
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
                padding: 3px 9px;
                border-radius: 4px;
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
