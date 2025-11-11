<!DOCTYPE html>
<html lang="es" xmlns:type="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Control - StockiFy</title>
    <link rel="stylesheet" href="assets/css/main.css?v=1.2">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=1.2">
    <link rel="stylesheet" href="assets/css/notifications.css?v=1.0">
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css" />
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css" />
    <script src="assets/js/theme.js"></script>
</head>
<body>
<header>
    <a href="/" id="header-logo">
        <img src="assets/img/LogoE.png" alt="StockiFy Logo">
    </a>
    <nav id="header-nav"></nav> </header>

<div class="dashboard-container">
    <aside class="dashboard-sidebar">
        <nav class="main-menu">
            <h3>Menú Principal</h3>
            <ul>
                <li><button class="menu-btn active" data-target-view="view-db"><i class="ph ph-table"></i> Ver Datos</button></li>
                <li><button class="menu-btn" data-target-view="config-db"><i class="ph ph-gear"></i> Configurar Tabla</button></li>
                <li><button class="menu-btn" data-target-view="analysis"><i class="ph ph-chart-line"></i> Análisis Económico</button></li>
                <li><button class="menu-btn" data-target-view="notifications"><i class="ph ph-bell"></i> Notificaciones</button></li>
                <hr>
                <li><a href="select-db.php" class="menu-link"><i class="ph ph-database"></i> Cambiar Base de Datos</a></li>
                <li><a href="create-db.php" class="menu-link"><i class="ph ph-plus-circle"></i> Crear Nueva Base de Datos</a></li>
            </ul>
        </nav>
    </aside>

    <main class="dashboard-main">
        <div id="view-db" class="dashboard-view">
            <div class="table-container">
                <div class="table-header">
                    <h2 id="table-title">Cargando...</h2>
                    <div class="table-controls">
                        <div class="search-wrapper">
                            <input type="text" id="search-input" placeholder="Buscar en la tabla...">

                            <button id="search-column-btn" class="btn btn-secondary">
                                <i class="ph ph-funnel"></i>
                                <span>Todas</span>
                                <i class="ph ph-caret-down"></i>
                            </button>

                            <div id="search-column-dropdown" class="search-dropdown hidden">
                                <button class="search-dropdown-item active" data-column="all">
                                    <i class="ph ph-check"></i> Todas las Columnas
                                </button>
                            </div>
                        </div>

                        <button id="add-row-btn" class="btn btn-primary" style="width: auto; margin-top: 0;">+ Añadir Fila</button>
                    </div>
                </div>
                <div class="table-wrapper">
                    <table id="data-table">
                        <thead></thead>
                        <tbody>
                        <tr><td colspan="100%">Cargando datos...</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div id="config-db" class="dashboard-view hidden">
            <h2><i class="ph ph-gear"></i> Configurar Tabla</h2>
            <p>Gestioná las secciones de tu tabla y otras configuraciones.</p>

            <div class="accordion" style="margin-top: 2rem;">

                <div class="accordion-item">
                    <button class="accordion-header" aria-expanded="false">
                        <span>Gestionar Columnas</span>
                        <i class="ph ph-caret-down"></i>
                    </button>
                    <div class="accordion-content">
                        <form id="add-column-form" class="form-inline">
                            <div class="form-group" style="flex-grow: 1;">
                                <label for="new-column-name">Nombre de la Nueva Columna</label>
                                <input type="text" id="new-column-name" placeholder="Ej: PrecioCompra" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Añadir Columna</button>
                        </form>

                        <h4 style="margin-top: 1.5rem;">Columnas Actuales</h4>
                        <div id="column-list-container"></div>
                        <p id="column-list-status">Cargando columnas...</p>
                    </div>
                </div>

                <div class="accordion-item">
                    <button class="accordion-header" aria-expanded="false">
                        <span style="color: var(--accent-red)">Zona de Peligro</span>
                        <i class="ph ph-caret-down"></i>
                    </button>
                    <div class="accordion-content">
                        <p style="color: var(--accent-red); margin-bottom: 1rem;">
                            Eliminar tu base de datos borrará permanentemente todas las tablas y datos.
                            <strong>Esta acción no se puede deshacer.</strong>
                        </p>
                        <button id="delete-db-btn" class="btn btn-danger">Eliminar esta Base de Datos</button>
                    </div>
                </div>

            </div>
        </div>

        <div id="analysis" class="dashboard-view hidden">
            <h2><i class="ph ph-chart-line"></i> Análisis Económico</h2>
            <p>Acá vas a poder ver balances y registrar movimientos.</p>
        </div>

        <div id="notifications" class="dashboard-view hidden">
            <h2><i class="ph ph-bell"></i>Notificaciones</h2>
            <p>Historial de todos los avisos importantes de tu cuenta.</p>

            <div class="accordion" style="margin-top: 2rem;">
                <div class="accordion-item">
                    <button class="accordion-header" aria-expanded="false">
                        <span><i class="ph ph-bug"></i> Herramienta de Debug</span>
                        <i class="ph ph-caret-down"></i>
                    </button>
                    <div class="accordion-content">
                        <form id="debug-toast-form" style="display: grid; gap: 1rem;">
                            <select id="debug-toast-type" class="btn" style="width: 100%; border-radius: 4px; padding: 12px; border: 2px solid black; background: white;">
                                <option value="success">Success (Éxito)</option>
                                <option value="error">Error (Rojo)</option>
                                <option value="warning">Warning (Amarillo)</option>
                                <option value="info">Info (Azul)</option>
                                <option value="system">System (Violeta)</option>
                            </select>
                            <input type="text" id="debug-toast-title" placeholder="Título de la Notificación" required>
                            <textarea id="debug-toast-message" placeholder="Mensaje de la Notificación..." style="min-height: 80px;"></textarea>
                            <button type="submit" class="btn btn-primary" style="width: auto;">Lanzar Notificación de Prueba</button>
                        </form>
                    </div>
                </div>
            </div>
            <div id="notifications-list" style="margin-top: 2rem;">
                <p>Cargando historial...</p>
            </div>
        </div>
    </main>
