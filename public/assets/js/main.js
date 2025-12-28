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

    setInterval(cycleText, 2200);
    initEntranceAnimations();

    const deleteModal = document.getElementById('delete-confirm-modal');
    if (deleteModal && deleteModal.parentElement !== document.body) {
        document.body.appendChild(deleteModal);
    }

    const isLoggedIn = await api.checkSessionStatus();

    if (isLoggedIn) {
        await checkInitialState();
    } else {
        showView('welcome-view');
    }
});

/* ===============================
   IMPORTADO
================================ */

function showContentView (content_id){
    const contentViews = document.querySelectorAll('.content-panel');

    contentViews.forEach(view => {view.classList.remove('active');});
    document.getElementById(content_id).classList.add('active');
}

function setupAboutSection(){
    const aboutOptions = document.querySelectorAll('.about-option');
    aboutOptions.forEach(option => {
        option.addEventListener('click', () => {
            aboutOptions.forEach(option => {option.classList.remove('active');});
            option.classList.add('active');

            const selectedOption = option.dataset.option;
            showContentView(selectedOption);
        });
    });
}

function setupOtherInfoSection(){
    const otherInfoOptions = document.querySelectorAll('.other-info-item');
    otherInfoOptions.forEach(option => {
        option.addEventListener('click', () => {
            const allOptionHeaders = document.querySelectorAll('.other-info-header');
            const allOptionBodies = document.querySelectorAll('.other-info-body');

            const activeOptionHeader = document.querySelector('.other-info-header.active');

            allOptionHeaders.forEach(header => {header.classList.remove('active');});
            allOptionBodies.forEach(body => {body.classList.remove('active');});

            const selectedOptionHeader = option.querySelector('.other-info-header');
            const selectedOptionBody = option.querySelector('.other-info-body');

            if (selectedOptionHeader !== activeOptionHeader) {
                selectedOptionHeader.classList.add('active');
                selectedOptionBody.classList.add('active');
            }
        });
    });
}

function setupContactForm(){
    const contactForm = document.getElementById('contact-form');

    contactForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(contactForm);

        const full_name = formData.get('name');
        const email = formData.get('email');
        const phone = formData.get('phone');
        const subject = formData.get('subject');
        const message = formData.get('message');

        const contactData = {'full_name' : full_name,
            'email': email,
            'phone': phone === '' ? null : phone,
            'subject' : subject === '' ? null : subject,
            'message' :message
        };

        const response = await api.registerContactForm(contactData);

        if (!response.success){alert('Ha ocurrido un error : ' + response.error);}
        else{alert('Contacto recibido!'); window.location.reload();}
    });
}

function innit(){
    setupAboutSection();
    setupOtherInfoSection();
    setupContactForm();
}

document.addEventListener('DOMContentLoaded', innit);
