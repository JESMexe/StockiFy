// public/assets/js/database/create-db.js
import * as api from '../api.js';
// Importamos funciones específicas del modal
import { openImportModal, initializeImportModal } from '../import.js';

document.addEventListener('DOMContentLoaded', () => {
    // Inicializa el modal (busca sus elementos)
    initializeImportModal();

    const createDbForm = document.getElementById('createDbForm');
    const messageDiv = document.getElementById('message');
    const prepareImportBtn = document.getElementById('prepare-import-btn');
    const importStatusDiv = document.getElementById('import-prepared-status'); // Para mostrar si los datos están listos

    if (!createDbForm || !prepareImportBtn) return;

    // --- Event Listener para ABRIR EL MODAL ---
    prepareImportBtn.addEventListener('click', () => {
        // Antes de abrir, podríamos pasarle las columnas actuales al modal si es necesario
        openImportModal();
    });

    // --- Event Listener para el ENVÍO FINAL ---
    createDbForm.addEventListener('submit', async (event) => {
        event.preventDefault(); // Detenemos el envío normal

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
                window.location.href = '/dashboard.php';
            } else {
                messageDiv.textContent = `Error: ${result.message}`;
            }
        } catch (error) {
            // Si hay un error de red o un 500
            messageDiv.textContent = `Error: ${error.message}`;
        } finally {
            submitButton.disabled = false;
            submitButton.textContent = 'Crear Base de Datos';
        }
    });

    // Función global para que import.js pueda actualizar el estado
    window.updateImportStatus = (message) => {
        if(importStatusDiv) {
            importStatusDiv.textContent = message;
            prepareImportBtn.textContent = "Modificar Importación CSV";
        }
    }
});