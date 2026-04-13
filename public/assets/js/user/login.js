import * as api from '../api.js';

document.addEventListener('DOMContentLoaded', function () {
    const loginForm = document.getElementById('loginForm');
    const messageDiv = document.getElementById('message');

    if (!loginForm) return;

    let errorTimeout; // Variable para trackear el tiempo del mensaje

    loginForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        clearTimeout(errorTimeout); // Limpiamos el mensaje anterior si hace clic rápido
        messageDiv.textContent = '';

        const credentials = {
            email: document.getElementById('email').value,
            password: document.getElementById('password').value
        };

        try {
            const result = await api.loginUser(credentials);

            if (result.success) {
                messageDiv.style.paddingTop = '2px';
                messageDiv.style.color = 'var(--accent-green)';
                messageDiv.textContent = '¡Inicio de sesión exitoso! Redirigiendo...';
                window.location.href = 'index';
            } else {
                messageDiv.style.paddingTop = '2px';
                messageDiv.style.color = 'var(--accent-red)';
                messageDiv.textContent = 'Correo o contraseña incorrectos.';

                errorTimeout = setTimeout(() => {
                    messageDiv.textContent = '';
                }, 3000);
            }
        } catch (error) {
            messageDiv.style.paddingTop = '2px';
            messageDiv.style.color = 'var(--accent-red)';
            messageDiv.textContent = 'Error al iniciar sesión. Consulte con el administrador.';

            errorTimeout = setTimeout(() => {
                messageDiv.textContent = '';
            }, 3000);
        }
    });
});