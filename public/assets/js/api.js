
import { pop_ups } from "./notifications/pop-up.js";



async function handleResponse(response) {
    if (!response.ok) {
        const errorData = await response.json().catch(() => null);
        const errorMessage = errorData?.message || `Error del servidor: ${response.status}`;
        pop_ups.error(errorMessage, "Error del Servidor.");
        throw new Error(errorMessage);
    }
    return response.json();
}

export async function loginUser(credentials) {
    const response = await fetch('/api/auth/login', {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(credentials),
    });
    return handleResponse(response);
}

export async function registerUser(userData) {
    const response = await fetch('/api/auth/register', {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(userData),
    });
    return handleResponse(response);
}

//falta manejar mejor la excepcion cuando el token expira, por ahora lo dejo asi porque en el front ya frena el acceso
export async function checkSessionStatus() {
    try {
        const response = await fetch('/api/auth/check-session');
        if (!response.ok) return false;
        const data = await response.json();
        return data.isLoggedIn;
    } catch (error) {
        pop_ups.error(`Error al verificar la sesión: ${error.message}`, "Error de Sesión");
        return false;
    }
}

export async function getDatabases() {
    const response = await fetch('/api/database/list');
    if (!response.ok) throw new Error('Error al conectar con el servidor.');
    return await response.json();
}

export async function selectDatabase(inventoryId) {
    const response = await fetch('/api/database/select', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ inventoryId }),
    });
    return handleResponse(response);
}

export async function createDatabase(databaseData) {
    const response = await fetch('/api/database/create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(databaseData),
    });
    return handleResponse(response);
}

export async function deleteDatabase() {
    const response = await fetch('/api/database/delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
    });
    return handleResponse(response);
}

export async function getCurrentInventoryPreferences() {
    const response = await fetch('/api/database/get-preferences-current', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
    });
    return handleResponse(response);
}

export async function getCurrentInventoryDefaults() {
    const response = await fetch('/api/database/get-defaults-current', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
    });
    return handleResponse(response);
}

export async function getInventoryProductsForChecker() {
    const response = await fetch('/api/inventory/get-products');
    return handleResponse(response);
}

export async function setCurrentInventoryPreferences(preferences) {
    const response = await fetch('/api/database/set-preferences-current', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(preferences),
    });
    return handleResponse(response);
}

export async function getUserVerifiedTables() {
    const response = await fetch('/api/database/get-verified-tables', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
    });
    return handleResponse(response);
}

export async function saveInventoryPreferences(data) {
    const response = await fetch('/api/table/save-prefs', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    return handleResponse(response);
}

export async function getTableData() {
    const response = await fetch('/api/table/get');
    return handleResponse(response);
}

export async function addItemToTable(itemData) {
    const response = await fetch('/api/table/add', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(itemData),
    });
    return handleResponse(response);
}

export async function updateTableRow(itemId, dataToUpdate) {
    const response = await fetch('/api/table/update-row', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ itemId, dataToUpdate }),
    });
    return handleResponse(response);
}

export async function manageTableColumn(action, data) {
    const response = await fetch('/api/table/manage-column', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action, ...data }),
    });
    return handleResponse(response);
}

export async function getAnalyticsDashboard() {
    const response = await fetch('/api/analytics/get-dashboard');
    return handleResponse(response);
}

export async function updateStock(itemId, action, value) {
    const response = await fetch('/api/stock/update', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ itemId, action, value }),
    });
    return handleResponse(response);
}

export async function getCsvHeaders(formData) {
    const response = await fetch('/api/import/get-csv-headers', {
        method: 'POST',
        body: formData,
    });
    return handleResponse(response);
}

export async function prepareCsvImport(formData) {
    const response = await fetch('/api/import/prepare-csv', {
        method: 'POST',
        body: formData,
    });
    return handleResponse(response);
}

export async function executeImport() {
    const res = await fetch('/api/import/execute-import', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ _ts: Date.now() })
    });
    return res.json();
}

export async function getUserProfile() {
    const response = await fetch('/api/user/profile');
    return handleResponse(response);
}

export async function checkUserAdmin(){
    const response = await fetch('/api/auth/check-admin', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
    })
    return handleResponse(response);
}

export async function updateStatistics(tableID, dates) {
    const response = await fetch('/api/statistics/update', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ tableID, dates }),
    });
    return handleResponse(response);
}

