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
                    <div class="stat-card average">
                        <span class="stat-title"><i class="ph ph-receipt"></i> Ticket Promedio</span>
                        <span class="stat-value" id="kpi-average">...</span>
                        <span class="stat-sub text-muted">Por venta realizada</span>
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

                <div class="charts-grid-wrapper" style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px;">
                    
                    <div class="charts-section">
                        <h4>Flujo de Caja (30 días)</h4>
                        <div id="main-chart" style="height: 350px;"></div>
                    </div>

                    <div style="display: flex; flex-direction: column; gap: 20px;">
                        
                        <div class="charts-section" style="min-height: 300px;">
                            <h4>Métodos de Pago</h4>
                            <div id="payment-chart" style="height: 250px; display:flex; justify-content:center; align-items:center;">
                                <span class="text-muted">Cargando...</span>
                            </div>
                        </div>

                    <div class="top-list-card">
                        <h4><i class="ph-bold ph-trophy"></i> Productos Más Vendidos</h4>
                        <div id="top-products-list" style="margin-top:15px;">Cargando...</div>
                    </div>
                </div>
            </div>
        `;
    }

    async loadData() {
        try {
            const data = await getAnalyticsDashboard();

            // Validación de seguridad
            if (!data || !data.success || !data.financials) {
                console.warn("Datos de analítica vacíos o error.");
                this.renderEmptyState();
                return;
            }

            this.updateKPIs(data.financials, data.inventory_value);
            this.renderChart(data.chart_data);
            this.renderTopProducts(data.top_products);

            this.renderPaymentChart(data.payment_distribution);

        } catch (e) {
            console.error("Error cargando analíticas:", e);
            this.renderEmptyState();
        }
    }

    renderPaymentChart(list) {
        const container = document.getElementById('payment-chart');
        if (!container) return;

        // Limpiar
        container.innerHTML = '';

        // Validar datos
        if (!list || !list.length) {
            container.innerHTML = '<p class="text-muted">Sin datos de pagos.</p>';
            return;
        }

        // Preparar series y labels para ApexCharts
        const series = list.map(item => parseFloat(item.total));
        const labels = list.map(item => item.name);

        const options = {
            series: series,
            labels: labels,
            chart: {
                type: 'donut',
                height: 280,
                fontFamily: 'inherit'
            },
            dataLabels: { enabled: false }, // Ocultar % dentro de la dona para limpieza
            plotOptions: {
                pie: {
                    donut: {
                        size: '65%',
                        labels: {
                            show: true,
                            name: { show: true },
                            value: {
                                show: true,
                                formatter: (val) => '$' + parseFloat(val).toLocaleString('es-AR')
                            },
                            total: {
                                show: true,
                                label: 'Total',
                                formatter: (w) => {
                                    const sum = w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                    return '$' + sum.toLocaleString('es-AR');
                                }
                            }
                        }
                    }
                }
            },
            legend: {
                position: 'bottom',
                horizontalAlign: 'center'
            },
            colors: ['#6610f2', '#28a745', '#007bff', '#ffc107', '#dc3545', '#17a2b8'], // Paleta de colores
            tooltip: {
                y: {
                    formatter: (val) => '$ ' + val.toLocaleString('es-AR', {minimumFractionDigits: 2})
                }
            }
        };

        const chart = new ApexCharts(container, options);
        chart.render();
    }

    renderEmptyState() {
        document.getElementById('top-products-list').innerHTML = '<p class="text-muted">No se pudieron cargar los datos.</p>';
        document.getElementById('kpi-revenue').textContent = '$0.00';
        document.getElementById('kpi-expenses').textContent = '$0.00';

        const avg = document.getElementById('kpi-average');
        if(avg) avg.textContent = '$0.00';
    }

    updateKPIs(fin, invValue) {
        const fmt = (n) => `$${parseFloat(n || 0).toLocaleString('es-AR', {minimumFractionDigits: 2})}`;

        const elRev = document.getElementById('kpi-revenue');
        const elAvg = document.getElementById('kpi-average'); // <--- NUEVO ELEMENTO
        const elExp = document.getElementById('kpi-expenses');

        document.getElementById('kpi-revenue').textContent = fmt(fin.revenue);
        document.getElementById('kpi-expenses').textContent = fmt(fin.expenses);
        document.getElementById('kpi-sales-count').textContent = `${fin.sales_count} ventas`;
        document.getElementById('kpi-purchases-count').textContent = `${fin.purchases_count} compras`;

        const balEl = document.getElementById('kpi-balance');
        balEl.textContent = fmt(fin.balance);
        balEl.style.color = fin.balance >= 0 ? 'var(--accent-green, #28a745)' : 'var(--accent-red, #dc3545)';

        if(elRev) elRev.textContent = fmt(fin.revenue);

        if(elAvg) elAvg.textContent = fmt(fin.average_ticket);

        if(elExp) elExp.textContent = fmt(fin.expenses);

        document.getElementById('kpi-inventory').textContent = fmt(invValue);
    }

    renderChart(data) {
        if (!window.ApexCharts) return;

        const salesMap = {};
        const purchMap = {};

        // Mapear datos recibidos del backend
        (data.sales || []).forEach(d => salesMap[d.date] = parseFloat(d.total));
        (data.purchases || []).forEach(d => purchMap[d.date] = parseFloat(d.total));

        const dates = [];
        const seriesSales = [];
        const seriesPurch = [];

        // Generar últimos 30 días
        for (let i = 29; i >= 0; i--) {
            const d = new Date();
            d.setDate(d.getDate() - i);

            // CORRECCIÓN IMPORTANTE: Usar fecha local, no UTC (toISOString falla con zonas horarias)
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            const dateStr = `${year}-${month}-${day}`;

            dates.push(dateStr);
            // Si no hay datos para ese día, usar 0
            seriesSales.push(salesMap[dateStr] || 0);
            seriesPurch.push(purchMap[dateStr] || 0);
        }

        // Recuperar colores de las variables CSS
        const styles = getComputedStyle(document.documentElement);
        const colorGreen = styles.getPropertyValue('--accent-green').trim() || '#28a745';
        const colorRed = styles.getPropertyValue('--accent-red').trim() || '#dc3545';

        const options = {
            series: [{ name: 'Ingresos', data: seriesSales }, { name: 'Gastos', data: seriesPurch }],
            chart: {
                type: 'area',
                height: 350,
                toolbar: {show:false},
                fontFamily: 'inherit',
                animations: { enabled: true } // Animación suave al actualizar
            },
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 2 },
            xaxis: {
                categories: dates,
                type: 'datetime',
                labels: { format: 'dd/MM' },
                tooltip: { enabled: false }
            },
            yaxis: {
                labels: {
                    formatter: (value) => { return "$" + value.toLocaleString('es-AR'); }
                }
            },
            colors: [colorGreen, colorRed],
            fill: { type: 'gradient', gradient: { opacityFrom: 0.5, opacityTo: 0.1 } },
            grid: { borderColor: '#f1f1f1' },
            tooltip: {
                y: {
                    formatter: function (val) {
                        return "$ " + val.toLocaleString('es-AR', {minimumFractionDigits: 2});
                    }
                }
            }
        };

        const chartEl = document.getElementById('main-chart');
        if(chartEl) {
            chartEl.innerHTML = '';
            const chart = new ApexCharts(chartEl, options);
            chart.render();
        }
    }

    renderTopProducts(list) {
        const c = document.getElementById('top-products-list');
        if (!c) return;

        if (!list || !list.length) {
            c.innerHTML = '<p class="text-muted" style="padding:10px;">Sin movimientos recientes.</p>';
            return;
        }

        c.innerHTML = list.map(p => {
            // Lógica para mostrar el precio
            let priceDisplay;
            let priceClass = 'top-item-val';

            if (p.status === 'missing_col' || p.current_price === null || p.current_price === undefined) {
                priceDisplay = '<span style="font-size:0.75rem; color:var(--accent-red);">Columna no ident.</span>';
            } else {
                // Intentar parsear el precio que viene de la tabla dinámica (puede ser string con $)
                let rawPrice = String(p.current_price).replace(/[^0-9.,-]/g, '');
                let val = parseFloat(rawPrice);
                if (isNaN(val)) {
                    priceDisplay = p.current_price; // Mostrar texto original si no es numero
                } else {
                    priceDisplay = '$' + val.toLocaleString('es-AR', {minimumFractionDigits: 2});
                }
            }

            return `
            <div class="top-list-item">
                <div>
                    <div class="top-item-name">${p.name || 'Producto sin nombre'}</div>
                    <div class="top-item-sub">${parseFloat(p.qty)} unid. vendidas</div>
                </div>
                <div class="${priceClass}">
                    ${priceDisplay}
                    <div style="font-size: 0.7rem; color: #999; font-weight:400; text-align:right;">Precio Actual</div>
                </div>
            </div>
            `;
        }).join('');
    }
}

export const analyticsModuleInstance = new AnalyticsModule();
window.analyticsModule = analyticsModuleInstance;