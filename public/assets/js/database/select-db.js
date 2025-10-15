// public/assets/js/database/select-db.js
import * as api from '../api.js';

// ---- MANEJADORES ----
async function handleSelectDatabase(event) {
    const target = event.target.closest('.db-list-item');
    if (!target) return;

    const inventoryId = target.dataset.dbId;
    try {
        await api.selectDatabase(inventoryId);
        window.location.href = '/index.php';
    } catch (error) {
        alert(`Error al seleccionar la base de datos: ${error.message}`);
    }
}

function populateDbList(databases, dbListElement) {
    dbListElement.innerHTML = ''; // Limpiamos la lista
    databases.forEach(db => {
        const li = document.createElement('li');
        li.textContent = db.name;
        li.dataset.dbId = db.id;
        li.classList.add('db-list-item');
        dbListElement.appendChild(li);
    });
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
            // Si por alguna razón llega aquí sin DBs, lo mando a crear una
            window.location.href = '/create-db.php';
        }
    } catch (error) {
        console.error("Error:", error);
        // Si hay un error de sesión, lo mando al login
        window.location.href = '/login.php';
    }
}

document.addEventListener('DOMContentLoaded', init);