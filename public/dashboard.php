<!DOCTYPE html>
<html lang="es" xmlns:type="http://www.w3.org/1999/xhtml">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Control - StockiFy</title>

    <!-- Tus hojas de estilo (se respetan versiones y orden) -->
    <link rel="stylesheet" href="assets/css/main.css?v=1.2">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=1.2">
    <link rel="stylesheet" href="assets/css/notifications.css?v=1.0">

    <!-- Iconos -->
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css" />
    <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css" />

    <!-- Tema -->
    <script src="assets/js/theme.js"></script>

    <!-- (Se mantienen también tus enlaces “plain” por compatibilidad con otras vistas) -->
    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
</head>
<body>
<header>
    <a href="/" id="header-logo">
        <img src="assets/img/LogoE.png" alt="StockiFy Logo">
    </a>
    <nav id="header-nav"></nav>
</header>

<!-- ===================== FONDO GRIS Y MODALES DE TRANSACCIONES (del compañero) ===================== -->
<div id="grey-background" class="hidden" style="z-index: 21">
    <div id="new-transaction-container" class="hidden">
        <div id="return-btn" class="return-btn" style="top: 0; left: 0">Volver</div>
        <div id="transaction-form-container">
        </div>
    </div>
    <div id="transaction-picker-modal" class="hidden">
        <div class="flex-row" style="justify-content: space-between; align-items: center; padding: 0.5rem;">

            <div id="modal-return-btn" class="return-btn" style="top: 0; left: 0">Volver</div>
            <div class="flex-row" style="padding: 0.5rem; align-items: center; justify-content: right;">
                <div class="flex-column" id="inventory-picker-container">
                    <div id="inventory-picker-name" class="btn"><p>Todos los Inventarios</p></div>
                    <p style="position: absolute; bottom: 90%; right: 0; font-size: 11px; min-width: 100px" class="inventory-info-btn">¿Donde están mis inventarios?</p>
                    <div id="item-picker-header" class="flex-row"></div>
                </div>
            </div>

        </div>
        <hr>
        <div id="item-picker-modal" class="picker-modal flex-column">
            <div id="item-list" class="flex-column picker-list"></div>
            <button class="btn btn-primary picker-confirm-btn" data-type="item" disabled>Selecccionar</button>
        </div>
        <div id="client-picker-modal" class="picker-modal">
            <div id="client-list" class="flex-column picker-list"></div>
            <button class="btn btn-primary picker-confirm-btn" data-type="client" disabled>Selecccionar</button>
        </div>
        <div id="provider-picker-modal" class="picker-modal">
            <div id="provider-list" class="flex-column picker-list"></div>
            <button class="btn btn-primary picker-confirm-btn" data-type="provider" disabled>Selecccionar</button>
        </div>
    </div>
    <div id="transaction-success-modal" class="flex-column hidden">
        <h2 style="color: var(--accent-green)" class="flex-row all-center">¡Exito!</h2>
        <div id="success-modal-body">
        </div>
    </div>
    <div id="transaction-info-modal" class="hidden">
    </div>
    <div id="inventory-info-modal" class="hidden">
        Solo se permite seleccionar los inventarios (y productos) que tienen activadas las columnas recomendadas de "Precio de Compra" y "Precio de Venta".
        <br>
        <br>
        ¡Activalas en la sección de "Configurar Tabla"!
        <br>
        <button class="btn btn-primary" id="close-inventory-info-modal">Cerrar</button>
    </div>
</div>
<!-- ===================== FIN FONDO GRIS Y MODALES DE TRANSACCIONES ===================== -->

