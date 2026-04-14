import * as api from './api.js';

// Elementos del Modal
const modalExport = document.getElementById('export-modal');
const btnRunExport = document.getElementById('btn-run-export');
const statusDiv = document.getElementById('export-status');

const chkInventory = document.getElementById('export-chk-inventory');
const chkSales = document.getElementById('export-chk-sales');
const chkAnalytics = document.getElementById('export-chk-analytics');

window.openExportModal = () => {
    if (modalExport) {
        modalExport.classList.remove('hidden');
        statusDiv.textContent = '';
        btnRunExport.disabled = false;
        btnRunExport.textContent = 'Generar Excel';
    }
};

window.closeExportModal = () => {
    if (modalExport) {
        modalExport.classList.add('hidden');
    }
};

// Utilidad para limpiar columnas internas indeseadas
const cleanRow = (row, hideFields = ['id', 'inventory_id', 'user_id', 'created_at', 'updated_at', 'details']) => {
    const cleaned = { ...row };
    hideFields.forEach(field => delete cleaned[field]);
    return cleaned;
};

// Generar una fecha formateada para el nombre del archivo
const getFormattedDate = () => {
    const d = new Date();
    return `${d.getFullYear()}${(d.getMonth() + 1).toString().padStart(2, '0')}${d.getDate().toString().padStart(2, '0')}_${d.getHours().toString().padStart(2, '0')}${d.getMinutes().toString().padStart(2, '0')}`;
};

