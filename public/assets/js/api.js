// public/assets/js/api_FINAL.js

import { pop_ups } from "./notifications/pop-up.js";


//import { isLoggedIn } from "./main.js";

/* ------------------- FUNCIONES INTERNAS ------------------- */
async function handleResponse(response) {
    if (!response.ok) {
        const errorData = await response.json().catch(() => null);
        const errorMessage = errorData?.message || `Error del servidor: ${response.status}`;
        pop_ups.error(errorMessage, "Error del Servidor.");
        throw new Error(errorMessage);
    }
    return response.json();
}

/* ------------------- AUTENTICACIÓN ------------------- */
export async function loginUser(credentials) {
    const response = await fetch('/api/auth/login.php', {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(credentials),
    });
    return handleResponse(response);
}

export async function registerUser(userData) {
    const response = await fetch('/api/auth/register.php', {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(userData),
    });
    return handleResponse(response);
}

export async function checkSessionStatus() {
    try {
        const response = await fetch('/api/auth/check-session.php');
        if (!response.ok) return false;
        const data = await response.json();
        return data.isLoggedIn;
    } catch (error) {
        pop_ups.error(`Error al verificar la sesión: ${error.message}`, "Error de Sesión");
        return false;
    }
}

/* ------------------- BASES DE DATOS ------------------- */
export async function getDatabases() {
    const response = await fetch('/api/database/list');
    if (!response.ok) throw new Error('Error al conectar con el servidor.');
    return await response.json();
}

export async function selectDatabase(inventoryId) {
    const response = await fetch('/api/database/select.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ inventoryId }),
    });
    return handleResponse(response);
}

export async function createDatabase(databaseData) {
    const response = await fetch('/api/database/create.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(databaseData),
    });
    return handleResponse(response);
}

export async function deleteDatabase() {
    const response = await fetch('/api/database/delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
    });
    return handleResponse(response);
}

export async function getCurrentInventoryPreferences() {
    const response = await fetch('/api/database/get-preferences-current.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
    });
    return handleResponse(response);
}

export async function getCurrentInventoryDefaults() {
    const response = await fetch('/api/database/get-defaults-current.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
    });
    return handleResponse(response);
}

export async function setCurrentInventoryPreferences(preferences) {
    const response = await fetch('/api/database/set-preferences-current.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(preferences),
    });
    return handleResponse(response);
}

export async function getUserVerifiedTables() {
    const response = await fetch('/api/database/get-verified-tables.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
    });
    return handleResponse(response);
}

export async function saveInventoryPreferences(data) {
    const response = await fetch('/api/table/save-prefs.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    return handleResponse(response);
}

/* ------------------- TABLAS ------------------- */
export async function getTableData() {
    const response = await fetch('/api/table/get.php');
    return handleResponse(response);
}

export async function addItemToTable(itemData) {
    const response = await fetch('/api/table/add.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(itemData),
    });
    return handleResponse(response);
}

export async function updateTableRow(itemId, dataToUpdate) {
    const response = await fetch('/api/table/update-row.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ itemId, dataToUpdate }),
    });
    return handleResponse(response);
}

export async function manageTableColumn(action, data) {
    const response = await fetch('/api/table/manage-column.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action, ...data }),
    });
    return handleResponse(response);
}

/* ------------------- ANALÍTICAS ------------------- */
export async function getAnalyticsDashboard() {
    const response = await fetch('/api/analytics/get-dashboard.php');
    return handleResponse(response);
}

/* ------------------- STOCK ------------------- */
export async function updateStock(itemId, action, value) {
    const response = await fetch('/api/stock/update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ itemId, action, value }),
    });
    return handleResponse(response);
}

/* ------------------- IMPORTACIÓN CSV ------------------- */
export async function getCsvHeaders(formData) {
    const response = await fetch('/api/import/get-csv-headers.php', {
        method: 'POST',
        body: formData,
    });
    return handleResponse(response);
}

export async function prepareCsvImport(formData) {
    const response = await fetch('/api/import/prepare-csv.php', {
        method: 'POST',
        body: formData,
    });
    return handleResponse(response);
}

export async function executeImport() {
    const response = await fetch('/api/import/execute-import.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
    });
    return handleResponse(response);
}

/* ------------------- USUARIOS ------------------- */
export async function getUserProfile() {
    const response = await fetch('/api/user/profile.php');
    return handleResponse(response);
}

export async function checkUserAdmin(){
    const response = await fetch('/api/auth/check-admin.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
    })
    return handleResponse(response);
}

/* ------------------- ESTADÍSTICAS ------------------- */
export async function updateStatistics(tableID, dates) {
    const response = await fetch('/api/statistics/update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ tableID, dates }),
    });
    return handleResponse(response);
}

export async function getDailyStatistics(tableID) {
    const response = await fetch('/api/statistics/update-daily.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ tableID }),
    });
    return handleResponse(response);
}

/* ------------------- CLIENTES ------------------- */
export async function getAllClients() {
    const response = await fetch('/api/customers/get-all-customers.php', { method: 'POST' });
    return handleResponse(response);
}

export async function getOrderedClients() {
    const response = await fetch('/api/customers/get-ordered-customers.php', { method: 'POST' });
    return handleResponse(response);
}

