// public/assets/js/database/select-db.js
import * as api from '../api.js';
import {pop_ups} from "../notifications/pop-up.js?v=3.0";

async function handleSelectDatabase(event) {
    const target = event.target.closest('button.db-list-item');
    if (!target) return;

    const inventoryId = target.dataset.dbId;
    try {
        await api.selectDatabase(inventoryId);
        window.location.href = '/dashboard';
    } catch (error) {
        pop_ups.warning(`Error al seleccionar la base de datos: ${error.message}`);
    }
}

function populateDbList(databases, dbListElement) {
    dbListElement.innerHTML = '';

    const owned  = databases.filter(db => db.role_name === 'Owner');
    const shared = databases.filter(db => db.role_name !== 'Owner');

    function createSectionLabel(text) {
        const label = document.createElement('p');
        label.textContent = text;
        label.style.cssText = 'margin: 0 0 8px; font-size: 0.72rem; font-weight: 900; text-transform: uppercase; color: #aaa; letter-spacing: 1.5px;';
        return label;
    }

    function createDbButton(db, roleBadge = null) {
        const button = document.createElement('button');
        button.dataset.dbId = db.id;
        button.classList.add('btn', 'btn-secondary', 'db-list-item');
        button.style.cssText = 'display: flex; justify-content: space-between; align-items: center; width: 100%; margin-bottom: 6px;';

        const nameSpan = document.createElement('span');
        nameSpan.textContent = db.name;
        button.appendChild(nameSpan);

        if (roleBadge) {
            const badge = document.createElement('span');
            badge.textContent = roleBadge;
            badge.style.cssText = 'font-size: 0.68rem; font-weight: 900; background: #f0e8f5; color: #B48EAD; padding: 2px 8px; border-radius: 4px; border: 1px solid #d4aed4; text-transform: uppercase; letter-spacing: 0.5px; flex-shrink: 0;';
            button.appendChild(badge);
        }

        return button;
    }

    if (owned.length > 0) {
        dbListElement.appendChild(createSectionLabel('Mis Inventarios'));
        owned.forEach(db => dbListElement.appendChild(createDbButton(db)));
    }

    if (shared.length > 0) {
        const sharedLabel = createSectionLabel('Compartidos Conmigo');
        sharedLabel.style.marginTop = '1.2rem';
        dbListElement.appendChild(sharedLabel);
        shared.forEach(db => dbListElement.appendChild(createDbButton(db, db.role_name)));
    }
}


async function init() {
    const nav = document.getElementById('header-nav');
    if (nav) nav.innerHTML = `<a href="/logout" class="btn btn-secondary">Cerrar Sesión</a>`;

    const dbList = document.getElementById('db-list');
    if (!dbList) return;

    try {
        const profileData = await api.getUserProfile();
        if (profileData.success && profileData.databases.length > 0) {
            populateDbList(profileData.databases, dbList);
            dbList.addEventListener('click', handleSelectDatabase);
        } else {
            window.location.href = '/create-db';
        }
    } catch (error) {
        pop_ups.error(`Error: ${error}`);
        window.location.href = '/login';
    }
}

document.addEventListener('DOMContentLoaded', init);