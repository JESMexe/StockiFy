// public/assets/js/database/create-db.js
import * as api from '../api.js';

document.addEventListener('DOMContentLoaded', () => {
    const createDbForm = document.getElementById('createDbForm');
    const messageDiv = document.getElementById('message');

    if (!createDbForm) return;

    createDbForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        const dbName = document.getElementById('dbNameInput').value.trim();
        const columns = document.getElementById('columnsInput').value.trim();
        const button = createDbForm.querySelector('button');

        if (!dbName || !columns) {
            messageDiv.textContent = 'Por favor, completa todos los campos.';
            messageDiv.style.color = 'var(--error-color)';
            return;
        }

        button.disabled = true;
        button.textContent = 'Creando...';

        try {
            const result = await api.createDatabase(dbName, columns);
            if (result.success) {
                messageDiv.style.color = 'var(--success-color)';
                messageDiv.textContent = `${result.message} Serás redirigido a la página principal.`;
                setTimeout(() => {
                    window.location.href = '/index.php'; // Redirigimos al index
                }, 2000);
            }
        } catch (error) {
            messageDiv.style.color = 'var(--error-color)';
            messageDiv.textContent = `Error: ${error.message}`;
            button.disabled = false;
            button.textContent = 'Crear Base de Datos';
        }
    });
});