import * as api from '../api.js';

document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('loginForm');
    const messageDiv = document.getElementById('message');

    if (!loginForm) return;

    loginForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        const credentials = {
            email: document.getElementById('email').value,
            password: document.getElementById('password').value
        };

        try {
            const result = await api.loginUser(credentials);

            if (result.success) {
                messageDiv.style.color = 'green';
                messageDiv.textContent = '¡Inicio de sesión exitoso! Redirigiendo...';
                window.location.href = '/index.php';
            }
        } catch (error) {
            messageDiv.style.color = 'red';
            messageDiv.textContent = `Error: ${error.message}`;
        }
    });
});