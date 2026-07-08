<?php
/**
 * plans.php - Checkout de Planes de StockiFy
 */
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/helpers/auth_helper.php';
require_once __DIR__ . '/../src/Services/Payments/PricingService.php';

use App\Services\Payments\PricingService;

$currentUser = getCurrentUser();
// Permitir ver planes a usuarios no logueados; se redirigirá si intentan adquirir.

$pricing = new PricingService();
$basicPrice = $pricing->getPlanPrice(1, $currentUser ? (int)$currentUser['id'] : null);
$professionalPrice = $pricing->getPlanPrice(2, $currentUser ? (int)$currentUser['id'] : null);
$lifetimePrice = $pricing->getPlanPrice(4, $currentUser ? (int)$currentUser['id'] : null);
$slotPrice = $pricing->getSlotUnitPrice();

$basicFormatted = '$' . number_format($basicPrice, 0, ',', '.');
$professionalFormatted = '$' . number_format($professionalPrice, 0, ',', '.');
$lifetimeFormatted = '$' . number_format($lifetimePrice, 0, ',', '.');
$slotFormatted = '$' . number_format($slotPrice, 0, ',', '.');

$professionalOriginal = round($professionalPrice / 0.85);
$professionalOriginalFormatted = '$' . number_format($professionalOriginal, 0, ',', '.');

$currentPlanId = $currentUser ? (int) ($currentUser['subscription_active'] ?? 0) : 0;

$plansData = json_encode([
    [
        'id' => 1,
        'name' => 'Básico',
        'price' => $basicFormatted,
        'priceRaw' => $basicPrice,
        'period' => ' / 30 días',
        'theme' => 'dark',
        'badge' => null,
        'badgeClass' => 'dark-badge',
        'desc' => 'Para emprendedores que recién inician y buscan orden operativo.',
        'features' => [
            ['text' => '1 Inventario activo', 'ok' => true],
            ['text' => 'Carga ilimitada de productos', 'ok' => true],
            ['text' => 'Registro de ventas y compras', 'ok' => true],
            ['text' => 'Cierre de caja diario', 'ok' => true],
            ['text' => 'Importación de datos', 'ok' => true],
            ['text' => 'Colaboradores incluidos', 'ok' => false],
            ['text' => 'Múltiples inventarios', 'ok' => false],
            ['text' => 'CRM de clientes y proveedores', 'ok' => false],
        ],
        'ctaLabel' => 'Adquirir Plan Básico',
    ],
    [
        'id' => 2,
        'name' => 'Profesional',
        'price' => $professionalFormatted,
        'priceRaw' => $professionalPrice,
        'priceStruck' => $professionalOriginalFormatted,
        'discount' => '-15% OFF',
        'period' => ' / 30 días',
        'theme' => 'pro',
        'badge' => 'Recomendado',
        'badgeClass' => 'pro-badge',
        'desc' => 'Para negocios y locales en crecimiento que necesitan delegar y controlar.',
        'features' => [
            ['text' => 'Todo lo del Plan Básico', 'ok' => true],
            ['text' => '3 cupos de usuario (dueño + 2 colaboradores)', 'ok' => true],
            ['text' => 'Inventarios ilimitados', 'ok' => true],
            ['text' => 'Gestión CRM (Clientes, Empleados, Proveedores)', 'ok' => true],
            ['text' => 'Control de Roles (RBAC)', 'ok' => true],
            ['text' => 'Analíticas y Top Productos', 'ok' => true],
            ['text' => 'Slots adicionales a ' . $slotFormatted . ' c/u', 'ok' => true],
        ],
        'ctaLabel' => 'Adquirir Plan Profesional',
    ],
    [
        'id' => 4,
        'name' => 'Vitalicio',
        'price' => $lifetimeFormatted,
        'priceRaw' => $lifetimePrice,
        'period' => ' único pago',
        'theme' => 'vital',
        'badge' => 'Único Pago',
        'badgeClass' => 'vital-badge',
        'desc' => 'Acceso de por vida. Sin renovaciones mensuales, jamás.',
        'features' => [
            ['text' => 'Todo lo del Plan Profesional', 'ok' => true],
            ['text' => '5 colaboradores incluidos', 'ok' => true],
            ['text' => 'Sin renovaciones mensuales', 'ok' => true],
            ['text' => 'Mantenimiento y soporte por 1 año', 'ok' => true],
            ['text' => 'Actualizaciones incluidas por 1 año', 'ok' => true],
        ],
        'ctaLabel' => 'Solicitar Plan Vitalicio',
    ],
    [
        'id' => 5,
        'name' => 'Empresarial',
        'price' => 'A Medida',
        'priceRaw' => 0,
        'period' => ' / 30 días',
        'theme' => 'dark',
        'badge' => 'Personalizado',
        'badgeClass' => 'dark-badge',
        'desc' => 'A la medida de grandes operaciones. Módulos propios, soporte prioritario.',
        'features' => [
            ['text' => 'Todo lo del Plan Profesional', 'ok' => true],
            ['text' => 'Colaboradores e Inventarios a medida', 'ok' => true],
            ['text' => 'Integraciones API personalizadas', 'ok' => true],
            ['text' => 'Servidor dedicado opcional', 'ok' => true],
            ['text' => 'Soporte premium 24/7', 'ok' => true],
        ],
        'ctaLabel' => 'Solicitar Plan Empresarial',
    ],
]);

