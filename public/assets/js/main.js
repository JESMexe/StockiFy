import * as api from './api.js';
import { pop_ups } from './notifications/pop-up.js';

function showView(viewId) {
    document.querySelectorAll('.view-container').forEach(v => v.classList.add('hidden'));
    const target = document.getElementById(viewId);
    if (target) target.classList.remove('hidden');
}

function initCyclingText() {
    const words = ['Clientes.', 'Proveedores.', 'Compras.', 'Ventas.', 'Estadísticas.'];
    const textEl = document.getElementById('cycling-text');
    if (!textEl) return;

    let i = 0;
    textEl.textContent = words[0];
    setInterval(() => {
        textEl.style.opacity = 0;
        textEl.style.transform = 'translateY(-0.3em)';
        setTimeout(() => {
            i = (i + 1) % words.length;
            textEl.textContent = words[i];
            textEl.style.transform = 'translateY(0.3em)';
            textEl.style.opacity = 1;
            setTimeout(() => {
                textEl.style.transform = 'translateY(0)';
            }, 200);
        }, 250);
    }, 2200);
}


function initEntranceAnimations() {
    const els = document.querySelectorAll('.fade-in-up');
    els.forEach((el, i) => {
        setTimeout(() => el.classList.add('is-visible'), 100 + i * 100);
    });
}

function setupHeader(isLoggedIn) {
    const nav = document.getElementById('header-nav');
    if (!nav) return;
    nav.innerHTML = isLoggedIn
        ? `<a href="/dashboard.php" class="btn btn-primary">Ir al Panel</a>
       <a href="/logout.php" class="btn btn-secondary">Cerrar Sesión</a>`
        : `<a href="/login.php" class="btn btn-secondary">Iniciar Sesión</a>
       <a href="/register.php" class="btn btn-primary">Crear Cuenta</a>`;
}

async function checkInitialState() {
    try {
        const profile = await api.getUserProfile();
        if (!profile?.success) throw new Error('Sesión inválida.');

        const { name, activeInventoryId, databases } = profile;
        const title = document.querySelector('#dashboard-view h2, #welcome-view h2');
        if (title && name) title.textContent = `¡Bienvenido, ${name}!`;

        // Mostrar siempre index; sin redirigir
        if (activeInventoryId) {
            showView('dashboard-view');
            return;
        }
        if (databases?.length > 0) {
            showView('select-db-view');
        } else {
            showView('empty-state-view');
        }
    } catch (err) {
        pop_ups.error(`Error al verificar sesión: ${err.message}`, 'Error');
        showView('welcome-view');
    }
}

document.addEventListener('DOMContentLoaded', async () => {
    initCyclingText();
    initEntranceAnimations();

    const isLoggedIn = await api.checkSessionStatus();
    setupHeader(isLoggedIn);
    if (isLoggedIn) await checkInitialState();
    else showView('welcome-view');
});
