import * as api from './api.js';
import { pop_ups } from './notifications/pop-up.js';
import {ui_helper} from "./ui-helper.js";

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
        // console.log("PROFILE:", profile); // Debug opcional

        if (!profile?.success) {
            showView("welcome-view");
            return;
        }

        const { user, databases, activeInventoryId } = profile;
        const name = user?.name ?? "Usuario";

        document.querySelectorAll('#welcome-view h2, #empty-state-view h2, #select-db-view h2, #dashboard-view h2')
            .forEach(el => el.textContent = `¡Bienvenido, ${name}!`);

        if (activeInventoryId) { showView("dashboard-view"); return; }
        if (databases && databases.length > 0) { showView("select-db-view"); return; }
        showView("empty-state-view");

    } catch (err) {
        pop_ups.error(`Error sesión: ${err.message}`);
        showView("welcome-view");
    }
}

/* ===============================
   Funciones de Secciones (About/Contact)
================================ */
function showContentView(content_id){
    document.querySelectorAll('.content-panel').forEach(v => v.classList.remove('active'));
    document.getElementById(content_id)?.classList.add('active');
}

function setupAboutSection(){
    document.querySelectorAll('.about-option').forEach(option => {
        option.addEventListener('click', () => {
            document.querySelectorAll('.about-option').forEach(o => o.classList.remove('active'));
            option.classList.add('active');
            showContentView(option.dataset.option);
        });
    });
}

function setupOtherInfoSection(){
    document.querySelectorAll('.other-info-item').forEach(option => {
        option.addEventListener('click', () => {
            document.querySelectorAll('.other-info-header, .other-info-body').forEach(el => el.classList.remove('active'));
            option.querySelector('.other-info-header')?.classList.add('active');
            option.querySelector('.other-info-body')?.classList.add('active');
        });
    });
}

function setupContactForm(){
    const contactForm = document.getElementById('contact-form');
    if(!contactForm) return;

    contactForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const fd = new FormData(contactForm);
        const data = {
            'full_name': fd.get('name'),
            'email': fd.get('email'),
            'phone': fd.get('phone') || null,
            'subject': fd.get('subject') || null,
            'message': fd.get('message')
        };

        const res = await api.registerContactForm(data);
        if (!res.success) alert('Error: ' + res.error);
        else { alert('Contacto recibido!'); window.location.reload(); }
    });
}

function innit(){
    setupAboutSection();
    setupOtherInfoSection();
    setupContactForm();
    ui_helper.renderHeader('stats');
}

/* ===============================
   INICIO
================================ */
document.addEventListener('DOMContentLoaded', async () => {
    setInterval(cycleText, 2200);
    initEntranceAnimations();
    innit();

    const deleteModal = document.getElementById('delete-confirm-modal');
    if (deleteModal && deleteModal.parentElement !== document.body) {
        document.body.appendChild(deleteModal);
    }

    const isLoggedIn = await api.checkSessionStatus();
    if (isLoggedIn) await checkInitialState();
    else showView('welcome-view');
});