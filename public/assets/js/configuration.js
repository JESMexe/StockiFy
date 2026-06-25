import { pop_ups } from './notifications/pop-up.js?v=3.0';

document.addEventListener('DOMContentLoaded', () => {
    initTabs();
    initGeneralProfile();
    initSecurityHandlers();
    initRemitoHandlers();
    initCatalogHandlers();

    const urlParams = new URLSearchParams(window.location.search);
    const tab = urlParams.get('tab');

    if (tab === 'soporte') {
        const btnSoporte = document.getElementById('btn-config-soporte');
        btnSoporte?.click();
    } else if (tab === 'remito') {
        const btnRemito = document.getElementById('btn-config-remito');
        btnRemito?.click();
    } else if (tab === 'catalogo') {
        const btnCatalogo = document.getElementById('btn-config-catalogo');
        btnCatalogo?.click();
    }
});

function initTabs() {
    const tabs = [
        { btn: document.getElementById('btn-config-cuenta'), container: document.getElementById('config-container-cuenta') },
        { btn: document.getElementById('btn-config-remito'), container: document.getElementById('remito-container') },
        { btn: document.getElementById('btn-config-catalogo'), container: document.getElementById('catalogo-container') },
        { btn: document.getElementById('btn-config-soporte'), container: document.getElementById('soporte-container') }
    ];

    tabs.forEach(tab => {
        if (!tab.btn) return;
        tab.btn.addEventListener('click', () => {
            tabs.forEach(t => {
                if (t.btn) t.btn.classList.remove('btn-option-selected');
                if (t.container) t.container.classList.add('hidden');
            });
            tab.btn.classList.add('btn-option-selected');
            tab.container?.classList.remove('hidden');
        });
    });
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
            if (!input) continue;
            
            const isChanged = input.value.trim() !== (original[key] || '');
            if (isChanged) hasChanges = true;
            
            // Toggle hint for this specific input
            const container = input.closest('.rustic-block');
            if (container) {
                const hint = container.querySelector('.unsaved-hint');
                if (hint) {
                    if (isChanged) hint.classList.remove('hidden');
                    else hint.classList.add('hidden');
                }
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
                Swal.fire({
                    title: '¡Guardado!',
                    text: 'Tus datos se actualizaron correctamente.',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                });
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

function initRemitoHandlers() {
    const form = document.getElementById('form-remito');
    if (!form) return;

    const descInput = document.getElementById('remito_description');
    const urlInput = document.getElementById('remito_url');
    const fileInput = document.getElementById('remito_logo_input');
    const previewImg = document.getElementById('remito-logo-preview');
    const placeholder = document.getElementById('remito-logo-placeholder');
    const btnDeleteLogo = document.getElementById('btn-delete-logo');
    const btnGuardar = document.getElementById('btn-guardar-remito');

    const original = window.inventoryData || {};

    let logoChanged = false;
    let logoFile = null;
    let logoDeleted = false;

    // Load initial values
    if (descInput) descInput.value = original.remito_description || '';
    if (urlInput) urlInput.value = original.remito_url || '';
    if (original.remito_logo && previewImg && placeholder && btnDeleteLogo) {
        previewImg.src = original.remito_logo;
        previewImg.style.display = 'block';
        placeholder.style.display = 'none';
        btnDeleteLogo.style.display = 'block';
    }

    const checkChanges = () => {
        let hasChanges = false;

        const descVal = descInput ? descInput.value.trim() : '';
        const urlVal = urlInput ? urlInput.value.trim() : '';

        const descChanged = descVal !== (original.remito_description || '');
        const urlChanged = urlVal !== (original.remito_url || '');

        if (descChanged || urlChanged || logoChanged) {
            hasChanges = true;
        }

        // Toggle hints
        if (descInput) {
            const hint = descInput.closest('.rustic-block')?.querySelector('.unsaved-hint');
            if (hint) {
                if (descChanged) hint.classList.remove('hidden');
                else hint.classList.add('hidden');
            }
        }
        if (urlInput) {
            const hint = urlInput.closest('.rustic-block')?.querySelector('.unsaved-hint');
            if (hint) {
                if (urlChanged) hint.classList.remove('hidden');
                else hint.classList.add('hidden');
            }
        }

        if (btnGuardar) {
            btnGuardar.disabled = !hasChanges;
            btnGuardar.textContent = hasChanges ? "Guardar Configuración de Remito" : "Sin cambios pendientes";
        }
    };

    // File Input Selection
    fileInput?.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                if (previewImg) {
                    previewImg.src = e.target.result;
                    previewImg.style.display = 'block';
                }
                if (placeholder) placeholder.style.display = 'none';
                if (btnDeleteLogo) btnDeleteLogo.style.display = 'block';
            };
            reader.readAsDataURL(file);

            logoFile = file;
            logoDeleted = false;
            logoChanged = true;
            checkChanges();
        }
    });

    // Delete Logo button
    btnDeleteLogo?.addEventListener('click', () => {
        if (fileInput) fileInput.value = '';
        if (previewImg) {
            previewImg.src = '';
            previewImg.style.display = 'none';
        }
        if (placeholder) placeholder.style.display = 'block';
        if (btnDeleteLogo) btnDeleteLogo.style.display = 'none';

        logoFile = null;
        logoDeleted = true;
        logoChanged = true;
        checkChanges();
    });

    descInput?.addEventListener('input', checkChanges);
    urlInput?.addEventListener('input', checkChanges);

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        if (!btnGuardar) return;

        btnGuardar.disabled = true;
        btnGuardar.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Guardando...';

        const formData = new FormData();
        formData.append('remito_description', descInput ? descInput.value.trim() : '');
        formData.append('remito_url', urlInput ? urlInput.value.trim() : '');
        if (logoFile) {
            formData.append('remito_logo', logoFile);
        }
        if (logoDeleted) {
            formData.append('delete_logo', 1);
        }

        try {
            const response = await fetch('/api/inventory/update-remito-settings.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();

            if (result.success) {
                Swal.fire({
                    title: '¡Guardado!',
                    text: 'La configuración de tu remito se actualizó correctamente.',
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                });

                // Update original state references
                window.inventoryData = {
                    remito_logo: result.remito_logo_path || '',
                    remito_description: result.remito_description || '',
                    remito_url: result.remito_url || ''
                };

                original.remito_logo = result.remito_logo_path || '';
                original.remito_description = result.remito_description || '';
                original.remito_url = result.remito_url || '';

                logoChanged = false;
                logoFile = null;
                logoDeleted = false;

                checkChanges();
            } else {
                throw new Error(result.message || 'Error desconocido.');
            }
        } catch (error) {
            pop_ups.error(error.message, "Error al guardar");
            btnGuardar.disabled = false;
            btnGuardar.textContent = "Guardar Configuración de Remito";
        }
    });
}


