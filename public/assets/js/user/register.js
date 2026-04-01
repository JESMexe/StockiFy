import * as api from '../api.js';

document.addEventListener('DOMContentLoaded', function () {
    const registerForm = document.getElementById('registerForm');
    const messageDiv = document.getElementById('message');

    if (!registerForm) return;

    let errorTimeout;

    registerForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        clearTimeout(errorTimeout);
        messageDiv.textContent = '';

        const formData = new FormData(registerForm);
        const userData = Object.fromEntries(formData.entries());

        try {
            const result = await api.registerUser(userData);

            if (result.success) {
                messageDiv.style.paddingTop = '2px';
                messageDiv.style.color = 'var(--accent-green)';
                messageDiv.textContent = '¡Registro exitoso! Redirigiendo...';
                setTimeout(() => { window.location.href = '/login.php'; }, 2000);
            } else {
                messageDiv.style.paddingTop = '2px';
                messageDiv.style.color = 'var(--accent-red)';
                messageDiv.textContent = result.message || 'Error al intentar registrarse.';

                errorTimeout = setTimeout(() => {
                    messageDiv.textContent = '';
                }, 4000);
            }
        } catch (error) {
            messageDiv.style.paddingTop = '2px';
            messageDiv.style.color = 'var(--accent-red)';
            messageDiv.textContent = `Error: ${error.message}`;

            errorTimeout = setTimeout(() => {
                messageDiv.textContent = '';
            }, 4000);
        }
    });
});