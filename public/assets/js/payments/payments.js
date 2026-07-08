/**
 * payments.js
 *
 * Módulo de pagos para StockiFy.
 * Gestiona la sección "Mi Suscripción" en settings.php:
 *   - Consulta el estado actual vía API
 *   - Renderiza los planes disponibles y el plan activo
 *   - Abre el flujo de Checkout Bricks de Mercado Pago
 *   - Gestiona el toggle de débito automático
 *
 * Se puede importar e invocar desde cualquier sección de la app (omnicanal).
 */

import { pop_ups } from '../notifications/pop-up.js?v=3.0';

export const paymentsModule = {
    state: null, // Datos de suscripción del usuario
    publicKey: null,

    /**
     * Punto de entrada del módulo. Carga el estado y renderiza la UI.
     */
    async init() {
        await this.loadStatus();
        this.renderSubscriptionSection();
        this.setupEvents();
    },

    // =========================================================================
    // Sección: Carga de Datos
    // =========================================================================

    async loadStatus() {
        try {
            const resp = await fetch('/api/payments/get-status.php');
            if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
            const data = await resp.json();

            if (!data.success) throw new Error(data.message || 'Error al obtener estado de suscripción');

            this.state     = data;
            this.publicKey = data.public_key;
            return data;
        } catch (e) {
            console.error('[paymentsModule] Error cargando estado:', e.message);
            return null;
        }
    },

    // =========================================================================
    // Sección: Renderizado de UI
    // =========================================================================

    renderSubscriptionSection() {
        const container = document.getElementById('suscripcion-container');
        if (!container || !this.state) return;

        const { 
            plan_name, 
            plan_id, 
            expires_at, 
            is_expired, 
            auto_debit_enabled, 
            plan_price, 
            slots_count, 
            slots_unit_price, 
            slots_total_price, 
            total_monthly_estimate 
        } = this.state;

        // Formatear fecha de expiración
        const expiresLabel = expires_at
            ? new Date(expires_at).toLocaleDateString('es-AR', { day: '2-digit', month: 'long', year: 'numeric' })
            : '—';

        const statusColor  = is_expired ? 'var(--accent-red)' : 'var(--accent-green)';
        const statusText   = is_expired ? '⚠ Vencido' : '✓ Activo';
        const planBadge    = plan_id > 0
            ? `<span class="helper-tag" style="background:${statusColor}; color:#fff; border-color:${statusColor};">${statusText} — ${plan_name}</span>`
            : `<span class="helper-tag" style="background:var(--accent-red); color:#fff; border-color:var(--accent-red);">Sin Plan Activo</span>`;

        container.innerHTML = `
            <h3 class="config-section-title"><i class="ph ph-crown"></i> Mi Suscripción</h3>
            <p style="color:#64748b; font-size:0.9rem; margin-bottom:1.5rem;">
                Gestioná tu plan de StockiFy. Podés renovarlo manualmente o activar el débito automático mensual.
            </p>

            <!-- Estado actual -->
            <div class="rustic-block" style="margin-bottom:1.5rem;">
                <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:0.75rem;">
                    <div>
                        <label class="option-label" style="margin-bottom:0.25rem;">Estado de la suscripción</label>
                        ${planBadge}
                        ${expires_at ? `<p style="margin:0.5rem 0 0; font-size:0.8rem; color:#64748b;"><i class="ph ph-calendar"></i> Vence el ${expiresLabel}</p>` : ''}
                    </div>
                    <i class="ph ph-crown" style="font-size:2.5rem; color:var(--accent-color); opacity:0.3;"></i>
                </div>
            </div>

            <!-- Toggle débito automático -->
            ${plan_id > 0 && plan_id !== 4 ? `
            <div class="catalog-row-block" style="margin-bottom:1.5rem;">
                <div>
                    <label class="option-label">Renovación Automática</label>
                    <p>Activá el débito automático para que tu plan se renueve cada mes sin que tengas que hacer nada.</p>
                </div>
                <label class="catalog-toggle" for="payment-auto-debit-toggle">
                    <input type="checkbox" id="payment-auto-debit-toggle" ${auto_debit_enabled ? 'checked' : ''}>
                    <span class="catalog-toggle-slider"></span>
                </label>
            </div>` : ''}

            <!-- Desglose de Facturación Mensual -->
            ${plan_id > 0 ? `
            <h4 style="font-weight:700; margin: 2rem 0 1rem; color:var(--color-black);">
                <i class="ph ph-receipt"></i> Detalle de Facturación Mensual
            </h4>
            <div class="rustic-block" style="margin-bottom:1.5rem; background:#fafafa; border: 2px dashed #1b1b1b;">
                <div style="display:flex; justify-content:space-between; padding: 0.5rem 0; border-bottom: 1px dashed #cbd5e1;">
                    <span>Costo base del Plan (${plan_name}):</span>
                    <strong>$${plan_price.toLocaleString('es-AR', { minimumFractionDigits: 0 })}</strong>
                </div>
                ${slots_count > 0 ? `
                <div style="display:flex; justify-content:space-between; padding: 0.5rem 0; border-bottom: 1px dashed #cbd5e1;">
                    <span>Slots de colaboradores adicionales (${slots_count} c/u a $${slots_unit_price.toLocaleString('es-AR', { minimumFractionDigits: 0 })}):</span>
                    <strong>$${slots_total_price.toLocaleString('es-AR', { minimumFractionDigits: 0 })}</strong>
                </div>
                ` : ''}
                <div style="display:flex; justify-content:space-between; padding: 0.75rem 0 0.5rem; font-size:1.1rem; font-weight:800; color:var(--accent-color);">
                    <span>Total estimado mensual:</span>
                    <span>$${total_monthly_estimate.toLocaleString('es-AR', { minimumFractionDigits: 0 })}</span>
                </div>
            </div>
            ` : ''}

            <!-- Botones de Acción de Planes -->
            <div style="margin-top:2rem; display:flex; gap:1rem; flex-wrap:wrap;">
                <a href="plans.php" class="btn btn-primary" style="display:inline-flex; align-items:center; gap:0.5rem; width:auto; text-decoration:none; background:var(--accent-color); border-color:#1b1b1b;">
                    <i class="ph ph-arrows-left-right"></i> Cambiar o Adquirir un Plan
                </a>
            </div>

            <!-- Modal de Checkout Bricks (se mantiene en la pestaña para flujos complementarios) -->
            <div id="payment-checkout-modal" class="hidden" style="position:fixed; inset:0; z-index:9999; background:rgba(0,0,0,0.6); display:flex; align-items:center; justify-content:center;">
                <div style="background:var(--bg-color, #fff); border-radius:12px; padding:2rem; width:100%; max-width:520px; position:relative; box-shadow:0 20px 60px rgba(0,0,0,0.3); border:3px solid #1b1b1b;">
                    <button id="payment-modal-close" style="position:absolute; top:1rem; right:1rem; background:none; border:none; cursor:pointer; font-size:1.5rem; color:#94a3b8;" title="Cerrar">
                        <i class="ph ph-x"></i>
                    </button>
                    <h3 id="payment-modal-title" style="margin-bottom:1rem; font-size:1.1rem; font-weight:800;"></h3>
                    <div id="payment-bricks-container">
                        <!-- Mercado Pago Checkout Bricks se monta aquí -->
                        <div id="mp-payment-brick" style="min-height:200px;"></div>
                    </div>
                    <div id="payment-modal-loading" style="text-align:center; padding:2rem; color:#64748b;">
                        <i class="ph ph-spinner" style="font-size:2rem; animation:spin 1s linear infinite;"></i>
                        <p>Preparando el checkout seguro...</p>
                    </div>
                    <div id="payment-modal-error" class="hidden" style="color:var(--accent-red); text-align:center; padding:1rem;"></div>
                </div>
            </div>
        `;
    },

    // =========================================================================
    // Sección: Eventos
    // =========================================================================

    setupEvents() {
        // Delegación de eventos en el container de suscripción
        const container = document.getElementById('suscripcion-container');
        if (!container) return;

        // Botones de pago de plan
        container.addEventListener('click', async (e) => {
            const payBtn = e.target.closest('.payment-pay-btn');
            if (payBtn) {
                const planId = parseInt(payBtn.dataset.planId);
                const name   = payBtn.dataset.name;
                await this.openPlanCheckout(planId, name);
            }

            // Cerrar modal
            if (e.target.closest('#payment-modal-close') || e.target.id === 'payment-checkout-modal') {
                this.closeCheckoutModal();
            }
        });

        // Toggle de débito automático
        container.addEventListener('change', async (e) => {
            if (e.target.id === 'payment-auto-debit-toggle') {
                await this.handleAutoDebitToggle(e.target.checked);
            }
        });
    },

    // =========================================================================
    // Sección: Flujo de Checkout
    // =========================================================================

    async openPlanCheckout(planId, planName) {
        this.showCheckoutModal(`Pago — ${planName}`);

        try {
            // 1. Crear preferencia de pago en el backend
            const resp = await fetch('/api/payments/create-intent.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ nature: 'plan_activation', plan_id: planId }),
            });
            const data = await resp.json();

            if (!data.success) throw new Error(data.message || 'Error al generar el checkout');

            // 2. Redirigir al checkout de Mercado Pago (Checkout Bricks / redirect)
            // En la implementación con Checkout Bricks completo, aquí se inicializaría el SDK de MP.
            // Para el flujo de redirección (más simple y seguro), redirigimos directamente.
            document.getElementById('payment-modal-loading').innerHTML = `
                <i class="ph ph-shield-check" style="font-size:2.5rem; color:var(--accent-green);"></i>
                <p style="margin:0.5rem 0;">Redirigiendo al checkout seguro de Mercado Pago...</p>
                <p style="font-size:0.8rem; color:#94a3b8;">Tu información de pago está protegida por Mercado Pago (PCI DSS).</p>
            `;

            // Pequeño delay UX antes de redirigir
            await new Promise(resolve => setTimeout(resolve, 1200));
            window.location.href = data.checkout_url;

        } catch (e) {
            this.showModalError(e.message);
        }
    },

    /**
     * Inicia el pago de una deuda de slots de colaboradores.
     * Puede ser invocado desde cualquier sección de la app.
     *
     * @param {number} debtId       - ID de la deuda en collaborator_slots_debts
     * @param {number} inventoryId  - ID del inventario
     * @param {number} totalAmount  - Monto a mostrar en la UI (informativo)
     */
    async openSlotsCheckout(debtId, inventoryId, totalAmount) {
        const confirmResult = await Swal.fire({
            title: 'Pagar Slots de Colaboradores',
            html: `Vas a pagar <strong>$${totalAmount.toLocaleString('es-AR')}</strong> por los slots adicionales de colaboradores.<br><br>Te redirigiremos al checkout seguro de Mercado Pago.`,
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: '<i class="ph ph-credit-card"></i> Ir al Checkout',
            cancelButtonText: 'Cancelar',
            customClass: {
                confirmButton: 'swal2-confirm',
                cancelButton: 'swal2-cancel'
            },
            buttonsStyling: false
        });

        if (!confirmResult.isConfirmed) return;

        try {
            const resp = await fetch('/api/payments/create-intent.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    nature: 'collaborator_slots',
                    debt_id: debtId,
                    inventory_id: inventoryId,
                }),
            });
            const data = await resp.json();
            if (!data.success) throw new Error(data.message || 'Error al generar el checkout');

            window.location.href = data.checkout_url;
        } catch (e) {
            pop_ups.error(e.message || 'Error al preparar el pago de slots.');
        }
    },

    async handleAutoDebitToggle(enabled) {
        try {
            const resp = await fetch('/api/payments/toggle-auto-debit.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ enabled }),
            });
            const data = await resp.json();

            if (data.success) {
                pop_ups.success(data.message);
                this.state.auto_debit_enabled = enabled;
            } else if (data.requires_subscription) {
                // Revertir el toggle temporalmente porque falta la autorización en MP
                const toggle = document.getElementById('payment-auto-debit-toggle');
                if (toggle) toggle.checked = false;

                const planId = this.state.plan_id;
                if (planId && planId !== 4) {
                    await this.initiateRecurringSubscription(planId);
                }
            } else {
                // Revertir el toggle si falló por otra razón
                const toggle = document.getElementById('payment-auto-debit-toggle');
                if (toggle) toggle.checked = !enabled;
                pop_ups.error(data.message || 'Error al actualizar configuración.');
            }
        } catch (e) {
            pop_ups.error('Error de conexión al actualizar configuración de débito automático.');
        }
    },

    async initiateRecurringSubscription(planId) {
        const confirmResult = await Swal.fire({
            title: 'Activar Débito Automático',
            html: `Para activar la renovación automática, necesitamos que apruebes la suscripción recurrente en Mercado Pago.<br><br>Solo lo hacés una vez y después se debita solo cada mes.`,
            icon: 'info',
            showCancelButton: true,
            confirmButtonText: 'Configurar en Mercado Pago',
            cancelButtonText: 'Cancelar',
            customClass: {
                confirmButton: 'swal2-confirm',
                cancelButton: 'swal2-cancel'
            },
            buttonsStyling: false
        });

        if (!confirmResult.isConfirmed) return;

        try {
            const resp = await fetch('/api/payments/create-intent.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ nature: 'plan_activation', plan_id: planId, subscription: true }),
            });
            const data = await resp.json();
            if (data.success) {
                window.location.href = data.checkout_url;
            } else {
                throw new Error(data.message);
            }
        } catch (e) {
            pop_ups.error(e.message || 'Error al configurar la suscripción automática.');
        }
    },

    // =========================================================================
    // Sección: Helpers de Modal
    // =========================================================================

    showCheckoutModal(title) {
        const modal   = document.getElementById('payment-checkout-modal');
        const titleEl = document.getElementById('payment-modal-title');
        const loading = document.getElementById('payment-modal-loading');
        const errorEl = document.getElementById('payment-modal-error');

        if (titleEl) titleEl.textContent = title;
        if (loading) loading.style.display = 'block';
        if (errorEl) errorEl.classList.add('hidden');
        if (modal)   modal.classList.remove('hidden');
        document.body.style.overflow = 'hidden';
    },

    closeCheckoutModal() {
        const modal = document.getElementById('payment-checkout-modal');
        if (modal) modal.classList.add('hidden');
        document.body.style.overflow = '';
    },

    showModalError(msg) {
        const loading = document.getElementById('payment-modal-loading');
        const errorEl = document.getElementById('payment-modal-error');
        if (loading) loading.style.display = 'none';
        if (errorEl) {
            errorEl.classList.remove('hidden');
            errorEl.innerHTML = `<i class="ph ph-warning-circle"></i> ${msg}`;
        }
    },
};

window.paymentsModule = paymentsModule;
