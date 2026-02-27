<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Base de Datos - StockiFy</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/auth.css">
    <link rel="stylesheet" href="assets/css/create-db.css">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css" />
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css" />
    <script src="assets/js/theme.js"></script>
</head>
<body>

<header>
    <a href="index.php" id="header-logo">
        <img src="assets/img/LogoE.png" alt="StockiFy Logo">
    </a>
    <nav id="header-nav">
    </nav>
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
                        Según las columnas que habilites y completes, la app podrá generar balances y estadísticas más precisas.
                    </p>

                    <div class="rc-item">
                        <button type="button" class="rc-btn" data-toggle="stock">
                            <span>Stock Mínimo</span>
                            <span class="tooltip-container" data-tooltip="Define el stock mínimo para recibir alertas automáticas cuando un producto esté por agotarse.">
                                <i class="ph ph-question"></i>
                            </span>
                        </button>
                    </div>

                    <div class="rc-item">
                        <button type="button" class="rc-btn" data-toggle="sale">
                            <span>Precio de Venta</span>
                            <span class="tooltip-container" data-tooltip="Activa esta columna si quieres registrar los precios de venta para generar reportes de ganancia.">
                                <i class="ph ph-question"></i>
                            </span>
                        </button>
                    </div>

                    <div class="rc-item">
                        <button type="button" class="rc-btn" data-toggle="buy">
                            <span>Precio de Compra</span>
                            <span class="tooltip-container" data-tooltip="Permite registrar el costo de adquisición de tus productos para calcular márgenes y balances.">
                                <i class="ph ph-question"></i>
                            </span>
                        </button>
                    </div>

                </div>
            </section>

            <div class="form-group">
                <label for="columnsInput">Nombres de las Columnas (separados por coma):</label>
                <textarea id="columnsInput" name="columns" rows="3" placeholder="Ej: SKU, Producto, Precio, Cantidad"></textarea>
                <small style="color: var(--color-gray); display: block; margin-top: 5px;">
                    Define aquí las columnas extra que necesites.
                </small>
            </div>

            <hr>

            <div class="form-group" style="width: 100%;">
                <label>Importación de Datos:</label>
                <div style="background-color: #eee; padding: 15px; border-radius: 8px; border-left: 4px solid var(--accent-color);">
                    <p style="color: #1b1b1b; font-size: 0.9rem; line-height: 1.5;">
                        Podrás importar los datos más tarde desde el Dashboard.
                        <br><br>
                        <strong style="color: var(--accent-color);">IMPORTANTE:</strong>
                        En el caso de que quieras importarlos luego, asegúrate de que las columnas que ingreses arriba (en el input y recomendadas)
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