<div class="dashboard-container">
    <aside class="dashboard-sidebar">
        <nav class="main-menu">
            <h3>Base de Datos</h3>
            <ul>
                <!--                     CODIGO DE NANO                        -->
                <!--        MODIFICACION DE ORDEN DE MENUS, MENUS NUEVOS       -->
                <li><button class="menu-btn active" data-target-view="view-db"><i class="ph ph-table"></i> Ver Datos</button></li>
                <li><button class="menu-btn" data-target-view="config-db"><i class="ph ph-gear"></i> Configurar Tabla</button></li>
                <li><a href="select-db.php" class="menu-link"><i class="ph ph-database"></i> Cambiar Base de Datos</a></li>
                <li><a href="create-db.php" class="menu-link"><i class="ph ph-plus-circle"></i> Crear Nueva Base de Datos</a></li>
                <hr>
            </ul>

            <h3>Transacciones</h3>
            <ul>
                <li><button class="menu-btn" data-target-view="sales"><i class="ph ph-money"></i> Ventas</button></li>
                <li><button class="menu-btn" data-target-view="receipts"><i class="ph ph-stack"></i> Compras</button></li>
                <li><button class="menu-btn" data-target-view="customers"><i class="ph ph-user-focus"></i> Clientes</button></li>
                <li><button class="menu-btn" data-target-view="providers"><i class="ph ph-van"></i> Proveedores</button></li>
                <hr>
            </ul>

            <h3>Usuario</h3>
            <ul>
                <li><button class="menu-btn" data-target-view="analysis"><i class="ph ph-chart-line"></i> Estadísticas Diarias</button></li>
                <li><button class="menu-btn" data-target-view="notifications"><i class="ph ph-bell"></i> Notificaciones</button></li>
            </ul>
            <!--                          FIN DE CODIGO                        -->
        </nav>
    </aside>

    <main class="dashboard-main">
        <!-- ===================== TU SECCIÓN: VER DATOS ===================== -->
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

                        <!-- (Se respeta tu botón para añadir fila) -->
                        <button id="add-row-btn" class="btn btn-primary" style="width: auto; margin-top: 0;">+ Añadir Fila</button>

                        <!-- (Del compañero: acceso rápido a transacciones desde Ver Datos) -->
                        <button class="btn btn-primary new-transaction-btn" style="width: auto; margin-top: 0;" data-transaction="sale">+ Agregar Venta</button>
                        <button class="btn btn-primary new-transaction-btn" style="width: auto; margin-top: 0;" data-transaction="receipt">+ Agregar Compra</button>
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
        <!-- ===================== FIN VER DATOS ===================== -->

        <!-- ===================== TU SECCIÓN: CONFIGURAR TABLA + (complementos del compañero) ===================== -->
        <div id="config-db" class="dashboard-view hidden">
            <h2><i class="ph ph-gear"></i> Configurar Tabla</h2>
            <p>Gestioná las secciones de tu tabla y otras configuraciones.</p>

            <div class="accordion" style="margin-top: 2rem;">
                <!-- Tu bloque “Gestionar Columnas” -->
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

                <!-- Bloque del compañero: Columnas Recomendadas / Automatizaciones -->
                <div class="accordion-item">
                    <button class="accordion-header" aria-expanded="false">
                        <span>Columnas Recomendadas y Automatizaciones</span>
                        <i class="ph ph-caret-down"></i>
                    </button>
                    <div class="accordion-content">
                        <form id="recomended-columns-form">
                            <div class="form-group flex-column recomended-column-group">
                                <input type="checkbox" class="hidden-checkbox" id="min-stock-input" name="min-stock" value="0">
                                <label for="min-stock-input" class="btn btn-secondary btn-checkbox-toggle">Stock Mínimo</label>
                                <input style="width:180px;" class="default-value-input" type="text" id="min-stock-default-input" name="min-stock-default" placeholder="Valor por Defecto (0)">
                            </div>

                            <div class="form-group flex-column recomended-column-group">
                                <input type="checkbox" class="hidden-checkbox" id="sale-price-input" name="sale-price" value="0">
                                <label for="sale-price-input" class="btn btn-secondary btn-checkbox-toggle">Precio de Venta</label>
                                <input style="width:180px;" class="default-value-input" type="text" id="sale-price-default-input" name="sale-price-default" placeholder="Valor por Defecto (0)">
                            </div>

                            <div class="form-group flex-column recomended-column-group">
                                <div class="flex-column all-center" style="gap:10px">
                                    <input type="checkbox" class="hidden-checkbox" id="receipt-price-input" name="receipt-price" value="0">
                                    <label for="receipt-price-input" class="btn btn-secondary btn-checkbox-toggle">Precio de Compra</label>
                                </div>
                                <input style="width:180px;" class="default-value-input" type="text" id="receipt-price-default-input" name="receipt-price-default" placeholder="Valor por Defecto (0)">
                                <div class="flex-column" style="position:relative; z-index:10;">
                                    <input type="checkbox" class="hidden-checkbox" id="auto-price-input" name="auto-price" value="0">
                                    <label for="auto-price-input" id="auto-price-checkbox" class="btn btn-secondary btn-checkbox-toggle">Calcular Automaticamente</label>
                                    <div class="form-group flex-column" id="auto-price-type-container" style="align-items:start;">
                                        <div class="flex-row"><input type="radio" id="auto-iva-input"  name="price-type" value="iva"><p>Precio de Venta + IVA 21%</p></div>
                                        <div class="flex-row"><input type="radio" id="auto-gain-input" name="price-type" value="gain"><p>Precio de Venta + Margen de Ganancia</p></div>
                                        <div class="flex-row"><input type="radio" id="auto-iva-gain-input" name="price-type" value="gain-iva"><p>Precio de Venta + Margen de Ganancia + IVA 21%</p></div>
                                    </div>
                                </div>
                            </div>

                            <div class="form-group flex-column recomended-column-group">
                                <input type="checkbox" class="hidden-checkbox" id="gain-input" name="gain" value="0">
                                <label for="gain-input" class="btn btn-secondary btn-checkbox-toggle">Margen de Ganancia</label>
                                <div class="flex-row">
                                    <input style="width:180px;" class="default-value-input" type="text" id="gain-default-input" name="gain-default" placeholder="Valor por Defecto (0)">
                                    <div class="form-group flex-column" id="gain-type-container" style="align-items:start;">
                                        <div class="flex-row"><input type="radio" id="percentage-gain-input" name="gain-type"><p>Porcentaje</p></div>
                                        <div class="flex-row"><input type="radio" id="hard-gain-input" name="gain-type"><p>Valor Fijo</p></div>
                                    </div>
                                </div>
                            </div>

                            <button class="btn btn-primary" id="save-changes-btn" disabled>Guardar Cambios</button>
                        </form>
                    </div>
                </div>

                <!-- Tu bloque “Zona de Peligro” (se mantiene para compatibilidad) -->
                <div class="accordion-item" style="border:2px solid var(--accent-red); border-radius:var(--border-radius);">
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
        <!-- ===================== FIN CONFIGURAR TABLA ===================== -->

        <!-- ===================== ESTADÍSTICAS DIARIAS (versión AVANZADA del compañero) ===================== -->
        <div id="analysis" class="dashboard-view hidden">
            <p style="font-size: 13px; text-align: right;" class="inventory-info-btn">¿Donde están mis inventarios?</p>
            <div class="menu-container">
                <div class="table-header">
                    <h2>Estadísticas Diarias</h2>
                    <div class="flex-row" style="margin-right: 20px; align-items: center; justify-content: flex-end;">
                        <h3>Mostrando Estadisticas para Inventario = </h3>
                        <div class="inventory-select-container">
                            <h4 class="select-inventory-btn" id="inventory-picker">Todos</h4>
                            <div class="inventories-dropdown flex-column hidden" id="inventories-dropdown">
                                <div class="inventory-btn" data-value="all" style="border: none"><h4>Todos</h4></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="table-wrapper" id="stats-wrapper">
                    <div class="daily-stats-wrapper flex-row" id="general-stats">
                        <div class="flex-column" style="gap: 2rem;">
                            <div class="stat-group-container">
                                <h3>Stock</h3>
                                <div class="flex-column">
                                    <div class="daily-stat-item" data-graph="stock-ingresado">
                                        <h4>Stock Ingresado</h4>
                                        <div class="daily-stat" id="daily-stock-ingresado">0</div>
                                    </div>
                                    <div class="daily-stat-item" data-graph="stock-vendido">
                                        <h4>Stock Vendido</h4>
                                        <div class="daily-stat" id="daily-stock-vendido">0</div>
                                    </div>
                                </div>
                            </div>
                            <div class="stat-group-container">
                                <h3>Monetarias</h3>
                                <div class="flex-column">
                                    <div class="daily-stat-item" data-graph="gastos">
                                        <h4>Gastos</h4>
                                        <div class="flex-column">
                                            <div class="daily-stat" id="daily-gastos">0</div>
                                        </div>
                                    </div>
                                    <div class="daily-stat-item" data-graph="ingresos">
                                        <h4>Ingresos</h4>
                                        <div class="daily-stat" id="daily-ingresos">0</div>
                                    </div>
                                    <div class="daily-stat-item" data-graph="ganancias">
                                        <h4>Ganancias</h4>
                                        <div class="daily-stat" id="daily-ganancias">0</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="flex-column" style="gap: 2rem">
                            <div class="stat-group-container">
                                <h3>Transacciones</h3>
                                <div class="flex-column">
                                    <div class="daily-stat-item" data-graph="ventas">
                                        <h4>Ventas Realizadas</h4>
                                        <div class="daily-stat" id="daily-ventas">0</div>
                                    </div>
                                    <div class="daily-stat-item" data-graph="compras">
                                        <h4>Compras Realizadas</h4>
                                        <div class="daily-stat" id="daily-compras">0</div>
                                    </div>
                                </div>
                            </div>

                            <div class="stat-group-container">
                                <h3>Conexiones</h3>
                                <div class="flex-column">
                                    <div class="daily-stat-item" data-graph="clientes">
                                        <h4>Nuevos Clientes</h4>
                                        <div class="daily-stat" id="daily-clientes">0</div>
                                    </div>
                                    <div class="daily-stat-item" data-graph="proveedores">
                                        <h4>Nuevos Proveedores</h4>
                                        <div class="daily-stat" id="daily-proveedores">0</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="estadisticas-graph-wrapper all-center">
                        <div class="flex-column stat-graph all-center" id="stock-ingresado-container">
                            <h2>Stock Ingresado por Hora</h2>
                            <div id="stock-ingresado-graph"></div>
                        </div>
                        <div class="flex-column all-center stat-graph hidden" id="stock-vendido-container">
                            <h2>Stock Vendido por Hora</h2>
                            <div id="stock-vendido-graph"></div>
                        </div>
                        <div class="flex-column stat-graph all-center hidden" id="ventas-container">
                            <h2>Ventas Realizadas por Hora</h2>
                            <div id="ventas-graph"></div>
                        </div>
                        <div class="flex-column all-center stat-graph hidden" id="compras-container">
                            <h2>Compras Realizadas por Hora</h2>
                            <div id="compras-graph"></div>
                        </div>
                        <div class="flex-column stat-graph all-center hidden" id="gastos-container">
                            <h2>Gastos por Hora</h2>
                            <div id="gastos-graph"></div>
                        </div>
                        <div class="flex-column all-center stat-graph hidden" id="ingresos-container">
                            <h2>Ingresos por Hora</h2>
                            <div id="ingresos-graph"></div>
                        </div>
                        <div class="flex-column all-center stat-graph hidden" id="ganancias-container">
                            <h2>Ganancias por Hora</h2>
                            <div id="ganancias-graph"></div>
                        </div>
                        <div class="flex-column stat-graph all-center hidden" id="clientes-container">
                            <h2>Nuevos Clientes por Hora</h2>
                            <div id="clientes-graph"></div>
                        </div>
                        <div class="flex-column all-center stat-graph hidden" id="proveedores-container">
                            <h2>Nuevos Proveedores por Hora</h2>
                            <div id="proveedores-graph"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- ===================== FIN ESTADÍSTICAS DIARIAS ===================== -->

        <!-- ===================== NOTIFICACIONES (TU VERSIÓN) ===================== -->
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
        <!-- ===================== FIN NOTIFICACIONES ===================== -->

        <!-- ===================== VENTAS (NUEVO DEL COMPAÑERO) ===================== -->
        <div id="sales" class="dashboard-view hidden">
            <div class="not-available-container hidden">
                <h2>Para gestionar el registro de Ventas, primero debes activar los siguientes Campos Recomendados para la Base de Datos seleccionada:</h2>
                <div class="missing-preference-text flex-column"></div>
                <button class="btn btn-primary got-to-config-btn">Ir a Configuración</button>
            </div>
            <div id="ventas-container" class="menu-container" style="overflow: visible;">
                <div class="table-header">
                    <h2>Ventas</h2>
                    <div class="table-controls">
                        <div data-direction="descending" data-order= "none" class="direction-btn">
                            <i class="ph ph-arrow-up hidden" style="margin-right: 5px"></i>
                            <i class="ph ph-arrow-down" style="margin-right: 5px"></i>
                        </div>
                        <div class="order-by-container">
                            <div class="flex-row" style="margin-right: 20px; align-items: center; justify-content: flex-end;">
                                <i class="ph ph-list-bullets" style="margin-right: 5px"></i>
                                <h4 class="order-by-btn">Ordenar Por</h4>
                            </div>
                            <div class="order-by-dropdown flex-column hidden">
                                <p data-order="sales-table-date" class="order-btn">Fecha</p>
                                <p data-order="sales-table-id" class="order-btn">Número</p>
                                <p data-order="sales-table-client" class="order-btn">Cliente</p>
                                <p data-order="sales-table-price" class="order-btn">Precio</p>
                            </div>
                        </div>
                        <button class="btn btn-primary new-transaction-btn" style="margin-top: 0; margin-left: 1rem;" data-transaction="sale">+ Crear una Venta</button>
                    </div>
                </div>
                <div class="table-wrapper">
                    <div id="sales-table-container">
                        <div id="sales-table-id-descending" class="transaction-view hidden">
                        </div>
                        <div id="sales-table-id-ascending" class="transaction-view hidden">
                        </div>
                        <div id="sales-table-client-descending" class="transaction-view hidden">
                        </div>
                        <div id="sales-table-client-ascending" class="transaction-view hidden">
                        </div>
                        <div id="sales-table-price-ascending" class="transaction-view hidden">
                        </div>
                        <div id="sales-table-price-descending" class="transaction-view hidden">
                        </div>
                        <div id="sales-table-date-ascending" class="transaction-view hidden">
                        </div>
                        <div id="sales-table-date-descending" class="transaction-view">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- ===================== FIN VENTAS ===================== -->

        <!-- ===================== COMPRAS (NUEVO DEL COMPAÑERO) ===================== -->
        <div id="receipts" class="dashboard-view hidden">
            <div class="not-available-container hidden">
                <h2>Para gestionar el registro de Compras, primero debes activar los siguientes Campos Recomendados para la Base de Datos seleccionada:</h2>
                <div class="missing-preference-text flex-column"></div>
                <button class="btn btn-primary got-to-config-btn">Ir a Configuración</button>
            </div>
            <div id="receipts-container" class="menu-container" style="overflow: visible;">
                <div class="table-header">
                    <h2>Compras</h2>
                    <div class="table-controls">
                        <div data-direction="descending" data-order= "none" class="direction-btn">
                            <i class="ph ph-arrow-up hidden" style="margin-right: 5px"></i>
                            <i class="ph ph-arrow-down" style="margin-right: 5px"></i>
                        </div>
                        <div class="order-by-container">
                            <div class="flex-row" style="margin-right: 20px; align-items: center; justify-content: flex-end;">
                                <i class="ph ph-list-bullets" style="margin-right: 5px"></i>
                                <h4 class="order-by-btn">Ordenar Por</h4>
                            </div>
                            <div class="order-by-dropdown flex-column hidden">
                                <p data-order="receipts-table-date" class="order-btn">Fecha</p>
                                <p data-order="receipts-table-id" class="order-btn">Número</p>
                                <p data-order="receipts-table-provider" class="order-btn">Proveedor</p>
                                <p data-order="receipts-table-price" class="order-btn">Precio</p>
                            </div>
                        </div>
                        <button class="btn btn-primary new-transaction-btn" style="margin-top: 0; margin-left: 1rem; justify-self: left" data-transaction="receipt">+ Crear una Compra</button>
                    </div>
                </div>
                <div class="table-wrapper">
                    <div id="receipts-table-container">
                        <div id="receipts-table-id-descending" class="transaction-view hidden">
                        </div>
                        <div id="receipts-table-id-ascending" class="transaction-view hidden">
                        </div>
                        <div id="receipts-table-provider-descending" class="transaction-view hidden">
                        </div>
                        <div id="receipts-table-provider-ascending" class="transaction-view hidden">
                        </div>
                        <div id="receipts-table-price-ascending" class="transaction-view hidden">
                        </div>
                        <div id="receipts-table-price-descending" class="transaction-view hidden">
                        </div>
                        <div id="receipts-table-date-descending" class="transaction-view">
                        </div>
                        <div id="receipts-table-date-ascending" class="transaction-view hidden">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- ===================== FIN COMPRAS ===================== -->

        <!-- ===================== CLIENTES (NUEVO DEL COMPAÑERO) ===================== -->
        <div id="customers" class="dashboard-view hidden">
            <div class="menu-container" style="overflow: visible;">
                <div class="table-header">
                    <h2>Clientes</h2>
                    <div class="table-controls">
                        <div data-direction="descending" data-order= "none" class="direction-btn">
                            <i class="ph ph-arrow-up hidden" style="margin-right: 5px"></i>
                            <i class="ph ph-arrow-down" style="margin-right: 5px"></i>
                        </div>
                        <div class="order-by-container">
                            <div class="flex-row" style="margin-right: 20px; align-items: center; justify-content: flex-end;">
                                <i class="ph ph-list-bullets" style="margin-right: 5px"></i>
                                <h4 class="order-by-btn">Ordenar Por</h4>
                            </div>
                            <div class="order-by-dropdown flex-column hidden">
                                <p data-order="customers-table-date" class="order-btn">Fecha</p>
                                <p data-order="customers-table-name" class="order-btn">Nombre</p>
                                <p data-order="customers-table-email" class="order-btn">Email</p>
                                <p data-order="customers-table-phone" class="order-btn">Teléfono</p>
                                <p data-order="customers-table-address" class="order-btn">Dirección</p>
                                <p data-order="customers-table-dni" class="order-btn">DNI</p>
                            </div>
                        </div>
                        <button class="btn btn-primary new-transaction-btn" style="margin-top: 0; margin-left: 1rem; justify-self: left" data-transaction="customer">+ Crear Cliente</button>
                    </div>
                </div>
                <div class="table-wrapper">
                    <div id="customers-table-container">
                        <div id="customers-table-email-descending" class="transaction-view hidden customer-view">
                        </div>
                        <div id="customers-table-email-ascending" class="transaction-view hidden customer-view">
                        </div>
                        <div id="customers-table-name-descending" class="transaction-view hidden customer-view">
                        </div>
                        <div id="customers-table-name-ascending" class="transaction-view hidden customer-view">
                        </div>
                        <div id="customers-table-date-descending" class="transaction-view customer-view">
                        </div>
                        <div id="customers-table-date-ascending" class="transaction-view hidden customer-view">
                        </div>
                        <div id="customers-table-phone-ascending" class="transaction-view hidden customer-view">
                        </div>
                        <div id="customers-table-phone-descending" class="transaction-view hidden customer-view">
                        </div>
                        <div id="customers-table-address-descending" class="transaction-view hidden customer-view">
                        </div>
                        <div id="customers-table-address-ascending" class="transaction-view hidden customer-view">
                        </div>
                        <div id="customers-table-dni-descending" class="transaction-view hidden customer-view">
                        </div>
                        <div id="customers-table-dni-ascending" class="transaction-view hidden customer-view">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- ===================== FIN CLIENTES ===================== -->

        <!-- ===================== PROVEEDORES (NUEVO DEL COMPAÑERO) ===================== -->
        <div id="providers" class="dashboard-view hidden">
            <div class="menu-container" style="overflow: visible;">
                <div class="table-header">
                    <h2>Proveedores</h2>
                    <div class="table-controls">
                        <div data-direction="descending" data-order= "none" class="direction-btn">
                            <i class="ph ph-arrow-up hidden" style="margin-right: 5px"></i>
                            <i class="ph ph-arrow-down" style="margin-right: 5px"></i>
                        </div>
                        <div class="order-by-container">
                            <div class="flex-row" style="margin-right: 20px; align-items: center; justify-content: flex-end;">
                                <i class="ph ph-list-bullets" style="margin-right: 5px"></i>
                                <h4 class="order-by-btn">Ordenar Por</h4>
                            </div>
                            <div class="order-by-dropdown flex-column hidden">
                                <p data-order="providers-table-date" class="order-btn">Fecha</p>
                                <p data-order="providers-table-name" class="order-btn">Nombre</p>
                                <p data-order="providers-table-email" class="order-btn">Email</p>
                                <p data-order="providers-table-address" class="order-btn">Dirección</p>
                                <p data-order="providers-table-phone" class="order-btn">Teléfono</p>
                            </div>
                        </div>
                        <button class="btn btn-primary new-transaction-btn" style="margin-top: 0; margin-left: 1rem; justify-self: left" data-transaction="provider">+ Crear Proveedor</button>
                    </div>
                </div>
                <div class="table-wrapper">
                    <div id="customers-table-container">
                        <div id="providers-table-email-descending" class="transaction-view hidden provider-view">
                        </div>
                        <div id="providers-table-email-ascending" class="transaction-view hidden provider-view">
                        </div>
                        <div id="providers-table-name-descending" class="transaction-view hidden provider-view">
                        </div>
                        <div id="providers-table-name-ascending" class="transaction-view hidden provider-view">
                        </div>
                        <div id="providers-table-date-descending" class="transaction-view provider-view">
                        </div>
                        <div id="providers-table-date-ascending" class="transaction-view hidden provider-view">
                        </div>
                        <div id="providers-table-phone-descending" class="transaction-view hidden provider-view">
                        </div>
                        <div id="providers-table-phone-ascending" class="transaction-view hidden provider-view">
                        </div>
                        <div id="providers-table-address-descending" class="transaction-view hidden provider-view">
                        </div>
                        <div id="providers-table-address-ascending" class="transaction-view hidden provider-view">
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- ===================== FIN PROVEEDORES ===================== -->

    </main>
