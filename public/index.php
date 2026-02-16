<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/helpers/auth_helper.php';
$currentUser = getCurrentUser();

if ($currentUser):
    $name = htmlspecialchars($currentUser['full_name']);
    $nombre = explode(' ', $name)[0];
endif
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StockiFy</title>
    <script src="assets/js/theme.js"></script>
    <script src="assets/js/cycling-text.js"></script>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/about-section.css">
    <link rel="icon" href="favicon.ico" type="image/x-icon">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css" />
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css" />
</head>

<body id="page-index">

<header>
    <a href="index.php" id="header-logo">
        <img src="assets/img/LogoE.png" alt="Stocky Logo">
    </a>
    <nav id="header-nav">
    </nav>
</header>

<main class="flex-column">
    <div class="flex-row all-center" id="welcome-container">

        <div class="flex-column">

            <h1>Tu <span style="color: var(--accent-color); ">solución</span> para la <span style="color: var(--accent-color)">gestión de inventario</span></h1>
            <h2>Te ayudamos con tus
                <span id="cycling-text-container">
                        <span style="color: var(--accent-color)" id="cycling-text">Ventas.</span>
                    </span>
            </h2>
        </div>
        <div class="flex-column all-center" style="padding: 20px;">
            <div id="welcome-view" class="view-container hidden">
                <h2>¡Bienvenido!</h2>
                <h3>Vemos que aún no has iniciado sesión.</h3>
                <p>Inicia sesión o regístrate para comenzar.</p>
                <div id="welcome-buttons" class="menu-buttons">
                    <a href="login.php" class="btn btn-secondary">Iniciar Sesión</a>
                    <a href="register.php" class="btn btn-primary">Crear una Cuenta</a>
                </div>
            </div>

            <div id="empty-state-view" class="view-container hidden">
                <h2>¡Bienvenido, <?php echo $nombre ?>!</h2>
                <p>Aún no has creado ninguna base de datos. ¡Crea la primera para empezar a organizarte!</p>
                <div class="menu-buttons">
                    <a href="create-db.php" class="btn btn-primary">Crear mi Primera Base de Datos</a>
                </div>
            </div>

            <div id="dashboard-view" class="view-container hidden">
                <h2>¡Bienvenido de nuevo, <?php echo $nombre ?>!</h2>
                <p>Estamos felices por volver a verte. ¡Hora de trabajar!</p>
                <div class="menu-buttons">
                    <a href="dashboard.php" class="btn btn-primary">Ir al Panel</a>
                </div>
            </div>

            <div id="select-db-view" class="view-container hidden">
                <h2>¡Bienvenido, <?php echo $nombre ?>!</h2>
                <p>Estamos felices por verte. ¡Selecciona una base de datos y comienza a trabajar!</p>
                <div class="menu-buttons">
                    <a href="select-db.php" class="btn btn-primary">Seleccionar Base de Datos</a>
                </div>
            </div>
        </div>
    </div>
    <div class="divider-wrapper">
        <div class="divider-line"></div>
        <div class="shape-wrap">
            <svg class="background-shape2 box-22" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 768 768" preserveAspectRatio="xMidYMid meet">
                <defs>
                    <clipPath id="cp1"><path d="M87.77 194.09h591.27v392.4H87.77z"/></clipPath>
                    <clipPath id="cp2"><path d="M192 82.24h487.06V664H192z"/></clipPath>
                    <clipPath id="cp3"><path d="M385.11 82.34L679.05 206.36 485.97 663.94 192.04 539.92z"/></clipPath>
                    <clipPath id="cp4"><path d="M115.84 82.24h459.23V664H115.84z"/></clipPath>
                    <clipPath id="cp5"><path d="M115.87 190.5L384.15 82.34l190.87 473.44-268.27 108.16z"/></clipPath>
                    <clipPath id="cp6"><path d="M115.84 456.14h319.94v242.22H115.84z"/></clipPath>
                    <clipPath id="cp7"><path d="M115.86 586.31l55.04-130.17 264.64 111.89-55.03 130.17z"/></clipPath>
                    <clipPath id="cp8"><path d="M345.28 456.14H664v232.72H345.28z"/></clipPath>
                    <clipPath id="cp9"><path d="M394.69 688.74l-49.25-132.47 269.31-100.13 49.25 132.47z"/></clipPath>
                    <clipPath id="cp10"><path d="M73.39 74.15h620.9v633.17H73.39z"/></clipPath>
                    <clipPath id="cp11"><path d="M90.99 196.3h584.86v388.15H90.99z"/></clipPath>
                    <clipPath id="cp12"><path d="M194.06 85.67h481.8v575.51h-481.8z"/></clipPath>
                    <clipPath id="cp13"><path d="M385.1 85.77l290.75 122.68-190.98 452.62-290.75-122.68z"/></clipPath>
                    <clipPath id="cp14"><path d="M118.74 85.67H573v575.51H118.74z"/></clipPath>
                    <clipPath id="cp15"><path d="M118.78 192.76l265.37-107 188.8 468.31-265.37 107z"/></clipPath>
                    <clipPath id="cp16"><path d="M118.74 455.52H435V695H118.74z"/></clipPath>
                    <clipPath id="cp17"><path d="M118.77 584.28l54.44-128.76 261.78 110.67-54.44 128.76z"/></clipPath>
                    <clipPath id="cp18"><path d="M345.7 455.52H661v230.2H345.7z"/></clipPath>
                    <clipPath id="cp19"><path d="M394.58 685.6l-48.72-131.04 266.39-99.04 48.72 131.04z"/></clipPath>
                    <clipPath id="cp20"><path d="M76.76 77.67h614.17v626.31H76.76z"/></clipPath>
                </defs>

                <g class="dynamic-fill" clip-path="url(#cp1)"><path d="M87.77 194.09h591.66v392.4H87.77z"/></g>
                <g class="dynamic-fill" clip-path="url(#cp2)"><g clip-path="url(#cp3)"><path d="M385.11 82.34L679.05 206.36 485.75 664.48 191.82 540.46z"/></g></g>
                <g class="dynamic-fill" clip-path="url(#cp4)"><g clip-path="url(#cp5)"><path d="M115.87 190.5L384.15 82.34 575.12 556.02 306.84 664.18z"/></g></g>
                <g class="dynamic-fill" clip-path="url(#cp6)"><g clip-path="url(#cp7)"><path d="M115.86 586.31l55.04-130.17 264.42 111.79-55.04 130.18z"/></g></g>
                <g class="dynamic-fill" clip-path="url(#cp8)"><g clip-path="url(#cp9)"><path d="M394.69 688.74l-49.25-132.47 269.08-100.04 49.26 132.47z"/></g></g>
                <g class="dynamic-fill" clip-path="url(#cp11)"><path d="M90.99 196.3h585.24v388.15H90.99z"/></g>
                <g class="dynamic-fill" clip-path="url(#cp12)"><g clip-path="url(#cp13)"><path d="M385.1 85.77l290.75 122.68-191.2 453.16-290.74-122.68z"/></g></g>
                <g class="dynamic-fill" clip-path="url(#cp14)"><g clip-path="url(#cp15)"><path d="M118.78 192.76l265.37-107 188.9 468.55-265.37 106.99z"/></g></g>
                <g class="dynamic-fill" clip-path="url(#cp16)"><g clip-path="url(#cp17)"><path d="M118.77 584.28l54.44-128.76 261.55 110.58-54.44 128.76z"/></g></g>
                <g class="dynamic-fill" clip-path="url(#cp18)"><g clip-path="url(#cp19)"><path d="M394.58 685.6l-48.72-131.04 266.16-98.96 48.72 131.04z"/></g></g>

                <g clip-path="url(#cp10)"><path fill="#1b1b1b" d="M693.69 196.42l.4-1.13-310.05-121.14-309.59 122.4-1.03-.41v.79l-.03.02.03.13v388.57L383.89 707.32l310.5-121.7V196.14zM383.73 97.97l105.19 37.67-286.73 111.29-97.1-38.3zM372.84 679.89l-116.28-46.97-58.35-21.91-102.6-40.46V228.75l101.55 40.06v85.45l9.04-2.51 6.12 8.38 8.51-1.97 8.25 8.72 7.19-2.5 11.62 11.52V288.8l9.04 3.58 115.9 45.7zM383.91 318.62L270.53 273.9l279.6-113.32 115.34 47zM672.22 570.55L395 679.89V338.09l277.2-109.37v341.83z"/></g>

                <g clip-path="url(#cp20)"><path fill="#ffffff" d="M690.34 198.61l.4-1.11-306.7-119.83-306.23 121.07-1.02-.4.43.77-.03.03.03.13v384.35l306.87 120.36 307.13-120.39V198.34zM383.73 101.23l104.05 37.27-283.62 110.08-96.05-37.89zM372.96 676.84L257.94 630.38l-57.72-21.67-101.48-40.02V230.59l100.46 39.63v84.52l8.94-2.48 6.05 8.29 8.42-1.95 8.17 8.63 7.11-2.48 11.49 11.4V290l8.94 3.54 114.64 45.21zM383.92 319.5L271.76 275.25 548.34 163.15l114.08 46.49zM669.1 568.69L394.88 676.84V338.75l274.19-108.19v338.13z"/></g>
            </svg>
        </div>
    </div>
    <div class="flex-column all-center" id="acerca-de-container">
        <p class="about-title">
            <span style="color: var(--accent-color); font-weight: 800;">StockiFy</span> es una aplicación web de control de inventario diseñada para funcionar con tus necesidades.
        </p>
        <p class="about-subtitle">
            Nuestro sistema fue creado a <span style="color: var(--accent-color); font-weight: 600;">tu</span> medida.
        </p>
        <div class="flex-row justify-between about-container">
            <div class="options-wrapper flex-column all-center">
                <div class="flex-row about-option active" data-option="content-1">
                    <p>Interfáz simple e intuitiva para facilitar tu gestión de stock.</p>
                </div>
                <div class="flex-row about-option" data-option="content-2">
                    <p>Poderosas herramientas para registrar transacciones y obtener estadísticas clave para tu negocio.</p>
                </div>
                <div class="flex-row about-option" style="border-bottom: none" data-option="content-3">
                    <p>Control Completo.</p>
                </div>
            </div>
            <div style="width: 100%">
                <div class="content-panel active" id="content-1">
                    <h3>Diseñado para la velocidad</h3>
                    <p>
                        Dile adiós a las hojas de cálculo complicadas y al software obsoleto. <span style="color: var(--accent-color); font-weight: 600">StockiFy</span>
                        está diseñado pensando en ti.<br>Nuestra interfaz <span style="color: var(--accent-color); font-weight: 600">limpia</span> y
                        <span style="color: var(--accent-color); font-weight: 600">moderna</span> te permite agregar productos, gestionar inventarios
                        y ver tu stock de un solo vistazo.
                    </p>
                </div>

                <div class="content-panel" id="content-2">
                    <h3>Decisiones basadas en datos</h3>
                    <p>
                        Registra cada venta y cada compra en segundos.  <span style="color: var(--accent-color); font-weight: 600">StockiFy</span>
                        actualiza automáticamente tu inventario en tiempo real
                        y genera reportes vitales.<br>Analiza cuanto vendiste y compraste, monitorea tus ganancias y gastos,
                        y <span style="color: var(--accent-color); font-weight: 600">toma decisiones informadas</span> con
                        estadísticas claras y precisas.
                    </p>
                </div>

                <div class="content-panel" id="content-3">
                    <h3>Todo tu negocio centralizado</h3>
                    <p>
                        Toma el control total. Desde la gestión de <span style="color: var(--accent-color); font-weight: 600">clientes</span>
                        y <span style="color: var(--accent-color); font-weight: 600">proveedores</span>  hasta la creación de
                        <span style="color: var(--accent-color); font-weight: 600">múltiples inventarios</span> para
                        diferentes sucursales, todo está centralizado.<br>Genera clientes y proveedores, revisa el historial
                        de tus compras y ventas, y obtén una <span style="color: var(--accent-color); font-weight: 600">visión de 360°</span>
                        de tu operación.
                    </p>
                </div>
            </div>
        </div>
    </div>
    <div class="other-info-wrapper">
        <div class="flex-column other-info-item">
            <div class="flex-row all-center other-info-header"><i class="ph ph-database" style="transform: scale(1.3)"></i><p>Inventarios</p></div>
            <div class="other-info-body">
                <p class="info-main-text">Lleva un conteo extacto de la cantidad de productos que tenés en stock con nuestros <span style="color: var(--accent-color)">Inventarios.</span></p>
                <p class="info-secondary-text">Crea la cantidad que necesites, identificalos con el nombre que quieras, y agregale tus propios campos.</p>
                <p class="info-secondary-text">Utilizá nuestras <span style="color: var(--accent-color); font-weight: 600">columnas recomendadas</span> para indicar cuando queres que te avisemos que un producto está por abajo
                    de una cantidad de stock pautada y establecer precios compra y venta, <span style="color: var(--accent-color); font-weight: 600">¡Descubrí las posibilidades!</span></p>
            </div>
        </div>
        <div class="flex-column other-info-item">
            <div class="flex-row all-center other-info-header"><i class="ph ph-money" style="transform: scale(1.3)"></i><p>Transacciones</p></div>
            <div class="other-info-body">
                <p class="info-main-text">Registra <span style="color: var(--accent-color); font-weight: 600">Ventas</span> y <span style="color: var(--accent-color); font-weight: 600">Compras</span>, crea <span style="color: var(--accent-color); font-weight: 600">Clientes</span> y <span style="color: var(--accent-color); font-weight: 600">Proveedores</span>, y junta ambas.</p>
                <p class="info-secondary-text">Con solo habilitar las Columnas Recomendadas <span style="color: var(--accent-color); font-weight: 600">"Precio de Venta"</span> y <span style="color: var(--accent-color); font-weight: 600">"Precio de Compra"</span>
                    podés comenzar a gestionar <span style="color: var(--accent-color); font-weight: 600">Transacciones</span> con tus productos.</p>
                <p class="info-secondary-text">Añadí <span style="color: var(--accent-color); font-weight: 600">Clientes</span>
                    recurrentes a tu negocio, asignalos a una venta y mandales la factura por mail; o registra un <span style="color: var(--accent-color); font-weight: 600">Proveedor</span> para
                    asignarlo a una Compra que hayas hecho.</p>
            </div>
        </div>
        <div class="flex-column other-info-item">
            <div class="flex-row all-center other-info-header"><i class="ph ph-chart-bar" style="transform: scale(1.3)"></i><p>Estadísticas</p></div>
            <div class="other-info-body">
                <p class="info-main-text">¿Estuviste registrando Ventas y Compras? Utiliza nuestras <span style="color: var(--accent-color); font-weight: 600">Estadísticas</span> automaticas para llevar tu negocio al próximo nivel.</p>
                <p class="info-secondary-text">Podrás ver tus <span style="color: var(--accent-color); font-weight: 600">estadisticas diarías</span> desde el Panel, o filtra entre ciertas fechas desde
                    la <span style="color: var(--accent-color); font-weight: 600">página dedicada de estadísticas.</span></p>
                <p class="info-secondary-text">Averiguá tu cantidad de stock ingresado y vendido, cuanto dinero ganaste y perdiste, cuantas ventas realizaste, y <span style="color: var(--accent-color); font-weight: 600">mucho más.</span></p>
            </div>
        </div>
    </div>

    <button class="scroll-top" onclick="window.scrollTo({top:0,behavior:'smooth'})">
        <i class="ph ph-arrow-up" style="font-size: 1.4rem;"></i>
    </button>

</main>
<footer class="footer">
    <div class="footer-container">

        <div class="footer-brand">
            <h3>StockiFy</h3>
            <p>Gestión inteligente para negocios que buscan crecer.</p>
        </div>

        <div class="footer-divider"></div>

        <div class="footer-bottom">
            <p> &copy <span id="year"></span> StockiFy. Todos los derechos reservados.</p>
            <p class="footer-dev">
                Desarrollado con arquitectura moderna por
                <span class="jesm-signature">JESMdev</span>
            </p>
        </div>
    </div>
</footer>
<script type="module" src="assets/js/main.js"></script>
<script>
    document.getElementById("year").textContent = new Date().getFullYear();
</script>
</body>
</html>