$initialPlanIndex = 1;
if (isset($_GET['plan'])) {
    $p = (int) $_GET['plan'];
    if ($p === 1)
        $initialPlanIndex = 0;
    elseif ($p === 2)
        $initialPlanIndex = 1;
    elseif ($p === 4)
        $initialPlanIndex = 2;
    elseif ($p === 5)
        $initialPlanIndex = 3;
}
$currentPlanIdJson = json_encode($currentPlanId);
$currentUserJson = json_encode([
    'full_name' => $currentUser ? ($currentUser['full_name'] ?? '') : '',
    'email' => $currentUser ? ($currentUser['email'] ?? '') : '',
    'cell' => $currentUser ? ($currentUser['cell'] ?? '') : '',
]);
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Elegí tu Plan | StockiFy</title>
    <meta name="description"
        content="Seleccioná el plan de StockiFy para tu negocio. Pagos seguros con tu billetera favorita.">
    <link rel="stylesheet" href="/assets/css/main.css">
    <link rel="stylesheet" href="/assets/css/sweetalert.css">
    <!-- Phosphor Icons -->
    <link rel="stylesheet" type="text/css"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css" />
    <link rel="stylesheet" type="text/css"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css" />
    <link rel="stylesheet" type="text/css"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/bold/style.css" />
    <script src="/assets/js/sweetalert2.all.min.js?v=11.0"></script>
    <script>
        if (typeof Swal === 'undefined') {
            var _s = document.createElement('script');
            _s.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
            document.head.appendChild(_s);
        }
    </script>
    <script src="/assets/js/theme.js"></script>
    <link rel="stylesheet" href="/assets/css/plans.css">
</head>

