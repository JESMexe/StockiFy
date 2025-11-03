// public/assets/js/database/create-db.js
import * as api from '../api.js';
import { openImportModal, initializeImportModal, setStockifyColumns } from '../import.js';

document.addEventListener('DOMContentLoaded', () => {
    initializeImportModal();

    const createDbForm = document.getElementById('createDbForm');
    const messageDiv = document.getElementById('message');
    const prepareImportBtn = document.getElementById('prepare-import-btn');
    const importStatusDiv = document.getElementById('import-prepared-status');

    if (!createDbForm || !prepareImportBtn) return;

    prepareImportBtn.addEventListener('click', () => {
        const columnsInputValue = document.getElementById('columnsInput')?.value.trim();
        const cols = columnsInputValue ? columnsInputValue.split(',').map(s => s.trim()).filter(s => s) : [];

        if (cols.length === 0) {
            alert("Por favor, primero definí las columnas (separadas por coma) antes de importar.");
            return; // No abro el modal
        }

        setStockifyColumns(cols);
        openImportModal();
    });

    createDbForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        const dbName = document.getElementById('dbNameInput').value.trim();
        const columns = document.getElementById('columnsInput').value.trim();
        const submitButton = createDbForm.querySelector('button[type="submit"]');

        if (!dbName || !columns) {
            messageDiv.textContent = 'Por favor, completa el nombre y las columnas.';
            return;
        }

        submitButton.disabled = true;
        submitButton.textContent = 'Creando...';
        messageDiv.textContent = '';

        try {
            const result = await api.createDatabase(dbName, columns);

            if (result.success) {
                messageDiv.textContent = result.message + "\nSerás redirigido al panel.";
                setTimeout(() => {
                    window.location.href = '/dashboard.php';
                }, 2000); // Le doy 2 segs para leer
            } else {
                messageDiv.textContent = `Error: ${result.message}`;
            }
        } catch (error) {
            messageDiv.textContent = `Error: ${error.message}`;
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = 'Crear Base de Datos';
        }
    });

    window.updateImportStatus = (message) => {
        if(importStatusDiv) {
            importStatusDiv.textContent = message;
            prepareImportBtn.textContent = "Modificar Importación CSV";
        }
    }
});