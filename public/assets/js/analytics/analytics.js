import { getAnalyticsDashboard } from '../api.js';

export class AnalyticsModule {
    constructor() {
        this.containerId = 'analysis';
        this.isInitialized = false;
    }

    init() {
        if (this.isInitialized) {
            this.loadData();
            return;
        }
        const container = document.getElementById(this.containerId);
        if (!container) return;

        container.innerHTML = this.renderBaseStructure();
        this.loadData();
        this.isInitialized = true;
    }

    renderBaseStructure() {
        return `
            <div class="analytics-layout">
                <div class="table-header">
                    <h2>Panel Financiero</h2>
                    <button class="btn btn-secondary btn-sm" onclick="analyticsModule.loadData()">
                        <i class="ph ph-arrows-clockwise"></i> Actualizar
                    </button>
                </div>

                <div class="stats-grid">
                    <div class="stat-card revenue">
                        <span class="stat-title"><i class="ph ph-trend-up"></i> Ingresos Totales</span>
                        <span class="stat-value" id="kpi-revenue">...</span>
                        <span class="stat-sub text-muted" id="kpi-sales-count">-- ventas</span>
                    </div>
                    <div class="stat-card expenses">
                        <span class="stat-title"><i class="ph ph-trend-down"></i> Gastos Totales</span>
                        <span class="stat-value" id="kpi-expenses">...</span>
                        <span class="stat-sub text-muted" id="kpi-purchases-count">-- compras</span>
                    </div>
                    <div class="stat-card balance">
                        <span class="stat-title"><i class="ph ph-scales"></i> Balance Neto</span>
                        <span class="stat-value" id="kpi-balance">...</span>
                        <span class="stat-sub text-muted">Ingresos - Egresos</span>
                    </div>
                    <div class="stat-card inventory">
                        <span class="stat-title"><i class="ph ph-package"></i> Stock Valorizado</span>
                        <span class="stat-value" id="kpi-inventory">...</span>
                        <span class="stat-sub text-muted">Capital en mercadería</span>
                    </div>
                </div>

                <div class="bottom-section" style="display:grid; gap:20px; grid-template-columns: 2fr 1fr;">
                    <div class="charts-section">
                        <h4>Flujo de Caja (Últimos 30 días)</h4>
                        <div id="main-chart" style="height: 350px;"></div>
                    </div>

                    <div class="top-list-card">
                        <h4>🏆 Productos Más Vendidos</h4>
                        <div id="top-products-list" style="margin-top:15px;">Cargando...</div>
                    </div>
                </div>
            </div>
        `;
    }

    async loadData() {
        try {
            const data = await getAnalyticsDashboard();

            // Validación de seguridad para que no explote si viene null
            if (!data || !data.success || !data.financials) {
                console.warn("Datos de analítica vacíos o error.");
                this.renderEmptyState();
                return;
            }

            this.updateKPIs(data.financials, data.inventory_value);
            this.renderChart(data.chart_data);
            this.renderTopProducts(data.top_products);

        } catch (e) {
            console.error("Error cargando analíticas:", e);
            this.renderEmptyState();
        }
    }

    renderEmptyState() {
        document.getElementById('top-products-list').innerHTML = '<p class="text-muted">No se pudieron cargar los datos.</p>';
        document.getElementById('kpi-revenue').textContent = '$0.00';
        document.getElementById('kpi-expenses').textContent = '$0.00';
    }

    updateKPIs(fin, invValue) {
        const fmt = (n) => `$${parseFloat(n).toLocaleString('es-AR', {minimumFractionDigits: 2})}`;

        document.getElementById('kpi-revenue').textContent = fmt(fin.revenue);
        document.getElementById('kpi-expenses').textContent = fmt(fin.expenses);
        document.getElementById('kpi-sales-count').textContent = `${fin.sales_count} ventas`;
        document.getElementById('kpi-purchases-count').textContent = `${fin.purchases_count} compras`;

        const balEl = document.getElementById('kpi-balance');
        balEl.textContent = fmt(fin.balance);
        // Usamos colores inline por si las clases CSS fallan, pero referenciando variables
        balEl.style.color = fin.balance >= 0 ? 'var(--accent-green, #28a745)' : 'var(--accent-red, #dc3545)';

        document.getElementById('kpi-inventory').textContent = fmt(invValue);
    }

    renderChart(data) {
        if (!window.ApexCharts) return;

        const salesMap = {};
        const purchMap = {};

        // Manejo seguro de arrays vacíos
        (data.sales || []).forEach(d => salesMap[d.date] = parseFloat(d.total));
        (data.purchases || []).forEach(d => purchMap[d.date] = parseFloat(d.total));

        const dates = [];
        const seriesSales = [];
        const seriesPurch = [];

        for (let i = 29; i >= 0; i--) {
            const d = new Date();
            d.setDate(d.getDate() - i);
            const dateStr = d.toISOString().split('T')[0];
            dates.push(dateStr);
            seriesSales.push(salesMap[dateStr] || 0);
            seriesPurch.push(purchMap[dateStr] || 0);
        }

        // Recuperar colores de las variables CSS (truco avanzado)
        const styles = getComputedStyle(document.documentElement);
        const colorGreen = styles.getPropertyValue('--accent-green').trim() || '#28a745';
        const colorRed = styles.getPropertyValue('--accent-red').trim() || '#dc3545';

        const options = {
            series: [{ name: 'Ingresos', data: seriesSales }, { name: 'Gastos', data: seriesPurch }],
            chart: { type: 'area', height: 350, toolbar: {show:false}, fontFamily: 'inherit' },
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 2 },
            xaxis: { categories: dates, type: 'datetime', labels: { format: 'dd/MM' } },
            colors: [colorGreen, colorRed], // Usar colores de la app
            fill: { type: 'gradient', gradient: { opacityFrom: 0.5, opacityTo: 0.1 } },
            grid: { borderColor: '#f1f1f1' }
        };

        const chartEl = document.getElementById('main-chart');
        chartEl.innerHTML = '';
        const chart = new ApexCharts(chartEl, options);
        chart.render();
    }

    renderTopProducts(list) {
        const c = document.getElementById('top-products-list');
        if (!list || !list.length) { c.innerHTML = '<p class="text-muted" style="padding:10px;">Sin movimientos recientes.</p>'; return; }

        c.innerHTML = list.map(p => `
            <div class="top-list-item">
                <div>
                    <div class="top-item-name">${p.name || 'Desconocido'}</div>
                    <div class="top-item-sub">${p.qty} unidades vendidas</div>
                </div>
                <div class="top-item-val">$${parseFloat(p.total).toFixed(2)}</div>
            </div>
        `).join('');
    }
}

export const analyticsModuleInstance = new AnalyticsModule();
window.analyticsModule = analyticsModuleInstance;