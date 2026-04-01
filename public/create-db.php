<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/helpers/auth_helper.php';

$currentUser = getCurrentUser();

if (!$currentUser) {
    header('Location: login.php');
    exit;
}

if (!isset($currentUser['subscription_active']) || $currentUser['subscription_active'] == 0) {
    header('Location: index.php#section-pricing');
    exit;
}

// LÍMITE DEL PLAN BÁSICO (1 Base de Datos)
if ($currentUser['subscription_active'] == 1) {
    $dbInstance = \App\core\Database::getInstance();
    $stmtCount = $dbInstance->prepare("SELECT COUNT(*) FROM inventories WHERE user_id = ?");
    $stmtCount->execute([$currentUser['id']]);
    $invCount = $stmtCount->fetchColumn();

    if ($invCount >= 1) {
        echo '
        <!DOCTYPE html>
        <html lang="es">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Límite Alcanzado - StockiFy</title>
            <link rel="stylesheet" href="assets/css/main.css">
            <link rel="stylesheet" href="assets/css/auth.css">
            <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css" />
            <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css" />
            <script src="assets/js/theme.js"></script>
        </head>
        <body style="display: flex; justify-content: center; align-items: center; min-height: 100vh; background-color: var(--bg-color); margin: 0;">
            <div class="auth-wrapper" style="width: 100%; max-width: 600px; padding: 2rem;">
                <div class="auth-form-container" style="text-align: center; padding: 3rem 2rem; border: 2px solid var(--accent-red); box-shadow: 8px 8px 0px var(--accent-red); background: var(--color-white); border-radius: 12px;">
                    <i class="ph-fill ph-lock-key" style="font-size: 4rem; color: var(--accent-red); margin-bottom: 1rem;"></i>
                    <h1 style="color: var(--accent-red); margin-bottom: 1rem; font-size: 2rem;">Acceso Restringido</h1>
                    <p style="font-size: 1.1rem; color: #666; margin-bottom: 2.5rem; line-height: 1.5;">
                        Tu <strong style="color: var(--color-black)">Plan Básico</strong> solo permite administrar <strong>1 inventario activo</strong>.<br><br>
                        Para crear múltiples inventarios y desbloquear todo el potencial de tu negocio, adquiere el <strong style="color: var(--accent-green)">Plan Profesional</strong>.
                    </p>
                    <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                        <a href="dashboard.php" class="btn btn-secondary" style="margin: 0;">Volver al Panel</a>
                        <a href="https://wa.me/5491163642040?text=Hola%20Joaquín!%20Me%20interesa%20ampliar%20el%20límite%20de%20mis%20inventarios%20y%20pasar%20al%20Plan%20Profesional." target="_blank" class="btn btn-primary" style="margin: 0; background-color: var(--accent-green); color: var(--color-white); border-color: var(--accent-green);">Mejorar mi Plan</a>
                    </div>
                </div>
            </div>
        </body>
        </html>
        ';
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Base de Datos - StockiFy</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="assets/css/create-db.css">
    <link rel="stylesheet" type="text/css"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css" />
    <link rel="stylesheet" type="text/css"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css" />
    <script src="assets/js/theme.js"></script>
</head>

<body>

    <header>
        <a href="index.php" id="header-logo">
            <img src="assets/img/LogoE.png" alt="StockiFy Logo">
        </a>
    </header>

    <div class="auth-wrapper">
        <div class="auth-form-container">
            <div class="auth-header">
                <h1>Nueva Base de Datos</h1>
                <p>Define la estructura de tu inventario.</p>
            </div>

            <form id="createDbForm" class="auth-form">

                <div class="form-group">
                    <label for="dbNameInput">Nombre de la Base de Datos:</label>
                    <input type="text" id="dbNameInput" name="dbName" placeholder="Ej: Inventario Principal" required>
                </div>

                <section class="rc-accordion">
                    <header class="rc-header" id="rc-toggle-header">
                        <h5>Columnas Recomendadas</h5>
                        <i class="ph ph-caret-down rc-arrow"></i>
                    </header>

                    <div id="recomended-columns-form" class="rc-content hidden">
                        <p class="rc-description">
                            Según las columnas que habilites y completes, la app podrá generar balances y estadísticas
                            más precisas.
                        </p>

                        <div class="rc-item">
                            <button type="button" class="rc-btn" data-toggle="stock">
                                <span>Stock Mínimo</span>
                                <span class="tooltip-container"
                                    data-tooltip="Define el stock mínimo para recibir alertas automáticas cuando un producto esté por agotarse.">
                                    <i class="ph ph-question"></i>
                                </span>
                            </button>
                        </div>

                        <div class="rc-item">
                            <button type="button" class="rc-btn" data-toggle="sale">
                                <span>Precio de Venta</span>
                                <span class="tooltip-container"
                                    data-tooltip="Activa esta columna si quieres registrar los precios de venta para generar reportes de ganancia.">
                                    <i class="ph ph-question"></i>
                                </span>
                            </button>
                        </div>

                        <div class="rc-item">
                            <button type="button" class="rc-btn" data-toggle="buy">
                                <span>Precio de Compra</span>
                                <span class="tooltip-container"
                                    data-tooltip="Permite registrar el costo de adquisición de tus productos para calcular márgenes y balances.">
                                    <i class="ph ph-question"></i>
                                </span>
                            </button>
                        </div>

                    </div>
                </section>

                <div class="form-group">
                    <label for="columnsInput">Nombres de las Columnas (separados por coma):</label>
                    <textarea id="columnsInput" name="columns" rows="3"
                        placeholder="Ej: SKU, Producto, Precio, Cantidad"></textarea>
                    <small style="color: var(--color-gray); display: block; margin-top: 5px;">
                        Define aquí las columnas extra que necesites.
                    </small>
                </div>

                <hr>

                <div class="form-group" style="width: 100%;">
                    <label>Importación de Datos:</label>
                    <div
                        style="background-color: #eee; padding: 15px; border-radius: 8px; border-left: 4px solid var(--accent-color);">
                        <p style="color: #1b1b1b; font-size: 0.9rem; line-height: 1.5;">
                            Podrás importar los datos más tarde desde el Dashboard.
                            <br><br>
                            <strong style="color: var(--accent-color);">IMPORTANTE:</strong>
                            En el caso de que quieras importarlos luego, asegúrate de que las columnas que ingreses
                            arriba (en el input y recomendadas)
                            <strong>coincidan en nombre y cantidad</strong> con las columnas de tu archivo CSV.
                        </p>
                    </div>
                </div>

                <div id="message" style="margin-top: 1rem; color: var(--accent-green);"></div>

                <button type="submit" class="btn btn-primary">Crear Base de Datos</button>

            </form>
        </div>
    </div>

    <script type="module" src="assets/js/database/create-db.js"></script>
    <script type="module" src="assets/js/api.js"></script>
</body>

</html>