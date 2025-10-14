<?php
require_once __DIR__ . '/../vendor/autoload.php';
$currentUser = getCurrentUser();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stocky</title>
    <link rel="stylesheet" href="assets/css/main.css">
</head>

<body id="page-index">

    <header>
        <a href="/" id="header-logo">
            <img src="assets/img/LogoE.png" alt="Stocky Logo">
        </a>
        <nav id="header-nav">
        </nav>
    </header>

    <main>

        <div id="welcome-view" class="view-container hidden">
            <h2>Bienvenido a Stocky</h2>
            <p>Tu solución definitiva para la gestión de inventario. Inicia sesión o regístrate para comenzar a organizar tus bases de datos.</p>
            <div id="welcome-buttons" class="menu-buttons">
                <a href="login.php" class="btn btn-secondary">Iniciar Sesión</a>
                <a href="register.php" class="btn btn-primary">Crear una Cuenta</a>
            </div>
        </div>

        <div id="login-view" class="view-container hidden">
            <p>Cargando aplicación...</p>
        </div>

        <!-- Vista de Carga Inicial -->
        <div id="loading-view" class="view-container hidden">
            <p>Cargando Bases de Datos...</p>
        </div>

        <!-- Vista para seleccionar una BDD existente -->
        <div id="selection-view" class="view-container hidden">
            <h2>Selecciona una Base de Datos</h2>
            <p>Elige con qué base de datos quieres trabajar.</p>
            <ul id="db-list">
                <!-- La lista de BDDs se generará aca con JS -->
            </ul>
            <hr>
            <button id="go-to-create-btn" class="btn btn-secondary">O crear una nueva</button>
        </div>

        <!-- Vista para crear la primera BDD -->
        <div id="first-time-view" class="view-container hidden">
            <h2><?php if ($currentUser): ?>
                    <h2>¡Bienvenido, <?= htmlspecialchars($currentUser['full_name']) ?>!</h2>
                <?php endif; ?></h2>
            <p>Parece que es tu primera vez acá. Para comenzar, crea tu primera base de datos.</p>
            <div id="db-creator-container">
                <label for="dbNameInput">Nombre de la Base de Datos:</label>
                <input type="text" id="dbNameInput" placeholder="Ej: MiInventario">
                <br><br>
                <label for="columnsInput">Nombres de las Columnas (separados por coma):</label>
                <textarea id="columnsInput" rows="3" placeholder="Ej: ID, Nombre, Precio, Stock"></textarea>
                <br><br>
                <button id="createDbBtn" class="btn btn-primary">Crear Base de Datos</button>
            </div>
        </div>

        <!-- Vista Principal de la Aplicación (Menú) -->
        <div id="main-app-view" class="view-container hidden">
            <h2>Menú Principal</h2>
            <p>Base de Datos activa: <strong id="active-db-name"></strong></p>
            <div class="menu-buttons">
                <button id="view-db-btn" class="btn">Ver Base de Datos</button>
                <button class="btn">Configurar Base de Datos</button>
                <button class="btn">Análisis Económico</button>
                <button class="btn">Notificaciones</button>
            </div>
            <hr>
            <button id="change-db-btn" class="btn btn-secondary">Cambiar de Base de Datos</button>
        </div>

        <!-- Vista para mostrar los datos de la BDD -->
        <div id="db-data-view" class="view-container hidden">
            <h2>Contenido de la Base de Datos</h2>
            <div id="db-table-container">
                <!-- La tabla con los datos se generará aquí -->
                <p>Aquí se mostrarán los datos de la base de datos...</p>
            </div>
            <hr>
            <button id="back-to-main-menu-btn" class="btn btn-secondary">Volver al Menú</button>
        </div>

        <div id="status-message"></div>
    </main>

<script type="module" src="assets/js/main.js"></script>
</body>
</html>