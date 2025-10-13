import { elements } from './state.js';
import { showView, showStatus, populateDbList } from './ui.js';
import * as api from './api.js';

// --- Handlers (Lógica de eventos) ---

async function handleSelectDatabase(dbName) {
    showView('loading');
    try {
        const result = await api.selectDatabase(dbName);
        elements.activeDbName.textContent = result.selectedDb;
        showView('main');
    } catch (error) {
        showStatus(error.message, 'error');
        await checkInitialState(); // Volver a la pantalla de selección si hay error
    }
}

async function handleCreateDatabase() {
    const dbName = elements.dbNameInput.value.trim();
    const columns = elements.columnsInput.value.trim();

    if (!dbName || !columns) {
        showStatus('Por favor, complete el nombre y las columnas.', 'error');
        return;
    }

    showStatus('Creando base de datos...', 'info');

    try {
        const result = await api.createDatabase(dbName, columns);
        showStatus(result.message, 'success');
        await handleSelectDatabase(dbName); // Seleccionar automáticamente después de crear
    } catch (error) {
        showStatus(error.message, 'error');
    }
}

// --- Inicialización de la Aplicación ---

function initializeEventListeners() {
    elements.createDbBtn.addEventListener('click', handleCreateDatabase);
    elements.goToCreateBtn.addEventListener('click', () => showView('firstTime'));
    elements.changeDbBtn.addEventListener('click', () => checkInitialState());
    elements.viewDbBtn.addEventListener('click', () => {
        showStatus('Función "Ver Base de Datos" aún no implementada.', 'info');
        showView('dbData');
    });
    elements.backToMainMenuBtn.addEventListener('click', () => showView('main'));
}

async function checkInitialState() {
    showView('loading');
    try {
        const databases = await api.getDatabases();
        if (databases && databases.length > 0) {
            populateDbList(databases, handleSelectDatabase);
            showView('selection');
        } else {
            showView('firstTime');
        }
    } catch (error) {
        showStatus(error.message, 'error');
    }
}

async function init() {
    const isLoggedIn = await api.checkSessionStatus()
    
    if(isLoggedIn){
        initializeEventListeners();
        checkInitialState(); // eslint-disable-line @typescript-eslint/no-floating-promises
    }
    else {
        window.location.href = 'login.php'
    }
}

// Iniciar la aplicación cuando el DOM esté listo
document.addEventListener('DOMContentLoaded', init);