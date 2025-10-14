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

export async function selectDatabase(dbName) {
    const response = await fetch(`/api/database/select/${dbName}`, { method: 'POST' });
    return handleResponse(response);
}


// --- FUNCIONES DEL PERFIL DE USUARIO ---

export async function getUserProfile() {
    // Usamos el método GET porque solo estamos solicitando datos
    const response = await fetch('/api/user/profile.php');
    return handleResponse(response); // Reutilizamos nuestra genial función handleResponse
}


export async function createDatabase(dbName, columns) {
    const requestBody = { dbName, columns }; // Enviamos ambos datos
    const response = await fetch('/api/database/create.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(requestBody),
    });
    return handleResponse(response);
}