// public/assets/js/main.js

import * as api from './api.js';

// ---- FUNCIÓN UTILITARIA PARA MANEJAR VISTAS ----
// Incluyo esto para que main.js sea autosuficiente y no dependa de ui.js
function showView(viewId) {
    // Primero, oculta todos los contenedores de vistas
    document.querySelectorAll('.view-container').forEach(view => {
        view.classList.add('hidden');
    });
    // Luego, muestra solo lo que quiero
    const viewToShow = document.getElementById(viewId);
    if (viewToShow) {
        viewToShow.classList.remove('hidden');
    }
}

async function checkInitialState() {
    try {
        const profileData = await api.getUserProfile();
        if (!profileData.success) throw new Error('Sesión inválida.');
        // Comprobamos si ya hay un inventario activo en la sesión
        // (Para esto, necesitamos que la API devuelva este dato)
        // Por ahora, simulo este chequeo
        const activeInventoryId = profileData.activeInventoryId;

        if (activeInventoryId) {
            // Si ya hay una DB seleccionada, muestro el panel de control
            const activeDbNameEl = document.getElementById('active-db-name');
            if(activeDbNameEl) {
                const activeInventory = profileData.databases.find(db => db.id == activeInventoryId);
                activeDbNameEl.textContent = activeInventory ? activeInventory.name : 'Desconocido';
                window.location.href = '/dashboard.php';
            }
            showView('main-app-view');
        } else if (profileData.databases && profileData.databases.length > 0) {
            // Si tiene bases de datos, pero ninguna está activa, lo redirijo a la página de selección
            window.location.href = '/select-db.php';
        } else {
            // Si no tiene ninguna base de datos, muestro la invitacion para crear la primera
            showView('empty-state-view');
        }
    } catch (error) {
        console.error("Error al cargar el estado inicial:", error);
        alert("Hubo un error al cargar tus datos. Serás redirigido.");
        window.location.href = 'logout.php';
    }
}

// ---- LÓGICA DE INICIALIZACIÓN PRINCIPAL ----

function setupHeader(isLoggedIn) {
    const nav = document.getElementById('header-nav');
    if (!nav) return;

    if (isLoggedIn) {
        // Si está logueado, botones "Ir al Panel" y "Cerrar Sesión"
        nav.innerHTML = `
            <a href="/dashboard.php" class="btn btn-primary">Ir al Panel</a> 
            <a href="/logout.php" class="btn btn-secondary">Cerrar Sesión</a>
        `;
    } else {
        // Si no, botones "Iniciar Sesión" y "Registrarse"
        nav.innerHTML = `
            <a href="/login.php" class="btn btn-secondary">Iniciar Sesión</a>
            <a href="/register.php" class="btn btn-primary">Registrarse</a>
        `;
    }
}
// ... (el resto de main.js) ...


// ---- LÓGICA DE LA APP (PARA USUARIOS LOGUEADOS) ----

async function handleCreateDatabase() {
    const dbNameInput = document.getElementById('dbNameInput');
    const columnsInput = document.getElementById('columnsInput'); // <-- Nuevo
    if (!dbNameInput || !columnsInput) return;

    const dbName = dbNameInput.value.trim();
    const columns = columnsInput.value.trim(); // <-- Nuevo

    if (!dbName || !columns) {
        alert('Por favor, completa el nombre y las columnas.');
        return;
    }

    try {
        const result = await api.createDatabase(dbName, columns); // <-- Enviamos ambos
        if (result.success) {
            alert(result.message);
            await checkInitialState();
        }
    } catch (error) {
        alert(`Error: ${error.message}`);
    }
}

function initializeEventListeners() {
    const createDbBtn = document.getElementById('createDbBtn');
    if (createDbBtn) {
        createDbBtn.addEventListener('click', handleCreateDatabase);
    }
}

async function init() {
    const isLoggedIn = await api.checkSessionStatus();
    setupHeader(isLoggedIn);

    if (isLoggedIn) {
        // Si el usuario esta logueado, inicio la aplicación
        initializeEventListeners();
        await checkInitialState();
    } else {
        // Si el usuario es un visitante, muestro el panel de bienvenida
        showView('welcome-view');
    }
}

// Iniciar todo cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', init);