function initCatalogHandlers() {
    const form = document.getElementById('form-catalogo');
    if (!form) return;

    const catalogData = window.catalogData || {};
    const inventoryId = catalogData.inventory_id;

    const activeSwitch = document.getElementById('catalog_active_switch');
    const settingsBody = document.getElementById('catalog-settings-body');
    const slugInput    = document.getElementById('catalog_slug');
    const slugIcon     = document.getElementById('slug-status-icon');
    const slugFeedback = document.getElementById('slug-feedback');
    const urlPreview   = document.getElementById('catalog-url-preview');
    const urlLink      = document.getElementById('catalog-url-link');
    const copyBtn      = document.getElementById('btn-copy-catalog-url');
    const btnGuardar   = document.getElementById('btn-guardar-catalogo');

    // Toggle active/inactive visual state
    activeSwitch?.addEventListener('change', () => {
        if (settingsBody) {
            settingsBody.style.opacity = activeSwitch.checked ? '1' : '0.5';
            settingsBody.style.pointerEvents = activeSwitch.checked ? 'auto' : 'none';
        }
    });

    // --- Slug validation with debounce ---
    let slugTimeout = null;
    let slugValid   = true;

    const validateSlug = async (value) => {
        const slug = value.toLowerCase().trim();
        slugInput.value = slug;

        if (!slug) {
            slugIcon.textContent = '';
            slugFeedback.textContent = '';
            urlPreview.style.display = 'none';
            return;
        }

        if (!/^[a-z0-9][a-z0-9\-]*[a-z0-9]$/.test(slug) && !/^[a-z0-9]{1,2}$/.test(slug)) {
            slugIcon.textContent = '✗';
            slugIcon.style.color = '#ef4444';
            slugFeedback.style.color = '#ef4444';
            slugFeedback.textContent = 'Solo letras minúsculas, números y guiones. No puede empezar ni terminar con guión.';
            slugValid = false;
            urlPreview.style.display = 'none';
            return;
        }

        slugIcon.textContent = '⏳';
        slugFeedback.style.color = '#64748b';
        slugFeedback.textContent = 'Verificando disponibilidad...';

        try {
            const res = await fetch(`/api/catalog/check-slug.php?slug=${encodeURIComponent(slug)}&inventory_id=${inventoryId}`);
            const data = await res.json();

            if (data.available) {
                slugIcon.textContent = '✓';
                slugIcon.style.color = '#16a34a';
                slugFeedback.style.color = '#16a34a';
                slugFeedback.textContent = '¡Disponible!';
                slugValid = true;

                // Update URL preview
                const fullUrl = `stockify.com.ar/catalogo/${slug}`;
                if (urlLink) { urlLink.href = `/catalogo/${slug}`; urlLink.textContent = fullUrl; }
                if (urlPreview) urlPreview.style.display = 'flex';
            } else {
                slugIcon.textContent = '✗';
                slugIcon.style.color = '#ef4444';
                slugFeedback.style.color = '#ef4444';
                slugFeedback.textContent = data.error || 'Este slug ya está en uso. Elegí otro.';
                slugValid = false;
                urlPreview.style.display = 'none';
            }
        } catch {
            slugIcon.textContent = '';
            slugFeedback.textContent = '';
            slugValid = true; // Allow save attempt
        }
    };

    slugInput?.addEventListener('input', (e) => {
        clearTimeout(slugTimeout);
        slugTimeout = setTimeout(() => validateSlug(e.target.value), 500);
    });

    // Copy URL button
    copyBtn?.addEventListener('click', async () => {
        const url = urlLink?.href || '';
        if (!url) return;
        try {
            await navigator.clipboard.writeText(window.location.origin + `/catalogo/${slugInput.value.trim()}`);
            copyBtn.innerHTML = '<i class="ph ph-check"></i> Copiado';
            setTimeout(() => { copyBtn.innerHTML = '<i class="ph ph-copy"></i> Copiar'; }, 2000);
        } catch {
            copyBtn.textContent = 'Error';
        }
    });

    // --- Logo Upload ---
    const catalogLogoInput = document.getElementById('catalog_logo_input');
    const catalogLogoPreview = document.getElementById('catalog-logo-preview');
    const catalogLogoPlaceholder = document.getElementById('catalog-logo-placeholder');
    const btnDeleteCatalogLogo = document.getElementById('btn-delete-catalog-logo');
    const catalogLogoUrlInput = document.getElementById('catalog_logo_url');

    let catalogLogoFile = null;
    let catalogLogoDeleted = false;

    catalogLogoInput?.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = (e) => {
                if (catalogLogoPreview) {
                    catalogLogoPreview.src = e.target.result;
                    catalogLogoPreview.style.display = 'block';
                }
                if (catalogLogoPlaceholder) catalogLogoPlaceholder.style.display = 'none';
                if (btnDeleteCatalogLogo) btnDeleteCatalogLogo.style.display = 'block';
            };
            reader.readAsDataURL(file);
            catalogLogoFile = file;
            catalogLogoDeleted = false;
        }
    });

    btnDeleteCatalogLogo?.addEventListener('click', () => {
        if (catalogLogoInput) catalogLogoInput.value = '';
        if (catalogLogoPreview) {
            catalogLogoPreview.src = '';
            catalogLogoPreview.style.display = 'none';
        }
        if (catalogLogoPlaceholder) catalogLogoPlaceholder.style.display = 'block';
        if (btnDeleteCatalogLogo) btnDeleteCatalogLogo.style.display = 'none';
        if (catalogLogoUrlInput) catalogLogoUrlInput.value = '';
        catalogLogoFile = null;
        catalogLogoDeleted = true;
    });

    // --- Form Submit ---
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const isActive = activeSwitch?.checked ?? false;
        const slug     = slugInput?.value.trim() ?? '';

        if (isActive && !slug) {
            Swal.fire('Campo requerido', 'Ingresá un slug para poder activar el catálogo.', 'warning');
            return;
        }

        if (isActive && !slugValid) {
            Swal.fire('Slug inválido', 'Revisá el slug antes de guardar.', 'warning');
            return;
        }

        btnGuardar.disabled = true;
        btnGuardar.innerHTML = '<i class="ph ph-spinner ph-spin"></i> Guardando...';

        let finalLogoUrl = catalogLogoUrlInput?.value || '';

        // If there's a new logo file, upload it first
        if (catalogLogoFile) {
            const formData = new FormData();
            formData.append('image', catalogLogoFile);
            formData.append('inventory_id', inventoryId);
            try {
                const uploadRes = await fetch('/api/catalog/upload-image.php', {
                    method: 'POST',
                    body: formData
                });
                const uploadData = await uploadRes.json();
                if (uploadData.success) {
                    finalLogoUrl = uploadData.url;
                    if (catalogLogoUrlInput) catalogLogoUrlInput.value = finalLogoUrl;
                } else {
                    throw new Error(uploadData.error || 'Error al subir la imagen del logo.');
                }
            } catch (err) {
                pop_ups.error(err.message, 'Error al subir logo');
                btnGuardar.disabled = false;
                btnGuardar.innerHTML = '<i class="ph ph-floppy-disk"></i> Guardar Configuración del Catálogo';
                return;
            }
        } else if (catalogLogoDeleted) {
            finalLogoUrl = '';
        }

        const payload = {
            inventory_id:     inventoryId,
            catalog_active:   isActive,
            catalog_slug:     slug,
            whatsapp:         document.getElementById('catalog_whatsapp')?.value.trim()  ?? '',
            instagram:        document.getElementById('catalog_instagram')?.value.trim() ?? '',
            address:          document.getElementById('catalog_address')?.value.trim()   ?? '',
            logo_url:         finalLogoUrl,
            show_price:       document.getElementById('catalog_show_price')?.checked     ?? true,
            show_exact_stock: document.getElementById('catalog_show_stock')?.checked     ?? true,
            show_action_button: document.getElementById('catalog_show_action_button')?.checked ?? true,
            button_text:      document.getElementById('catalog_button_text')?.value.trim() ?? '',
            button_link:      document.getElementById('catalog_button_link')?.value.trim() ?? '',
            button_icon:      document.getElementById('catalog_button_icon')?.value ?? 'ph-whatsapp-logo',
            button_color:     document.getElementById('catalog_button_color')?.value ?? 'whatsapp-green',
            theme_color:      document.getElementById('catalog_theme_color')?.value ?? 'accent-color',
            theme_pattern:    document.getElementById('catalog_theme_pattern')?.value ?? 'dots',
        };

        try {
            const res  = await fetch('/api/catalog/save-catalog-settings.php', {
                method:  'POST',
                headers: { 'Content-Type': 'application/json' },
                body:    JSON.stringify(payload),
            });
            const data = await res.json();

            if (data.success) {
                let confirmHtml = '¡Configuración guardada correctamente!';
                if (data.public_url) {
                    confirmHtml += `<br><br><a href="/catalogo/${encodeURIComponent(slug)}" target="_blank"
                        style="color:var(--accent-color); font-weight:600;">Ver mi catálogo →</a>`;
                }
                Swal.fire({
                    title:             isActive ? '¡Catálogo activo!' : '¡Guardado!',
                    html:              confirmHtml,
                    icon:              'success',
                    confirmButtonText: 'Aceptar',
                });
                // Update URL preview with saved slug
                if (slug && urlLink && urlPreview) {
                    const fullUrl = `stockify.com.ar/catalogo/${slug}`;
                    urlLink.href        = `/catalogo/${slug}`;
                    urlLink.textContent = fullUrl;
                    urlPreview.style.display = 'flex';
                }
            } else {
                throw new Error(data.error || 'Error desconocido.');
            }
        } catch (err) {
            pop_ups.error(err.message, 'Error al guardar');
        } finally {
            btnGuardar.disabled = false;
            btnGuardar.innerHTML = '<i class="ph ph-floppy-disk"></i> Guardar Configuración del Catálogo';
        }
    });
}
