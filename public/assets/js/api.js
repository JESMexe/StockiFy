// public/assets/js/api.js
import {pop_ups} from "./notifications/pop-up.js";

async function handleResponse(response) {
    if (!response.ok) {
        const errorData = await response.json().catch(() => null);
        const errorMessage = errorData?.message || `Error del servidor: ${response.status}`;
        throw new Error(errorMessage);
    }
    return response.json(); // Si todo esta bien, devuelvo el JSON.
}

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
        pop_ups.error("Error al verificar la sesión: ${error.message}", "Error en la Sesión");
        return false;
    }
}

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


export async function getTableData() {
    const response = await fetch('/api/table/get.php');
    return handleResponse(response);
}


// --- FUNCIONES DEL PERFIL DE USUARIO ---
export async function getUserProfile() {
    const response = await fetch('/api/user/profile.php');
    return handleResponse(response);
}


export async function createDatabase(dbName, columns) {
    const requestBody = { dbName, columns };
    const response = await fetch('/api/database/create.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(requestBody),
    });
    return handleResponse(response);
}

/**
 * Uploads a CSV file to get its headers and the target StockiFy columns.
 * @param {FormData} formData - The FormData object containing the 'csvFile'.
 * @returns {Promise<object>} Object with { success: bool, csvHeaders: [], stockifyColumns: [] }
 */
export async function getCsvHeaders(formData) {
    const response = await fetch('/api/import/get-csv-headers.php', {
        method: 'POST',
        body: formData, // No 'Content-Type' header needed for FormData;
    });
    return handleResponse(response);
}

/**
 * Envía el archivo CSV y el mapeo para ser procesados y guardados en sesión.
 * @param {FormData} formData - FormData con 'csvFile', 'mapping' (JSON string), 'overwrite' (string 'true'/'false').
 * @returns {Promise<object>} Resultado de la preparación.
 */
export async function prepareCsvImport(formData) {
    const response = await fetch('/api/import/prepare-csv.php', {
        method: 'POST',
        body: formData,
    });
    return handleResponse(response);
}

// --- FUNCIONES DE STOCK ---
export async function updateStock(itemId, action, value) {
    const response = await fetch('/api/stock/update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ itemId, action, value }),
    });
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

export async function deleteDatabase() {
    // No necesita body, el backend usa la sesión
    const response = await fetch('/api/database/delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
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

export async function updateTableRow(itemId, dataToUpdate) {
    const response = await fetch('/api/table/update-row.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ itemId, dataToUpdate }),
    });
    return handleResponse(response);
}

export async function manageTableColumn(action, data) {
    const payload = { action, ...data };

    const response = await fetch('/api/table/manage-column.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    });

    return handleResponse(response);
}
