<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/helpers/auth_helper.php';
$currentUser = getCurrentUser();

$nombre = "Usuario";
if ($currentUser) {
    $name = htmlspecialchars($currentUser['full_name']);
    $nombre = explode(' ', $name)[0];
}

$showWelcome = !$currentUser ? '' : 'hidden';
$showDashboard = $currentUser ? '' : 'hidden';
?>

<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StockiFy | Software de Gestión de Inventario y Ventas</title>
    <meta name="description"
        content="Aumenta tus ganancias con StockiFy. Software inteligente y moderno para control de inventario, registro de ventas, alertas de stock mínimo e informes de negocio.">
    <meta name="robots" content="index, follow">
    <meta name="author" content="JESMdev">
    <meta property="og:title" content="StockiFy | Tu solución de inventario">
    <meta property="og:description"
        content="Automatiza tu stock y descubre fugas de liquidez y ganancia con herramientas precisas.">
    <meta property="og:image" content="https://stockify.com.ar/assets/img/1.png">
    <meta property="og:url" content="https://stockify.com.ar/">
    <meta property="og:type" content="website">

    <script src="assets/js/theme.js"></script>
    <script src="assets/js/cycling-text.js"></script>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/index.css">
    <link rel="stylesheet" href="assets/css/about-section.css">
    <link rel="icon" href="favicon.ico" type="image/x-icon">

    <link rel="stylesheet" type="text/css"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css" />
    <link rel="stylesheet" type="text/css"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css" />
    <link rel="stylesheet" type="text/css"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/bold/style.css" />

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css" />
</head>

