<?php
session_start();

$loggedIn = isset($_SESSION['user_id']);
$nombre   = $_SESSION['username'] ?? 'Usuario';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>StockiFy — Control simple y potente</title>

    <link rel="stylesheet" href="assets/css/main.css?v=2.2">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=2.2">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css">
    <script defer src="assets/js/theme.js"></script>
</head>
<body>
<header>
    <a href="/" id="header-logo">
        <img src="assets/img/LogoE.png" alt="StockiFy Logo">
    </a>
    <nav id="header-nav">
        <?php if ($loggedIn): ?>
            <a href="logout.php" class="btn btn-secondary">Cerrar Sesión</a>
        <?php else: ?>
            <a href="login.php" class="btn btn-secondary">Iniciar Sesión</a>
            <a href="register.php" class="btn btn-primary">Crear Cuenta</a>
        <?php endif; ?>
    </nav>
</header>

<main class="page">
    <section class="hero fade-in-up">
        <div class="hero__text">
            <h1>La forma más simple de <span class="accent">gestión de inventario</span></h1>
            <h2>Te ayudamos con tus <span id="cycling-text-container"><span id="cycling-text" class="accent">Ventas.</span></span></h2>
        </div>

        <div class="status">

            <!-- Vista 1: NO LOGUEADO -->
            <div id="welcome-view" class="view-container fade-in-up hidden">
                <h2>¡Bienvenido!</h2>
                <p>Iniciá sesión o registrate para empezar a organizar tu inventario.</p>
                <div class="actions">
                    <a href="login.php" class="btn btn-secondary">Iniciar Sesión</a>
                    <a href="register.php" class="btn btn-primary">Crear una Cuenta</a>
                </div>
            </div>

            <!-- Vista 2: LOGUEADO PERO SIN BASES -->
            <div id="empty-state-view" class="view-container fade-in-up hidden">
                <h2></h2>
                <p>Aún no creaste ninguna base de datos. ¡Arranquemos con la primera!</p>
                <div class="actions">
                    <a href="create-db.php" class="btn btn-primary">Crear mi Primera Base</a>
                </div>
            </div>

            <!-- Vista 3: LOGUEADO CON BASES PERO SIN SELECCIONAR UNA -->
            <div id="select-db-view" class="view-container fade-in-up hidden">
                <h2></h2>
                <p>Seleccioná una base de datos y comenzá a trabajar.</p>
                <div class="actions">
                    <a href="select-db.php" class="btn btn-primary">Seleccionar Base de Datos</a>
                    <a href="create-db.php" class="btn btn-secondary">Crear Nueva Base</a>
                </div>
            </div>

            <!-- Vista 4: LOGUEADO + BASE ACTIVA -->
            <div id="dashboard-view" class="view-container fade-in-up hidden">
                <h2></h2>
                <p>Tenés una base activa. ¡Hora de trabajar!</p>
                <div class="actions">
                    <a href="dashboard.php" class="btn btn-primary">Ir al Panel</a>
                </div>
            </div>

        </div>

    </section>

    <section class="features fade-in-up">
        <article class="view-card">
            <h3><i class="ph ph-table"></i> Inventario simple</h3>
            <p>Creá columnas personalizadas, importá CSV y administrá tu stock de forma ágil.</p>
            <a class="btn btn-secondary" href="/create-db.php">Crear Base</a>
        </article>

        <article class="view-card">
            <h3><i class="ph ph-arrows-left-right"></i> Transacciones</h3>
            <p>Registrá ventas y compras, y mantené el historial siempre ordenado.</p>
            <a class="btn btn-secondary" href="/dashboard.php#sales">Ir a Ventas</a>
        </article>

        <article class="view-card">
            <h3><i class="ph ph-chart-line"></i> Estadísticas diarias</h3>
            <p>Observá ingresos, gastos y ganancias por hora para tomar mejores decisiones.</p>
            <a class="btn btn-secondary" href="/dashboard.php#analisys">Ver Estadísticas</a>
        </article>
    </section>

    <footer class="fade-in-up" data-delay="260">
        <p>
            StockiFy es una aplicación web de control de inventario pensada para adaptarse a tu negocio.
            En su nivel más básico permite llevar un conteo detallado de tus productos; y además sumamos
            herramientas para transacciones, clientes, proveedores y análisis, todo en un mismo lugar.
        </p>
    </footer>

</main>

<script type="module" src="assets/js/main.js?v=2.2"></script>
</body>
</html>
