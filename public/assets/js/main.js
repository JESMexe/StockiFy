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
    showView('loading-view'); // Muestra "Cargando..."

    try {
        const profileData = await api.getUserProfile();

        if (profileData.success) {
            const user = profileData.user;
            const databases = profileData.databases;

            // Saluo al usuario
            console.log(`Bienvenido, ${user.full_name || user.username}!`);

            if (databases && databases.length > 0) {
                // Si el usuario tiene bases de datos, muestro la lista
                const dbList = document.getElementById('db-list');
                dbList.innerHTML = ''; // Limpiamos la lista por si acaso
                databases.forEach(db => {
                    const li = document.createElement('li');
                    li.textContent = db.name; // Asumiendo que cada DB tiene una propiedad "name"
                    dbList.appendChild(li);
                });
                showView('selection-view');
            } else {
                // Si no tiene, lo invito a crear la primera
                showView('first-time-view');
            }
        } else {
            // Si por alguna razon la API falla, muestro un error
            showView('welcome-view'); // O una vista de error específica
        }
    } catch (error) {
        console.error("Error al cargar el perfil del usuario:", error);
        window.location.href = 'login.php';
    }
}

// ---- LÓGICA DE INICIALIZACIÓN PRINCIPAL ----

function setupHeader(isLoggedIn) {
    const nav = document.getElementById('header-nav');
    if (!nav) return;

    if (isLoggedIn) {
        nav.innerHTML = `<a href="/logout.php" class="btn btn-secondary">Cerrar Sesión</a>`;
    } else {
        nav.innerHTML = `
            <a href="/login.php" class="btn btn-secondary">Iniciar Sesión</a>
            <a href="/register.php" class="btn btn-primary">Registrarse</a>
        `;
    }
}


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