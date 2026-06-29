import { pop_ups } from './notifications/pop-up.js?v=3.0';

// Shared logo state (accessible by both customizer panel and form submit)
let _sharedCatalogLogoFile    = null;
let _sharedCatalogLogoDeleted = false;

document.addEventListener('DOMContentLoaded', () => {
    initTabs();
    initGeneralProfile();
    initSecurityHandlers();
    initRemitoHandlers();
    initCatalogHandlers();
    initCatalogCustomizerPanel();

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

    // --- Logo Upload --- (handled by initCatalogCustomizerPanel, shares _sharedCatalogLogoFile)
    const catalogLogoUrlInput = document.getElementById('catalog_logo_url');

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
        if (_sharedCatalogLogoFile) {
            const formData = new FormData();
            formData.append('image', _sharedCatalogLogoFile);
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
        } else if (_sharedCatalogLogoDeleted) {
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
            // Colorimetría
            color_bg:      (() => { const s = document.getElementById('catalog_color_bg'); return s?.value === 'custom' ? (document.getElementById('catalog_color_bg_custom')?.value || '#F4F4F6') : (s?.value || '#F4F4F6'); })(),
            color_pattern: (() => { const s = document.getElementById('catalog_color_pattern'); return s?.value === 'custom' ? (document.getElementById('catalog_color_pattern_custom')?.value || 'rgba(0,0,0,0.08)') : (s?.value || 'rgba(0,0,0,0.08)'); })(),
            color_card:    (() => { const s = document.getElementById('catalog_color_card'); return s?.value === 'custom' ? (document.getElementById('catalog_color_card_custom')?.value || '#FFFFFF') : (s?.value || '#FFFFFF'); })(),
            color_accent:  (() => { const s = document.getElementById('catalog_color_accent'); return s?.value === 'custom' ? (document.getElementById('catalog_color_accent_custom')?.value || 'theme') : (s?.value || 'theme'); })(),
            color_label:   (() => { const s = document.getElementById('catalog_color_label'); return s?.value === 'custom' ? (document.getElementById('catalog_color_label_custom')?.value || '#8A8A8A') : (s?.value || '#8A8A8A'); })(),
            color_title:   (() => { const s = document.getElementById('catalog_color_title'); return s?.value === 'custom' ? (document.getElementById('catalog_color_title_custom')?.value || '#1A1A1A') : (s?.value || '#1A1A1A'); })(),
            color_price:   (() => { const s = document.getElementById('catalog_color_price'); return s?.value === 'custom' ? (document.getElementById('catalog_color_price_custom')?.value || '#1A1A1A') : (s?.value || '#1A1A1A'); })(),
            color_header_bg: (() => { const s = document.getElementById('catalog_color_header_bg'); return s?.value === 'custom' ? (document.getElementById('catalog_color_header_bg_custom')?.value || '#FFFFFF') : (s?.value || '#FFFFFF'); })(),
            color_social_bg: (() => { const s = document.getElementById('catalog_color_social_bg'); return s?.value === 'custom' ? (document.getElementById('catalog_color_social_bg_custom')?.value || '#FFFFFF') : (s?.value || '#FFFFFF'); })(),
            color_badge_bg:  (() => { const s = document.getElementById('catalog_color_badge_bg'); return s?.value === 'custom' ? (document.getElementById('catalog_color_badge_bg_custom')?.value || '#A3BE8C') : (s?.value || '#A3BE8C'); })(),
            // Sombras
            shadow_filter_section: document.getElementById('catalog_shadow_filter_section')?.checked ?? true,
            shadow_category_pill:  document.getElementById('catalog_shadow_category_pill')?.checked  ?? true,
            shadow_product_card:   document.getElementById('catalog_shadow_product_card')?.checked   ?? true,
            shadow_modal:          document.getElementById('catalog_shadow_modal')?.checked          ?? true,
            // Tipografía
            font_family:           document.getElementById('catalog_font_family')?.value             ?? 'Outfit',
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

// ============================================================
// PANEL CUSTOMIZADOR DEL CATÁLOGO
// ============================================================

function initCatalogCustomizerPanel() {
    const openBtn  = document.getElementById('btn-open-catalog-customizer');
    const closeBtn = document.getElementById('btn-close-catalog-customizer');
    const panel    = document.getElementById('catalog-customizer-panel');

    if (!openBtn || !panel) return;

    openBtn.addEventListener('click', () => {
        panel.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
        updateMockupPreview();
    });

    closeBtn?.addEventListener('click', closeCustPanel);

    panel.addEventListener('click', (e) => {
        if (e.target === panel) closeCustPanel();
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !panel.classList.contains('hidden')) closeCustPanel();
    });

    function closeCustPanel() {
        panel.classList.add('hidden');
        document.body.style.overflow = '';
    }

    // Logo en el customizer
    const logoInput       = document.getElementById('catalog_logo_input');
    const logoPreview     = document.getElementById('catalog-logo-preview');
    const logoPlaceholder = document.getElementById('catalog-logo-placeholder');
    const deleteLogoBtn   = document.getElementById('btn-delete-catalog-logo');
    const logoUrlInput    = document.getElementById('catalog_logo_url');

    logoInput?.addEventListener('change', (e) => {
        const file = e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = (ev) => {
            if (logoPreview)     { logoPreview.src = ev.target.result; logoPreview.style.display = 'block'; }
            if (logoPlaceholder)   logoPlaceholder.style.display = 'none';
            if (deleteLogoBtn)     deleteLogoBtn.style.display = 'block';
            updateMockupLogo(ev.target.result);
        };
        reader.readAsDataURL(file);
        _sharedCatalogLogoFile = file;
        _sharedCatalogLogoDeleted = false;
    });

    deleteLogoBtn?.addEventListener('click', () => {
        if (logoInput)       logoInput.value = '';
        if (logoPreview)   { logoPreview.src = ''; logoPreview.style.display = 'none'; }
        if (logoPlaceholder) logoPlaceholder.style.display = 'block';
        if (deleteLogoBtn)   deleteLogoBtn.style.display = 'none';
        if (logoUrlInput)    logoUrlInput.value = '';
        _sharedCatalogLogoFile = null;
        _sharedCatalogLogoDeleted = true;
        updateMockupLogo(null);
    });

    // Escuchar cambios en todos los controles
    [
        'catalog_theme_color', 'catalog_theme_pattern',
        'catalog_button_color', 'catalog_button_text', 'catalog_button_icon',
        'catalog_show_action_button', 'catalog_show_price', 'catalog_show_stock',
        'catalog_whatsapp', 'catalog_instagram', 'catalog_address',
        // colorimetry
        'catalog_color_bg', 'catalog_color_bg_custom',
        'catalog_color_pattern', 'catalog_color_pattern_custom',
        'catalog_color_card', 'catalog_color_card_custom',
        'catalog_color_accent', 'catalog_color_accent_custom',
        'catalog_color_label', 'catalog_color_label_custom',
        'catalog_color_title', 'catalog_color_title_custom',
        'catalog_color_price', 'catalog_color_price_custom',
        'catalog_color_header_bg', 'catalog_color_header_bg_custom',
        'catalog_color_social_bg', 'catalog_color_social_bg_custom',
        'catalog_color_badge_bg', 'catalog_color_badge_bg_custom',
        // shadows
        'catalog_shadow_filter_section', 'catalog_shadow_category_pill',
        'catalog_shadow_product_card', 'catalog_shadow_modal',
        // typography
        'catalog_font_family',
    ].forEach(id => {
        const el = document.getElementById(id);
        if (!el) return;
        el.addEventListener('change', updateMockupPreview);
        el.addEventListener('input',  updateMockupPreview);
    });

    // Zoom controls
    const zoomIn    = document.getElementById('mockup-zoom-in');
    const zoomOut   = document.getElementById('mockup-zoom-out');
    const zoomLabel = document.getElementById('mockup-zoom-label');
    const mockupEl2 = document.getElementById('catalog-mockup');
    let currentZoom = 1.0;
    if (mockupEl2) mockupEl2.dataset.zoom = currentZoom;
    const ZOOM_STEP = 0.1;
    const ZOOM_MIN  = 0.5;
    const ZOOM_MAX  = 1.5;

    function applyZoom() {
        if (!mockupEl2) return;
        mockupEl2.style.transform = `scale(${currentZoom})`;
        mockupEl2.dataset.zoom = currentZoom;
        if (zoomLabel) zoomLabel.textContent = Math.round(currentZoom * 100) + '%';
        adjustZoomWrapper();
    }

    zoomIn?.addEventListener('click', () => {
        currentZoom = Math.min(ZOOM_MAX, parseFloat((currentZoom + ZOOM_STEP).toFixed(1)));
        applyZoom();
    });
    zoomOut?.addEventListener('click', () => {
        currentZoom = Math.max(ZOOM_MIN, parseFloat((currentZoom - ZOOM_STEP).toFixed(1)));
        applyZoom();
    });
}

function adjustZoomWrapper() {
    const mockupEl = document.getElementById('catalog-mockup');
    if (!mockupEl) return;
    const wrapper = mockupEl.parentElement;
    if (!wrapper) return;

    // Temporarily clear wrapper styles to let the mockup layout naturally
    const prevW = wrapper.style.width;
    const prevH = wrapper.style.height;
    wrapper.style.width = '';
    wrapper.style.height = '';

    const zoom = parseFloat(mockupEl.dataset.zoom) || 1.0;
    
    // Measure natural (unscaled) dimensions
    const w = mockupEl.offsetWidth;
    const h = mockupEl.offsetHeight;

    if (w > 0 && h > 0) {
        wrapper.style.width = (w * zoom) + 'px';
        wrapper.style.height = (h * zoom) + 'px';
    } else {
        wrapper.style.width = prevW;
        wrapper.style.height = prevH;
    }
}

const _THEME_COLOR_HEX = {
    'accent-color':  null,
    'accent-green':  '#A3BE8C',
    'accent-blue':   '#88C0D0',
    'accent-red':    '#BF616A',
    'accent-yellow': '#EBCB8B',
    'accent-violet': '#B48EAD',
};

const _BTN_BG = {
    'whatsapp-green':  '#25D366',
    'instagram-pink':  'linear-gradient(135deg,#f09433 0%,#e6683c 25%,#dc2743 50%,#cc2366 75%,#bc1888 100%)',
    'facebook-blue':   '#1877F2',
    'accent-green':    '#A3BE8C',
    'accent-red':      '#BF616A',
    'accent-blue':     '#88C0D0',
    'accent-yellow':   '#EBCB8B',
    'accent-violet':   '#B48EAD',
    'accent-color':    null,
    'color-black':     '#1b1b1b',
};

const _BTN_TXT = {
    'whatsapp-green':  '#fff',
    'instagram-pink':  '#fff',
    'facebook-blue':   '#fff',
    'accent-green':    '#000',
    'accent-red':      '#fff',
    'accent-blue':     '#fff',
    'accent-yellow':   '#000',
    'accent-violet':   '#fff',
    'accent-color':    '#fff',
    'color-black':     '#fff',
};

function updateMockupPreview() {
    const g = (id) => document.getElementById(id);

    const themeColor   = g('catalog_theme_color')?.value   ?? 'accent-color';
    const themePattern = g('catalog_theme_pattern')?.value ?? 'dots';
    const btnColor     = g('catalog_button_color')?.value  ?? 'whatsapp-green';
    const btnText      = g('catalog_button_text')?.value   ?? 'Consultar';
    const btnIcon      = g('catalog_button_icon')?.value   ?? 'ph-whatsapp-logo';
    const showBtn      = g('catalog_show_action_button')?.checked ?? true;
    const showPrice    = g('catalog_show_price')?.checked  ?? true;
    const showStock    = g('catalog_show_stock')?.checked  ?? true;
    const whatsapp     = g('catalog_whatsapp')?.value.trim()  ?? '';
    const instagram    = g('catalog_instagram')?.value.trim() ?? '';
    const address      = g('catalog_address')?.value.trim()   ?? '';

    const accentHex = _THEME_COLOR_HEX[themeColor];
    let accentVal = accentHex;
    if (!accentVal) {
        accentVal = getComputedStyle(document.documentElement).getPropertyValue('--accent-color').trim() || '#88C0D0';
    }

    // Price elements (all cards)
    const priceEls = document.querySelectorAll('.mockup-product-price');
    priceEls.forEach(el => {
        el.style.display = showPrice ? '' : 'none';
    });

    // Stock badges (all cards)
    const stockBadges = document.querySelectorAll('.mockup-product-badge');
    stockBadges.forEach(el => {
        if (showStock) {
            el.textContent = 'Disponible';
            el.classList.remove('badge-out-of-stock');
            el.classList.add('badge-stock');
        } else {
            el.textContent = 'Sin Stock';
            el.classList.remove('badge-stock');
            el.classList.add('badge-out-of-stock');
        }
    });

    const mockupBg = g('mockup-bg');
    // Read colorimetry values
    const resolveColor = (selectId, customId) => {
        const sel = g(selectId);
        if (!sel) return null;
        if (sel.value === 'custom' || sel.value === 'theme') {
            if (sel.value === 'theme') return null;
            return g(customId)?.value || null;
        }
        return sel.value;
    };

    updateColorPickersVisibility();

    const colorBg      = resolveColor('catalog_color_bg',      'catalog_color_bg_custom')      || '#F4F4F6';
    const colorPattern = resolveColor('catalog_color_pattern', 'catalog_color_pattern_custom') || 'rgba(0,0,0,0.08)';
    const colorCard    = resolveColor('catalog_color_card',    'catalog_color_card_custom')    || '#FFFFFF';
    const colorAccent  = resolveColor('catalog_color_accent',  'catalog_color_accent_custom');
    const colorLabel   = resolveColor('catalog_color_label',   'catalog_color_label_custom')   || '#8A8A8A';
    const colorTitle   = resolveColor('catalog_color_title',   'catalog_color_title_custom')   || '#1A1A1A';
    const colorPrice   = resolveColor('catalog_color_price',   'catalog_color_price_custom')   || '#1A1A1A';
    const colorHeaderBg = resolveColor('catalog_color_header_bg', 'catalog_color_header_bg_custom') || '#FFFFFF';
    const colorSocialBg = resolveColor('catalog_color_social_bg', 'catalog_color_social_bg_custom') || '#FFFFFF';
    const colorBadgeBg  = resolveColor('catalog_color_badge_bg',  'catalog_color_badge_bg_custom')  || '#A3BE8C';

    // Apply colorimetry to mockup CSS variables
    const mockupEl = document.getElementById('catalog-mockup');
    if (mockupEl) {
        mockupEl.style.setProperty('--mockup-bg-color',     colorBg);
        mockupEl.style.setProperty('--mockup-card-bg',      colorCard);
        mockupEl.style.setProperty('--mockup-label-color',  colorLabel);
        mockupEl.style.setProperty('--mockup-title-color',  colorTitle);
        mockupEl.style.setProperty('--mockup-price-color',  colorPrice);
        const finalAccent = colorAccent || accentVal;
        mockupEl.style.setProperty('--mockup-accent-color', finalAccent);
        // Also update the active pill directly for compatibility
        const activePill = document.querySelector('.mockup-category-pill.active');
        if (activePill) activePill.style.backgroundColor = finalAccent;
    }

    const mockupNav = g('mockup-nav');
    if (mockupNav) {
        mockupNav.style.backgroundColor = colorHeaderBg;
    }

    const mockupContactBtns = document.querySelectorAll('.mockup-contact-btn');
    mockupContactBtns.forEach(btn => {
        btn.style.backgroundColor = colorSocialBg;
    });

    const mockupBadges = document.querySelectorAll('.mockup-product-badge.badge-stock');
    mockupBadges.forEach(badge => {
        badge.style.backgroundColor = colorBadgeBg;
    });

    // Shadows
    const shadowPill = g('catalog_shadow_category_pill')?.checked ?? true;
    const shadowCard = g('catalog_shadow_product_card')?.checked ?? true;
    const shadowFilter = g('catalog_shadow_filter_section')?.checked ?? true;

    const filterSec = document.querySelector('.mockup-filter-section');
    if (filterSec) {
        filterSec.style.boxShadow = shadowFilter ? '4px 4px 0px var(--color-black)' : 'none';
    }

    const card = document.querySelector('.mockup-product-card');
    if (card) {
        card.style.boxShadow = shadowCard ? '4px 4px 0 var(--color-black)' : 'none';
    }

    const pills = document.querySelectorAll('.mockup-category-pill');
    pills.forEach(p => {
        p.style.boxShadow = shadowPill ? '1.5px 1.5px 0px var(--color-black)' : 'none';
        p.style.transform = shadowPill ? '' : 'none';
    });

    // Font
    const fontFamily = g('catalog_font_family')?.value ?? 'Outfit';
    loadMockupFont(fontFamily);

    if (mockupBg) {
        mockupBg.style.backgroundColor = colorBg;
        let bgImg = 'none';
        const patternColor = colorPattern;
        if (themePattern === 'dots') {
            bgImg = `radial-gradient(${patternColor} 1.5px, transparent 1.5px)`;
            mockupBg.style.backgroundSize = '24px 24px';
        } else if (themePattern === 'grid') {
            bgImg = `linear-gradient(${patternColor} 1px,transparent 1px),linear-gradient(90deg,${patternColor} 1px,transparent 1px)`;
            mockupBg.style.backgroundSize = '24px 24px';
        } else if (themePattern === 'lines') {
            bgImg = `repeating-linear-gradient(45deg,${patternColor} 0,${patternColor} 2px,transparent 2px,transparent 12px)`;
            mockupBg.style.backgroundSize = 'auto';
        } else {
            mockupBg.style.backgroundSize = 'auto';
        }
        mockupBg.style.backgroundImage = bgImg;
    }

    // Action buttons (all cards)
    const actionBtns = document.querySelectorAll('.mockup-action-btn');
    actionBtns.forEach(btn => {
        btn.style.display = showBtn ? '' : 'none';
        const bg  = _BTN_BG[btnColor]  ?? accentVal;
        const col = _BTN_TXT[btnColor] ?? '#fff';
        btn.style.background = bg;
        btn.style.color      = col;

        const iconEl = btn.querySelector('.mockup-btn-icon');
        const textEl = btn.querySelector('.mockup-btn-text');
        if (iconEl) iconEl.className = 'ph ' + btnIcon;
        if (textEl) textEl.textContent = btnText || 'Consultar';
    });

    const mapEl = g('mockup-contact-map');
    const igEl  = g('mockup-contact-ig');
    const waEl  = g('mockup-contact-wa');
    if (mapEl) mapEl.style.display = address   ? 'flex' : 'none';
    if (igEl)  igEl.style.display  = instagram ? 'flex' : 'none';
    if (waEl)  waEl.style.display  = whatsapp  ? 'flex' : 'none';

    // Update mockup logo content
    let currentLogoSrc = null;
    if (!_sharedCatalogLogoDeleted) {
        if (_sharedCatalogLogoFile) {
            const logoPreview = g('catalog-logo-preview');
            if (logoPreview && logoPreview.style.display !== 'none') {
                currentLogoSrc = logoPreview.src;
            }
        } else {
            currentLogoSrc = g('catalog_logo_url')?.value || '';
        }
    }
    updateMockupLogo(currentLogoSrc, accentVal);
    adjustZoomWrapper();
}

function updateMockupLogo(src, accentVal) {
    const logoContainer = document.getElementById('mockup-nav-logo');
    if (!logoContainer) return;

    const businessNameEl = document.getElementById('mockup-business-name');
    const businessName = (businessNameEl && businessNameEl.dataset.originalName) || 
                         (businessNameEl && businessNameEl.textContent.trim()) || 
                         'Mi Negocio';

    if (src) {
        logoContainer.innerHTML = `
            <img class="business-logo" src="${src}" alt="Logo" style="max-height:32px; max-width:150px; object-fit:contain;">
        `;
    } else {
        const initial = businessName.charAt(0).toUpperCase();
        logoContainer.innerHTML = `
            <div class="mockup-logo-circle" id="mockup-logo-circle" style="width:32px; height:32px; border-radius:50%; background:${accentVal || '#3b82f6'}; color:#fff; font-weight:900; font-size:0.9rem; display:flex; align-items:center; justify-content:center; overflow:hidden; flex-shrink:0; border:var(--border-soft); box-shadow:2px 2px 0px var(--color-black); transition:background-color 0.3s ease;">
                ${initial}
            </div>
            <span class="mockup-business-name" id="mockup-business-name" style="font-size:0.8rem; font-weight:900; text-transform:uppercase; color: var(--color-black);">${businessName}</span>
        `;
        const newBusinessNameEl = document.getElementById('mockup-business-name');
        if (newBusinessNameEl) {
            newBusinessNameEl.dataset.originalName = businessName;
        }
    }
}

function updateColorPickersVisibility() {
    const pairs = [
        { selectId: 'catalog_color_bg',      pickerId: 'catalog_color_bg_custom' },
        { selectId: 'catalog_color_pattern', pickerId: 'catalog_color_pattern_custom' },
        { selectId: 'catalog_color_card',    pickerId: 'catalog_color_card_custom' },
        { selectId: 'catalog_color_accent',  pickerId: 'catalog_color_accent_custom' },
        { selectId: 'catalog_color_label',   pickerId: 'catalog_color_label_custom' },
        { selectId: 'catalog_color_title',   pickerId: 'catalog_color_title_custom' },
        { selectId: 'catalog_color_price',   pickerId: 'catalog_color_price_custom' },
        { selectId: 'catalog_color_header_bg', pickerId: 'catalog_color_header_bg_custom' },
        { selectId: 'catalog_color_social_bg', pickerId: 'catalog_color_social_bg_custom' },
        { selectId: 'catalog_color_badge_bg',  pickerId: 'catalog_color_badge_bg_custom' }
    ];

    pairs.forEach(pair => {
        const selectEl = document.getElementById(pair.selectId);
        const pickerEl = document.getElementById(pair.pickerId);
        if (selectEl && pickerEl) {
            if (selectEl.value === 'custom') {
                pickerEl.classList.add('show');
            } else {
                pickerEl.classList.remove('show');
            }
        }
    });
}

function loadMockupFont(fontName) {
    if (!fontName) return;
    const fontId = 'dynamic-mockup-font';
    let linkEl = document.getElementById(fontId);
    if (!linkEl) {
        linkEl = document.createElement('link');
        linkEl.id = fontId;
        linkEl.rel = 'stylesheet';
        document.head.appendChild(linkEl);
    }
    const fontMap = {
        'Outfit': 'Outfit:wght@300;400;500;600;700;800',
        'Inter': 'Inter:wght@300;400;500;600;700;800',
        'Lexend': 'Lexend:wght@300;400;500;600;700;800',
        'Space Grotesk': 'Space+Grotesk:wght@400;500;600;700',
        'Syne': 'Syne:wght@400;600;800',
        'Poppins': 'Poppins:wght@300;400;500;600;700;800',
        'Montserrat': 'Montserrat:wght@300;400;500;600;700;800',
        'Playfair Display': 'Playfair+Display:ital,wght@0,400;0,700;1,400',
        'Courier Prime': 'Courier+Prime:wght@400;700'
    };
    const query = fontMap[fontName] || 'Outfit:wght@300;400;500;600;700;800';
    linkEl.href = `https://fonts.googleapis.com/css2?family=${query}&display=swap`;
    
    const mockupEl = document.getElementById('catalog-mockup');
    if (mockupEl) {
        mockupEl.style.setProperty('font-family', `'${fontName}', sans-serif`, 'important');
    }
}
