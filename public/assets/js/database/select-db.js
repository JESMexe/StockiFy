// public/assets/js/database/select-db.js
import * as api from '../api.js';
import { pop_ups } from "../notifications/pop-up.js?v=3.0";

let globalUserPlan = 0;

async function handleSelectDatabase(event) {
    const target = event.target.closest('button.db-list-item');
    if (!target) return;

    const inventoryId = target.dataset.dbId;
    const isOwner = target.dataset.role === 'Owner';

    if (globalUserPlan === 0) {
        if (isOwner) {
            window.location.href = 'index#section-pricing';
            return;
        } else {
            pop_ups.warning(
                'Para acceder a los inventarios a los que fuiste invitado, debes cambiar tu plan al de "Invitado" usando el botón al final de la página.',
                'Acceso Restringido'
            );
            return;
        }
    }

    try {
        await api.selectDatabase(inventoryId);
        window.location.href = '/dashboard';
    } catch (error) {
        pop_ups.warning(`Error al seleccionar la base de datos: ${error.message}`);
    }
}

function populateDbList(databases, dbListElement) {
    dbListElement.innerHTML = '';

    const owned = databases.filter(db => db.role_name === 'Owner');
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
        button.dataset.role = roleBadge ? roleBadge : 'Owner';
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

function escapeHtml(string) {
    if (!string) return '';
    return string
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}

async function handleBecomeGuestAction() {
    try {
        const res = await api.becomeGuest();
        if (res.success) {
            pop_ups.success(res.message || 'Has sido registrado como Invitado.');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            pop_ups.error(res.message || 'No se pudo actualizar el plan.');
        }
    } catch (error) {
        pop_ups.error(error.message || 'Ocurrió un error al procesar tu solicitud.');
    }
}

async function init() {
    const nav = document.getElementById('header-nav');
    if (nav) nav.innerHTML = `<a href="/logout" class="btn btn-secondary">Cerrar Sesión</a>`;

    const selectionView = document.getElementById('selection-view');
    const dbList = document.getElementById('db-list');
    if (!dbList || !selectionView) return;

    try {
        const profileData = await api.getUserProfile();
        if (!profileData.success) {
            window.location.href = '/login';
            return;
        }

        const plan = parseInt(profileData.user.plan ?? 0);
        globalUserPlan = plan;

        if (plan === 0) {
            if (profileData.databases.length === 0) {
                // Render interface for plan 0 users with no databases
                selectionView.innerHTML = `
                    <h2>Acceso al Panel</h2>
                    <p>Elige cómo deseas continuar para ingresar a la plataforma.</p>
                    <div style="display: flex; flex-direction: column; gap: 12px; margin-top: 25px;">
                        <button id="btn-become-guest" class="btn btn-primary" style="margin: 0; width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; font-weight: 700; height: 48px; border: 2px solid #1b1b1b;">
                            <i class="ph-bold ph-envelope-simple-open" style="font-size: 1.3rem;"></i> Buscar Inventario al que fui invitado
                        </button>
                        <a href="index#section-pricing" class="btn btn-secondary" style="margin: 0; width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; font-weight: 700; height: 48px; border: 2px solid #1b1b1b; background: #fff; color: #1b1b1b;">
                            <i class="ph-bold ph-sparkle" style="font-size: 1.3rem;"></i> Ver Planes y Precios
                        </a>
                    </div>
                `;

                document.getElementById('btn-become-guest').addEventListener('click', handleBecomeGuestAction);
                return;
            } else {
                // List databases even for plan 0 users
                populateDbList(profileData.databases, dbList);
                dbList.addEventListener('click', handleSelectDatabase);

                // If they have invited databases, show banner underneath
                const hasInvitedDbs = profileData.databases.some(db => db.role_name !== 'Owner');
                if (hasInvitedDbs) {
                    const banner = document.createElement('div');
                    banner.style.cssText = 'background: #f8fafc; border: 2px solid #1b1b1b; border-radius: 12px; padding: 20px; margin-top: 25px; box-shadow: 4px 4px 0px #1b1b1b; text-align: left;';
                    banner.innerHTML = `
                        <p style="color: #475569; font-size: 0.9rem; line-height: 1.5; margin: 0 0 15px 0;">
                            Si deseas acceder únicamente a los inventarios a los que fuiste invitado (y no a los creados por vos), podes cambiar tu plan al de <strong>Invitado</strong>. Recordá que esto no te impide adquirir un plan de pago en el futuro.
                        </p>
                        <button id="btn-become-guest-inline" class="btn btn-primary" style="margin: 0; width: 100%; display: flex; align-items: center; justify-content: center; gap: 8px; font-weight: 700; height: 44px; border: 2px solid #1b1b1b;">
                            <i class="ph-bold ph-user-check" style="font-size: 1.2rem;"></i> Cambiar a plan Invitado
                        </button>
                    `;
                    const hr = selectionView.querySelector('hr');
                    if (hr) {
                        selectionView.insertBefore(banner, hr);
                    } else {
                        selectionView.appendChild(banner);
                    }

                    document.getElementById('btn-become-guest-inline').addEventListener('click', handleBecomeGuestAction);
                }
            }
        }

        if (plan === 5) {
            // Hide create options for Guests (plan 5)
            const hr = selectionView.querySelector('hr');
            if (hr) hr.remove();
            const createBtn = selectionView.querySelector('a[href="create-db"]');
            if (createBtn) createBtn.remove();
        }

        if (plan !== 0) {
            if (profileData.databases.length > 0) {
                populateDbList(profileData.databases, dbList);
                dbList.addEventListener('click', handleSelectDatabase);
            } else {
                if (plan === 5) {
                    // Show card for guests with no active invitations
                    dbList.innerHTML = `
                        <div style="background: #f8fafc; border: 2px solid #1b1b1b; border-radius: 12px; padding: 25px; text-align: center; margin-top: 20px; box-shadow: 4px 4px 0px #1b1b1b;">
                            <i class="ph-fill ph-envelope-simple-open" style="font-size: 3rem; color: var(--accent-color); margin-bottom: 15px; display: block;"></i>
                            <h3 style="margin-top: 0; color: #1b1b1b; font-size: 1.25rem; font-weight: 800;">Sin invitaciones pendientes</h3>
                            <p style="color: #666; font-size: 0.95rem; line-height: 1.5; margin: 10px 0 20px 0;">
                                Aún no has sido invitado a ningún inventario. Para comenzar a colaborar, comparte tu correo de registro con el administrador o propietario del inventario:
                            </p>
                            <div style="background: #fff; border: 2px dashed #1b1b1b; padding: 10px 15px; border-radius: 8px; font-weight: 700; color: var(--accent-color); font-size: 1.1rem; display: inline-block;">
                                ${escapeHtml(profileData.user.email)}
                            </div>
                        </div>
                    `;
                } else {
                    window.location.href = '/create-db';
                }
            }
        }
    } catch (error) {
        pop_ups.error(`Error: ${error}`);
        window.location.href = '/login';
    }
}

document.addEventListener('DOMContentLoaded', init);