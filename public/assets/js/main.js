// public/assets/js/main.js

import * as api from './api.js';
import {pop_ups} from "./notifications/pop-up.js";

function showView(viewId) {
    document.querySelectorAll('.view-container').forEach(view => {
        view.classList.add('hidden');
    });
    const viewToShow = document.getElementById(viewId);
    if (viewToShow) {
        viewToShow.classList.remove('hidden');
    }
}

async function checkInitialState() {
    try {
        const profileData = await api.getUserProfile();
        if (!profileData.success) throw new Error('Sesión inválida.');
        const activeInventoryId = profileData.activeInventoryId;

        if (activeInventoryId) {
            const activeDbNameEl = document.getElementById('active-db-name');
            if(activeDbNameEl) {
                const activeInventory = profileData.databases.find(db => db.id == activeInventoryId);
                activeDbNameEl.textContent = activeInventory ? activeInventory.name : 'Desconocido';
                window.location.href = '/dashboard.php';
            }
            showView('main-app-view');
        } else if (profileData.databases && profileData.databases.length > 0) {
            window.location.href = '/select-db.php';
        } else {
            showView('empty-state-view');
        }
    } catch (error) {
        pop_ups.error(`Error al cargar el estado inicial: ${error.message}`);
        alert("Hubo un error al cargar tus datos. Serás redirigido.");
        window.location.href = 'logout.php';
    }
}


function setupHeader(isLoggedIn) {
    const nav = document.getElementById('header-nav');
    if (!nav) return;

    if (isLoggedIn) {
        nav.innerHTML = `
            <a href="/dashboard.php" class="btn btn-primary">Ir al Panel</a> 
            <a href="/logout.php" class="btn btn-secondary">Cerrar Sesión</a>
        `;
    } else {
        nav.innerHTML = `
            <a href="/login.php" class="btn btn-secondary">Iniciar Sesión</a>
            <a href="/register.php" class="btn btn-primary">Registrarse</a>
        `;
    }
}

async function handleCreateDatabase() {
    const dbNameInput = document.getElementById('dbNameInput');
    const columnsInput = document.getElementById('columnsInput');
    if (!dbNameInput || !columnsInput) return;

    const dbName = dbNameInput.value.trim();
    const columns = columnsInput.value.trim();

    if (!dbName || !columns) {
        pop_ups.warning('Por favor, completa el nombre y las columnas.');
        return;
    }

    try {
        const result = await api.createDatabase(dbName, columns);
        if (result.success) {
            pop_ups.warning(result.message);
            await checkInitialState();
        }
    } catch (error) {
        pop_ups.warning(`Error: ${error.message}`);
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
        initializeEventListeners();
        await checkInitialState();
    } else {
        showView('welcome-view');
    }
}

document.addEventListener('DOMContentLoaded', init);

