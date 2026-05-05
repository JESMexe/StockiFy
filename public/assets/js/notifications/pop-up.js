/**
 * StockiFy Pop-up System v3.0
 * Sistema global de notificaciones y diálogos.
 */

export const notificationConfig = {
    success: { icon: 'ph-check-circle', color: 'var(--accent-green)' },
    error: { icon: 'ph-warning-circle', color: 'var(--accent-red)' },
    warning: { icon: 'ph-warning', color: 'var(--accent-yellow)' },
    info: { icon: 'ph-info', color: 'var(--accent-blue)' },
    system: { icon: 'ph-hard-drives', color: 'var(--accent-violet)' },
    dev: { icon: 'ph ph-code', color: 'var(--accent-violet)' },
    note: { icon: 'ph-note', color: 'var(--color-black)' }
};

function _showToast(type, title, message, duration = 5000) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const config = notificationConfig[type] || notificationConfig.info;
    const toast = document.createElement('div');
    toast.className = 'toast-notification';
    toast.style.setProperty('--toast-color', config.color);

    toast.innerHTML = `
        <i class="toast-icon ph ${config.icon}"></i>
        <div class="toast-content">
            <strong class="toast-title">${title}</strong>
            <p class="toast-message">${message || ''}</p>
        </div>
        <button class="toast-close-btn"><i class="ph ph-x"></i></button>
        <div class="toast-timer" style="animation-duration: ${duration}ms"></div>
    `;

    // Restauramos el guardado en DB que tenías originalmente
    fetch('/api/notifications/create', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            type,
            title,
            message,
            inventory_id: window.activeInventoryId
        })
    })
        .then(response => response.json())
        .then(data => {
            if (!data.success) console.error('Error al guardar la notificación en la DB.');
        })
        .catch(err => console.error('Error de red guardando notificación:', err));

    container.appendChild(toast);

    setTimeout(() => toast.classList.add('show'), 100);

    const closeBtn = toast.querySelector('.toast-close-btn');
    const timer = setTimeout(() => _close(toast), duration);

    closeBtn.addEventListener('click', () => {
        clearTimeout(timer);
        _close(toast);
    });
}

function _close(toast) {
    toast.classList.remove('show');
    toast.classList.add('hide');
    setTimeout(() => toast.remove(), 500);
}

function _showPrompt(title, message, placeholder = '', initialValue = '') {
    return new Promise((resolve, reject) => {
        const modal = document.getElementById('stockify-global-modal');
        const titleEl = document.getElementById('prompt-title');
        const messageEl = document.getElementById('prompt-message');
        const inputEl = document.getElementById('prompt-input');
        const form = document.getElementById('prompt-form');
        const cancelBtn = document.getElementById('prompt-cancel-btn');

        if (!modal) return reject(new Error('Modal no encontrado'));

        titleEl.textContent = title;
        messageEl.textContent = message;
        inputEl.placeholder = placeholder;
        inputEl.value = initialValue;
        inputEl.style.display = 'block';
        inputEl.required = true;

        modal.classList.remove('hidden');
        modal.style.display = 'flex';
        inputEl.focus();

        const cleanup = () => {
            modal.classList.add('hidden');
            modal.style.display = 'none';
            form.onsubmit = null;
            cancelBtn.onclick = null;
        };

        form.onsubmit = (e) => {
            e.preventDefault();
            const val = inputEl.value.trim();
            cleanup();
            resolve(val);
        };

        cancelBtn.onclick = () => {
            cleanup();
            resolve(null);
        };
    });
}

function _showConfirm(title, message) {
    return new Promise((resolve) => {
        const modal = document.getElementById('stockify-global-modal');
        const titleEl = document.getElementById('prompt-title');
        const messageEl = document.getElementById('prompt-message');
        const inputEl = document.getElementById('prompt-input');
        const form = document.getElementById('prompt-form');
        const cancelBtn = document.getElementById('prompt-cancel-btn');

        if (!modal) return resolve(false);

        form.onsubmit = null;
        cancelBtn.onclick = null;

        titleEl.textContent = title;
        messageEl.textContent = message;
        inputEl.style.display = 'none';
        inputEl.required = false;

        modal.classList.remove('hidden');
        modal.style.display = 'flex';
        modal.style.zIndex = '9999';

        const cleanup = () => {
            modal.classList.add('hidden');
            modal.style.display = 'none';
            form.onsubmit = null;
            cancelBtn.onclick = null;
        };

        form.onsubmit = (e) => {
            e.preventDefault();
            cleanup();
            resolve(true);
        };

        cancelBtn.onclick = (e) => {
            e.preventDefault();
            cleanup();
            resolve(false);
        };
    });
}

export const pop_ups = {
    success: (message, title = 'Éxito') => _showToast('success', title, message),
    error: (message, title = 'Error') => _showToast('error', title, message),
    warning: (message, title = 'Advertencia') => _showToast('warning', title, message),
    info: (message, title = 'Información') => _showToast('info', title, message),
    system: (message, title = 'Sistema') => _showToast('system', title, message),
    dev: (message, title = 'Desarrolladores') => _showToast('system', title, message),
    prompt: _showPrompt,
    confirm: _showConfirm
};
