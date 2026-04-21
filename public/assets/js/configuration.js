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
        activeBtn.classList.add('btn-option-selected');
        inactiveBtn.classList.remove('btn-option-selected');

        showContainer.classList.remove('hidden');
        hideContainer.classList.add('hidden');
    };

    btnCuenta?.addEventListener('click', () => switchTab(btnCuenta, btnSoporte, containerCuenta, containerSoporte));
    btnSoporte?.addEventListener('click', () => switchTab(btnSoporte, btnCuenta, containerSoporte, containerCuenta));
}

function initSecurityHandlers() {
    const btnPass = document.getElementById('btn-change-password');

    if (!btnPass) return;

    btnPass.addEventListener('click', async () => {
        const { isConfirmed } = await Swal.fire({
            title: '¿Cambiar contraseña?',
            text: 'Te enviaremos un código de seguridad a tu email actual.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, enviar código',
            cancelButtonText: 'Cancelar'
        });

        if (!isConfirmed) return;

        try {
            const res = await fetch('./../api/auth/init-password-change', {
                method: 'POST',
                headers: { 'Accept': 'application/json' }
            });

            const data = await res.json();

            if (!res.ok || !data.success) {
                Swal.fire('Error', data.message || 'No se pudo iniciar el cambio de contraseña.', 'error');
                return;
            }

            const { value: formValues } = await Swal.fire({
                title: 'Verificación de Seguridad',
                customClass: {
                    popup: 'stockify-otp-popup'
                },
                html: `
        <p class="stockify-otp-text">Ingresá el código enviado y tu nueva clave</p>

        <div class="stockify-otp-group" id="stockify-otp-group">
            <input class="stockify-otp-slot" type="text" inputmode="numeric" maxlength="1" autocomplete="one-time-code">
            <input class="stockify-otp-slot" type="text" inputmode="numeric" maxlength="1">
            <input class="stockify-otp-slot" type="text" inputmode="numeric" maxlength="1">
            <input class="stockify-otp-slot" type="text" inputmode="numeric" maxlength="1">
            <input class="stockify-otp-slot" type="text" inputmode="numeric" maxlength="1">
            <input class="stockify-otp-slot" type="text" inputmode="numeric" maxlength="1">
        </div>

        <input
            id="new-password-input"
            type="password"
            class="swal2-input stockify-password-input"
            placeholder="Nueva contraseña (mín. 8 caracteres)"
            autocomplete="new-password"
        >
    `,
                focusConfirm: false,
                confirmButtonText: 'Actualizar contraseña',
                showCancelButton: true,
                cancelButtonText: 'Cancelar',
                showLoaderOnConfirm: true,
                didOpen: () => {
                    const slots = Array.from(document.querySelectorAll('.stockify-otp-slot'));
                    const passwordInput = document.getElementById('new-password-input');

                    if (slots.length > 0) {
                        slots[0].focus();
                    }

                    slots.forEach((slot, index) => {
                        slot.addEventListener('input', (e) => {
                            let value = e.target.value.replace(/\D/g, '');
                            e.target.value = value;

                            if (value && index < slots.length - 1) {
                                slots[index + 1].focus();
                                slots[index + 1].select();
                            }
                        });

                        slot.addEventListener('keydown', (e) => {
                            if (e.key === 'Backspace' && !slot.value && index > 0) {
                                slots[index - 1].focus();
                                slots[index - 1].value = '';
                            }

                            if (e.key === 'ArrowLeft' && index > 0) {
                                e.preventDefault();
                                slots[index - 1].focus();
                            }

                            if (e.key === 'ArrowRight' && index < slots.length - 1) {
                                e.preventDefault();
                                slots[index + 1].focus();
                            }
                        });

                        slot.addEventListener('paste', (e) => {
                            e.preventDefault();
                            const pasted = (e.clipboardData || window.clipboardData)
                                .getData('text')
                                .replace(/\D/g, '')
                                .slice(0, 6);

                            if (!pasted) return;

                            pasted.split('').forEach((digit, i) => {
                                if (slots[i]) slots[i].value = digit;
                            });

                            const nextIndex = Math.min(pasted.length, slots.length - 1);
                            if (slots[nextIndex]) {
                                slots[nextIndex].focus();
                                slots[nextIndex].select();
                            }

                            if (pasted.length === 6 && passwordInput) {
                                passwordInput.focus();
                            }
                        });
                    });
                },
                preConfirm: async () => {
                    const slots = Array.from(document.querySelectorAll('.stockify-otp-slot'));
                    const code = slots.map(slot => slot.value.trim()).join('');
                    const pass = document.getElementById('new-password-input')?.value || '';

                    if (!/^\d{6}$/.test(code)) {
                        Swal.showValidationMessage('El código debe tener exactamente 6 dígitos');
                        return false;
                    }

                    if (pass.length < 8) {
                        Swal.showValidationMessage('La nueva contraseña debe tener al menos 8 caracteres');
                        return false;
                    }

                    try {
                        const res = await fetch('./../api/auth/finalize-password-change', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json'
                            },
                            body: JSON.stringify({ code, new_password: pass })
                        });
                        
                        const data = await res.json();
                        if (!res.ok || !data.success) {
                            Swal.showValidationMessage(data.message || 'Error al actualizar la contraseña');
                            return false;
                        }
                        
                        return data;
                    } catch (e) {
                         Swal.showValidationMessage('No se pudo conectar con el servidor.');
                         return false;
                    }
                }
            });

            if (!formValues) return;

            Swal.fire('¡Éxito!', formValues.message || 'Contraseña actualizada correctamente.', 'success');

        } catch (e) {
            Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
        }
    });
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

    const countrySel = document.getElementById('cell_country');
    const prefixSel = document.getElementById('cell_prefix');
    const numberInput = document.getElementById('cell_number');

    if (inputs.cell && numberInput) {
        let currentVal = inputs.cell.value.trim();
        if (currentVal.startsWith('549')) {
            numberInput.value = currentVal.substring(3);
        } else {
            numberInput.value = currentVal; // Caso legado si guardó mal
        }

        const syncCell = () => {
            let num = numberInput.value.replace(/\D/g, ''); // Solo numeros permitidos
            if (num === '') {
                inputs.cell.value = '';
            } else {
                inputs.cell.value = countrySel.value + prefixSel.value + num;
            }
            inputs.cell.dispatchEvent(new Event('input')); // Dispara checkChanges
        };

        countrySel?.addEventListener('change', syncCell);
        prefixSel?.addEventListener('change', syncCell);
        numberInput?.addEventListener('input', syncCell);
    }

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
            const response = await fetch('/api/user/update', {
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

