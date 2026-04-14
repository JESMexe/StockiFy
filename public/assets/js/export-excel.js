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

        // 3. HOJA DE ANALÍTICAS / METRICAS
        if (chkAnalytics.checked) {
            statusDiv.textContent = 'Reuniendo Métricas (Top Clientes)...';
            const clientsRes = await api.getOrderedClients();
            let clientsData = [];
            if (clientsRes && clientsRes.customers) {
                clientsData = clientsRes.customers.map(client => ({
                    'Ranking': '', // Lo dejamos para llenarlo secuencial
                    'Nombre Cliente': client.first_name + ' ' + (client.last_name || ''),
                    'Teléfono': client.phone || '',
                    'Total Gastado ($)': parseFloat(client.amount_spent) || 0,
                    'Compras Realizadas': parseInt(client.purchases_made) || 0,
                }));
                // Asignar pos ranking
                clientsData.forEach((c, index) => c['Ranking'] = `#${index + 1}`);
            }
            if (clientsData.length === 0) {
                clientsData.push({ Mensaje: "No hay clientes suficientes para generar métricas." });
            }
            
            const wsAnalytics = XLSX.utils.json_to_sheet(clientsData);
            XLSX.utils.book_append_sheet(wb, wsAnalytics, "Top_Clientes");
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