</div>

<div id="import-modal" class="modal-overlay hidden">
    <div class="modal-content view-container"> <button id="close-modal-btn" class="modal-close-btn">&times;</button>

        <div class="modal-header">
            <h2>Importar Datos desde CSV</h2>
            <p>Selecciona o arrastra tu archivo CSV.</p>
        </div>

        <div class="modal-body">
            <div id="import-step-1">
                <div id="drop-zone" class="drop-zone">
                    <p>Arrastra tu archivo CSV acá o hacé clic para seleccionar</p>
                    <input type="file" id="csv-file-input" accept=".csv" style="display: none;">
                </div>
                <div id="import-status" style="margin-top: 1rem;"></div>
            </div>

            <div id="import-step-2" class="hidden">
                <h3>Mapeá las Columnas</h3>
                <p>Asigná las columnas de tu archivo a las de StockiFy.</p>
                <form id="mapping-form" style="max-height: 40vh; overflow-y: auto; padding-right: 10px;"></form>
            </div>
        </div>

        <div class="modal-footer">
            <button id="import-cancel-btn" class="btn btn-secondary">Cancelar</button>
            <button id="validate-prepare-btn" class="btn btn-primary hidden">Validar y Preparar Datos</button>
        </div>
    </div>
</div>

<div id="delete-confirm-modal" class="modal-overlay hidden">
    <div class="modal-content view-container" style="max-width: 450px; --accent-color-hover: var(--accent-red = '#BF616A') !important;">
        <button id="close-delete-modal-btn" class="modal-close-btn">&times;</button>

        <div class="modal-header">
            <h2 style="color: var(--accent-red);">Confirmar Eliminación</h2>
            <p>Esta acción <strong>no se puede deshacer</strong>. Se borrará permanentemente la base de datos "<strong id="delete-db-name-confirm"></strong>" y todos sus datos.</p>
        </div>

        <div class="modal-body">
            <p style="text-align: left; color: var(--color-gray);">Para confirmar, escribí el nombre exacto de la base de datos:</p>
            <input type="text" id="delete-confirm-input" placeholder="Nombre de la Base de Datos" style="margin-bottom: 1rem; border-color: var(--accent-red);">
            <div id="delete-error-message" style="color: var(--accent-red); font-weight: 500;"></div>
        </div>

        <div class="modal-footer">
            <button id="cancel-delete-btn" class="btn btn-secondary">Cancelar</button>
            <button id="confirm-delete-btn" class="btn btn-danger" disabled>Eliminar Permanentemente</button>
        </div>
    </div>
</div>

<div id="toast-container"></div>

<div id="custom-prompt-modal" class="modal-overlay hidden">
    <div class="modal-content view-container" style="max-width: 450px;">
        <div class="modal-header">
            <h2 id="prompt-title">Título del Prompt</h2>
            <p id="prompt-message" style="text-align: left;">Mensaje de ayuda.</p>
        </div>
        <form id="prompt-form">
            <div class="modal-body">
                <input type="text" id="prompt-input" placeholder="Escribí acá..." required>
            </div>
            <div class="modal-footer">
                <button type="button" id="prompt-cancel-btn" class="btn btn-secondary">Cancelar</button>
                <button type="submit" id="prompt-confirm-btn" class="btn btn-primary">Confirmar</button>
            </div>
        </form>
    </div>
</div>

<script type="module" src="assets/js/import.js"></script>
<script type="module" src="assets/js/dashboard.js"></script>
</body>
</html>