</div>

<!-- ===================== MODALES GENERALES (tuyos) ===================== -->
<div id="import-modal" class="modal-overlay hidden" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
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

<div id="delete-confirm-modal" class="modal-overlay hidden" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%);">
    <div class="modal-content view-container" style="max-width: 450px;">
        <button id="close-delete-modal-btn" class="modal-close-btn">&times;</button>

        <div class="modal-header">
            <h2 style="color: var(--accent-red);">Confirmar Eliminación</h2>
            <p>Esta acción <strong>no se puede deshacer</strong>. Se borrará permanentemente la base de datos "<strong id="delete-db-name-confirm"></strong>" y todos sus datos.</p>
        </div>

        <div class="modal-body">
            <p style="text-align: left; color: var(--color-gray);">Para confirmar, escribí el nombre exacto de la base de datos:</p>
            <input type="text" id="delete-confirm-input" placeholder="Nombre de la Base de Datos" style="margin-bottom: 1rem;">
            <div id="delete-error-message" style="color: var(--accent-red); font-weight: 500;"></div>
        </div>

        <div class="modal-footer">
            <button id="cancel-delete-btn" class="btn btn-secondary">Cancelar</button>
            <button id="confirm-delete-btn" class="btn btn-primary" disabled>Eliminar Permanentemente</button>
        </div>
    </div>
</div>
<!-- ===================== FIN MODALES GENERALES ===================== -->

<!-- Notificaciones/toasts -->
<div id="toast-container"></div>

<!-- Prompt genérico -->
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

<!-- ===================== SCRIPTS ===================== -->
<!-- Librería para gráficos (necesaria para Estadísticas Diarias del compañero) -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>

<!-- Tus módulos (mantengo orden y rutas) -->

<script type="module" src="assets/js/import.js"></script>
<script type="module" src="assets/js/dashboard.js"></script>
</body>
</html>
