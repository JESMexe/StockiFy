import * as api from '../api.js';

document.addEventListener('DOMContentLoaded', function() {
    const registerForm = document.getElementById('registerForm');
    const messageDiv = document.getElementById('message');

    if (!registerForm) return;

    registerForm.addEventListener('submit', async (event) => {
        event.preventDefault();

        const formData = new FormData(registerForm);
        const userData = Object.fromEntries(formData.entries());

        try {
            const result = await api.registerUser(userData);

            if (result.success) {
                messageDiv.style.color = 'green';
                messageDiv.textContent = '¡Registro exitoso! Redirigiendo...';
                setTimeout(() => { window.location.href = '/login.php'; }, 2000);
            }
        } catch (error) {
            messageDiv.style.color = 'red';
            messageDiv.textContent = `Error: ${error.message}`;
        }
    });
});