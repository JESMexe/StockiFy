<?php
require_once __DIR__ . '/../vendor/autoload.php';
$currentUser = getCurrentUser();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>StockiFy</title>
    <script src="assets/js/theme.js"></script>
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
            <h2>
                <?php
                    if ($currentUser):
                        $name = htmlspecialchars($currentUser['full_name']);
                        $nombre = explode(' ', $name)[0];
                ?>
                    <h2>¡Bienvenido, <?= $nombre ?>!</h2>
                <?php else: ?>
                    <h2>¡Bienvenido!</h2>
                    <h3>Vemos que aún no has iniciado sesión.</h3>
                <?php endif; ?>
            </h2>
            <p>Tu solución para la gestión de inventario. Inicia sesión o regístrate para comenzar.</p>
            <div id="welcome-buttons" class="menu-buttons">
                <a href="login.php" class="btn btn-secondary">Iniciar Sesión</a>
                <a href="register.php" class="btn btn-primary">Crear una Cuenta</a>
            </div>
        </div>

        <div id="empty-state-view" class="view-container hidden">
            <h2>¡Empecemos!</h2>
            <p>Aún no has creado ninguna base de datos. ¡Crea la primera para empezar a organizarte!</p>
            <div class="menu-buttons">
                <a href="create-db.php" class="btn btn-primary">Crear mi Primera Base de Datos</a>
            </div>
        </div>

    </main>

<script type="module" src="assets/js/main.js"></script>
</body>
</html>

