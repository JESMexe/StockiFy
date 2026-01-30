import { pop_ups } from './notifications/pop-up.js';

document.addEventListener('DOMContentLoaded', () => {
    initTabs();
    initGeneralProfile();
    initSecurityHandlers();

    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');

    if (tab === 'soporte') {
        const btnSoporte = document.getElementById('btn-config-soporte');
        btnSoporte?.click();
    }
});

function initTabs() {
    const btnCuenta = document.getElementById('btn-config-cuenta');
    const btnSoporte = document.getElementById('btn-config-soporte');
    const containerCuenta = document.getElementById('config-container-cuenta');
    const containerSoporte = document.getElementById('soporte-container');

    const switchTab = (activeBtn, inactiveBtn, showContainer, hideContainer) => {
        // 1. Manejo de Clases en los Botones
        activeBtn.classList.add('btn-option-selected');
        inactiveBtn.classList.remove('btn-option-selected');

        // 2. Manejo de Visibilidad de Contenedores
        showContainer.classList.remove('hidden');
        hideContainer.classList.add('hidden');
    };

    btnCuenta?.addEventListener('click', () => switchTab(btnCuenta, btnSoporte, containerCuenta, containerSoporte));
    btnSoporte?.addEventListener('click', () => switchTab(btnSoporte, btnCuenta, containerSoporte, containerCuenta));
}

function initSecurityHandlers() {
    const btnPass = document.getElementById('btn-change-password');
    if (btnPass) {
        btnPass.addEventListener('click', async () => {
            const { isConfirmed } = await Swal.fire({
                title: '¿Cambiar contraseña?',
                text: "Te enviaremos un código de seguridad a tu email actual.",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sí, enviar código',
                cancelButtonText: 'Cancelar'
            });

            if (isConfirmed) {
                try {
                    const res = await fetch('./../api/auth/init-password-change.php');
                    const data = await res.json();

                    if (data.success) {
                        const { value: formValues } = await Swal.fire({
                            title: 'Verificación de Seguridad',
                            html:
                                '<p style="font-size:0.8rem;margin-bottom:10px;">Ingresa el código enviado y tu nueva clave</p>' +
                                '<input id="otp-code" class="swal2-input" placeholder="Código de 6 dígitos" maxlength="6" inputmode="numeric">' +
                                '<input id="new-password-input" type="password" class="swal2-input" placeholder="Nueva Contraseña (mín. 6 caracteres)">',
                            focusConfirm: false,
                            preConfirm: () => {
                                const code = document.getElementById('otp-code').value.trim();
                                const pass = document.getElementById('new-password-input').value.trim();

                                // VALIDACIÓN INTERNA DEL CARTEL
                                if (!code || code.length !== 6) {
                                    Swal.showValidationMessage('El código debe tener exactamente 6 dígitos');
                                    return false;
                                }
                                if (pass.length < 6) {
                                    Swal.showValidationMessage('La nueva contraseña debe tener al menos 6 caracteres');
                                    return false;
                                }
                                return { code: code, new_password: pass };
                            }
                        });

                        if (formValues) {
                            // Enviar al servidor
                            const resFinal = await fetch('./../api/auth/finalize-password-change.php', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify(formValues) // Enviamos {code, new_password}
                            });
                            const dataFinal = await resFinal.json();

                            if (dataFinal.success) {
                                Swal.fire('¡Éxito!', dataFinal.message, 'success');
                            } else {
                                Swal.fire('Error', dataFinal.message, 'error');
                            }
                        }
                    } else {
                        Swal.fire('Error', data.message, 'error');
                    }
                } catch (e) {
                    Swal.fire('Error', 'No se pudo conectar con el servidor', 'error');
                }
            }
        });
    }
}


function initGeneralProfile() {
    const form = document.getElementById('form-micuenta');
    const btnGuardar = document.getElementById('btn-guardar');

    const inputs = {
        username: document.getElementById('username'),
        full_name: document.getElementById('full_name'),
        dni: document.getElementById('dni'),
        cell: document.getElementById('cell')
    };

    const original = window.userData || {};

    const checkChanges = () => {
        let hasChanges = false;
        for (const [key, input] of Object.entries(inputs)) {
            if (input && input.value.trim() !== (original[key] || '')) {
                hasChanges = true;
                break;
            }
        }
        btnGuardar.disabled = !hasChanges;
        btnGuardar.textContent = hasChanges ? "Guardar Cambios de Perfil" : "Sin cambios pendientes";
    };

    Object.values(inputs).forEach(input => input?.addEventListener('input', checkChanges));

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        btnGuardar.disabled = true;
        btnGuardar.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Guardando...';

        const payload = {
            username: inputs.username.value.trim(),
            full_name: inputs.full_name.value.trim(),
            dni: inputs.dni.value.trim(),
            cell: inputs.cell.value.trim()
        };

        try {
            const response = await fetch('/api/user/update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });
            const result = await response.json();

            if (result.success) {
                pop_ups.success("Perfil Guardado");
                window.userData = { ...window.userData, ...payload };
                checkChanges();
            } else {
                throw new Error(result.message);
            }
        } catch (error) {
            pop_ups.error(error.message, "Error al guardar");
            btnGuardar.disabled = false;
            btnGuardar.textContent = "Guardar Cambios de Perfil";
        }
    });
}