export async function updateCustomer(customer){
    const response = await fetch('/api/customers/update.php', {
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
    const response = await fetch('/api/customers/create.php', {
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
    const response = await fetch('/api/customers/create-customer.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ client }),
    });
    return handleResponse(response);
}

export async function getCustomerById(id) {
    const response = await fetch('/api/customers/get-by-id.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id }),
    });
    return handleResponse(response);
}

/* ------------------- EMPLEADOS ------------------- */

export async function getEmployeeList(order = 'desc') {
    const response = await fetch(`/api/employees/get-all.php?order=${order}`);
    return handleResponse(response);
}

export async function createEmployeeNew(name) {
    const response = await fetch('/api/employees/create.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name: name })
    });
    return handleResponse(response);
}

/* ------------------- PROVEEDORES ------------------- */

// === PROVEEDORES (PROVIDERS) ===

export async function getProviderList(order = 'desc') {
    const response = await fetch(`/api/providers/get-all.php?order=${order}`);
    return handleResponse(response);
}

export async function createProviderNew(data) {
    const response = await fetch('/api/providers/create.php', {
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
    const response = await fetch('/api/providers/get-all.php', { method: 'POST' });
    return handleResponse(response);
}

export async function getOrderedProviders() {
    const response = await fetch('/api/providers/get-ordered.php', { method: 'POST' });
    return handleResponse(response);
}

export async function createProvider(provider) {
    const response = await fetch('/api/providers/create.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ provider }),
    });
    return handleResponse(response);
}

export async function updateProvider(provider){
    const response = await fetch('/api/providers/update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(provider),
    })
    return handleResponse(response);
}

export async function getProdivderById(id){
    const response = await fetch('/api/providers/get-by-id.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({id}),
    });
    return handleResponse(response);
}

/* ------------------- PRODUCTOS ------------------- */
export async function getAllProducts() {
    const response = await fetch('/api/products/get-all.php', { method: 'POST' });
    return handleResponse(response);
}

export async function getProductData(productID,tableID){
    const response = await fetch('/api/products/get-product-data.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({productID,tableID}),
    });
    return handleResponse(response);
}

export async function getTableProducts(table) {
    const response = await fetch('/api/products/get-all-from-table.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(table),
    });
    return handleResponse(response);
}

export async function getFullReceiptInfo(receiptID){
    const response = await fetch('/api/receipts/get-info.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(receiptID),
    });
    return handleResponse(response);
}

/* ------------------- MÉTODOS DE PAGO ------------------- */
export async function getAllPaymentMethods() {
    const response = await fetch('/api/payment-methods/get-all.php');
    return handleResponse(response);
}

export async function createPaymentMethod(data) { // Recibe objeto data completo
    const response = await fetch('/api/payment-methods/create.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    return handleResponse(response);
}

export async function updatePaymentMethod(id, data) { // Recibe objeto data completo
    const response = await fetch('/api/payment-methods/update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id, ...data })
    });
    return handleResponse(response);
}

export async function deletePaymentMethod(id) {
    const response = await fetch('/api/payment-methods/delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ id })
    });
    return handleResponse(response);
}

/* ------------------- VENTAS Y COMPRAS ------------------- */
export async function createSale(saleInfo) {
    const response = await fetch('/api/sales/create.php', {
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
    const response = await fetch('/api/purchases/update-provider.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ purchase_id: purchaseId, provider_id: providerId })
    });
    return handleResponse(response);
}

export async function updateSaleCustomer(saleId, clientId) {
    const response = await fetch('/api/sales/update-customer.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ sale_id: saleId, client_id: clientId })
    });
    return handleResponse(response);
}

// --- MÓDULO VENTAS V2 (API) ---

// Obtener todas las ventas (Listado)
export async function getAllSales() {
    // Apunta al nuevo archivo get-all.php
    const response = await fetch('/api/sales/get-all.php');
    return handleResponse(response);
}

// Obtener detalles de UNA venta
export async function getSaleDetails(id) {
    const response = await fetch(`/api/sales/get-details.php?id=${id}`);
    return handleResponse(response);
}

export async function getSalesHistory(order = 'desc') {
    const response = await fetch(`/api/sales/get-all.php?order=${order}`);
    return handleResponse(response);
}

export async function getSaleResources() {
    const response = await fetch('/api/sales/get-resources.php');
    return handleResponse(response);
}

export async function updateSaleList(productList){
    const response = await fetch('/api/sales/update-product-list.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(productList),
    });
    return handleResponse(response);
}

export async function getPurchaseResources() {
    const response = await fetch('/api/purchases/get-resources.php');
    return handleResponse(response);
}

export async function createPurchase(data) {
    const response = await fetch('/api/purchases/create.php', {
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
    const response = await fetch('/api/receipts/create.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(receiptInfo),
    });
    return handleResponse(response);
}

export async function updateRececiptList(productList){
    const response = await fetch('/api/receipts/update-product-list.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(productList),
    });
    return handleResponse(response);
}

export async function sendSaleEmail(emailInfo) {
    const response = await fetch('/api/sales/send-email.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ emailInfo }),
    });
    return handleResponse(response);
}

export async function getUserSales() {
    const response = await fetch('/api/sales/get-user-sales.php', { method: 'POST' });
    return handleResponse(response);
}

export async function getUserReceipts() {
    const response = await fetch('/api/receipts/get-user-receipts.php', { method: 'POST' });
    return handleResponse(response);
}

export async function getSaleItemlist(saleId) {
    const response = await fetch('/api/sales/get-product-list.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(saleId),
    });
    return handleResponse(response);
}

export async function getReceiptItemlist(receiptId) {
    const response = await fetch('/api/receipts/get-product-list.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(receiptId),
    });
    return handleResponse(response);
}

export async function registerContactForm(contactData){
    const response = await fetch('/StockiFy/api/contact/register-contact-email.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(contactData),
    })
    return handleResponse(response);
}