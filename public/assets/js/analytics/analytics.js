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
                    <div class="stat-card ticket">
                        <span class="stat-title"><i class="ph ph-receipt"></i> Ticket Promedio</span>
                        <span class="stat-value" id="kpi-ticket">...</span>
                        <span class="stat-sub text-muted">Promedio por venta</span>
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
                        <h4><i class="ph-bold ph-trophy"></i> Productos Más Vendidos</h4>
                        <div id="top-products-list" style="margin-top:15px;">Cargando...</div>
                    </div>
                    
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="top-list-card">
                            <h4><i class="ph-bold ph-users-three"></i> Mejores Clientes</h4>
                            <div id="top-clients-list" style="margin-top:15px;">Cargando...</div>
                        </div>
                        
                        <div class="top-list-card">
                            <h4><i class="ph-bold ph-user-circle"></i> Mejores Vendedores</h4>
                            <div id="top-sellers-list" style="margin-top:15px;">Cargando...</div>
                        </div>
                    </div>
                    
                    <div class="top-list-card">
                        <h4><i class="ph-bold ph-currency-circle-dollar"></i> Monedas (Divisas)</h4>
                        <div id="currency-chart" style="min-height: 250px;"></div>
                    </div>
                    
                    <div class="charts-section">
                        <h4><i class="ph-bold ph-clock"></i> Horarios Pico (Intensidad de Ventas)</h4>
                        <div id="peak-hours-chart" style="height: 250px;"></div>
                    </div>
                    
                    <div class="top-list-card">
                        <h4><i class="ph-bold ph-chart-pie-slice"></i> Medios de Pago</h4>
                        <div id="payment-chart" style="min-height: 250px;"></div>
                    </div>
                </div>
            </div>
        `;
    }

    async loadData() {
        try {
            const data = await getAnalyticsDashboard();

            if (!data || !data.success || !data.financials) {
                console.warn("Datos vacíos o error.");
                this.renderEmptyState();
                return;
            }

            this.updateKPIs(data.financials, data.inventory_value);
            this.renderChart(data.chart_data);
            this.renderTopProducts(data.top_products);

            if (data.payment_distribution) {
                this.renderPaymentChart(data.payment_distribution);
            }

            if (data.currency_distribution) {
                this.renderCurrencyChart(data.currency_distribution);
            }

            if (data.top_clients) this.renderTopClients(data.top_clients);
            if (data.peak_hours) this.renderPeakHoursChart(data.peak_hours);

            if (data.top_sellers) this.renderTopSellers(data.top_sellers);

        } catch (e) {
            console.error("Error cargando analíticas:", e);
            this.renderEmptyState();
        }
    }

    renderTopSellers(list) {
        const c = document.getElementById('top-sellers-list');
        if (!list || !list.length) {
            c.innerHTML = '<p class="text-muted">Sin datos de vendedores.</p>';
            return;
        }

        c.innerHTML = list.map(seller => `
            <div class="top-list-item">
                <div style="display:flex; align-items:center; gap:10px;">
                    <div style="width:32px; height:32px; background:#e9ecef; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#495057; font-weight:bold; font-size:12px;">
                        ${seller.name ? seller.name.charAt(0).toUpperCase() : 'U'}
                    </div>
                    <div>
                        <div class="top-item-name">${seller.name || 'Vendedor'}</div>
                        <div class="top-item-sub">${seller.sales_count} ventas</div>
                    </div>
                </div>
                <div class="top-item-val" style="color: var(--accent-color);">
                    $${parseFloat(seller.total).toLocaleString('es-AR', {minimumFractionDigits: 2})}
                </div>
            </div>
        `).join('');
    }

    renderTopClients(list) {
        const c = document.getElementById('top-clients-list');
        if (!list || !list.length) {
            c.innerHTML = '<p class="text-muted">Sin datos de clientes.</p>';
            return;
        }

        c.innerHTML = list.map(client => `
            <div class="top-list-item">
                <div>
                    <div class="top-item-name">${client.name || 'Cliente Genérico'}</div>
                    <div class="top-item-sub">${client.sales_count} compras realizadas</div>
                </div>
                <div class="top-item-val">$${parseFloat(client.total).toLocaleString('es-AR', {minimumFractionDigits: 2})}</div>
            </div>
        `).join('');
    }

    renderPeakHoursChart(data) {
        if (!window.ApexCharts) return;
        const el = document.getElementById('peak-hours-chart');
        el.innerHTML = '';

        const hours = Array.from({length: 24}, (_, i) => i);
        const counts = new Array(24).fill(0);

        data.forEach(d => {
            const h = parseInt(d.hour);
            if (h >= 0 && h < 24) counts[h] = parseInt(d.count);
        });

        const maxVal = Math.max(...counts);
        const colors = counts.map(val => val === maxVal && val > 0 ? 'var(--accent-color)' : '#88C0D0'); // Rojo para el pico máximo, Azul resto

        const options = {
            series: [{ name: 'Ventas', data: counts }],
            chart: { type: 'bar', height: 250, toolbar: {show:false}, fontFamily: 'inherit' },
            plotOptions: {
                bar: { borderRadius: 4, columnWidth: '60%', distributed: true } // distributed para colores individuales
            },
            colors: colors,
            xaxis: {
                categories: hours.map(h => `${h}hs`),
                labels: { rotate: -45, style: { fontSize: '10px' } }
            },
            dataLabels: { enabled: false },
            legend: { show: false }, // Ocultamos leyenda porque usamos distributed colors
            grid: { borderColor: '#f1f1f1' },
            tooltip: {
                y: { formatter: (val) => `${val} ventas` }
            }
        };

        const chart = new ApexCharts(el, options);
        chart.render();
    }

    renderCurrencyChart(data) {
        if (!window.ApexCharts) return;
        const el = document.getElementById('currency-chart');
        el.innerHTML = '';

        if (!data || data.length === 0) {
            el.innerHTML = '<p class="text-muted text-center" style="padding:20px;">Sin datos de divisas</p>';
            return;
        }

        const labels = data.map(i => i.name);
        const series = data.map(i => parseFloat(i.total));

        const options = {
            series: series,
            labels: labels,
            chart: { type: 'donut', height: 280, fontFamily: 'inherit' },
            colors: ['#88C0D0', '#A3BE8C', '#EBCB8B', '#BF616A', '#B48EAD', '#88C0D0', '#A3BE8C', '#EBCB8B', '#BF616A'],
            dataLabels: { enabled: false },
            legend: { position: 'bottom' },
            plotOptions: {
                pie: {
                    donut: {
                        size: '65%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: 'Total',
                                formatter: function (w) {
                                    const sum = w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                    return "$" + sum.toLocaleString('es-AR', {maximumFractionDigits: 0});
                                }
                            }
                        }
                    }
                }
            }
        };

        const chart = new ApexCharts(el, options);
        chart.render();
    }

    renderPaymentChart(data) {
        if (!window.ApexCharts) return;
        const el = document.getElementById('payment-chart');
        el.innerHTML = '';

        if (!data || data.length === 0) {
            el.innerHTML = '<p class="text-muted text-center" style="padding:20px;">Sin datos de pagos</p>';
            return;
        }

        const labels = data.map(i => i.name);
        const series = data.map(i => parseFloat(i.total));

        const options = {
            series: series,
            labels: labels,
            chart: { type: 'donut', height: 280, fontFamily: 'inherit' },
            colors: ['#88C0D0', '#A3BE8C', '#EBCB8B', '#BF616A', '#B48EAD', '#88C0D0', '#A3BE8C', '#EBCB8B', '#BF616A'],
            dataLabels: { enabled: false },
            legend: { position: 'bottom' },
            plotOptions: {
                pie: {
                    donut: {
                        size: '65%',
                        labels: {
                            show: true,
                            total: {
                                show: true,
                                label: 'Total',
                                formatter: function (w) {
                                    const sum = w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                    return "$" + sum.toLocaleString('es-AR', {maximumFractionDigits: 0});
                                }
                            }
                        }
                    }
                }
            }
        };

        const chart = new ApexCharts(el, options);
        chart.render();
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

        document.getElementById('kpi-ticket').textContent = fmt(fin.average_ticket);

        const balEl = document.getElementById('kpi-balance');
        balEl.textContent = fmt(fin.balance);
        balEl.style.color = fin.balance >= 0 ? 'var(--accent-green, #28a745)' : 'var(--accent-red, #dc3545)';

        const invEl = document.getElementById('kpi-inventory');
        const invText = fmt(invValue);

        invEl.textContent = invText;
        invEl.title = invText;
        invEl.classList.add('ellipsis');
    }

    renderChart(data) {
        if (!window.ApexCharts) return;

        const ymdLocal = (date) => {
            const y = date.getFullYear();
            const m = String(date.getMonth() + 1).padStart(2, '0');
            const d = String(date.getDate()).padStart(2, '0');
            return `${y}-${m}-${d}`;
        };

        const salesMap = {};
        const purchMap = {};

        (data.sales || []).forEach(d => salesMap[d.date] = parseFloat(d.total));
        (data.purchases || []).forEach(d => purchMap[d.date] = parseFloat(d.total));

        const dates = [];
        const seriesSales = [];
        const seriesPurch = [];

        for (let i = 29; i >= 0; i--) {
            const d = new Date();
            d.setDate(d.getDate() - i);

            const dateStr = ymdLocal(d); // ✅ LOCAL, no UTC

            dates.push(dateStr);
            seriesSales.push(salesMap[dateStr] || 0);
            seriesPurch.push(purchMap[dateStr] || 0);
        }

        const styles = getComputedStyle(document.documentElement);
        const colorGreen = styles.getPropertyValue('--accent-green').trim() || '#28a745';
        const colorRed = styles.getPropertyValue('--accent-red').trim() || '#dc3545';

        const labels = dates.map(ds => {
            const [y, m, d] = ds.split('-');
            return `${d}/${m}`;
        });

        const options = {
            series: [
                { name: 'Ingresos', data: seriesSales },
                { name: 'Gastos', data: seriesPurch }
            ],
            chart: { type: 'area', height: 350, toolbar: { show: false }, fontFamily: 'inherit' },
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 2 },
            xaxis: { categories: labels, type: 'category' },
            colors: [colorGreen, colorRed],
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
        if (!c) return;

        if (!list || !list.length) {
            c.innerHTML = '<p class="text-muted" style="padding:10px; text-align:center;">Sin movimientos recientes.</p>';
            return;
        }

        c.innerHTML = list.map(p => `
            <div class="top-list-item">
                <div>
                    <div class="top-item-name">${p.name || 'Desconocido'}</div>
                    <div class="top-item-sub">${p.qty} unidades vendidas</div>
                </div>
                <div class="top-item-val" style="color: var(--accent-color);">
                    $${parseFloat(p.total).toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2})}
                </div>
            </div>
        `).join('');
    }

}



export const analyticsModuleInstance = new AnalyticsModule();
window.analyticsModule = analyticsModuleInstance;