export async function getDailyStatistics(tableID) {
    const response = await fetch('/api/statistics/update-daily', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ tableID }),
    });
    return handleResponse(response);
}

export async function getAllClients() {
    const response = await fetch('/api/customers/get-all-customers', { method: 'POST' });
    return handleResponse(response);
}

export async function getOrderedClients() {
    const response = await fetch('/api/customers/get-ordered-customers', { method: 'POST' });
    return handleResponse(response);
}

export async function updateCustomer(customer){
    const response = await fetch('/api/customers/update', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(customer),
    })
    return handleResponse(response);
}

export async function getCustomerList(order = 'desc') {
    const response = await fetch(`/api/customers/get-all.php?order=${order}`);
    return handleResponse(response);
}

export async function createCustomerNew(data) {
    const response = await fetch('/api/customers/create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    return handleResponse(response);
}

export async function getCustomerDetails(id) {
    const response = await fetch(`/api/customers/get-details.php?id=${id}`);
    return handleResponse(response);
}

export async function createClient(client) {
    const response = await fetch('/api/customers/create-customer', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ client }),
    });
    return handleResponse(response);
}

export async function getCustomerById(id) {
    const response = await fetch('/api/customers/get-by-id', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id }),
    });
    return handleResponse(response);
}


export async function getEmployeeList(order = 'desc') {
    const response = await fetch(`/api/employees/get-all.php?order=${order}`);
    return handleResponse(response);
}

export async function createEmployeeNew(name) {
    const response = await fetch('/api/employees/create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: name })
    });
    return handleResponse(response);
}



export async function getProviderList(order = 'desc') {
    const response = await fetch(`/api/providers/get-all.php?order=${order}`);
    return handleResponse(response);
}

export async function createProviderNew(data) {
    const response = await fetch('/api/providers/create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    return handleResponse(response);
}

export async function getProviderDetails(id) {
    const response = await fetch(`/api/providers/get-details.php?id=${id}`);
    return handleResponse(response);
}


export async function getAllProviders() {
    const response = await fetch('/api/providers/get-all', { method: 'POST' });
    return handleResponse(response);
}

export async function getOrderedProviders() {
    const response = await fetch('/api/providers/get-ordered', { method: 'POST' });
    return handleResponse(response);
}

export async function createProvider(provider) {
    const response = await fetch('/api/providers/create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ provider }),
    });
    return handleResponse(response);
}

export async function updateProvider(provider){
    const response = await fetch('/api/providers/update', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(provider),
    })
    return handleResponse(response);
}

export async function getProdivderById(id){
    const response = await fetch('/api/providers/get-by-id', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({id}),
    });
    return handleResponse(response);
}

export async function getAllProducts() {
    const response = await fetch('/api/products/get-all', { method: 'POST' });
    return handleResponse(response);
}

export async function getProductData(productID,tableID){
    const response = await fetch('/api/products/get-product-data', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({productID,tableID}),
    });
    return handleResponse(response);
}

export async function getTableProducts(table) {
    const response = await fetch('/api/products/get-all-from-table', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(table),
    });
    return handleResponse(response);
}

export async function getFullReceiptInfo(receiptID){
    const response = await fetch('/api/receipts/get-info', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(receiptID),
    });
    return handleResponse(response);
}

export async function getAllPaymentMethods() {
    const response = await fetch('/api/payment-methods/get-all');
    return handleResponse(response);
}

export async function createPaymentMethod(data) { // Recibe objeto data completo
    const response = await fetch('/api/payment-methods/create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    return handleResponse(response);
}

export async function updatePaymentMethod(id, data) { // Recibe objeto data completo
    const response = await fetch('/api/payment-methods/update', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, ...data })
    });
    return handleResponse(response);
}

export async function deletePaymentMethod(id) {
    const response = await fetch('/api/payment-methods/delete', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    });
    return handleResponse(response);
}

export async function createSale(saleInfo) {
    const response = await fetch('/api/sales/create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(saleInfo),
    });
    return handleResponse(response);
}

export async function getSaleDetailsNew(id) {
    const response = await fetch(`/api/sales/get-details.php?id=${id}`);
    return handleResponse(response);
}

export async function updatePurchaseProvider(purchaseId, providerId) {
    const response = await fetch('/api/purchases/update-provider', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ purchase_id: purchaseId, provider_id: providerId })
    });
    return handleResponse(response);
}