<body id="page-plans">

    <!-- NAV -->
    <header>
        <a href="dashboard" id="header-logo">
            <img src="assets/img/LogoE.png" alt="Stocky Logo">
        </a>
        <nav id="header-nav">
            <a href="about-us.php" class="btn btn-secondary">
                <i class="ph ph-users"></i>
                <span>¿Quiénes Somos?</span>
            </a>
            <?php if ($currentUser): ?>
                <a href="settings.php" class="btn btn-secondary">
                    <i class="ph ph-gear"></i>
                    <span>Configuración</span>
                </a>
                <a href="logout.php" class="btn btn-secondary btn-header-logout">
                    <i class="ph ph-sign-out"></i>
                    <span>Cerrar Sesión</span>
                </a>
            <?php else: ?>
                <a href="login.php" class="btn btn-secondary">
                    <i class="ph ph-sign-in"></i>
                    <span>Iniciar Sesión</span>
                </a>
            <?php endif; ?>
        </nav>
    </header>

    <div class="plans-outer">

        <!-- Header de página -->
        <div class="plans-top-header">
            <p class="plans-top-eyebrow"><i class="ph ph-tag"></i> Planes y Precios</p>
            <h1 class="plans-top-title">Elegí el plan perfecto para tu negocio</h1>
            <p class="plans-top-subtitle">Todos los pagos son procesados de forma segura por tu billetera seleccionada.
                Sin sorpresas.</p>
        </div>

        <!-- Mobile Plan Tabs (visible solo en ≤768px via CSS) -->
        <div class="mobile-plan-tabs" id="mobile-plan-tabs">
            <button class="mpt-tab" data-idx="0">
                <i class="ph ph-package"></i>
                <span>Básico</span>
            </button>
            <button class="mpt-tab active" data-idx="1">
                <i class="ph-fill ph-star"></i>
                <span>Pro</span>
            </button>
            <button class="mpt-tab" data-idx="2">
                <i class="ph-fill ph-sketch-logo"></i>
                <span>Vital</span>
            </button>
            <button class="mpt-tab" data-idx="3">
                <i class="ph-fill ph-buildings"></i>
                <span>Empresa</span>
            </button>
        </div>

        <!-- Caja invisible: 3 columnas alineadas horizontalmente (Grid de 5 columnas, con divisores) -->
        <div class="checkout-frame">

            <!-- ══════════════════════════════════════ -->
            <!-- COL 1: Carrusel / Selector            -->
            <!-- ══════════════════════════════════════ -->
            <div class="col-carousel">

                <!-- Carrusel (Básico / Profesional) -->
                <div id="monthly-carousel-area" class="monthly-carousel-area">
                    <div class="carousel-row">
                        <button class="carousel-arrow" id="carousel-prev" aria-label="Plan anterior">
                            <i class="ph-bold ph-caret-left"></i>
                        </button>
                        <div class="carousel-card-slot" id="carousel-slot">
                            <!-- JS inyecta Básico/Profesional -->
                        </div>
                        <button class="carousel-arrow" id="carousel-next" aria-label="Plan siguiente">
                            <i class="ph-bold ph-caret-right"></i>
                        </button>
                    </div>
                    <div class="carousel-dots" id="carousel-dots"></div>
                </div>

                <!-- Slot para mostrar la tarjeta de Plan Especial si está activo -->
                <div id="special-card-area" class="special-card-area">
                    <div id="special-card-slot">
                        <!-- JS inyecta la card del Plan Especial -->
                    </div>
                    <button class="back-to-monthly-btn" id="back-to-monthly">
                        <i class="ph ph-arrow-left"></i> Ver planes mensuales
                    </button>
                </div>

                <!-- Subdivisión Visual -->
                <div class="special-plans-divider">
                </div>

                <!-- Selector para Plan Vitalicio (sin botón Ver) -->
                <div class="custom-plan-selector-card" id="select-vitalicio" role="button">
                    <i class="ph-fill ph-sketch-logo"></i>
                    <div class="custom-plan-selector-card-info">
                        <strong>Plan Vitalicio</strong>
                        <span>Único pago · De por vida</span>
                    </div>
                </div>

                <!-- Selector para Plan Empresarial (sin botón Ver) -->
                <div class="custom-plan-selector-card" id="select-empresarial" role="button">
                    <i class="ph-fill ph-buildings"></i>
                    <div class="custom-plan-selector-card-info">
                        <strong>Plan Empresarial</strong>
                        <span>A medida · Módulos y soporte</span>
                    </div>
                </div>

            </div>

            <!-- ══════════════════════════════════════ -->
            <!-- COL 2: Detalle del plan / Formulario   -->
            <!-- ══════════════════════════════════════ -->
            <div class="col-detail">

                <div>
                    <p class="detail-eyebrow">Plan Seleccionado:</p>
                    <h2 class="detail-plan-name" id="detail-name">—</h2>
                    <p class="detail-plan-desc" id="detail-desc"></p>
                </div>

                <!-- Listado de features del plan -->
                <ul class="detail-benefits" id="detail-benefits">
                    <!-- JS inyecta los beneficios -->
                </ul>

                <!-- Sección de Acción: Checkout o Formulario de Contacto -->
                <div id="action-area">
                    <!-- Se inyecta dinámicamente:
                     1) Selector método de pago + CTA "Ir al checkout" (para Básico y Profesional)
                     2) Formulario de solicitud (para Vitalicio y Empresarial) -->
                </div>

            </div>

            <!-- ══════════════════════════════════════ -->
            <!-- COL 3: Subventana dividida a la mitad  -->
            <!-- ══════════════════════════════════════ -->
            <div class="col-breakdown">
                <!-- Mitad Superior: Desglose / Resumen de solicitud -->
                <div class="mobile-accordion" id="accordion-breakdown">
                    <button class="mobile-accordion-toggle" aria-expanded="false">
                        <i class="ph-bold ph-receipt"></i>
                        <span>Desglose de precios</span>
                        <i class="ph ph-caret-down mobile-accordion-chevron"></i>
                    </button>
                    <div class="mobile-accordion-body">
                        <div class="breakdown-card">
                            <h3 class="breakdown-title" id="breakdown-card-title">
                                <i class="ph-bold ph-receipt"></i>
                                Desglose de precios
                            </h3>
                            <div id="breakdown-lines-or-steps"></div>
                        </div>
                    </div>
                </div>

                <!-- Mitad Inferior: Políticas de Confianza y Seguridad -->
                <div class="mobile-accordion" id="accordion-trust">
                    <button class="mobile-accordion-toggle" aria-expanded="false">
                        <i class="ph-fill ph-shield-check"></i>
                        <span>Seguridad y Confianza</span>
                        <i class="ph ph-caret-down mobile-accordion-chevron"></i>
                    </button>
                    <div class="mobile-accordion-body">
                        <div class="trust-card">
                            <div class="trust-item">
                                <i class="ph-fill ph-shield-check"></i>
                                <span>StockiFy <strong>no almacena</strong> tus datos bancarios. Los pagos se procesan a través
                                    de la billetera seleccionada.</span>
                            </div>
                            <div class="trust-item">
                                <i class="ph-fill ph-arrows-clockwise"></i>
                                <span><strong>Prepago sin contratos:</strong> Pagás únicamente los 30 días de acceso. Si no
                                    renovás, no hay débitos automáticos ni penalidades.</span>
                            </div>
                            <div class="trust-item">
                                <i class="ph-fill ph-database"></i>
                                <span><strong>Garantía de tus datos:</strong> Si tu plan expira y no lo renovás a tiempo, tu
                                    cuenta vuelve a la versión gratuita y <strong>tus datos no se borran</strong> (se resguardan
                                    por hasta 1 año).</span>
                            </div>
                            <div class="trust-item">
                                <i class="ph-fill ph-users-three"></i>
                                <span>Colaboradores adicionales: <strong><?= $slotFormatted ?></strong> por slot / 30 días
                                    (Planes
                                    Profesional y Vitalicio).</span>
                            </div>

                            <div class="trust-badge">
                                <i class="ph-fill ph-lock-key" style="color: var(--accent-color)"></i>
                                Transacción 100% segura
                            </div>

                            <div class="trust-links">
                                <a href="terms-of-service.php" class="trust-link">Términos y Condiciones</a>
                                <a href="privacy-policy.php" class="trust-link">Política de Privacidad</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div><!-- /.checkout-frame -->

        <!-- SECCIÓN DE PREGUNTAS FRECUENTES (FAQ) -->
        <div class="plans-faq-section">
            <h3 class="faq-section-title">Preguntas Frecuentes</h3>
            <div class="faq-grid">
                <div class="faq-item">
                    <button class="faq-question-toggle">
                        <h4 class="faq-question">¿Cómo funciona el modelo prepago?</h4>
                        <i class="ph ph-caret-down faq-chevron"></i>
                    </button>
                    <div class="faq-answer-wrap">
                        <p class="faq-answer">Es simple: pagás por adelantado el acceso por 30 días. Al finalizar este
                            período, el sistema no te renovará de manera automática ni te cobrará nada sin tu autorización.
                            Vos decidís cuándo y cómo renovar tu plan.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <button class="faq-question-toggle">
                        <h4 class="faq-question">¿Qué pasa si mi plan vence y no lo renuevo? ¿Pierdo mis datos?</h4>
                        <i class="ph ph-caret-down faq-chevron"></i>
                    </button>
                    <div class="faq-answer-wrap">
                        <p class="faq-answer">No, para nada. Tu información está 100% segura. Si tu plan expira y decidís no
                            renovarlo inmediatamente, tu cuenta volverá a la versión gratuita de forma automática. Tus
                            productos, ventas registradas y base de datos se mantendrán intactos. Te garantizamos el
                            resguardo seguro de tus datos por hasta un año de inactividad total.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <button class="faq-question-toggle">
                        <h4 class="faq-question">¿Cuáles son los medios de pago aceptados?</h4>
                        <i class="ph ph-caret-down faq-chevron"></i>
                    </button>
                    <div class="faq-answer-wrap">
                        <p class="faq-answer">Todos los pagos se procesan exclusivamente a través de Mercado Pago. Dentro de
                            su plataforma segura, podés abonar usando dinero en cuenta, transferencia bancaria (mediante
                            Alias o CBU/CVU), tarjeta de débito o crédito, o en efectivo (a través de Pago Fácil /
                            Rapipago).</p>
                    </div>
                </div>
                <div class="faq-item">
                    <button class="faq-question-toggle">
                        <h4 class="faq-question">¿Puedo pasarme de un plan mensual a otro en cualquier momento?</h4>
                        <i class="ph ph-caret-down faq-chevron"></i>
                    </button>
                    <div class="faq-answer-wrap">
                        <p class="faq-answer">Sí, podés cambiar de plan cuando quieras. El sistema tomará los días no
                            utilizados de tu plan actual a tu favor y calculará la diferencia de manera transparente.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <button class="faq-question-toggle">
                        <h4 class="faq-question">¿Qué es el Plan Vitalicio?</h4>
                        <i class="ph ph-caret-down faq-chevron"></i>
                    </button>
                    <div class="faq-answer-wrap">
                        <p class="faq-answer">Es un pago único que te otorga acceso de por vida al uso de la app con todas
                            las características del Plan Profesional (con 5 colaboradores incluidos) y 1 año completo de
                            actualizaciones y soporte. Los años siguientes, las actualizaciones y el mantenimiento son
                            opcionales y se abonan de forma anual.</p>
                    </div>
                </div>
                <div class="faq-item">
                    <button class="faq-question-toggle">
                        <h4 class="faq-question">¿Puedo activar la renovación automática si lo deseo?</h4>
                        <i class="ph ph-caret-down faq-chevron"></i>
                    </button>
                    <div class="faq-answer-wrap">
                        <p class="faq-answer">Sí, si preferís olvidarte de los vencimientos mensuales, podés activar el
                            débito automático en cualquier momento desde la sección de configuración de tu cuenta.</p>
                    </div>
                </div>
            </div>
        </div>

    </div><!-- /.plans-outer -->

    <!-- Modal de carga de checkout -->
    <div id="checkout-modal">
        <div class="checkout-modal-box">
            <button class="checkout-modal-close" id="modal-close-btn"><i class="ph ph-x"></i></button>
            <h3 id="modal-title" class="modal-title"></h3>
            <div id="modal-loading" class="modal-loading-box">
                <i class="ph ph-spinner modal-spinner"></i>
                <p class="modal-loading-title">Preparando el checkout seguro...</p>
                <p class="modal-loading-subtitle">Serás redirigido a tu billetera en segundos</p>
            </div>
            <div id="modal-error" class="modal-error-box">
                <i class="ph ph-warning-circle modal-error-icon"></i>
                <span id="modal-error-text" class="modal-error-text"></span>
            </div>
        </div>
    </div>

    <script type="module">
        /* ───────────────────────────────────────────
           DATA
        ─────────────────────────────────────────── */
        const PLANS = <?= $plansData ?>;
        const CURRENT_PLAN = <?= $currentPlanIdJson ?>;
        const USER_DATA = <?= $currentUserJson ?>;
        const IS_LOGGED_IN = <?= json_encode($currentUser !== null) ?>;
        let activeIdx = <?= $initialPlanIndex ?>; // 0: Básico, 1: Profesional, 2: Vitalicio, 3: Empresarial
        let slideDir = 'right';

        const isCurrent = (plan) => plan.id === CURRENT_PLAN && CURRENT_PLAN > 0;

        /* ───────────────────────────────────────────
           ROUTING / SELECTION
        ─────────────────────────────────────────── */
        function selectPlan(idx) {
            if (idx === activeIdx) return;
            slideDir = idx > activeIdx ? 'right' : 'left';
            activeIdx = idx;
            updateView();
        }

        function updateView() {
            renderLeftColumn();
            renderCenterColumn();
            renderRightColumn();

            // Transición suave para la columna central
            const detailCol = document.querySelector('.col-detail');
            if (detailCol) {
                detailCol.classList.remove('transition-fade-blur');
                void detailCol.offsetWidth; // trigger reflow
                detailCol.classList.add('transition-fade-blur');
            }
        }

        /* ───────────────────────────────────────────
           RENDER LEFT COLUMN
        ─────────────────────────────────────────── */
        function renderLeftColumn() {
            const monthlyArea = document.getElementById('monthly-carousel-area');
            const specialArea = document.getElementById('special-card-area');
            const slot = document.getElementById('carousel-slot');
            const dots = document.getElementById('carousel-dots');
            const specSlot = document.getElementById('special-card-slot');

            // Desmarcar selectores especiales por defecto
            document.getElementById('select-vitalicio').classList.remove('active-selection');
            document.getElementById('select-empresarial').classList.remove('active-selection');

            if (activeIdx === 0 || activeIdx === 1) {
                // Modo Mensual (Carrusel activo)
                monthlyArea.style.display = 'flex';
                specialArea.style.display = 'none';

                slot.innerHTML = '';
                dots.innerHTML = '';

                [PLANS[0], PLANS[1]].forEach((plan, i) => {
                    const indexInPlans = i; // 0 o 1
                    const card = document.createElement('div');
                    const themeClass = plan.theme === 'pro' ? 'card-theme-pro' : 'card-theme-dark';

                    card.className = [
                        'pricing-card-v2',
                        themeClass,
                        indexInPlans === activeIdx ? (slideDir === 'right' ? 'card-anim-right' : 'card-anim-left') : '',
                        isCurrent(plan) ? 'card-current' : '',
                    ].filter(Boolean).join(' ');

                    card.style.display = indexInPlans === activeIdx ? 'flex' : 'none';

                    // Badge
                    let badgeHtml = '';
                    if (isCurrent(plan)) {
                        badgeHtml = `<span class="${plan.badgeClass || 'dark-badge'}">Plan Actual</span>`;
                    } else if (plan.badge) {
                        badgeHtml = `<span class="${plan.badgeClass || 'dark-badge'}">${plan.badge}</span>`;
                    }

                    // Precio
                    let priceHtml = '';
                    if (plan.priceStruck) {
                        priceHtml = `<div class="price-container">
                    <div class="price-original">
                        <span class="price-strike">${plan.priceStruck}</span>
                        <span class="discount-badge">${plan.discount}</span>
                    </div>
                    <div class="price-val">${plan.price}<span class="price-period">${plan.period}</span></div>
                </div>`;
                    } else {
                        priceHtml = `<div class="price-val">${plan.price}<span class="price-period">${plan.period}</span></div>`;
                    }

                    // Features (max 7)
                    const feats = (plan.features || []).slice(0, 7).map(f => {
                        const icon = f.ok
                            ? `<i class="ph-bold ph-check"></i>`
                            : `<i class="ph-bold ph-x"></i>`;
                        return `<li>${icon}${f.text}</li>`;
                    }).join('');

                    // Se removió el botón "Adquirir..." redundante de la tarjeta de preview

                    card.innerHTML = `${badgeHtml}<h3>${plan.name}</h3>${priceHtml}<ul>${feats}</ul>`;
                    slot.appendChild(card);

                    // Dot
                    const dot = document.createElement('div');
                    dot.className = 'c-dot' + (indexInPlans === activeIdx ? ' active' : '');
                    dot.addEventListener('click', () => selectPlan(indexInPlans));
                    dots.appendChild(dot);
                });

            } else {
                // Modo Especial (Vitalicio o Empresarial)
                monthlyArea.style.display = 'none';
                specialArea.style.display = 'flex';

                specSlot.innerHTML = '';
                const plan = PLANS[activeIdx];
                const themeClass = plan.theme === 'vital' ? 'card-theme-vital' : 'card-theme-dark';

                const card = document.createElement('div');
                card.className = [
                    'pricing-card-v2',
                    themeClass,
                    'card-anim-right',
                    isCurrent(plan) ? 'card-current' : '',
                ].filter(Boolean).join(' ');

                // Badge
                let badgeHtml = '';
                if (isCurrent(plan)) {
                    badgeHtml = `<span class="${plan.badgeClass || 'dark-badge'}">Plan Actual</span>`;
                } else if (plan.badge) {
                    badgeHtml = `<span class="${plan.badgeClass || 'dark-badge'}">${plan.badge}</span>`;
                }

                // Precio
                let priceHtml = `<div class="price-val">${plan.price}<span class="price-period">${plan.period}</span></div>`;

                // Features (max 7)
                const feats = (plan.features || []).slice(0, 7).map(f => {
                    const icon = f.ok
                        ? `<i class="ph-bold ph-check"></i>`
                        : `<i class="ph-bold ph-x"></i>`;
                    return `<li>${icon}${f.text}</li>`;
                }).join('');

                // Se removió el botón "Ver/Adquirir" de la tarjeta de preview

                card.innerHTML = `${badgeHtml}<h3>${plan.name}</h3>${priceHtml}<ul>${feats}</ul>`;
                specSlot.appendChild(card);

                // Resaltar selector del pie de columna
                if (activeIdx === 2) {
                    document.getElementById('select-vitalicio').classList.add('active-selection');
                } else if (activeIdx === 3) {
                    document.getElementById('select-empresarial').classList.add('active-selection');
                }
            }
        }

        /* ───────────────────────────────────────────
           RENDER CENTER COLUMN
        ─────────────────────────────────────────── */
        function renderCenterColumn() {
            const plan = PLANS[activeIdx];

            const nameEl = document.getElementById('detail-name');
            nameEl.textContent = plan.name;
            document.getElementById('detail-desc').textContent = plan.desc || '';

            // Beneficios listado
            document.getElementById('detail-benefits').innerHTML = (plan.features || []).map(f => {
                const iconClass = f.ok ? 'ph-bold ph-check ok' : 'ph-bold ph-x no';
                return `<li class="detail-benefit"><i class="${iconClass}"></i><span>${f.text}</span></li>`;
            }).join('');

            const actionArea = document.getElementById('action-area');
            actionArea.innerHTML = '';

            if (activeIdx === 0 || activeIdx === 1) {
                // Mensuales: selector de pago + Checkout MP
                actionArea.innerHTML = `
            <p class="payment-label">Seleccione un método de pago:</p>
            <div class="payment-method-card" id="mp-card" role="button" tabindex="0">
                <span class="payment-selected-pill" style="display: none;">Seleccionado</span>
                <img src="/assets/img/iconos/mp.png" alt="Mercado Pago" class="payment-method-logo">
                <div class="payment-method-info">
                    <p class="payment-method-name">Mercado Pago</p>
                    <p class="payment-method-subdesc">Tarjeta, débito, efectivo y más desde tu billetera</p>
                </div>
                <i class="ph-bold ph-caret-right payment-method-arrow"></i>
            </div>
            <p class="payment-more-note">Más billeteras próximamente</p>

            <button class="main-cta main-cta-margin-mp" id="main-cta-btn">
                <i class="ph ph-credit-card"></i>
                <span>${plan.ctaLabel}</span>
            </button>
        `;

                let selectedPayment = false; // El método de pago empieza deseleccionado para evitar confusión

                const btn = document.getElementById('main-cta-btn');
                if (isCurrent(plan)) {
                    btn.className = 'main-cta main-cta-margin-mp is-disabled';
                    btn.disabled = true;
                    btn.innerHTML = '<i class="ph ph-check-circle"></i><span>Tu plan actual</span>';
                } else {
                    btn.onclick = () => {
                        if (!selectedPayment) {
                            Swal.fire({
                                title: 'Seleccioná un método de pago',
                                text: 'Por favor, hacé clic en Mercado Pago para seleccionarlo antes de continuar con la suscripción.',
                                icon: 'info',
                                confirmButtonText: 'Entendido',
                                confirmButtonColor: 'var(--accent-color)',
                                customClass: {
                                    popup: 'swal-custom-popup',
                                    confirmButton: 'swal-custom-confirm-btn'
                                }
                            });
                            return;
                        }
                        triggerCheckout();
                    };
                }

                // Listener en la tarjeta para seleccionarla
                const mpCard = document.getElementById('mp-card');
                mpCard.addEventListener('click', () => {
                    if (isCurrent(plan)) return;
                    selectedPayment = !selectedPayment;
                    if (selectedPayment) {
                        mpCard.classList.add('payment-selected-active');
                        mpCard.querySelector('.payment-selected-pill').style.display = 'block';
                    } else {
                        mpCard.classList.remove('payment-selected-active');
                        mpCard.querySelector('.payment-selected-pill').style.display = 'none';
                    }
                });

            } else {
                // Especiales (Vitalicio / Empresarial): Formulario de contacto
                const titleText = activeIdx === 2 ? 'Solicitud de Plan Vitalicio' : 'Solicitud de Plan Empresarial';
                actionArea.innerHTML = `
            <form class="contact-form-box" id="contact-form">
                <h4 class="contact-form-title">
                    <i class="ph ph-envelope-simple-open"></i>
                    <span>${titleText}</span>
                </h4>
                <div class="form-group">
                    <label for="contact-name">Nombre y Apellido</label>
                    <input type="text" id="contact-name" class="form-input" required value="${USER_DATA.full_name}">
                </div>
                <div class="form-group">
                    <label for="contact-phone">Número de teléfono (WhatsApp)</label>
                    <input type="text" id="contact-phone" class="form-input" required placeholder="Ej: +54 9 11 1234 5678" value="${USER_DATA.cell}">
                </div>
                <div class="form-group">
                    <label for="contact-email">Correo electrónico</label>
                    <input type="email" id="contact-email" class="form-input" required value="${USER_DATA.email}">
                </div>
                <div class="form-group">
                    <label for="contact-comments">¿Qué idea tenés o qué requerimientos precisás?</label>
                    <textarea id="contact-comments" class="form-input form-textarea" placeholder="Breve texto comentando vagamente tu idea o qué precisás para tu local..."></textarea>
                </div>
                <button type="submit" class="main-cta main-cta-margin-contact" id="contact-submit-btn">
                    <i class="ph ph-paper-plane-tilt"></i>
                    <span>Enviar Solicitud por Email</span>
                </button>
            </form>
        `;

                document.getElementById('contact-form').addEventListener('submit', handleFormSubmit);
            }
        }

        /* ───────────────────────────────────────────
           RENDER RIGHT COLUMN
        ─────────────────────────────────────────── */
        function renderRightColumn() {
            const plan = PLANS[activeIdx];
            const titleEl = document.getElementById('breakdown-card-title');
            const contentEl = document.getElementById('breakdown-lines-or-steps');

            if (activeIdx === 0 || activeIdx === 1) {
                // Desglose de precios tradicional
                titleEl.innerHTML = '<i class="ph-bold ph-receipt"></i> Desglose de precios';

                let html = '';
                if (plan.priceStruck) {
                    html += `
            <div class="price-line">
                <span class="price-line-label"><i class="ph ph-tag"></i> Precio sin descuento</span>
                <span class="price-line-val struck">${plan.priceStruck}</span>
            </div>
            <div class="price-line">
                <span class="price-line-label"><i class="ph ph-percent"></i> Descuento</span>
                <span class="price-line-val accent-text">${plan.discount}</span>
            </div>
            <div class="price-sep"></div>`;
                }

                html += `
        <div class="price-line">
            <span class="price-line-label"><i class="ph ph-package"></i> Plan ${plan.name}</span>
            <span class="price-line-val">${plan.price}</span>
        </div>`;

                html += `
        <div class="price-line" style="opacity:.65;">
            <span class="price-line-label"><i class="ph ph-users"></i> Colaboradores adicionales</span>
            <span class="price-line-val price-line-colaboradores">Se suman si contratás</span>
        </div>`;

                html += `
        <div class="price-total-row">
            <span class="price-total-label">Total / 30 días</span>
            <span class="price-total-val">${plan.price}</span>
        </div>
        <p class="price-total-note">Pago único por adelantado · Sin renovación automática obligatoria</p>`;

                contentEl.innerHTML = html;

            } else {
                // Proceso de Solicitud de cotización
                titleEl.innerHTML = '<i class="ph-bold ph-paper-plane-tilt"></i> Solicitud de Plan';

                const isVital = (plan.id === 4);
                const periodNote = isVital ? 'Pago único · Sin renovaciones' : 'Pago por 30 días · Sin renovación automática';

                contentEl.innerHTML = `
            <div class="request-info-box">
                <p class="request-info-intro">
                    Completá la información del formulario central para iniciar el proceso de activación especial (${isVital ? 'pago único' : 'abono mensual'}).
                </p>
                <div class="request-step">
                    <span class="request-step-num">1</span>
                    <div>
                        <strong class="request-step-title">Enviar Formulario</strong>
                        <span class="request-step-desc">Los datos le llegarán a Joaquín por correo de manera inmediata.</span>
                    </div>
                </div>
                <div class="request-step">
                    <span class="request-step-num">2</span>
                    <div>
                        <strong class="request-step-title">Contacto Personal</strong>
                        <span class="request-step-desc">Te contactaremos por WhatsApp o mail para charlar sobre tu negocio.</span>
                    </div>
                </div>
                <div class="request-step">
                    <span class="request-step-num">3</span>
                    <div>
                        <strong class="request-step-title">Activación y Pago</strong>
                        <span class="request-step-desc">Coordinamos el método de pago que prefieras y activamos la cuenta.</span>
                    </div>
                </div>
                <p class="request-warning">
                    * Tu cuenta no sufrirá ningún cargo automático por esta solicitud.
                </p>
                <p class="request-period-note">
                    ${periodNote}
                </p>
            </div>
        `;
            }
        }

        /* ───────────────────────────────────────────
           EVENT LISTENERS — Left Column selectors
        ─────────────────────────────────────────── */
        document.getElementById('carousel-prev').addEventListener('click', () => {
            // Solo navega entre index 0 (Básico) y 1 (Profesional)
            const nextIdx = activeIdx === 0 ? 1 : 0;
            selectPlan(nextIdx);
        });
        document.getElementById('carousel-next').addEventListener('click', () => {
            const nextIdx = activeIdx === 0 ? 1 : 0;
            selectPlan(nextIdx);
        });

        document.getElementById('back-to-monthly').addEventListener('click', () => {
            selectPlan(1); // Volver al Profesional por defecto
        });

        document.getElementById('select-vitalicio').addEventListener('click', () => {
            selectPlan(2);
        });

        document.getElementById('select-empresarial').addEventListener('click', () => {
            selectPlan(3);
        });

        /* ───────────────────────────────────────────
           CHECKOUT (Mercado Pago)
        ─────────────────────────────────────────── */
        function triggerCheckout() {
            if (!IS_LOGGED_IN) {
                window.location.href = 'login.php';
                return;
            }
            const plan = PLANS[activeIdx];
            if (isCurrent(plan)) return;

            const modal = document.getElementById('checkout-modal');
            const titleEl = document.getElementById('modal-title');
            const loadEl = document.getElementById('modal-loading');
            const errEl = document.getElementById('modal-error');

            titleEl.textContent = 'Procesando — ' + plan.name;
            loadEl.style.display = 'block';
            errEl.style.display = 'none';
            modal.classList.add('open');
            document.body.style.overflow = 'hidden';

            fetch('/api/payments/create-intent.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ nature: 'plan_activation', plan_id: plan.id }),
            })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) throw new Error(data.message || 'Error al generar el checkout');
                    loadEl.innerHTML =
                        '<i class="ph ph-shield-check modal-success-icon"></i>' +
                        '<p class="modal-loading-title">Redirigiendo a tu billetera...</p>' +
                        '<p class="modal-loading-subtitle">Tus datos están protegidos</p>';
                    return new Promise(r => setTimeout(r, 1000)).then(() => {
                        window.location.href = data.checkout_url;
                    });
                })
                .catch(e => {
                    loadEl.style.display = 'none';
                    errEl.style.display = 'block';
                    document.getElementById('modal-error-text').textContent = e.message;
                });
        }

        /* ───────────────────────────────────────────
           FORM SUBMISSION (Vitalicio / Empresarial)
        ─────────────────────────────────────────── */
        function handleFormSubmit(e) {
            e.preventDefault();
            if (!IS_LOGGED_IN) {
                window.location.href = 'login.php';
                return;
            }

            const plan = PLANS[activeIdx];
            const submitBtn = document.getElementById('contact-submit-btn');

            // Cambiar estado a cargando
            const originalContent = submitBtn.innerHTML;
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="ph ph-spinner" style="animation:spin 1s linear infinite;"></i> Enviando solicitud...';

            const reqData = {
                plan_name: plan.id === 4 ? 'Plan Vitalicio' : 'Plan Empresarial',
                name: document.getElementById('contact-name').value,
                phone: document.getElementById('contact-phone').value,
                email: document.getElementById('contact-email').value,
                comments: document.getElementById('contact-comments').value
            };

            fetch('/api/payments/request-custom-plan.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(reqData)
            })
                .then(r => r.json())
                .then(data => {
                    if (!data.success) throw new Error(data.message || 'Error al procesar la solicitud.');

                    Swal.fire({
                        title: '¡Solicitud Recibida!',
                        text: 'Tu solicitud ha sido enviada por correo. Joaquín se contactará con vos a la brevedad por WhatsApp.',
                        icon: 'success',
                        confirmButtonText: 'Entendido',
                        confirmButtonColor: 'var(--accent-color)',
                        customClass: {
                            popup: 'swal-custom-popup',
                            confirmButton: 'swal-custom-confirm-btn'
                        }
                    }).then(() => {
                        // Volver a planes mensuales
                        selectPlan(1);
                    });
                })
                .catch(error => {
                    Swal.fire({
                        title: 'Error',
                        text: error.message,
                        icon: 'error',
                        confirmButtonText: 'Intentar nuevamente',
                        confirmButtonColor: 'var(--accent-red)'
                    });
                })
                .finally(() => {
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = originalContent;
                });
        }

        function closeModal() {
            document.getElementById('checkout-modal').classList.remove('open');
            document.body.style.overflow = '';
        }
        document.getElementById('modal-close-btn').addEventListener('click', closeModal);
        document.getElementById('checkout-modal').addEventListener('click', e => {
            if (e.target === e.currentTarget) closeModal();
        });

        /* ───────────────────────────────────────────
           MOBILE PLAN TABS
        ─────────────────────────────────────────── */
        const mobileTabs = document.getElementById('mobile-plan-tabs');
        if (mobileTabs) {
            mobileTabs.querySelectorAll('.mpt-tab').forEach(tab => {
                tab.addEventListener('click', () => {
                    const idx = parseInt(tab.dataset.idx, 10);
                    selectPlan(idx);
                });
            });
        }

        // Sync mobile tabs when activeIdx changes
        const originalUpdateView = updateView;
        updateView = function() {
            originalUpdateView();
            syncMobileTabs();
        };

        function syncMobileTabs() {
            if (!mobileTabs) return;
            mobileTabs.querySelectorAll('.mpt-tab').forEach(tab => {
                const idx = parseInt(tab.dataset.idx, 10);
                tab.classList.toggle('active', idx === activeIdx);
            });
        }

        /* ───────────────────────────────────────────
           MOBILE ACCORDIONS (Breakdown / Trust)
        ─────────────────────────────────────────── */
        document.querySelectorAll('.mobile-accordion-toggle').forEach(toggle => {
            toggle.addEventListener('click', () => {
                const accordion = toggle.closest('.mobile-accordion');
                const isOpen = accordion.classList.contains('open');
                accordion.classList.toggle('open', !isOpen);
                toggle.setAttribute('aria-expanded', !isOpen);
            });
        });

        /* ───────────────────────────────────────────
           MOBILE FAQ ACCORDIONS
        ─────────────────────────────────────────── */
        document.querySelectorAll('.faq-question-toggle').forEach(toggle => {
            toggle.addEventListener('click', () => {
                const item = toggle.closest('.faq-item');
                const isOpen = item.classList.contains('faq-open');
                item.classList.toggle('faq-open', !isOpen);
            });
        });

        /* ───────────────────────────────────────────
           INIT
        ─────────────────────────────────────────── */
        updateView();
    </script>

</body>

</html>