window.runExport = async () => {
    if (!chkInventory.checked && !chkSales.checked && !chkAnalytics.checked) {
        statusDiv.textContent = '¡Debes seleccionar al menos una opción para exportar!';
        statusDiv.style.color = 'var(--accent-red)';
        return;
    }

    try {
        btnRunExport.disabled = true;
        btnRunExport.textContent = 'Preparando Archivo...';
        statusDiv.style.color = 'var(--color-black)';
        statusDiv.textContent = 'Recopilando datos...';

        // Inicializar el Workbook
        const wb = XLSX.utils.book_new();

        // 1. HOJA DE INVENTARIO
        if (chkInventory.checked) {
            statusDiv.textContent = 'Generando Hoja de Inventario...';
            // Utilizamos window.allData que debería estar cargado en dashboard.js
            let inventoryData = window.allData ? [...window.allData] : [];
            const cleanInventory = inventoryData.map(item => cleanRow(item));
            if (cleanInventory.length === 0) {
                cleanInventory.push({ Mensaje: "No hay productos en este inventario" });
            }
            const wsInventory = XLSX.utils.json_to_sheet(cleanInventory);
            XLSX.utils.book_append_sheet(wb, wsInventory, "Inventario");
        }

        // 2. HOJA DE VENTAS
        if (chkSales.checked) {
            statusDiv.textContent = 'Consiguiendo Historial de Ventas...';
            const salesRes = await api.getSalesHistory('desc');
            let salesData = [];
            if (salesRes && salesRes.sales) {
                salesData = salesRes.sales.map(sale => ({
                    'ID Venta': sale.id,
                    'Fecha': sale.sale_date,
                    'Cliente': sale.client_name || 'Consumidor Final',
                    'Total_Cobrado': parseFloat(sale.total) || 0,
                    'Facturado': sale.is_invoiced == 1 ? 'Sí' : 'No',
                    'Medio de Pago': sale.payment_method_name || 'Desconocido',
                    'Tipo': sale.method_type || 'Desconocido'
                }));
            }
            if (salesData.length === 0) {
                salesData.push({ Mensaje: "No hay ventas registradas." });
            }
            const wsSales = XLSX.utils.json_to_sheet(salesData);
            XLSX.utils.book_append_sheet(wb, wsSales, "Ventas_y_FlujoCaja");
        }

        // 3. HOJA DE ANALÍTICAS / METRICAS (MEGA-EXPORT)
        if (chkAnalytics.checked) {
            statusDiv.textContent = 'Calculando Mega-Analíticas...';
            // Cache buster for Analytics bypassing api.js cache if needed
            const response = await fetch(`/api/analytics/get-dashboard.php?_t=${Date.now()}`);
            const dashboardRes = await response.json();
            
            if (dashboardRes && dashboardRes.success) {
                // --- HOJA A: RESUMEN FINANCIERO ---
                const fin = dashboardRes.financials || {};
                const invVal = dashboardRes.inventory_value || 0;
                
                const resumenData = [
                    { 'Métrica': 'Ingresos Totales (Ventas)', 'Valor ($)': parseFloat(fin.total_revenue) || 0 },
                    { 'Métrica': 'Gastos Totales (Compras)', 'Valor ($)': parseFloat(fin.total_expense) || 0 },
                    { 'Métrica': 'Balance Neto', 'Valor ($)': parseFloat(fin.net_balance) || 0 },
                    { 'Métrica': 'Ticket Promedio (Ventas)', 'Valor ($)': parseFloat(fin.average_ticket) || 0 },
                    { 'Métrica': 'Cantidad Ventas Totales', 'Valor ($)': parseInt(fin.total_sales_count) || 0 },
                    { 'Métrica': 'Valorización Inventario Inicial', 'Valor ($)': parseFloat(invVal) || 0 }
                ];
                const wsResumen = XLSX.utils.json_to_sheet(resumenData);
                XLSX.utils.book_append_sheet(wb, wsResumen, "Resumen_Financiero");

                // --- HOJA B: TOP RENDIMIENTO ---
                // Vamos a juntar Top Productos, Clientes y Vendedores rellenando con espacios blancos para no chocar columnas
                let topRendimiento = [];
                const maxRows = Math.max(
                    (dashboardRes.top_products || []).length, 
                    (dashboardRes.top_clients || []).length, 
                    (dashboardRes.top_sellers || []).length,
                    1
                );
                
                for(let i=0; i<maxRows; i++) {
                    const prod = dashboardRes.top_products ? dashboardRes.top_products[i] : null;
                    const cli = dashboardRes.top_clients ? dashboardRes.top_clients[i] : null;
                    const sell = dashboardRes.top_sellers ? dashboardRes.top_sellers[i] : null;
                    
                    topRendimiento.push({
                        '--- PRODUCTOS MÁS VENDIDOS ---': prod ? prod.name || prod.product_name : '',
                        'Prod_Cant': prod ? parseInt(prod.sold_quantity) || 0 : '',
                        'Prod_Ingreso': prod ? parseFloat(prod.total_revenue) || 0 : '',
                        '|': '|',
                        '--- MEJORES CLIENTES ---': cli ? cli.client_name || cli.first_name : '',
                        'Cli_Compras': cli ? parseInt(cli.purchases_count || cli.purchases_made) || 0 : '',
                        'Cli_Gasto': cli ? parseFloat(cli.total_spent || cli.amount_spent) || 0 : '',
                        '||': '||',
                        '--- MEJORES VENDEDORES ---': sell ? sell.employee_name || sell.username || sell.name : '',
                        'Vend_Ventas': sell ? parseInt(sell.sales_count) || 0 : '',
                        'Vend_Ingreso': sell ? parseFloat(sell.total_revenue) || 0 : ''
                    });
                }
                const wsRendimiento = XLSX.utils.json_to_sheet(topRendimiento);
                XLSX.utils.book_append_sheet(wb, wsRendimiento, "Top_Rendimiento");

                // --- HOJA C: USOS Y MÉTRICAS ---
                let usosMetricas = [];
                const maxRowsUsos = Math.max(
                    (dashboardRes.payment_distribution || []).length, 
                    (dashboardRes.currency_distribution || []).length, 
                    (dashboardRes.peak_hours || []).length,
                    1
                );
                
                for(let i=0; i<maxRowsUsos; i++) {
                    const pay = dashboardRes.payment_distribution ? dashboardRes.payment_distribution[i] : null;
                    const curr = dashboardRes.currency_distribution ? dashboardRes.currency_distribution[i] : null;
                    const peak = dashboardRes.peak_hours ? dashboardRes.peak_hours[i] : null;
                    
                    usosMetricas.push({
                        '--- MEDIOS DE PAGO ---': pay ? pay.pt_name || pay.name : '',
                        'Pago_Cant': pay ? parseInt(pay.usage_count) || 0 : '',
                        'Pago_Ingreso': pay ? parseFloat(pay.total_amount) || 0 : '',
                        '|': '|',
                        '--- MONEDAS ---': curr ? curr.currency_name || curr.name : '',
                        'Moneda_Total': curr ? parseFloat(curr.total_amount) || 0 : '',
                        '||': '||',
                        '--- HORARIOS PICO ---': peak ? peak.sale_hour + ':00' : '',
                        'Hora_Ventas': peak ? parseInt(peak.sales_count) || 0 : ''
                    });
                }
                const wsUsos = XLSX.utils.json_to_sheet(usosMetricas);
                XLSX.utils.book_append_sheet(wb, wsUsos, "Usos_y_Metricas");

            } else {
                // Fallback si no hay datos de analíticas
                let fallback = [{ Mensaje: "No se pudieron obtener métricas." }];
                XLSX.utils.book_append_sheet(wb, XLSX.utils.json_to_sheet(fallback), "Analiticas_Error");
            }
        }

        statusDiv.textContent = 'Iniciando descarga...';
        
        // Escribimos el workbook a un archivo (.xlsx)
        const fileName = `StockiFy_Reporte_${getFormattedDate()}.xlsx`;
        XLSX.writeFile(wb, fileName);

        statusDiv.style.color = 'var(--accent-green)';
        statusDiv.textContent = `¡Excel generado con éxito!`;
        
        setTimeout(() => {
            window.closeExportModal();
        }, 3000);

    } catch (err) {
        console.error("Error al exportar:", err);
        statusDiv.style.color = 'var(--accent-red)';
        statusDiv.textContent = 'Ocurrió un error al procesar el Excel.';
    } finally {
        btnRunExport.disabled = false;
        btnRunExport.textContent = 'Generar Excel';
    }
};
