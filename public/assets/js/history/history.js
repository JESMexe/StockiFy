import { pop_ups } from '../notifications/pop-up.js?v=3.0';

export class HistoryModule {
    constructor() {
        this.containerId = 'history-log';
        this.isInitialized = false;
        this.allNotifications = [];
        this.currentTab = 'movements'; // 'movements' o 'errors'
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
                        <h2 style="margin:0;"><i class="ph ph-clock-counter-clockwise"></i> Centro de Actividad</h2>
                        <div class="history-tabs" style="display: flex; gap: 15px; margin-top: 15px;">
                            <button class="history-tab-btn active" data-tab="movements">Movimientos</button>
                            <button class="history-tab-btn" data-tab="errors">Errores Técnicos</button>
                        </div>
                    </div>
                    <div class="table-controls" style="display: flex; gap: 10px; margin-bottom: 5px;">
                        <button id="refresh-history-btn" class="btn btn-secondary" title="Actualizar">
                            <i class="ph ph-arrows-clockwise"></i>
                        </button>
                        <button id="clear-history-btn" class="btn btn-danger" title="Limpiar todo el historial">
                            <i class="ph ph-trash"></i>
                        </button>
                    </div>
                </div>

                <div id="history-content-area" style="flex-grow: 1; overflow: hidden; display: flex; flex-direction: column;">
                    <!-- Aquí se renderiza la tabla o el mensaje de error -->
                    <div class="rustic-block" style="flex-grow: 1; overflow: hidden; display: flex; flex-direction: column; background: #fff; border: 2px solid #1b1b1b; border-radius: 12px; height: 100%;">
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
        document.getElementById('clear-history-btn')?.addEventListener('click', () => this.handleClearHistory());

        const tabs = document.querySelectorAll('.history-tab-btn');
        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                this.currentTab = tab.dataset.tab;
                this.renderCurrentView();
            });
        });
    }

    async loadHistory() {
        try {
            const response = await fetch('/api/notifications/get.php');
            const res = await response.json();

            if (res.success) {
                this.allNotifications = res.notifications;
                this.renderCurrentView();
            }
        } catch (error) {
            console.error("Error cargando historial:", error);
        }
    }

    renderCurrentView() {
        const body = document.getElementById('history-body');
        if (!body) return;

        let filtered = [];
        let emptyMessage = "No hay movimientos registrados.";

        if (this.currentTab === 'movements') {
            // Filtrar para NO mostrar errores en movimientos
            filtered = this.allNotifications.filter(n => n.type !== 'error');
            emptyMessage = "No hay movimientos registrados.";
        } else {
            // Mostrar SOLO errores
            filtered = this.allNotifications.filter(n => n.type === 'error');
            emptyMessage = "No se han detectado errores técnicos. ¡Todo funciona perfecto!";
        }

        if (filtered.length === 0) {
            body.innerHTML = `
                <tr>
                    <td colspan="4" style="text-align: center; padding: 60px;">
                        <div style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5;">${this.currentTab === 'errors' ? '✅' : '📁'}</div>
                        <p style="color: #666; font-weight: 600;">${emptyMessage}</p>
                        ${this.currentTab === 'errors' ? '<p style="font-size: 0.85rem; color: #999; margin-top: 10px;">Si experimentás algún fallo, aparecerá acá para que lo reportes.</p>' : ''}
                    </td>
                </tr>
            `;
            return;
        }

        body.innerHTML = (this.currentTab === 'errors' ? this.renderErrorHeader() : '') + filtered.map(item => {
            const date = new Date(item.created_at.replace(/-/g, '/'));
            const dateStr = date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
            
            let badgeStyle = `background: var(--accent-blue); color: #fff; border: 1px solid #1b1b1b;`;
            let typeLabel = item.type || 'INFO';

            if (item.type === 'success') { badgeStyle = `background: var(--accent-green); color: #fff; border: 1px solid #1b1b1b;`; typeLabel = 'ÉXITO'; }
            if (item.type === 'error') { badgeStyle = `background: var(--accent-red); color: #fff; border: 1px solid #1b1b1b;`; typeLabel = 'ERROR'; }
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

    renderErrorHeader() {
        return `
            <tr>
                <td colspan="4" style="background: #fff5f5; border-bottom: 2px solid var(--accent-red); padding: 20px;">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div style="font-size: 2rem;">🛠️</div>
                        <div>
                            <strong style="color: var(--accent-red); display: block; font-size: 1.1rem;">Buzón de Errores Técnicos</strong>
                            <p style="margin: 0; font-size: 0.85rem; color: #666;">Acá se enlistarán los errores para que así puedas notificarle al programador y enviarle el código específico.</p>
                        </div>
                    </div>
                </td>
            </tr>
        `;
    }

    injectStyles() {
        if (document.getElementById('history-styles')) return;
        const style = document.createElement('style');
        style.id = 'history-styles';
        style.innerHTML = `
            .history-tab-btn {
                background: none;
                border: none;
                border-bottom: 3px solid transparent;
                padding: 5px 10px;
                font-weight: 700;
                cursor: pointer;
                color: #888;
                transition: all 0.2s;
                font-size: 0.95rem;
            }
            .history-tab-btn.active {
                color: var(--accent-color);
                border-bottom-color: var(--accent-color);
            }
            .history-badge {
                padding: 4px 10px;
                border-radius: 4px;
                font-size: 0.7rem;
                font-weight: 900;
                display: inline-block;
            }
            #history-table tr:hover { background: #fafafa; }
        `;
        document.head.appendChild(style);
    }

    async handleClearHistory() {
        const confirm = window.confirm("¿Estás seguro de que querés borrar TODO el historial? Esta acción no se puede deshacer.");
        if (!confirm) return;

        try {
            const response = await fetch('/api/notifications/delete_all.php', { method: 'POST' });
            const res = await response.json();
            if (res.success) {
                pop_ups.info("Historial limpiado");
                this.loadHistory();
            }
        } catch (error) { pop_ups.error("Error al limpiar"); }
    }
}

window.HistoryModule = new HistoryModule();
