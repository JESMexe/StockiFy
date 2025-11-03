// public/assets/js/database/select-db.js
import * as api from '../api.js';

// ---- MANEJADORES ----
async function handleSelectDatabase(event) {
    const target = event.target.closest('button.db-list-item');
    if (!target) return;

    const inventoryId = target.dataset.dbId;
    try {
        await api.selectDatabase(inventoryId);
        window.location.href = '/dashboard.php';
    } catch (error) {
        alert(`Error al seleccionar la base de datos: ${error.message}`);
    }
}

function populateDbList(databases, dbListElement) {
    dbListElement.innerHTML = '';
    const container = document.createElement('div');
    container.classList.add('menu-buttons');
    databases.forEach(db => {
        const button = document.createElement('button');
        button.textContent = db.name;
        button.dataset.dbId = db.id;
        button.classList.add('btn', 'btn-secondary', 'db-list-item');
        dbListElement.appendChild(button);
    });
    dbListElement.appendChild(container);
}

// ---- INICIALIZACIÓN ----
async function init() {
    const nav = document.getElementById('header-nav');
    if (nav) nav.innerHTML = `<a href="/logout.php" class="btn btn-secondary">Cerrar Sesión</a>`;

    const dbList = document.getElementById('db-list');
    if (!dbList) return;

    try {
        const profileData = await api.getUserProfile();
        if (profileData.success && profileData.databases.length > 0) {
            populateDbList(profileData.databases, dbList);
            dbList.addEventListener('click', handleSelectDatabase);
        } else {
            window.location.href = '/create-db.php';
        }
    } catch (error) {
        console.error("Error:", error);
        window.location.href = '/login.php';
    }
}

document.addEventListener('DOMContentLoaded', init);