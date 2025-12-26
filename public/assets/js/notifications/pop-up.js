export const notificationConfig = {
    success: { icon: 'ph-check-circle', color: 'var(--accent-green)' },
    error: { icon: 'ph-warning-circle', color: 'var(--accent-red)' },
    warning: { icon: 'ph-warning', color: 'var(--accent-yellow)' },
    info: { icon: 'ph-info', color: 'var(--accent-blue)' },
    system: { icon: 'ph-hard-drives', color: 'var(--accent-purple)' },
    dev: { icon: 'ph ph-code', color: 'var(--accent-purple)' }
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

    fetch('/api/notifications/create.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ type, title, message })
    })
        .then(response => response.json())
        .then(data => {
            if (!data.success) console.error('Error al guardar la notificación en la DB.');
        })
        .catch(err => console.error('Error de red guardando notificación:', err));


    container.appendChild(toast);

    // Animación de entrada
    setTimeout(() => toast.classList.add('show'), 100);

    // Lógica de cierre
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
        const modal = document.getElementById('custom-prompt-modal');
        const titleEl = document.getElementById('prompt-title');
        const messageEl = document.getElementById('prompt-message');
        const inputEl = document.getElementById('prompt-input');
        const form = document.getElementById('prompt-form');

        if(!modal || !titleEl || !messageEl || !inputEl || !form) {
            console.error("Faltan elementos del DOM para el prompt personalizado.");
            return reject(new Error('Componente de UI no encontrado.'));
        }

        titleEl.textContent = title;
        messageEl.textContent = message;
        inputEl.placeholder = placeholder;
        inputEl.value = initialValue;

        modal.classList.remove('hidden');
        inputEl.focus();

        const newForm = form.cloneNode(true);
        form.parentNode.replaceChild(newForm, form);

        const newCancelBtn = newForm.querySelector('#prompt-cancel-btn');

        newForm.addEventListener('submit', (e) => {
            e.preventDefault();
            modal.classList.add('hidden');
            resolve(newForm.querySelector('#prompt-input').value.trim());
        });

        newCancelBtn.addEventListener('click', () => {
            modal.classList.add('hidden');
            reject(new Error('Acción cancelada por el usuario.'));
        });
    });
}

function _showConfirm(title, message) {
    return new Promise((resolve, reject) => {
        const modal = document.getElementById('custom-prompt-modal');
        const titleEl = document.getElementById('prompt-title');
        const messageEl = document.getElementById('prompt-message');
        const inputEl = document.getElementById('prompt-input');
        const form = document.getElementById('prompt-form');

        if (!modal || !titleEl || !messageEl || !inputEl || !form) {
            return reject(new Error('UI no encontrada.'));
        }

        titleEl.textContent = title;
        messageEl.textContent = message;

        inputEl.style.display = 'none';
        inputEl.value = 'CONFIRMADO';

        modal.classList.remove('hidden');

        const newForm = form.cloneNode(true);
        form.parentNode.replaceChild(newForm, form);

        const hiddenInput = newForm.querySelector('#prompt-input');

        const newCancelBtn = newForm.querySelector('#prompt-cancel-btn');

        newForm.addEventListener('submit', (e) => {
            e.preventDefault();
            modal.classList.add('hidden');
            inputEl.style.display = 'block';
            resolve(true);
        });

        newCancelBtn.addEventListener('click', () => {
            modal.classList.add('hidden');
            inputEl.style.display = 'block';
            resolve(false);
        });
    });
}


export const pop_ups = {
    success: (message, title = 'Éxito') => {
        _showToast('success', title, message);
    },
    error: (message, title = 'Error') => {
        _showToast('error', title, message);
    },
    warning: (message, title = 'Advertencia') => {
        _showToast('warning', title, message);
    },
    info: (message, title = 'Información') => {
        _showToast('info', title, message);
    },
    system: (message, title = 'Sistema') => {
        _showToast('system', title, message);
    },
    dev: (message, title = 'Desarrolladores') => {
        _showToast('system', title, message);
    },

    prompt: _showPrompt,
    confirm: _showConfirm
};

