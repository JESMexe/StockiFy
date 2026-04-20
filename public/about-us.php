<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/helpers/auth_helper.php';
$currentUser = getCurrentUser();
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StockiFy - La Plataforma de Gestión Imbatible</title>
    <!-- Fonts -->
    <link rel="stylesheet" href="assets/css/main.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/about-us.css?v=<?= time() ?>">
    <link rel="stylesheet" href="assets/css/promo-bar.css?v=<?= time() ?>">
    <!-- Icons -->
    <link rel="stylesheet" type="text/css"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css" />
    <link rel="stylesheet" type="text/css"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css" />
    <link rel="stylesheet" type="text/css"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/bold/style.css" />
    <!-- Dynamic Theme -->
    <script src="assets/js/theme.js"></script>
    <script src="assets/js/about-us.js"></script>
    <script src="assets/js/promo-bar.js" defer></script>
</head>

<body class="bg-pattern">
    <header>
        <a href="index" id="header-logo">
            <img src="assets/img/LogoE.png" alt="StockiFy Logo">
        </a>
        <nav id="header-nav" style="display: flex; gap: 10px;">
            <?php if ($currentUser): ?>
                <a href="select-db" class="btn btn-primary" style="margin:0;">Ir al Panel</a>
            <?php endif; ?>
            <a href="index" class="btn btn-secondary" style="margin:0;">Volver al Inicio</a>
        </nav>
    </header>
    <?php
    $showPromoBar = !$currentUser || (isset($currentUser['subscription_active']) && (int) $currentUser['subscription_active'] === 0);
    if ($showPromoBar):
        ?>
        <div class="promo-secondary-bar">
            <div class="promo-bg-carousel">
                <div class="carousel-track">
                    <!-- JS rellena esto -->
                </div>
            </div>
            <div class="promo-main-content">
                <div class="promo-text-center">Potenciá tu negocio al máximo nivel: Probá el Acceso Total de
                    <strong>StockiFy</strong> <span class="text-accent">GRATIS</span> por 30 días.
                </div>
                <div class="promo-button-wrapper">
                    <a href="register" class="btn-promo">Probar Ahora</a>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <main>
        <!-- Hero Section -->
        <section class="about-hero">
            <div class="container">
                <img src="assets/img/LogoE2.png" alt="StockiFy Logo Large" class="hero-logo">
                <h1>Construido para <span id="auto-type"></span></h1>
                <p class="hero-intro">
                    <strong>StockiFy</strong> no es solo una base de datos. Es una infraestructura robusta de nivel
                    empresarial diseñada para seguirte el ritmo facilitando cada proceso sensible de tu negocio.
                </p>
            </div>
        </section>

        <div class="section-divider"></div>

        <!-- Interactive Selector Section (Claude V3 Style) -->
        <section class="selector-section">
            <div class="container">
                <div class="selector-header" style="text-align: center; margin-bottom: 4rem;">
                    <h2 style="font-size: 3.8rem; letter-spacing: -2px;">¿Qué podés hacer con <strong>StockiFy</strong>?
                    </h2>
                    <p style="color: var(--accent-color); font-weight: 800; letter-spacing: 2px; padding-top: 10px;">
                        POTENCIAMOS TU TRABAJO AL MÁXIMO NIVEL</p>
                </div>

                <div class="selector-mobile-dropdown">
                    <div class="custom-feature-dropdown" id="custom-feature-dropdown">
                        <div class="custom-dropdown-header" id="custom-dropdown-header">
                            <span id="custom-dropdown-selected">Gestión Inteligente</span>
                            <i class="ph ph-caret-down select-icon"></i>
                        </div>
                        <ul class="custom-dropdown-list" id="custom-dropdown-list">
                            <li data-value="gestion" class="active">Gestión Inteligente</li>
                            <li data-value="movimiento">Seguridad Bancaria</li>
                            <li data-value="vinculos">Conectividad Meta</li>
                            <li data-value="mirar">Escalabilidad Cloudflare</li>
                        </ul>
                    </div>
                </div>

                <div class="selector-tabs-container">
                    <button class="feature-tab active" data-feature="gestion">Gestión Inteligente</button>
                    <button class="feature-tab" data-feature="movimiento">Seguridad Bancaria</button>
                    <button class="feature-tab" data-feature="vinculos">Conectividad Meta</button>
                    <button class="feature-tab" data-feature="mirar">Escalabilidad Cloudflare</button>
                </div>

                <div class="selector-main-layout">
                    <div class="selector-window">
                        <div class="window-top-bar">
                            <div class="dot red"></div>
                            <div class="dot yellow"></div>
                            <div class="dot green"></div>
                        </div>
                        <div class="selector-window-content">
                            <div class="selector-content-inner">
                                <h3 id="selector-title">Gestión Inteligente</h3>
                                <p id="selector-desc">
                                    Olvidate de las hojas de papel o las planillas confusas. Con nuestras tablas
                                    dinámicas,
                                    manejás tu stock como si fuera un documento estructurado, pero con la potencia de
                                    una
                                    base de datos profesional. Tenés todo a mano y siempre actualizado según tus
                                    necesidades.
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="selector-highlights">
                        <div class="highlight-item">
                            <h4 style="color: #1b1b1b; font-weight: 900;">Control de Flujos</h4>
                            <p>Sincronización total entre depósitos y puntos de venta sin latencia.</p>
                        </div>
                        <div class="highlight-item">
                            <h4 style="color: #1b1b1b; font-weight: 900;">Integración Nativa</h4>
                            <p>Conectividad certificada con el ecosistema de Meta y flujos de Google.</p>
                        </div>
                        <div class="highlight-item">
                            <h4 style="color: #1b1b1b; font-weight: 900;">Aval Global</h4>
                            <p>Protección perimetral mediante el sistema de élite de Cloudflare.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <div class="section-divider"></div>

        <!-- NEW: Interactive Feature Gallery (Accordion Style) -->
        <section class="gallery-section">
            <div class="container">
                <div style="text-align: center; margin-bottom: 4rem;">
                    <h2 style="font-size: 3.5rem; font-weight: 900; letter-spacing: -1.5px;">Explorá la Interfaz de
                        Élite</h2>
                </div>

                <div class="gallery-container">
                    <div class="gallery-item active" data-gallery="inventory">
                        <div class="gallery-item-overlay"></div>
                        <i class="ph ph-database" style="font-size: 6rem; color: #ccc;"></i>
                        <div class="gallery-progress">
                            <div class="gallery-progress-fill"></div>
                        </div>
                    </div>
                    <div class="gallery-item" data-gallery="dashboard">
                        <div class="gallery-item-overlay"></div>
                        <i class="ph ph-chart-line-up" style="font-size: 6rem; color: #ccc;"></i>
                        <div class="gallery-progress">
                            <div class="gallery-progress-fill"></div>
                        </div>
                    </div>
                    <div class="gallery-item" data-gallery="setup">
                        <div class="gallery-item-overlay"></div>
                        <i class="ph ph-gear" style="font-size: 6rem; color: #ccc;"></i>
                        <div class="gallery-progress">
                            <div class="gallery-progress-fill"></div>
                        </div>
                    </div>
                </div>

                <div class="gallery-info">
                    <h3 id="gallery-target-title">Control de Inventario Preciso</h3>
                    <p id="gallery-target-desc">Visualizá todo tu stock con filtros avanzados. El sistema te muestra
                        alertas visuales de bajo stock y te permite realizar ajustes globales en segundos.</p>
                </div>
            </div>
        </section>

        <!-- Trust Marquee Section (Full Width Background) -->
        <section class="section-full marquee-container" id="trust-marquee">
            <div class="marquee-track">
                <div class="marquee-content">
                    <img src="./assets/img/gg_logo.png" alt="Google Cloud" class="marquee-logo">
                    <img src="./assets/img/mt_logo.png" alt="Meta" class="marquee-logo">
                    <img src="./assets/img/cf_logo.png" alt="Cloudflare" class="marquee-logo">
                    <!-- Repeated to fill space on ultrawide monitors -->
                    <img src="./assets/img/gg_logo.png" alt="Google Cloud" class="marquee-logo">
                    <img src="./assets/img/mt_logo.png" alt="Meta" class="marquee-logo">
                    <img src="./assets/img/cf_logo.png" alt="Cloudflare" class="marquee-logo">
                </div>
                <!-- Exact Duplicate for Seamless Loop -->
                <div class="marquee-content">
                    <img src="./assets/img/gg_logo.png" alt="Google Cloud" class="marquee-logo">
                    <img src="./assets/img/mt_logo.png" alt="Meta" class="marquee-logo">
                    <img src="./assets/img/cf_logo.png" alt="Cloudflare" class="marquee-logo">
                    <img src="./assets/img/gg_logo.png" alt="Google Cloud" class="marquee-logo">
                    <img src="./assets/img/mt_logo.png" alt="Meta" class="marquee-logo">
                    <img src="./assets/img/cf_logo.png" alt="Cloudflare" class="marquee-logo">
                </div>
            </div>
            <div class="custom-tooltip" id="marquee-tooltip">Empresas que avalaron nuestra veracidad y confían en
                nosotros</div>
        </section>

        <!-- Pillars Section -->
        <section class="section-full section-bg-white">
            <div class="container">
                <div class="pillar-grid">
                    <div class="pillar-card">
                        <i class="ph ph-shield-check"></i>
                        <h3>Seguridad Imbatible</h3>
                        <p>No somos un sistema más. Utilizamos la misma infraestructura de seguridad que protege a las
                            mayores corporaciones del mundo.</p>
                    </div>
                    <div class="pillar-card">
                        <i class="ph ph-globe"></i>
                        <h3>Global y Ubicuo</h3>
                        <p>Accedé a tus datos desde cualquier lugar del planeta. Cloudflare nos permite darte una
                            velocidad de respuesta superior y constante.</p>
                    </div>
                    <div class="pillar-card">
                        <i class="ph ph-users"></i>
                        <h3>Aval de Gigantes</h3>
                        <p>Nuestras integraciones con WhatsApp y Google cuentan con el respaldo de las API oficiales,
                            asegurando estabilidad total y profesionalismo.</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Pricing Section (Clustered from Index) -->
        <section class="section-full section-bg-gray" id="pricing-conversion">
            <div class="container">
                <div style="text-align: center; margin-bottom: 4rem;">
                    <h2 style="font-size: 3.5rem; letter-spacing: -2px;">Comenzá con <strong>StockiFy</strong></h2>
                    <p style="color: #666; max-width: 600px; margin: 20px auto 0;">Elegí el plan que mejor se adapte a
                        las necesidades de tu emprendimiento.</p>
                </div>

                <div id="pricing-carousel-container" style="width: 100%; max-width: 100vw; overflow: visible;">
                    <div class="pricing-wrapper scale-wrapper" id="pricing-wrapper">

                        <div class="pricing-card-v2 card-theme-dark">
                            <h3>Básico</h3>
                            <div class="price-val">$240.000<span style="font-size: 1rem; opacity: 0.7;">/mes</span>
                            </div>
                            <ul>
                                <li><i class="ph-bold ph-check"></i> Un solo Inventario Activo</li>
                                <li><i class="ph-bold ph-check"></i> Gestión de Productos</li>
                                <li><i class="ph-bold ph-check"></i> Analíticas y Cierre de Caja</li>
                                <li><i class="ph-bold ph-check"></i> Importación de Datos</li>

                                <div style="height: 1px; background: rgba(128,128,128,0.2); margin: 0.5rem 0;"></div>

                                <li style="opacity: 0.6; border-bottom: none;"><i class="ph-bold ph-lock-key"
                                        style="color: var(--accent-red) !important;"></i> Gestión CRM (Clientes,
                                    Empleados y
                                    Proveedores)</li>
                            </ul>
                            <a href="https://wa.me/5491163642040?text=Hola%20Joaquín!%20Me%20interesa%20adquirir%20el%20Plan%20Básico%20de%20StockiFy.%20¿Cómo%20podemos%20avanzar?"
                                target="_blank" class="btn-pricing">Consultar Plan</a>
                        </div>

                        <div class="pricing-card-v2 card-theme-pro">
                            <div class="pro-badge">Recomendado</div>
                            <h3>Profesional</h3>
                            <div class="price-val">$299.999<span style="font-size: 1.1rem; opacity: 0.7;">/mes</span>
                            </div>
                            <ul>
                                <li style="font-weight: 700;"><i class="ph-bold ph-plus"></i> Todo lo que contiene el
                                    Plan
                                    Básico</li>
                                <li><i class="ph-bold ph-check"></i> Inventarios Ilimitados</li>
                                <li><i class="ph-bold ph-check"></i> Gestión CRM (Clientes, Empleados y Proveedores)
                                </li>
                                <li><i class="ph-bold ph-check"></i> Carga Ilimitada de productos</li>
                                <li><i class="ph-bold ph-check"></i> Acceso de terminal desde el Teléfono</li>
                                <li><i class="ph-bold ph-check"></i> Gestión de Métodos de Pago</li>
                                <li><i class="ph-bold ph-check"></i> Alertas de Stock Mínimo Alcanzado</li>
                                <li><i class="ph-bold ph-check"></i> Stock Valorizado, Ticket Promedio</li>
                                <li><i class="ph-bold ph-check"></i> Analíticas (Top Productos, Mejores Vendedores y
                                    Clientes)</li>
                                <li><i class="ph-bold ph-check"></i> Manejo de Comisión por Vendedor</li>
                            </ul>
                            <a href="https://wa.me/5491163642040?text=Hola%20Joaquín!%20Me%20interesa%20adquirir%20el%20Plan%20Profesional%20de%20StockiFy.%20¿Cómo%20podemos%20avanzar?"
                                target="_blank" class="btn-pricing">Adquirir Plan</a>
                        </div>

                        <div class="pricing-card-v2 card-theme-dark">
                            <div class="dark-badge">A Medida</div>
                            <h3>Empresarial</h3>
                            <div class="price-val">A cotizar</div>
                            <ul>
                                <li style="font-weight: 700;"><i class="ph-bold ph-plus"></i> Todo lo que contiene el
                                    Plan
                                    Profesional</li>
                                <li style="border-bottom: none; padding-bottom: 0;"><i class="ph-bold ph-check"></i>
                                    Contacto directo con el desarrollador para mayor personalización: </li>
                                <li style="border-bottom: none; padding-bottom: 0; margin-left: 20px;"><i
                                        class="ph-bold ph-caret-right"></i> Módulos Programados a Medida</li>
                                <li style="border-bottom: none; padding-bottom: 0; margin-left: 20px;"><i
                                        class="ph-bold ph-caret-right"></i> Análisis de Datos Exclusivos</li>
                                <li style="margin-left: 20px;"><i class="ph-bold ph-caret-right"></i> Módulos
                                    personalizados
                                    para tus analíticas</li>
                                <li><i class="ph-bold ph-check"></i> Soporte Inmediato 24/7</li>
                                <li style="border-bottom: none;"><i class="ph-bold ph-star"></i> Una Aplicación 100%
                                    Personalizada para tu negocio</li>
                            </ul>
                            <a href="https://wa.me/5491163642040?text=Hola%20Joaquín!%20Me%20interesa%20el%20Plan%20Empresarial%20de%20StockiFy.%20Necesito%20funciones%20a%20medida%20para%20mi%20negocio.%20¿Podemos%20coordinar%20una%20reunión?"
                                target="_blank" class="btn-pricing">Contactar Ventas</a>
                        </div>

                        <div class="pricing-card-v2 card-theme-vital">
                            <div class="vital-badge">Único Pago</div>
                            <h3>Vitalicio</h3>
                            <div class="price-val">USD 3.999<span style="font-size: 0.9rem; opacity: 0.7;">/único</span>
                            </div>

                            <div
                                style="margin-top: 5px; margin-bottom: 5px; font-size: 0.75rem; color: #555; background: #e5e5e5; padding: 6px 10px; border-radius: 4px; display: flex; align-items: flex-start; border: 1px solid #d0d0d0; line-height: 1.4;">
                                <i class="ph-bold ph-info"
                                    style="margin-right: 6px; font-size: 1rem; margin-top: 1px; flex-shrink: 0;"></i>
                                <span><strong>Comunicado:</strong> El otorgamiento de esta licencia se encuentra
                                    condicionado a disponibilidad (Límite operativo de 20 fundadores).</span>
                            </div>
                            <ul>
                                <li style="font-weight: 700;"><i class="ph-bold ph-star"></i> Acceso Absoluto:</li>
                                <li><i class="ph-bold ph-check"></i> Pago único de por vida</li>
                                <li><i class="ph-bold ph-check"></i> Funciones Empresariales</li>
                                <li><i class="ph-bold ph-check"></i> Updates gratuitos infinitos</li>
                            </ul>
                            <a href="https://wa.me/5491163642040?text=Hola%20Joaquín!%20Me%20interesa%20adquirir%20la%20Licencia%20Vitalicia%20(Edición%20Fundadores)%20de%20StockiFy.%20¿Cómo%20avanzamos?"
                                target="_blank" class="btn-pricing">Inversión Única</a>
                        </div>
                    </div>

                    <div class="swiper-pagination pricing-pagination" id="pricing-pagination" style="display: none;">
                    </div>
                </div>
        </section>

        <div class="section-divider" style="margin-top: 0; "></div>

        <!-- FAQ Section (Modular) -->
        <section class="section-full faq-section">
            <div class="container">
                <div style="text-align: center; margin-bottom: 4rem;">
                    <h2 style="font-size: 3rem; font-weight: 900;">Preguntas Frecuentes</h2>
                </div>

                <div class="faq-grid">
                    <div class="faq-item">
                        <div class="faq-question">
                            <span>¿Cómo se sincroniza el precio con el dólar?</span>
                            <i class="ph ph-caret-down"></i>
                        </div>
                        <div class="faq-answer">
                            StockiFy permite vincular tus productos al dólar oficial o MEP. El sistema actualiza los
                            precios de venta automáticamente según la paridad que definas en la configuración global,
                            protegiendo tu rentabilidad sin esfuerzo manual.
                        </div>
                    </div>
                    <div class="faq-item">
                        <div class="faq-question">
                            <span>¿Es seguro tener mis datos en la nube?</span>
                            <i class="ph ph-caret-down"></i>
                        </div>
                        <div class="faq-answer">
                            Utilizamos infraestructura de grado bancario protegida por Cloudflare Armor. Tus datos
                            viajan encriptados y realizamos copias de seguridad automáticas cada hora para garantizar
                            que tu información esté siempre disponible y segura.
                        </div>
                    </div>
                    <div class="faq-item">
                        <div class="faq-question">
                            <span>¿Qué soporte técnico ofrecen?</span>
                            <i class="ph ph-caret-down"></i>
                        </div>
                        <div class="faq-answer">
                            Contamos con soporte técnico oficial vía WhatsApp para todos nuestros planes. El plan
                            Empresarial incluye soporte prioritario 24/7 y contacto directo con el equipo de desarrollo
                            para ajustes específicos.
                        </div>
                    </div>
                    <div class="faq-item">
                        <div class="faq-question">
                            <span>¿Hay un límite de carga de productos?</span>
                            <i class="ph ph-caret-down"></i>
                        </div>
                        <div class="faq-answer">
                            En el Plan Básico tienes una capacidad optimizada para emprendedores, mientras que en los
                            planes Profesional y Empresarial la carga es ilimitada. El sistema soporta miles de SKUs sin
                            pérdida de performance.
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Final Story Block (¿Quiénes Somos?) -->
        <section class="section-full section-bg-gray"
            style="border-top: 3px solid #1b1b1b; border-bottom: 2px solid #1b1b1b;">
            <div class="container">
                <div class="story-card">
                    <h2>¿Quiénes Somos?</h2>
                    <p>StockiFy nació de una idea clara: sacarle peso de encima a los que deciden <strong>"ponerle el
                            pecho"</strong> a un proyecto propio con energía y visión. Emprender es un viaje increíble,
                        pero la gestión debe ser profesional, segura y escalable.</p>
                    <p>
                        Somos un equipo que cree en la superioridad técnica. No creamos herramientas simples; creamos
                        arquitecturas de trabajo robustas que te permiten competir al nivel de los grandes, respaldadas
                        por la tecnología de mayor confianza del mercado mundial.
                    </p>
                </div>
            </div>
        </section>

        <!-- Final Footer Snap -->
        <section style="height: 100px; background: #fff;"></section>
    </main>

    <footer class="footer"
        style="background-color: var(--accent-color); color: white; padding: 4rem 2rem 2rem; width: 100%;">
        <div class="footer-container" style="max-width: 1200px; margin: 0 auto;">
            <div class="footer-brand" style="margin-bottom: 2rem;">
                <img src="assets/img/LogoE3.png" style="width: 300px; height: auto;" alt="StockiFy Logo">
            </div>
            <div class="footer-bottom"
                style="text-align: left; margin-top: 40px; border-top: 1px solid rgba(255,255,255,0.2); padding-top: 20px;">
                <div
                    style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 10px;">
                    <div>
                        <p style="margin-bottom: 8px; font-size: 0.9rem; color: rgba(255,255,255,0.8);">&copy; <span
                                id="year">2026</span> StockiFy. Todos los derechos reservados.</p>
                        <div class="footer-links" style="display: flex; gap: 20px;">
                            <a href="privacy-policy"
                                style="color: var(--color-white); text-decoration: none; font-size: 0.85rem; opacity: 0.9; transition: opacity 0.2s;">Política
                                de Privacidad</a>
                            <a href="terms-of-service"
                                style="color: var(--color-white); text-decoration: none; font-size: 0.85rem; opacity: 0.9; transition: opacity 0.2s;">Condiciones
                                del Servicio</a>
                            <a href="about-us"
                                style="color: var(--color-white); text-decoration: none; font-size: 0.85rem; opacity: 1; text-decoration: underline; font-weight: bold;">¿Quiénes
                                Somos?</a>
                        </div>
                    </div>
                    <p class="footer-dev" style="margin: 0; font-size: 0.9rem; color: rgba(255,255,255,0.8);">Created by
                        <span style="color: var(--color-white); font-weight: bold">JESMdev</span>
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script>
        document.getElementById("year").textContent = new Date().getFullYear();
    </script>
    <script src="assets/js/main.js"></script>
</body>

</html>