import { pop_ups } from '../notifications/pop-up.js?v=3.0';

export class HistoryModule {
    constructor() {
        this.containerId = 'history-log';
        this.isInitialized = false;
        this.allNotifications = [];
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
                <div class="table-header" style="display: flex; justify-content: space-between; align-items: flex-end; padding-bottom: 10px; border-bottom: 2px solid #1b1b1b;">
                    <div>
                        <h2 style="margin:0;"><i class="ph ph-notebook"></i> Historial de Movimientos</h2>
                        <p style="color: #666; margin-top: 4px;">Registro permanente e inmutable de la actividad del inventario.</p>
                    </div>
                    <div class="table-controls" style="display: flex; gap: 10px; margin-bottom: 5px;">
                        <button id="refresh-history-btn" class="btn btn-secondary" title="Actualizar">
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
                                        <th style="padding: 15px; text-align: left; width: 180px;">Fecha y Hora</th>
                                        <th style="padding: 15px; text-align: left; width: 120px;">Tipo</th>
                                        <th style="padding: 15px; text-align: left;">Acción / Detalle</th>
                                        <th style="padding: 15px; text-align: left; width: 150px;">Usuario</th>
                                    </tr>
                                </thead>
                                <tbody id="history-body">
                                    <tr><td colspan="4" style="text-align: center; padding: 40px; color: #999;">Cargando registros...</td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    attachEvents() {
        document.getElementById('refresh-history-btn')?.addEventListener('click', () => this.loadHistory());
    }

    async loadHistory() {
        try {
            const response = await fetch('/api/history/get.php');
            const res = await response.json();

            if (res.success) {
                // El historial NO muestra errores técnicos, solo movimientos.
                this.allNotifications = res.notifications.filter(n => n.type !== 'error');
                this.renderHistory();
            }
        } catch (error) {
            console.error("Error cargando historial:", error);
        }
    }

    renderHistory() {
        const body = document.getElementById('history-body');
        if (!body) return;

        if (this.allNotifications.length === 0) {
            body.innerHTML = `<tr><td colspan="4" style="text-align: center; padding: 40px; color: #999;">No hay movimientos registrados.</td></tr>`;
            return;
        }

        body.innerHTML = this.allNotifications.map(item => {
            const date = new Date(item.created_at.replace(/-/g, '/'));
            const dateStr = date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            
            let badgeStyle = `background: var(--accent-blue); color: #fff; border: 1px solid #1b1b1b;`;
            let typeLabel = 'INFO';

            if (item.type === 'success') { badgeStyle = `background: var(--accent-green); color: #fff; border: 1px solid #1b1b1b;`; typeLabel = 'ÉXITO'; }
            if (item.type === 'warning') { badgeStyle = `background: var(--accent-yellow); color: #000; border: 1px solid #1b1b1b;`; typeLabel = 'ALERTA'; }
            if (item.type === 'info') { badgeStyle = `background: var(--accent-blue); color: #fff; border: 1px solid #1b1b1b;`; typeLabel = 'INFO'; }

            return `
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 12px 15px; font-size: 0.85rem; font-weight: 600; color: #444;">${dateStr}</td>
                    <td style="padding: 12px 15px;">
                        <span class="history-badge" style="${badgeStyle}">${typeLabel}</span>
                    </td>
                    <td style="padding: 12px 15px;">
                        <div style="font-weight: 800; color: #1b1b1b; margin-bottom: 2px;">${item.title || 'Movimiento'}</div>
                        <div style="font-size: 0.85rem; color: #666; word-break: break-word;">${item.message}</div>
                    </td>
                    <td style="padding: 12px 15px;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <div style="width: 24px; height: 24px; background: #eee; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.7rem; font-weight: bold; border: 1px solid #1b1b1b;">A</div>
                            <span style="font-size: 0.85rem; font-weight: 600;">Admin</span>
                        </div>
                    </td>
                </tr>
            `;
        }).join('');

        this.injectStyles();
    }

    injectStyles() {
        if (document.getElementById('history-styles')) return;
        const style = document.createElement('style');
        style.id = 'history-styles';
        style.innerHTML = `
            .history-badge {
                padding: 4px 10px;
                border-radius: 4px;
                font-size: 0.7rem;
                font-weight: 900;
                display: inline-block;
            }
            .no-hover:hover {
                transform: none !important;
                box-shadow: none !important;
            }
            #history-table tr:hover { background: #fafafa; }
        `;
        document.head.appendChild(style);
    }
}

window.HistoryModule = new HistoryModule();
