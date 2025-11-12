<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Crear Base de Datos - StockiFy</title>
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/auth.css">
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
    <div class="auth-form-container"> <div class="auth-header">
            <h1>Nueva Base de Datos</h1>
            <p>Define la estructura y, opcionalmente, importa datos.</p>
        </div>

        <form id="createDbForm" class="auth-form">

            <div class="form-group">
                <label for="dbNameInput">Nombre de la Base de Datos:</label>
                <input type="text" id="dbNameInput" name="dbName" placeholder="Ej: Inventario Principal" required>
            </div>

            <!-- Columnas Recomendadas -->
            <section class="rc-accordion">
                <header class="rc-header" id="rc-toggle-header">
                    <h5>Columnas Recomendadas</h5>
                    <i class="ph ph-caret-down rc-arrow"></i>
                </header>

                <div class="rc-content">
                    <p class="rc-description">
                        Según las columnas que habilites y completes, la app podrá generar balances y estadísticas más precisas.
                    </p>

                    <!-- STOCK MÍNIMO -->
                    <div class="rc-item" data-col="stock">
                        <button type="button" class="rc-btn" data-toggle="stock">
                            <span>Stock Mínimo</span>
                            <i class="ph ph-question rc-help" data-tooltip="Define el stock mínimo para recibir alertas automáticas cuando un producto esté por agotarse."></i>
                        </button>

                        <div class="rc-extra hidden" id="stock-extra">
                            <label class="rc-inline">
                                <input type="checkbox" id="set-stock-now"> Establecer ahora
                            </label>
                            <input type="number" id="stock-value" class="rc-input hidden" placeholder="Valor por defecto (0)">
                        </div>
                    </div>

                    <!-- PRECIO DE VENTA -->
                    <div class="rc-item" data-col="sale">
                        <button type="button" class="rc-btn" data-toggle="sale">
                            <span>Precio de Venta</span>
                            <i class="ph ph-question rc-help" data-tooltip="Activa esta columna si quieres registrar los precios de venta para generar reportes de ganancia."></i>
                        </button>
                    </div>

                    <!-- PRECIO DE COMPRA -->
                    <div class="rc-item" data-col="buy">
                        <button type="button" class="rc-btn" data-toggle="buy">
                            <span>Precio de Compra</span>
                            <i class="ph ph-question rc-help" data-tooltip="Permite registrar el costo de adquisición de tus productos para calcular márgenes y balances."></i>
                        </button>
                    </div>

                    <!-- MARGEN DE GANANCIA -->
                    <div class="rc-item" data-col="gain">
                        <button type="button" class="rc-btn" data-toggle="gain">
                            <span>Margen de Ganancia</span>
                            <i class="ph ph-question rc-help" data-tooltip="Determina el porcentaje o valor fijo de ganancia esperado por cada producto."></i>
                        </button>

                        <div class="rc-extra hidden" id="gain-extra">
                            <label class="rc-radio">
                                <input type="radio" name="gain-type" checked> Porcentaje
                            </label>
                            <label class="rc-radio">
                                <input type="radio" name="gain-type"> Valor fijo
                            </label>
                        </div>
                    </div>
                    <p class="rc-note">
                        Podrás modificar o eliminar estas columnas en cualquier momento desde la app más adelante.
                    </p>
                </div>
            </section>



            <div class="form-group">
                <label for="columnsInput">Nombres de las Columnas (separados por coma):</label>
                <textarea id="columnsInput" name="columns" rows="3" placeholder="Ej: SKU, Producto, Precio, Cantidad"></textarea>
                <small style="color: var(--color-gray); display: block; margin-top: 5px;">Podrás importar datos para estas columnas más adelante.</small>
            </div>

            <hr> <div class="form-group">
                <label>Importar Datos (Opcional):</label>
                <button type="button" id="prepare-import-btn" class="btn btn-secondary">Preparar Importación desde CSV</button>
                <div id="import-prepared-status" style="margin-top: 10px; color: var(--accent-green); font-weight: 500;"></div>
            </div>

            <div id="message" style="margin-top: 1rem; color: var(--accent-red);"></div>

            <button type="submit" class="btn btn-primary">Crear Base de Datos</button>

        </form>
    </div>
</div>

<div id="import-modal" class="modal-overlay hidden">
    <div class="modal-content view-container">
        <button id="close-modal-btn" class="modal-close-btn">&times;</button>
        <div class="modal-header">
            <h2>Importar Datos desde CSV</h2>
            <p>Selecciona o arrastra tu archivo CSV.</p>
        </div>
        <div class="modal-body">
            <div id="import-step-1">
                <div id="drop-zone" class="drop-zone">
                    <p>Arrastra tu archivo CSV aquí o haz clic para seleccionar</p>
                    <input type="file" id="csv-file-input" accept=".csv" style="display: none;">
                </div>
                <div id="import-status" style="margin-top: 1rem;"></div>
            </div>
            <div id="import-step-2" class="hidden">
                <h3>Mapea las Columnas</h3>
                <p>Asigna las columnas de tu archivo a las de StockiFy.</p>

                <!-- Nota nueva -->
                <p class="modal-note">
                    <strong>Nota:</strong> la app crea automáticamente un <strong>ID único</strong> para cada fila, por lo que no necesitás incluirlo en el CSV.
                </p>
                <div class="modal-section"></div>

                <form id="mapping-form" style="max-height: 40vh; overflow-y: auto; padding-right: 10px;"></form>

            </div>
        </div>
        <div class="modal-footer">
            <button id="import-cancel-btn" class="btn btn-secondary">Cancelar</button>
            <button id="validate-prepare-btn" class="btn btn-primary hidden">Validar y Preparar Datos</button>
        </div>
    </div>
</div>


<script type="module" src="assets/js/database/create-db.js"></script>
<script type="module" src="assets/js/import.js"></script>
<script type="module" src="assets/js/api.js"></script>
<script>
    (function () {
        const headerBtn = document.getElementById('open-columnas-recomendadas-btn');
        const content = document.getElementById('recomended-columns-form');
        if (!headerBtn || !content) return;

        headerBtn.addEventListener('click', () => {
            const open = headerBtn.getAttribute('aria-expanded') === 'true';
            headerBtn.setAttribute('aria-expanded', String(!open));
            content.classList.toggle('open', !open);
        });
    })();
</script>

</body>
</html>