export async function updateSaleCustomer(saleId, clientId) {
    const response = await fetch('/api/sales/update-customer', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ sale_id: saleId, client_id: clientId })
    });
    return handleResponse(response);
}


export async function getAllSales() {
    const response = await fetch('/api/sales/get-all');
    return handleResponse(response);
}

export async function getSaleDetails(id) {
    const response = await fetch(`/api/sales/get-details.php?id=${id}`);
    return handleResponse(response);
}

export async function getSalesHistory(order = 'desc') {
    const response = await fetch(`/api/sales/get-all.php?order=${order}`);
    return handleResponse(response);
}

export async function getSaleResources() {
    const response = await fetch('/api/sales/get-resources');
    return handleResponse(response);
}

export async function updateSaleList(productList){
    const response = await fetch('/api/sales/update-product-list', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(productList),
    });
    return handleResponse(response);
}

export async function getPurchaseResources() {
    const response = await fetch('/api/purchases/get-resources');
    return handleResponse(response);
}

export async function createPurchase(data) {
    const response = await fetch('/api/purchases/create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    return handleResponse(response);
}

export async function getPurchasesHistory(order = 'desc') {
    const response = await fetch(`/api/purchases/get-all.php?order=${order}`);
    return handleResponse(response);
}

export async function getPurchaseDetails(id) {
    const response = await fetch(`/api/purchases/get-details.php?id=${id}`);
    return handleResponse(response);
}

export async function createReceipt(receiptInfo) {
    const response = await fetch('/api/receipts/create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(receiptInfo),
    });
    return handleResponse(response);
}

export async function updateRececiptList(productList){
    const response = await fetch('/api/receipts/update-product-list', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(productList),
    });
    return handleResponse(response);
}

export async function sendSaleEmail(emailInfo) {
    const response = await fetch('/api/sales/send-email', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ emailInfo }),
    });
    return handleResponse(response);
}

export async function getUserSales() {
    const response = await fetch('/api/sales/get-user-sales', { method: 'POST' });
    return handleResponse(response);
}

export async function getUserReceipts() {
    const response = await fetch('/api/receipts/get-user-receipts', { method: 'POST' });
    return handleResponse(response);
}

export async function getSaleItemlist(saleId) {
    const response = await fetch('/api/sales/get-product-list', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(saleId),
    });
    return handleResponse(response);
}

export async function getReceiptItemlist(receiptId) {
    const response = await fetch('/api/receipts/get-product-list', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(receiptId),
    });
    return handleResponse(response);
}

export async function registerContactForm(contactData){
    const response = await fetch('/StockiFy/api/contact/register-contact-email', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(contactData),
    });
    return handleResponse(response);
}

export async function getExchangeRate(forceRefresh = false) {
    try {
        const url = forceRefresh ? '/api/table/get-rate.php?force_refresh=true' : '/api/table/get-rate.php';
        const response = await fetch(url);
        if (!response.ok) {
            const data = await response.json().catch(() => null);
            if (data && data.message === 'API_DOWN') {
                return await promptManualRateFallback();
            }
            throw new Error(data?.message || `Error del servidor: ${response.status}`);
        }
        return await response.json();
    } catch (e) {
        if (e.message === 'API_DOWN') {
            return await promptManualRateFallback();
        }
        pop_ups.error(e.message, "Error Divisas");
        throw e;
    }
}

async function promptManualRateFallback() {
    try {
        const val = await pop_ups.prompt(
            "API del Dólar Caída",
            "La API externa no responde. Ingresá manualmente el valor (1 USD = X ARS) para continuar:",
            " Ej: 1250 ",
            ""
        );

        const rate = parseFloat(val);
        if (isNaN(rate) || rate <= 0) {
            pop_ups.error("El valor ingresado no es válido.", "Error");
            throw new Error("Valor manual inválido");
        }

        const newConfig = { type: 'manual', manual_rate: rate, api_source: 'blue' };
        await setCurrentInventoryPreferences({ exchange_config: newConfig });

        pop_ups.warning(`Cotización bloqueada en $${rate} ARS. Podés volver a modo Automático desde el menú de Ajustes en la tabla.`, "Modo Manual Activado");
        
        return { success: true, buy: rate, sell: rate, avg: rate, updated: new Date().toISOString(), source: 'manual' };

    } catch (e) {
        pop_ups.error("Se canceló la operación porque no se definió un tipo de cambio.", "Cancelado");
        throw new Error("No se definió tipo de cambio.");
    }
}