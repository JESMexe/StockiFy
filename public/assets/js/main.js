import * as api from './api.js';
import { pop_ups } from './notifications/pop-up.js';

/* ===============================
   Texto animado de portada
================================ */
const words = ["Ventas.", "Compras.", "Estadísticas.", "Clientes."];
let currentIndex = 0;

function cycleText() {
    const el = document.getElementById("cycling-text");
    if (!el) return;

    el.style.opacity = "0";
    el.style.transform = "translateY(8px)";

    setTimeout(() => {
        currentIndex = (currentIndex + 1) % words.length;
        el.textContent = words[currentIndex];
        el.style.opacity = "1";
        el.style.transform = "translateY(0)";
    }, 250);
}

/* ===============================
   Animaciones de entrada
================================ */
function initEntranceAnimations() {
    const els = document.querySelectorAll('.fade-in-up');
    els.forEach((el, i) => {
        setTimeout(() => el.classList.add('is-visible'), 100 + i * 100);
    });
}

/* ===============================
   Mostrar vistas
================================ */
function showView(viewId) {
    document.querySelectorAll('.view-container').forEach(v => v.classList.add('hidden'));
    const target = document.getElementById(viewId);
    if (target) target.classList.remove('hidden');
}

/* ===============================
   checkInitialState()
================================ */
async function checkInitialState() {
    try {
        const profile = await api.getUserProfile();
        console.log("PROFILE DESDE API:", profile);

        if (!profile?.success) {
            showView("welcome-view");
            return;
        }

        const { user, databases, activeInventoryId } = profile;

        // Actualizar saludo
        const name = user?.name ?? "Usuario";
        document.querySelectorAll('#welcome-view h2, #empty-state-view h2, #select-db-view h2, #dashboard-view h2')
            .forEach(el => el.textContent = `¡Bienvenido, ${name}!`);

        // Lógica principal
        if (activeInventoryId) {
            showView("dashboard-view");
            return;
        }

        if (databases && databases.length > 0) {
            showView("select-db-view");
            return;
        }

        showView("empty-state-view");

    } catch (err) {
        pop_ups.error(`Error al verificar sesión: ${err.message}`, 'Error');
        showView("welcome-view");
    }
}

/* ===============================
   INICIO
================================ */
document.addEventListener('DOMContentLoaded', async () => {
    setInterval(cycleText, 2200);
    initEntranceAnimations();

    const isLoggedIn = await api.checkSessionStatus();

    if (isLoggedIn) {
        await checkInitialState();
    } else {
        showView('welcome-view');
    }
});
