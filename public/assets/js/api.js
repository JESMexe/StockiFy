// public/assets/js/api.js
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
        console.error("Error al verificar la sesión:", error);
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
/**
 * Actualiza el stock de un item específico.
 * @param {number} itemId El ID del item a actualizar.
 * @param {string} action La acción a realizar: 'set', 'add', 'remove'.
 * @param {number} value El valor para 'set' o la cantidad para 'add'/'remove'.
 * @returns {Promise<object>} Objeto con { success: bool, newStock: number } o un error.
 */
export async function updateStock(itemId, action, value) {
    const response = await fetch('/api/stock/update.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ itemId, action, value }),
    });
    return handleResponse(response);
}

/**
 * Añade una nueva fila de datos a la tabla activa.
 * @param {object} itemData - Objeto con { columna: valor, ... } para la nueva fila.
 * @returns {Promise<object>} Objeto con { success: bool, newItem: object } o un error.
 */
export async function addItemToTable(itemData) {
    const response = await fetch('/api/table/add.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(itemData),
    });
    return handleResponse(response);
}



/**
 * Elimina la base de datos (inventario) activa actualmente en la sesión.
 * @returns {Promise<object>} Objeto con { success: bool, message: string }
 */
export async function deleteDatabase() {
    // No necesita body, el backend usa la sesión
    const response = await fetch('/api/database/delete.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
    });
    return handleResponse(response);
}

/**
 * Le pide al backend que inserte los datos que ya están en la sesión.
 */
export async function executeImport() {
    const response = await fetch('/api/import/execute-import.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
    });
    return handleResponse(response);
}

/**
 * Actualiza una fila completa en una tabla dinámica.
 * @param {number} itemId El ID del item (fila) a actualizar.
 * @param {object} dataToUpdate Un objeto con las { columna: valor } a actualizar.
 * @returns {Promise<object>} Objeto con { success: bool, updatedItem: object }
 */
export async function updateTableRow(itemId, dataToUpdate) {
    const response = await fetch('/api/table/update-row.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ itemId, dataToUpdate }),
    });
    return handleResponse(response);
}