<body id="page-index">

    <div id="imgModal" class="img-modal" aria-hidden="true">
        <button class="img-modal-close" type="button" aria-label="Cerrar">✕</button>
        <img id="imgModalContent" alt="Vista ampliada de demostración de StockiFy">
    </div>

    <div class="side-nav">
        <div class="nav-dot active" data-id="section-hero" title="Inicio"
            onclick="document.getElementById('section-hero').scrollIntoView({behavior: 'smooth'})"></div>
        <div class="nav-dot" data-id="section-features" title="Características"
            onclick="document.getElementById('section-features').scrollIntoView({behavior: 'smooth'})"></div>
        <div class="nav-dot" data-id="section-pillars" title="Pilares"
            onclick="document.getElementById('section-pillars').scrollIntoView({behavior: 'smooth'})"></div>
        <div class="nav-dot" data-id="section-gallery" title="Galería"
            onclick="document.getElementById('section-gallery').scrollIntoView({behavior: 'smooth'})"></div>
        <div class="nav-dot" data-id="section-pricing" title="Planes y Precios"
            onclick="document.getElementById('section-pricing').scrollIntoView({behavior: 'smooth'})"></div>
    </div>

    <header>
        <a href="index" id="header-logo">
            <img src="assets/img/LogoE.png" alt="Logotipo Oficial de StockiFy">
        </a>
        <nav id="header-nav" style="display: flex; gap: 10px;">
            <?php if ($currentUser): ?>
                <a href="select-db" class="btn btn-primary" style="margin:0;">Ir al Panel</a>
                <a href="logout" class="btn btn-secondary" style="margin:0;">Cerrar Sesión</a>
            <?php else: ?>
                <a href="login" class="btn btn-secondary" style="margin:0;">Iniciar Sesión</a>
            <?php endif; ?>
        </nav>
    </header>

    <div class="background-animation-wrapper">
        <svg class="background-shape2 box-22" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 768 768"
            preserveAspectRatio="xMidYMid meet">
            <defs>
                <clipPath id="cp1">
                    <path d="M87.77 194.09h591.27v392.4H87.77z" />
                </clipPath>
                <clipPath id="cp2">
                    <path d="M192 82.24h487.06V664H192z" />
                </clipPath>
                <clipPath id="cp3">
                    <path d="M385.11 82.34L679.05 206.36 485.97 663.94 192.04 539.92z" />
                </clipPath>
                <clipPath id="cp4">
                    <path d="M115.84 82.24h459.23V664H115.84z" />
                </clipPath>
                <clipPath id="cp5">
                    <path d="M115.87 190.5L384.15 82.34l190.87 473.44-268.27 108.16z" />
                </clipPath>
                <clipPath id="cp6">
                    <path d="M115.84 456.14h319.94v242.22H115.84z" />
                </clipPath>
                <clipPath id="cp7">
                    <path d="M115.86 586.31l55.04-130.17 264.64 111.89-55.03 130.17z" />
                </clipPath>
                <clipPath id="cp8">
                    <path d="M345.28 456.14H664v232.72H345.28z" />
                </clipPath>
                <clipPath id="cp9">
                    <path d="M394.69 688.74l-49.25-132.47 269.31-100.13 49.25 132.47z" />
                </clipPath>
                <clipPath id="cp10">
                    <path d="M73.39 74.15h620.9v633.17H73.39z" />
                </clipPath>
                <clipPath id="cp11">
                    <path d="M90.99 196.3h584.86v388.15H90.99z" />
                </clipPath>
                <clipPath id="cp12">
                    <path d="M194.06 85.67h481.8v575.51h-481.8z" />
                </clipPath>
                <clipPath id="cp13">
                    <path d="M385.1 85.77l290.75 122.68-190.98 452.62-290.75-122.68z" />
                </clipPath>
                <clipPath id="cp14">
                    <path d="M118.74 85.67H573v575.51H118.74z" />
                </clipPath>
                <clipPath id="cp15">
                    <path d="M118.78 192.76l265.37-107 188.8 468.31-265.37 107z" />
                </clipPath>
                <clipPath id="cp16">
                    <path d="M118.74 455.52H435V695H118.74z" />
                </clipPath>
                <clipPath id="cp17">
                    <path d="M118.77 584.28l54.44-128.76 261.78 110.67-54.44 128.76z" />
                </clipPath>
                <clipPath id="cp18">
                    <path d="M345.7 455.52H661v230.2H345.7z" />
                </clipPath>
                <clipPath id="cp19">
                    <path d="M394.58 685.6l-48.72-131.04 266.39-99.04 48.72 131.04z" />
                </clipPath>
                <clipPath id="cp20">
                    <path d="M76.76 77.67h614.17v626.31H76.76z" />
                </clipPath>
            </defs>

            <g class="dynamic-fill" clip-path="url(#cp1)">
                <path d="M87.77 194.09h591.66v392.4H87.77z" />
            </g>
            <g class="dynamic-fill" clip-path="url(#cp2)">
                <g clip-path="url(#cp3)">
                    <path d="M385.11 82.34L679.05 206.36 485.75 664.48 191.82 540.46z" />
                </g>
            </g>
            <g class="dynamic-fill" clip-path="url(#cp4)">
                <g clip-path="url(#cp5)">
                    <path d="M115.87 190.5L384.15 82.34 575.12 556.02 306.84 664.18z" />
                </g>
            </g>
            <g class="dynamic-fill" clip-path="url(#cp6)">
                <g clip-path="url(#cp7)">
                    <path d="M115.86 586.31l55.04-130.17 264.42 111.79-55.04 130.18z" />
                </g>
            </g>
            <g class="dynamic-fill" clip-path="url(#cp8)">
                <g clip-path="url(#cp9)">
                    <path d="M394.69 688.74l-49.25-132.47 269.08-100.04 49.26 132.47z" />
                </g>
            </g>
            <g class="dynamic-fill" clip-path="url(#cp11)">
                <path d="M90.99 196.3h585.24v388.15H90.99z" />
            </g>
            <g class="dynamic-fill" clip-path="url(#cp12)">
                <g clip-path="url(#cp13)">
                    <path d="M385.1 85.77l290.75 122.68-191.2 453.16-290.74-122.68z" />
                </g>
            </g>
            <g class="dynamic-fill" clip-path="url(#cp14)">
                <g clip-path="url(#cp15)">
                    <path d="M118.78 192.76l265.37-107 188.9 468.55-265.37 106.99z" />
                </g>
            </g>
            <g class="dynamic-fill" clip-path="url(#cp16)">
                <g clip-path="url(#cp17)">
                    <path d="M118.77 584.28l54.44-128.76 261.55 110.58-54.44 128.76z" />
                </g>
            </g>
            <g class="dynamic-fill" clip-path="url(#cp18)">
                <g clip-path="url(#cp19)">
                    <path d="M394.58 685.6l-48.72-131.04 266.16-98.96 48.72 131.04z" />
                </g>
            </g>

            <g clip-path="url(#cp10)">
                <path fill="#1b1b1b"
                    d="M693.69 196.42l.4-1.13-310.05-121.14-309.59 122.4-1.03-.41v.79l-.03.02.03.13v388.57L383.89 707.32l310.5-121.7V196.14zM383.73 97.97l105.19 37.67-286.73 111.29-97.1-38.3zM372.84 679.89l-116.28-46.97-58.35-21.91-102.6-40.46V228.75l101.55 40.06v85.45l9.04-2.51 6.12 8.38 8.51-1.97 8.25 8.72 7.19-2.5 11.62 11.52V288.8l9.04 3.58 115.9 45.7zM383.91 318.62L270.53 273.9l279.6-113.32 115.34 47zM672.22 570.55L395 679.89V338.09l277.2-109.37v341.83z" />
            </g>

            <g clip-path="url(#cp20)">
                <path fill="#ffffff"
                    d="M690.34 198.61l.4-1.11-306.7-119.83-306.23 121.07-1.02-.4.43.77-.03.03.03.13v384.35l306.87 120.36 307.13-120.39V198.34zM383.73 101.23l104.05 37.27-283.62 110.08-96.05-37.89zM372.96 676.84L257.94 630.38l-57.72-21.67-101.48-40.02V230.59l100.46 39.63v84.52l8.94-2.48 6.05 8.29 8.42-1.95 8.17 8.63 7.11-2.48 11.49 11.4V290l8.94 3.54 114.64 45.21zM383.92 319.5L271.76 275.25 548.34 163.15l114.08 46.49zM669.1 568.69L394.88 676.84V338.75l274.19-108.19v338.13z" />
            </g>
        </svg>
    </div>

    <main class="scroll-container" id="main-scroller" style="padding: 0 !important;">

        <section class="section bg-pattern" id="section-hero">
            <div class="flex-row all-center" id="welcome-container">
                <div class="flex-column" style="z-index: 2;">
                    <h1>Tu <span style="color: var(--accent-color); ">solución</span> para la <span
                            style="color: var(--accent-color)">gestión de inventario</span></h1>
                    <h2>Te ayudamos con tus
                        <span id="cycling-text-container">
                            <span style="color: var(--accent-color)" id="cycling-text">Ventas.</span>
                        </span>
                    </h2>
                </div>

                <div class="flex-column all-center" style="padding: 20px; z-index: 2;">
                    <div id="welcome-view" class="view-container <?php echo $showWelcome; ?>">
                        <h2>¡Bienvenido!</h2>
                        <h3>Vemos que aún no has iniciado sesión.</h3>
                        <p>Inicia sesión o regístrate para comenzar.</p>
                        <div id="welcome-buttons" class="menu-buttons">
                            <a href="login" class="btn btn-secondary">Iniciar Sesión</a>
                            <a href="register" class="btn btn-primary">Crear una Cuenta</a>
                        </div>
                    </div>

                    <div id="dashboard-view" class="view-container <?php echo $showDashboard; ?>">
                        <h2>¡Bienvenido, <?php echo $nombre ?>!</h2>
                        <p>Estamos felices por volver a verte.</p>
                        <div class="menu-buttons">
                            <a href="select-db" class="btn btn-primary">Ir al Panel</a>
                        </div>
                    </div>
                </div>
            </div>

            <div style="position: absolute; bottom: 20px; animation: bounce 2s infinite; opacity: 0.4;">
                <i class="ph ph-caret-down" style="font-size: 2rem;"></i>
            </div>
        </section>

        <section class="section bg-pattern" id="section-features">
            <div class="flex-column all-center" id="acerca-de-container" style="z-index: 2;">
                <p class="about-title">
                    <span style="color: var(--accent-color); font-weight: 800;">StockiFy</span> es una aplicación web de
                    control de inventario diseñada para funcionar con tus necesidades.
                </p>
                <p class="about-subtitle">
                    Nuestro sistema fue creado a <span style="color: var(--accent-color); font-weight: 600;">tu</span>
                    medida.
                </p>

                <div class="flex-row justify-between about-container">
                    <div class="options-wrapper flex-column all-center">
                        <div class="flex-row about-option active" data-option="content-1">
                            <p>Interfáz simple e intuitiva para facilitar tu gestión de stock.</p>
                        </div>
                        <div class="flex-row about-option" data-option="content-2">
                            <p>Poderosas herramientas para registrar transacciones.</p>
                        </div>
                        <div class="flex-row about-option" style="border-bottom: none" data-option="content-3">
                            <p>Control Completo.</p>
                        </div>
                    </div>
                    <div style="width: 100%">
                        <div class="content-panel active" id="content-1">
                            <h3>Diseñado para la velocidad</h3>
                            <p>
                                Dile adiós a las hojas de cálculo complicadas. <span
                                    style="color: var(--accent-color); font-weight: 600">StockiFy</span>
                                está diseñado pensando en ti.
                            </p>
                        </div>

                        <div class="content-panel" id="content-2">
                            <h3>Decisiones basadas en datos</h3>
                            <p>
                                Registra cada venta y cada compra en segundos. <span
                                    style="color: var(--accent-color); font-weight: 600">StockiFy</span>
                                actualiza automáticamente tu inventario.
                            </p>
                         </div>

                        <div class="content-panel" id="content-3">
                            <h3>Todo tu negocio centralizado</h3>
                            <p>
                                Toma el control total. Desde clientes hasta proveedores.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="section bg-pattern" id="section-pillars">
            <h2 style="margin-bottom: 1.5rem; z-index: 2;">Tus herramientas principales</h2>

            <div class="other-info-wrapper scale-wrapper">
                <div class="flex-column other-info-item">
                    <div class="flex-row all-center other-info-header"><i class="ph ph-database"
                            style="transform: scale(1.3)"></i>
                        <p>Inventarios</p>
                    </div>
                    <div class="other-info-body">
                        <p class="info-main-text">Lleva un conteo extacto de la cantidad de productos que tenés en stock
                            con nuestros <span style="color: var(--accent-color)">Inventarios.</span></p>
                        <p class="info-secondary-text">Crea la cantidad que necesites, identificalos con el nombre que
                            quieras, y agregale tus propios campos.</p>
                        <p class="info-secondary-text">Utilizá nuestras <span
                                style="color: var(--accent-color); font-weight: 600">columnas recomendadas</span> para
                            indicar cuando queres que te avisemos que un producto está por abajo
                            de una cantidad de stock pautada y establecer precios compra y venta, <span
                                style="color: var(--accent-color); font-weight: 600">¡Descubrí las posibilidades!</span>
                        </p>
                    </div>
                </div>
                <div class="flex-column other-info-item">
                    <div class="flex-row all-center other-info-header"><i class="ph ph-money"
                            style="transform: scale(1.3)"></i>
                        <p>Transacciones</p>
                    </div>
                    <div class="other-info-body">
                        <p class="info-main-text">Registra <span
                                style="color: var(--accent-color); font-weight: 600">Ventas</span> y <span
                                style="color: var(--accent-color); font-weight: 600">Compras</span>, crea <span
                                style="color: var(--accent-color); font-weight: 600">Clientes</span> y <span
                                style="color: var(--accent-color); font-weight: 600">Proveedores</span>, y junta ambas.
                        </p>
                        <p class="info-secondary-text">Con solo habilitar las Columnas Recomendadas <span
                                style="color: var(--accent-color); font-weight: 600">"Precio de Venta"</span> y <span
                                style="color: var(--accent-color); font-weight: 600">"Precio de Compra"</span>
                            podés comenzar a gestionar <span
                                style="color: var(--accent-color); font-weight: 600">Transacciones</span> con tus
                            productos.</p>
                        <p class="info-secondary-text">Añadí <span
                                style="color: var(--accent-color); font-weight: 600">Clientes</span>
                            recurrentes a tu negocio, asignalos a una venta y mandales la factura por mail; o registra
                            un <span style="color: var(--accent-color); font-weight: 600">Proveedor</span> para
                            asignarlo a una Compra que hayas hecho.</p>
                    </div>
                </div>
                <div class="flex-column other-info-item">
                    <div class="flex-row all-center other-info-header"><i class="ph ph-chart-bar"
                            style="transform: scale(1.3)"></i>
                        <p>Estadísticas</p>
                    </div>
                    <div class="other-info-body">
                        <p class="info-main-text">¿Estuviste registrando Ventas y Compras? Utiliza nuestras <span
                                style="color: var(--accent-color); font-weight: 600">Estadísticas</span> automaticas
                            para llevar tu negocio al próximo nivel.</p>
                        <p class="info-secondary-text">Podrás ver tus <span
                                style="color: var(--accent-color); font-weight: 600">estadisticas diarías</span> desde
                            el Panel, o filtra entre ciertas fechas desde
                            la <span style="color: var(--accent-color); font-weight: 600">página dedicada de
                                estadísticas.</span></p>
                        <p class="info-secondary-text">Averiguá tu cantidad de stock ingresado y vendido, cuanto dinero
                            ganaste y perdiste, cuantas ventas realizaste, y <span
                                style="color: var(--accent-color); font-weight: 600">mucho más.</span></p>
                    </div>
                </div>
            </div>
        </section>

        <section class="section carousel-section bg-pattern" id="section-gallery"
            style="justify-content: space-between;">

            <div style="width: 100%; text-align: center; margin-top: 4rem; z-index: 2;">
                <h2 style="">Galería de Funciones</h2>
                <p style="color: #888;">Desliza para explorar</p>
            </div>

            <div class="swiper mySwiper scale-wrapper" style="z-index: 2; width: 100%;">
                <div class="swiper-wrapper">

                    <div class="swiper-slide">
                        <div class="slide-img-container">
                            <img src="./assets/img/1.png" alt="Control de Inventario y Tablas Dinámicas StockiFy">
                        </div>
                        <div class="slide-content">
                            <i class="ph ph-table slide-icon"></i>
                            <h3>Tablas Dinámicas</h3>
                            <p>Edición rápida y gestión de datos tipo hoja de cálculo.</p>
                        </div>
                    </div>

                    <div class="swiper-slide">
                        <div class="slide-img-container">
                            <img src="./assets/img/2.png" alt="Gestión de Usuarios, Clientes y Proveedores">
                        </div>
                        <div class="slide-content">
                            <i class="ph ph-users slide-icon"></i>
                            <h3>Gestión Humana</h3>
                            <p>Controlá quiénes son tus clientes, proveedores o vendedores para poder asignarles una
                                comisión.</p>
                        </div>
                    </div>

                    <div class="swiper-slide">
                        <div class="slide-img-container">
                            <img src="./assets/img/3.png" alt="Importación masiva de datos en CSV para stock">
                        </div>
                        <div class="slide-content">
                            <i class="ph ph-file-csv slide-icon"></i>
                            <h3>Importación CSV</h3>
                            <p>Carga masiva de productos desde archivos externos.</p>
                        </div>
                    </div>

                    <div class="swiper-slide">
                        <div class="slide-img-container">
                            <img src="./assets/img/4.png" alt="Notificaciones en tiempo real y alertas de Stock bajo">
                        </div>
                        <div class="slide-content">
                            <i class="ph ph-bell slide-icon"></i>
                            <h3>Alertas de Stock</h3>
                            <p>Notificaciones automáticas cuando el inventario es bajo.</p>
                        </div>
                    </div>

                    <div class="swiper-slide">
                        <div class="slide-img-container">
                            <img src="./assets/img/5.png" alt="Interfaz responsiva en teléfono móvil para el sistema">
                        </div>
                        <div class="slide-content">
                            <i class="ph ph-device-mobile slide-icon"></i>
                            <h3>Versión para Teléfonos</h3>
                            <p>La app para el teléfono pasa a ser una estación de control y manejo más simple y versátil
                                para el día a día en el negocio.</p>
                        </div>
                    </div>
                </div>
                <div class="swiper-pagination"></div>
            </div>
        </section>

        <section class="section bg-pattern" id="section-pricing"
            style="display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 100dvh; gap: 2rem; padding: 4rem 1rem;">
            <div style="z-index: 2; text-align: center; margin-bottom: 2rem;">
                <h2 style="font-size: 2.0rem;  color: var(--color-black); padding-bottom: 20px;">Planes y Precios</h2>
                <p style="color: #666; max-width: 600px; margin: 0 auto;">Elige el plan que mejor se
                    adapte a las necesidades de tu emprendimiento para llevar el control al máximo nivel.</p>
            </div>

            <div id="pricing-carousel-container" style="width: 100%; max-width: 100vw; overflow: visible;">
                <div class="pricing-wrapper scale-wrapper" id="pricing-wrapper">

                    <div class="pricing-card-v2 card-theme-dark">
                        <h3>Básico</h3>
                        <div class="price-val">$240.000<span style="font-size: 1rem; opacity: 0.7;">/mes</span></div>
                        <ul>
                            <li><i class="ph-bold ph-check"></i> Un solo Inventario Activo</li>
                            <li><i class="ph-bold ph-check"></i> Gestión de Productos</li>
                            <li><i class="ph-bold ph-check"></i> Analíticas y Cierre de Caja</li>
                            <li><i class="ph-bold ph-check"></i> Importación de Datos</li>

                            <div style="height: 1px; background: rgba(128,128,128,0.2); margin: 0.5rem 0;"></div>

                            <li style="opacity: 0.6; border-bottom: none;"><i class="ph-bold ph-lock-key"
                                    style="color: var(--accent-red) !important;"></i> Gestión CRM (Clientes, Empleados y
                                Proveedores)</li>
                        </ul>
                        <a href="https://wa.me/5491163642040?text=Hola%20Joaquín!%20Me%20interesa%20adquirir%20el%20Plan%20Básico%20de%20StockiFy.%20¿Cómo%20podemos%20avanzar?"
                            target="_blank" class="btn-pricing">Consultar Plan</a>
                    </div>

                    <div class="pricing-card-v2 card-theme-pro">
                        <div class="pro-badge">Recomendado</div>
                        <h3>Profesional</h3>
                        <div class="price-val">$299.999<span style="font-size: 1.1rem; opacity: 0.7;">/mes</span></div>
                        <ul>
                            <li style="font-weight: 700;"><i class="ph-bold ph-plus"></i> Todo lo que contiene el Plan
                                Básico</li>
                            <li><i class="ph-bold ph-check"></i> Inventarios Ilimitados</li>
                            <li><i class="ph-bold ph-check"></i> Gestión CRM (Clientes, Empleados y Proveedores)</li>
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
                            <li style="font-weight: 700;"><i class="ph-bold ph-plus"></i> Todo lo que contiene el Plan
                                Profesional</li>
                            <li style="border-bottom: none; padding-bottom: 0;"><i class="ph-bold ph-check"></i>
                                Contacto directo con el desarrollador para mayor personalización: </li>
                            <li style="border-bottom: none; padding-bottom: 0; margin-left: 20px;"><i
                                    class="ph-bold ph-caret-right"></i> Módulos Programados a Medida</li>
                            <li style="border-bottom: none; padding-bottom: 0; margin-left: 20px;"><i
                                    class="ph-bold ph-caret-right"></i> Análisis de Datos Exclusivos</li>
                            <li style="margin-left: 20px;"><i class="ph-bold ph-caret-right"></i> Módulos personalizados
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

                <div class="swiper-pagination pricing-pagination" id="pricing-pagination" style="display: none;"></div>
            </div>

        </section>




        <section class="section bg-pattern" id="section-footer"
            style="min-height: auto; padding: 0; scroll-snap-align: end;">
            <footer class="footer"
                style="background-color: var(--accent-color); color: white; padding: 4rem 2rem 2rem; width: 100%;">
                <div class="footer-container" style="max-width: 1200px; margin: 0 auto;">
                    <div class="footer-brand" style="margin-bottom: 2rem;">
                        <img src="assets/img/LogoE3.png" style="width: 300px; height: auto;" alt="StockiFy Logo">
                    </div>
                    <div class="footer-bottom"
                        style="text-align: left; margin-top: 40px; border-top: 1px solid rgba(255,255,255,0.1); padding-top: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-end; flex-wrap: wrap; gap: 10px;">
                            <div>
                                <p style="margin-bottom: 8px; font-size: 0.9rem; color: rgba(255,255,255,0.7);">&copy; <span id="year"></span> StockiFy. Todos los derechos reservados.</p>
                                <div class="footer-links" style="display: flex; gap: 20px;">
                                    <a href="privacy-policy" style="color: var(--color-white); text-decoration: none; font-size: 0.85rem; opacity: 0.8; transition: opacity 0.2s;">Política de Privacidad</a>
                                    <a href="terms-of-service" style="color: var(--color-white); text-decoration: none; font-size: 0.85rem; opacity: 0.8; transition: opacity 0.2s;">Condiciones del Servicio</a>
                                    <a href="about-us" style="color: var(--color-white); text-decoration: none; font-size: 0.85rem; opacity: 0.8; transition: opacity 0.2s;">¿Quiénes Somos?</a>
                                </div>
                            </div>
                            <p class="footer-dev" style="margin: 0; font-size: 0.9rem; color: rgba(255,255,255,0.7);">Created by <span style="color: var(--color-white); font-weight: bold">JESMdev</span></p>
                        </div>
                    </div>
                </div>
            </footer>
        </section>
    </main>




    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
</body>

</html>