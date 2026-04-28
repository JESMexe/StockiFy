<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/helpers/auth_helper.php';

$currentUser = getCurrentUser();

if (!$currentUser) {
    header('Location: login');
    exit;
}

if (!isset($currentUser['subscription_active']) || $currentUser['subscription_active'] == 0) {
    header('Location: index#section-pricing');
    exit; // Immediately kills server execution. Zero bytes sent to browser.
}
?>
<!DOCTYPE html>
<html lang="es" xmlns:type="http://www.w3.org/1999/xhtml">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Panel de Control - StockiFy</title>

    <link rel="stylesheet" href="assets/css/main.css?v=1.2">
    <link rel="stylesheet" href="assets/css/dashboard.css?v=1.3">
    <link rel="stylesheet" href="assets/css/notifications.css?v=1.0">
    <link rel="stylesheet" href="assets/css/employees.css?v=1.0">
    <link rel="stylesheet" href="assets/css/purchases.css?v=1.2">
    <link rel="stylesheet" href="assets/css/payments.css?v=1.2">

    <link rel="stylesheet" type="text/css"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css" />
    <link rel="stylesheet" type="text/css"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/bold/style.css" />
    <link rel="stylesheet" type="text/css"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css" />

    <script src="assets/js/theme.js"></script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.css"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>

    <link rel="stylesheet" href="assets/css/main.css">
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link rel="stylesheet" href="assets/css/analytics.css">
    <link rel="stylesheet" href="assets/css/sales.css">
</head>

<body>
    <header>
        <a href="/" id="header-logo">
            <img src="assets/img/LogoE.png" alt="StockiFy Logo">
        </a>
        <nav id="header-nav"></nav>
    </header>

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
                        <div id="inventory-picker-name" class="btn">
                            <p>Todos los Inventarios</p>
                        </div>
                        <p style="position: absolute; bottom: 90%; right: 0; font-size: 11px; min-width: 100px"
                            class="inventory-info-btn">¿Donde están mis inventarios?</p>
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
            Solo se permite seleccionar los inventarios (y productos) que tienen activadas las columnas recomendadas de
            "Precio de Compra" y "Precio de Venta".
            <br>
            <br>
            ¡Activalas en la sección de "Configurar Tabla"!
            <br>
            <button class="btn btn-primary" id="close-inventory-info-modal">Cerrar</button>
        </div>
    </div>

    <div class="desktop-app-view">
        <div class="dashboard-container">
            <aside class="dashboard-sidebar">
                <nav class="main-menu">
                    <h3>Base de Datos</h3>
                    <ul>
                        <li><button class="menu-btn active" data-target-view="view-db"><i class="ph ph-table"></i> Ver
                                Datos</button></li>
                        <li><button class="menu-btn" data-target-view="config-db"><i class="ph ph-gear"></i> Configurar
                                Tabla</button></li>
                        <li><a href="select-db" class="menu-link"><i class="ph ph-database"></i> Cambiar Base de
                                Datos</a></li>

                        <?php
                        $dbInstance = \App\core\Database::getInstance();
                        $stmtCount = $dbInstance->prepare("SELECT COUNT(*) FROM inventories WHERE user_id = ?");
                        $stmtCount->execute([$currentUser['id']]);
                        $invCount = $stmtCount->fetchColumn();
                        $canCreateDb = ($currentUser['subscription_active'] >= 2) || ($currentUser['subscription_active'] == 1 && $invCount == 0);
                        ?>

                        <?php if ($canCreateDb): ?>
                            <li><a href="create-db" class="menu-link"><i class="ph ph-plus-circle"></i> Crear Nueva Base
                                    de Datos</a></li>
                            <?php
                        else: ?>
                            <li style="opacity: 0.5;" title="Límite del Plan Básico alcanzado."><a href="#"
                                    onclick="window.showLockedFeatureToast('Múltiples Inventarios'); return false;"
                                    class="menu-link"><i class="ph ph-plus-circle"></i> Nuevo Inventario <i
                                        class="ph-fill ph-lock-key"
                                        style="margin-left: auto; color: var(--accent-red)"></i></a></li>
                            <?php
                        endif; ?>
                        <hr>
                    </ul>

                    <h3>Transacciones</h3>
                    <ul>
                        <li><button class="menu-btn" data-target-view="sales"><i class="ph ph-money"></i>
                                Ventas</button></li>
                        <li><button class="menu-btn" data-target-view="receipts"><i class="ph ph-stack"></i>
                                Compras</button></li>

                        <?php if ($currentUser['subscription_active'] >= 2): ?>
                            <li><button class="menu-btn" data-target-view="customers"><i class="ph ph-user-focus"></i>
                                    Clientes</button></li>
                            <li><button class="menu-btn" data-target-view="providers"><i class="ph ph-van"></i>
                                    Proveedores</button></li>
                            <li><button class="menu-btn" data-target-view="employees"><i
                                        class="ph ph-identification-badge"></i> Empleados</button></li>
                            <?php
                        else: ?>
                            <div
                                style="margin-top: 15px; margin-bottom: 5px; padding-left: 10px; font-size: 0.7rem; color: #888; text-transform: uppercase; font-weight: bold;">
                                Funciones VIP:</div>
                            <li style="opacity: 0.5;" title="Bloqueado en el Plan Básico"><button class="menu-btn"
                                    onclick="window.showLockedFeatureToast('Sección de Clientes');"><i
                                        class="ph ph-user-focus"></i> Clientes <i class="ph-fill ph-lock-key"
                                        style="margin-left: auto; color: var(--accent-red)"></i></button></li>
                            <li style="opacity: 0.5;" title="Bloqueado en el Plan Básico"><button class="menu-btn"
                                    onclick="window.showLockedFeatureToast('Sección de Proveedores');"><i
                                        class="ph ph-van"></i> Proveedores <i class="ph-fill ph-lock-key"
                                        style="margin-left: auto; color: var(--accent-red)"></i></button></li>
                            <li style="opacity: 0.5;" title="Bloqueado en el Plan Básico"><button class="menu-btn"
                                    onclick="window.showLockedFeatureToast('Sección de Empleados');"><i
                                        class="ph ph-identification-badge"></i> Empleados <i class="ph-fill ph-lock-key"
                                        style="margin-left: auto; color: var(--accent-red)"></i></button></li>
                            <?php
                        endif; ?>
                        <hr>
                    </ul>

                    <h3>Usuario</h3>
                    <ul>
                        <li><button class="menu-btn" data-target-view="analysis"><i class="ph ph-chart-line"></i>
                                Analíticas</button></li>
                        <li><button class="menu-btn" data-target-view="notifications"><i class="ph ph-bell"></i>
                                Notificaciones</button></li>
                        <li><button class="menu-btn" data-target-view="payments"><i class="ph ph-wallet"></i> Métodos de
                                Pago</button></li>
                    </ul>
                </nav>
            </aside>

            <main class="dashboard-main">
                <div id="view-db" class="dashboard-view">
                    <div class="table-container">
                        <div class="table-header">
                            <div
                                style="display: flex; align-items: center; gap: 10px; height: 100%; min-width: 0; overflow: hidden;">
                                <h2 id="table-title" style="margin: 0; line-height: 1;">Cargando...
                                </h2>
                                <button id="refresh-table-btn" class="btn btn-secondary"
                                    title="Recargar y actualizar datos"
                                    style="padding: 4px 8px; display: flex; align-items: center; justify-content: center; border-radius: 6px; flex-shrink: 0; width: 32px; height: 32px;">
                                    <i class="ph-bold ph-arrows-clockwise"
                                        style="font-size: 1.3rem; line-height: 1;"></i>
                                </button>
                            </div>

                            <div class="table-controls">
                                <button id="send-report-btn" class="btn hidden"
                                    title="Enviar reporte de reposición (Stock Crítico) al correo o WhatsApp">
                                    <i class="ph ph-paper-plane-right"
                                        style="font-size: 1.4rem; font-weight: bold;"></i>
                                </button>

                                <button id="critical-filter-btn" class="btn hidden"
                                    title="Mostrar solo productos con stock bajo o pérdidas">
                                    <i class="ph ph-warning-circle" style="font-size: 1.4rem; font-weight: bold;"></i>
                                </button>

                                <div class="search-wrapper">

                                    <!-- Honey-pot para engañar al autocompletado de Opera/Chrome -->
                                    <input type="text" style="display:none" aria-hidden="true">
                                    <input type="password" style="display:none" aria-hidden="true">
                                    <input type="search" id="search-input" name="q_internal" placeholder="Buscar en la tabla..." spellcheck="false">

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

                                <button id="manage-columns-btn" class="btn btn-secondary"
                                    title="Ocultar o mostrar columnas" onclick="window.openColumnManager()">
                                    <i class="ph ph-eye" style="font-size: 1.2rem; font-weight: bold;"></i>
                                </button>



                                <button id="open-export-modal-btn" class="btn btn-secondary"
                                    style="display: flex; align-items: center; gap: 8px;" title="Exportar a Excel"
                                    onclick="window.openExportModal()">
                                    <i class="ph ph-export"></i> Exportar
                                </button>

                                <button id="open-import-modal-btn" class="btn btn-secondary"
                                    style="display: flex; align-items: center; gap: 8px;">
                                    <i class="ph ph-download-simple"></i> Importar Datos
                                </button>



                                <button id="add-row-btn" class="btn btn-primary" style="width: auto; margin-top: 0;">+
                                    Añadir Fila</button>
                            </div>

                        </div>

                        <div class="table-wrapper">
                            <table id="data-table">
                                <thead></thead>
                                <tbody>
                                    <tr>
                                        <td colspan="100%">Cargando datos...</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="config-db" class="dashboard-view hidden">
                    <h2><i class="ph ph-gear"></i> Configuración de Inventario</h2>
                    <p>Personalizá cómo StockiFy entiende y procesa tus datos.</p>

                    <div class="accordion" style="margin-top: 2rem;">

                        <div class="accordion-item">
                            <button class="accordion-header" aria-expanded="false">
                                <span><i class="ph-bold ph-magnifying-glass"></i> Identificación de Columnas</span>
                                <i class="ph ph-caret-down"></i>
                            </button>
                            <div class="accordion-content" style="max-height: 0px; overflow: hidden;">
                                <p style="font-size: 0.9rem; color: #666; margin-bottom: 1rem;">
                                    Seleccioná qué columna de tu tabla corresponde a cada dato. Esto permite que el
                                    sistema calcule ganancias y registre ventas correctamente.
                                </p>

                                <form id="mapping-columns-form"
                                    style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                    <div class="form-group">
                                        <label class="micro-label">Columna NOMBRE / PRODUCTO</label>
                                        <select id="map-name-col" class="rustic-select">
                                            <option value="">-- Sin asignar --</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="micro-label">Columna STOCK ACTUAL</label>
                                        <select id="map-stock-col" class="rustic-select">
                                            <option value="">-- Sin asignar --</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="micro-label">Columna PRECIO COMPRA (Costo)</label>
                                        <select id="map-buy-price-col" class="rustic-select">
                                            <option value="">-- Sin asignar --</option>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label class="micro-label">Columna PRECIO VENTA</label>
                                        <select id="map-sale-price-col" class="rustic-select">
                                            <option value="">-- Sin asignar --</option>
                                        </select>
                                    </div>

                                    <div style="grid-column: 1 / -1; text-align: right; margin-top: 10px;">
                                        <button type="submit" class="btn btn-primary" id="save-mapping-btn">Guardar
                                            Referencias</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <button class="accordion-header" aria-expanded="false">
                                <span><i class="ph-bold ph-lightning"></i> Funcionalidades Extra</span>
                                <i class="ph ph-caret-down"></i>
                            </button>
                            <div class="accordion-content">
                                <form id="automated-features-form">
                                    <div class="config-list">

                                        <div class="rustic-block" style="margin-bottom: 1.5rem;">
                                            <div class="block-header"
                                                style="display: flex; align-items: center; gap: 10px; margin-bottom: 0.5rem;">
                                                <input type="checkbox" id="feature-min-stock" style="margin: 0;">
                                                <span id="option-label-CSM" class="option-label"
                                                    style="font-weight: bold; font-size: 1.1rem; color: var(--accent-color);">Control
                                                    de Stock Mínimo</span>
                                            </div>

                                            <div class="reveal-wrapper" id="wrap-min-stock">
                                                <div class="reveal-inner" style="padding-left: 25px;">
                                                    <p
                                                        style="font-size: 0.9rem; color: #666; margin-top: 0; margin-bottom: 1rem; line-height: 1.4;">
                                                        Crea una columna <b>"Stock Mínimo"</b>. Si tu stock real baja de
                                                        este número, recibirás una alerta visual en la tabla.
                                                    </p>

                                                    <div style="border-top: 1px dashed #ccc; padding-top: 15px;">
                                                        <label class="micro-label"
                                                            style="color: var(--accent-color); display:block; margin-bottom: 8px;">Importar
                                                            datos iniciales</label>
                                                        <p style="font-size: 0.85rem; color:#888; margin-bottom: 10px;">
                                                            ¿Ya tenés una columna con estos valores? Copialos aquí:</p>

                                                        <div class="flex-row" style="gap: 0; align-items: center;">
                                                            <select id="import-min-stock-source" class="rustic-select"
                                                                style="flex-grow: 1; height: 38px; border-top-right-radius: 0; border-bottom-right-radius: 0;">
                                                                <option value="">-- Elegir columna origen --</option>
                                                            </select>
                                                            <button type="button" id="btn-import-min-stock"
                                                                class="btn btn-secondary btn-sm"
                                                                style="height: 38px; margin: 0; border-top-left-radius: 0; border-bottom-left-radius: 0; border-left: 0;">Importar</button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="rustic-block">
                                            <div class="block-header"
                                                style="display: flex; align-items: center; gap: 10px; margin-bottom: 0.5rem;">
                                                <input type="checkbox" id="feature-gain" style="margin: 0;">
                                                <span id="option-label-CMG" class="option-label"
                                                    style="font-weight: bold; font-size: 1.1rem; color: var(--accent-color);">Cálculo
                                                    de Margen de Ganancia</span>
                                            </div>

                                            <div class="reveal-wrapper" id="wrap-gain">
                                                <div class="reveal-inner" style="padding-left: 25px;">
                                                    <p
                                                        style="font-size: 0.9rem; color: #666; margin-top: 0; margin-bottom: 1rem; line-height: 1.4;">
                                                        Muestra automáticamente la diferencia entre tu Precio de Venta y
                                                        Compra.
                                                        <br><i style="color: var(--accent-color)">Requiere tener
                                                            mapeados ambos precios arriba.</i>
                                                    </p>

                                                    <div class="radio-inline"
                                                        style="display: flex; align-items: center; gap: 15px;">
                                                        <label class="micro-label"
                                                            style="margin-right: 5px;">Formato:</label>

                                                        <label class="modal-option"
                                                            style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                                            <input type="radio" name="gain-type" value="percent" checked
                                                                style="margin: 0;">
                                                            <span>Porcentaje (%)</span>
                                                        </label>

                                                        <label class="modal-option"
                                                            style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                                            <input type="radio" name="gain-type" value="fixed"
                                                                style="margin: 0;">
                                                            <span>Dinero ($)</span>
                                                        </label>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="rustic-block">
                                            <div class="block-header"
                                                style="display: flex; align-items: center; gap: 10px; margin-bottom: 0.5rem;">
                                                <i class="ph-bold ph-currency-dollar"
                                                    style="color: var(--accent-color); font-size: 1.2rem;"></i>
                                                <span class="option-label"
                                                    style="font-weight: bold; font-size: 1.1rem; color: var(--accent-color);">Tipo
                                                    de Cambio (USD - ARS)</span>
                                            </div>

                                            <div class="reveal-inner" style="padding-left: 25px;">
                                                <p
                                                    style="font-size: 0.9rem; color: #666; margin-top: 0; margin-bottom: 1rem; line-height: 1.4;">
                                                    Definí cómo StockiFy calculará el valor de la divisa en todas tus
                                                    operaciones.
                                                </p>

                                                <div class="radio-inline"
                                                    style="display: flex; align-items: flex-start; flex-direction: column; gap: 10px; margin-bottom: 10px;">
                                                    <label class="modal-option"
                                                        style="margin-bottom: 0; padding-bottom: 0; display: flex; align-items: center; gap: 6px; cursor: pointer;">
                                                        <input type="radio" name="exchange-type" value="api" checked
                                                            style="margin: 0;">
                                                        <span>Automático (API Dólar)</span>
                                                    </label>

                                                    <div id="exchange-api-options"
                                                        style="margin-bottom: 15px; margin-top: 0; padding-top: 0; margin-left: 20px;">
                                                        <div
                                                            style="display: flex; align-items: stretch; gap: 10px; height: 44px;">
                                                            <select id="exchange-api-source" class="rustic-select"
                                                                style="max-width: 200px; margin: 0; height: 100%; box-sizing: border-box;">
                                                                <option value="blue">Dólar Blue</option>
                                                                <option value="oficial">Dólar Oficial</option>
                                                                <option value="bolsa">Dólar MEP (Bolsa)</option>
                                                                <option value="cripto">Dólar Cripto</option>
                                                                <option value="mayorista">Dólar Mayorista</option>
                                                            </select>
                                                            <span id="exchange-api-live-value"
                                                                style="display: flex; align-items: center; justify-content: center; height: 100%; box-sizing: border-box; font-weight: bold; font-size: 1.1rem; color: var(--accent-green); transition: color 0.3s; padding: 0 16px; background: var(--accent-green-20); border-radius: 4px; border: 1px solid var(--accent-green);">$
                                                                ...</span>
                                                        </div>
                                                        <div
                                                            style="font-size: 0.8rem; color: #888; margin-top: 8px; display: flex; align-items: center; gap: 8px;">
                                                            <i class="ph-bold ph-info"
                                                                style="color: var(--accent-color);"></i> El valor se
                                                            actualiza automáticamente cada 1 hora.
                                                            <button type="button" id="exchange-api-force-refresh"
                                                                style="background: none; border: 1px solid #ddd; border-radius: 4px; padding: 3px 8px; font-size: 0.75rem; cursor: pointer; display: flex; align-items: center; gap: 4px; transition: all 0.2s;">
                                                                <i class="ph-bold ph-arrows-clockwise"></i> Recargar
                                                            </button>
                                                        </div>
                                                    </div>

                                                    <label class="modal-option"
                                                        style="display: flex; align-items: center; gap: 6px; cursor: pointer; margin-top: 10px;">
                                                        <input type="radio" name="exchange-type" value="manual"
                                                            style="margin: 0;">
                                                        <span>Manual (Fijo)</span>
                                                    </label>

                                                    <div id="exchange-manual-options"
                                                        style="margin-top: 5px; margin-left: 20px; display: none; align-items: center; gap: 8px;">
                                                        <span style="font-weight: bold; color: #555;">1 USD =</span>
                                                        <div style="display: flex; align-items: center; gap: 4px;">
                                                            <span
                                                                style="font-weight: bold; color: #555; font-size: 1.1rem;">$</span>
                                                            <input type="number" id="exchange-manual-rate"
                                                                class="rustic-input" placeholder="Ej: 1250" step="0.01"
                                                                min="1"
                                                                style="max-width: 120px; display: inline-block; margin: 0; height: 38px; box-sizing: border-box;">
                                                        </div>
                                                        <span style="font-weight: bold; color: #555;">ARS</span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                    </div>
                                    <div class="form-footer" style="margin-top: 2rem; text-align: right;">
                                        <button type="submit" class="btn btn-primary" id="save-features-btn">Aplicar
                                            Cambios</button>
                                    </div>
                                </form>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <button class="accordion-header" aria-expanded="false">
                                <span><i class="ph-bold ph-columns"></i> Gestionar Columnas Manuales</span>
                                <i class="ph ph-caret-down"></i>
                            </button>
                            <div class="accordion-content">
                                <form id="add-column-form" class="form-inline">
                                    <div class="form-group" style="flex-grow: 1;">
                                        <label for="new-column-name">Nombre de la Nueva Columna</label>
                                        <input type="text" id="new-column-name"
                                            placeholder="Ej: Ubicación, Talle, Color..." required>
                                    </div>
                                    <button type="submit" class="btn btn-primary"
                                        style="height: 44px; margin: 0; padding: 0 16px; align-self: flex-end;">Añadir</button>
                                </form>

                                <h4 style="margin-top: 1.5rem;">Mis Columnas</h4>
                                <div id="column-list-container"></div>
                                <p id="column-list-status">Cargando columnas...</p>
                            </div>
                        </div>

                        <div class="accordion-item" style="border:2px solid var(--accent-red);">
                            <button class="accordion-header" aria-expanded="false">
                                <span style="color: var(--accent-red)">Zona de Peligro</span>
                                <i class="ph ph-caret-down"></i>
                            </button>
                            <div class="accordion-content">
                                <p style="color: var(--accent-red); margin-bottom: 1rem;">
                                    Esta acción borrará permanentemente el inventario y sus registros.
                                </p>
                                <button id="delete-db-btn" class="btn btn-danger">Eliminar Inventario</button>
                            </div>
                        </div>

                    </div>
                </div>

                <div id="analysis" class="dashboard-view hidden">
                </div>

                <div id="notifications" class="dashboard-view hidden">
                    <h2><i class="ph ph-bell"></i> Notificaciones</h2>
                    <p>Gestioná tus avisos y recordatorios del inventario actual.</p>

                    <div class="accordion" style="margin-top: 2rem;">
                        <div class="accordion-item">
                            <button class="accordion-header" aria-expanded="false">
                                <span><i class="ph ph-note"></i> Creador de Notas Rápidas</span>
                                <i class="ph ph-caret-down"></i>
                            </button>
                            <div class="accordion-content">
                                <form id="create-note-form" style="display: grid; gap: 1rem;">
                                    <div class="flex-row" style="gap: 1rem;">
                                        <select id="note-type" class="rustic-select" style="flex: 1;">
                                            <option value="info">Azul (Nota)</option>
                                            <option value="success">Verde (Éxito)</option>
                                            <option value="warning">Amarillo (Aviso)</option>
                                            <option value="error">Rojo (Urgente)</option>
                                            <option value="system">Violeta (Sistema)</option>
                                        </select>
                                        <input type="text" id="note-title" placeholder="Título de la nota..."
                                            style="flex: 2;" required>
                                    </div>
                                    <textarea id="note-message" placeholder="Escribí tu mensaje aquí..."
                                        style="min-height: 80px;"></textarea>
                                    <button type="submit" class="btn btn-primary" style="width: auto;">Guardar
                                        Nota</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div id="notifications-list" style="margin-top: 2rem;">
                        <p>Cargando notificaciones...</p>
                    </div>
                </div>

                <div id="sales" class="dashboard-view hidden">
                </div>

                <div id="receipts" class="dashboard-view hidden">
                </div>

                <div id="customers" class="dashboard-view hidden">
                </div>

                <div id="providers" class="dashboard-view hidden">
                </div>

                <div id="employees" class="dashboard-view hidden">
                </div>

                <div id="payments" class="dashboard-view hidden">
                </div>

            </main>
        </div>
    </div>

    <div class="mobile-app-view hidden">

        <div class="mobile-actions-grid">

            <div class="mobile-card action-sale" onclick="window.openMobileTransaction('sale')">
                <div class="icon-circle"><i class="ph-bold ph-shopping-cart"></i></div>
                <h3>Nueva Venta</h3>
                <p>Registrar salida</p>
            </div>

            <div class="mobile-card action-purchase" onclick="window.openMobileTransaction('purchase')">
                <div class="icon-circle"><i class="ph-bold ph-package"></i></div>
                <h3>Nueva Compra</h3>
                <p>Registrar entrada</p>
            </div>

            <div class="mobile-card action-check" onclick="window.openMobilePriceChecker()">
                <div class="icon-circle"><i class="ph-bold ph-barcode"></i></div>
                <h3>Consultar</h3>
                <p>Ver Precio/Stock</p>
            </div>

            <div class="mobile-card action-balance" onclick="window.openCashBalance()">
                <div class="icon-circle"><i class="ph-bold ph-currency-dollar"></i></div>
                <h3>Cierre de Caja</h3>
                <p>Balance del día</p>
            </div>
        </div>

        <div class="mobile-quick-stats">
        </div>
    </div>

    <div id="mobile-balance-modal" class="mobile-modal-overlay hidden">
        <div class="mobile-modal-content">
            <div class="mobile-modal-header">
                <h2>Balance & Caja</h2>
                <button class="close-icon" onclick="window.closeCashBalance()">&times;</button>
            </div>

            <div class="balance-body">
                <div class="period-selector">
                    <button id="btn-period-today" class="period-btn active"
                        onclick="window.loadBalanceData('today')">Hoy</button>
                    <button id="btn-period-month" class="period-btn"
                        onclick="window.loadBalanceData('month')">Mes</button>
                    <button id="btn-period-year" class="period-btn"
                        onclick="window.loadBalanceData('year')">Año</button>
                </div>

                <div class="balance-main-card">
                    <p id="balance-date-label">Resumen de Hoy</p>
                    <h1 id="balance-total">$0.00</h1>
                    <span class="badge-status">Balance Neto</span>
                </div>

                <div class="balance-details">
                    <div class="detail-row income">
                        <div class="icon"><i class="ph-fill ph-arrow-down-left"></i></div>
                        <div class="info">
                            <span>Ingresos (Ventas)</span>
                            <h4 id="balance-income">$0.00</h4>
                        </div>
                    </div>
                    <div class="detail-row expense">
                        <div class="icon"><i class="ph-fill ph-arrow-up-right"></i></div>
                        <div class="info">
                            <span>Egresos (Compras)</span>
                            <h4 id="balance-expense">$0.00</h4>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Export Modal -->
    <div id="export-modal" class="modal-overlay hidden" style="z-index: 2000;">
        <div class="modal-content" style="max-width: 500px; padding: 30px; border-radius: 12px;">
            <div class="modal-header" style="margin-bottom: 20px;">
                <h2 style="margin: 0;">Exportar a Excel</h2>
                <button class="modal-close-btn" onclick="window.closeExportModal()">&times;</button>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 25px; font-size: 0.95rem; color: var(--color-gray); line-height: 1.5;">
                    Seleccioná qué hojas querés generar en tu reporte final:</p>

                <div style="display: flex; flex-direction: column; gap: 18px;">
                    <label
                        style="display: flex; align-items: flex-start; gap: 15px; cursor: pointer; padding: 18px; background: #fafafa; border-radius: 10px; border: 1px solid #e2e8f0; transition: border-color 0.2s;">
                        <input type="checkbox" id="export-chk-inventory" checked
                            style="width: 22px; height: 22px; margin-top: 2px; accent-color: var(--accent-color);">
                        <div style="display: flex; flex-direction: column; gap: 4px;">
                            <strong style="color: var(--color-black); font-size: 1rem;">Inventario Actual</strong>
                            <span style="font-size: 0.85rem; color: var(--color-gray); line-height: 1.4;">Exporta el
                                estado completo de stock, precios y categorías.</span>
                        </div>
                    </label>

                    <label
                        style="display: flex; align-items: flex-start; gap: 15px; cursor: pointer; padding: 18px; background: #fafafa; border-radius: 10px; border: 1px solid #e2e8f0; transition: border-color 0.2s;">
                        <input type="checkbox" id="export-chk-sales" checked
                            style="width: 22px; height: 22px; margin-top: 2px; accent-color: var(--accent-color);">
                        <div style="display: flex; flex-direction: column; gap: 4px;">
                            <strong style="color: var(--color-black); font-size: 1rem;">Historial de Ventas</strong>
                            <span style="font-size: 0.85rem; color: var(--color-gray); line-height: 1.4;">Crea una hoja
                                ideal para proyecciones de Flujo de Caja.</span>
                        </div>
                    </label>

                    <label
                        style="display: flex; align-items: flex-start; gap: 15px; cursor: pointer; padding: 18px; background: #fafafa; border-radius: 10px; border: 1px solid #e2e8f0; transition: border-color 0.2s;">
                        <input type="checkbox" id="export-chk-analytics" checked
                            style="width: 22px; height: 22px; margin-top: 2px; accent-color: var(--accent-color);">
                        <div style="display: flex; flex-direction: column; gap: 4px;">
                            <strong style="color: var(--color-black); font-size: 1rem;">Métricas (Top
                                Rendimiento)</strong>
                            <span style="font-size: 0.85rem; color: var(--color-gray); line-height: 1.4;">Listados
                                estáticos con mejores clientes y productos más vendidos.</span>
                        </div>
                    </label>
                </div>

                <div id="export-status"
                    style="margin-top: 20px; color: var(--accent-color); font-weight: 500; font-size: 0.95rem; min-height: 20px;">
                </div>
            </div>
            <div class="modal-footer"
                style="margin-top: 30px; display: flex; justify-content: flex-end; gap: 12px; border-top: 1px solid #eee; padding-top: 20px;">
                <button class="btn btn-secondary" onclick="window.closeExportModal()"
                    style="margin: 0; padding: 10px 20px;">Cancelar</button>
                <button class="btn btn-primary" id="btn-run-export" onclick="window.runExport()"
                    style="margin: 0; padding: 10px 24px;">Generar Excel</button>
            </div>
        </div>
    </div>

    <!-- Import Modal -->
    <div id="import-modal" class="modal-overlay hidden">
        <div class="modal-content view-container import-modal"> <button id="close-modal-btn"
                class="modal-close-btn">&times;</button>

            <div class="modal-header">
                <h2>Importar Datos</h2>
                <div class="import-tabs">
                    <button class="import-tab active" data-tab="csv">Archivo CSV</button>
                    <button class="import-tab" data-tab="tiendanube">TiendaNube</button>
                </div>
            </div>

            <div class="modal-body">
                <div id="import-section-csv">
                    <p>Selecciona o arrastra tu archivo CSV.</p>
                    <div id="import-step-1">
                        <div id="import-drop-zone" class="drop-zone">
                            <p>Arrastra tu archivo CSV acá o hacé clic para seleccionar</p>
                            <input type="file" id="csv-file-input" accept=".csv" style="display: none;">
                        </div>
                        <div id="import-status" style="margin-top: 1rem;"></div>
                    </div>

                    <div class="import-overwrite-section">
                        <label class="import-overwrite-box" for="import-overwrite-toggle">
                            <input type="checkbox" id="import-overwrite-toggle" />
                            <div class="import-overwrite-text">
                                <div class="import-overwrite-title">
                                    <strong>Sobre-escribir datos actuales</strong>
                                    <span class="import-overwrite-badge">Borra y reemplaza</span>
                                </div>
                                <p>Si está activado, se elimina lo existente y queda solo lo importado del CSV.</p>
                            </div>
                        </label>
                    </div>

                    <div id="import-step-2" class="hidden">
                        <h3>Mapeá las Columnas</h3>
                        <p>Asigná las columnas de tu archivo a las de StockiFy.</p>
                        <form id="mapping-form" class="import-mapping-form"
                            style="max-height: 40vh; overflow-y: auto; padding-right: 10px;"></form>
                    </div>
                </div>

                <div id="import-section-tiendanube" class="hidden">
                    <div id="tn-connection-status" class="tn-status-container" style="padding: 20px; text-align: center;">
                        <p><i class="ph ph-spinner ph-spin"></i> Verificando conexión con TiendaNube...</p>
                    </div>
                    
                    <div id="tn-config-step" class="hidden">
                        <p style="margin-bottom: 15px;">Mapeá las columnas de tu inventario con los datos de tu tienda.</p>
                        <div id="tn-mapping-form" class="import-mapping-form" style="max-height: 40vh; overflow-y: auto; padding-right: 10px;"></div>
                    </div>
                </div>

            <div class="modal-footer">
                <button id="import-cancel-btn" class="btn btn-secondary">Cancelar</button>
                <button id="validate-prepare-btn" class="btn btn-primary hidden">Validar y Preparar Datos</button>
                <button id="tn-import-btn" class="btn btn-primary hidden">Sincronizar con TiendaNube</button>
            </div>
        </div>
    </div>

    <div id="delete-confirm-modal" class="modal-overlay hidden">
        <div class="modal-content view-container" style="max-width: 520px;">
            <button id="close-delete-modal-btn" class="modal-close-btn">&times;</button>

            <div class="modal-header">
                <h2 style="color: var(--accent-red);"><i class="ph ph-warning-octagon"></i> Eliminar Inventario</h2>
                <p>Esta acción <strong>no se puede deshacer</strong>. Se borrará permanentemente
                    "<strong id="delete-db-name-confirm"></strong>" y todos sus datos.</p>
            </div>

            <div class="modal-body">

                <!-- ── Step 1: Nombre del inventario ── -->
                <div id="delete-step-1">
                    <p class="delete-step-label"><span class="delete-step-badge">1</span> Escribí el nombre exacto del
                        inventario para continuar:</p>
                    <input type="text" id="delete-confirm-input" placeholder="Nombre del Inventario" autocomplete="off">
                    <div id="delete-error-message"
                        style="color: var(--accent-red); font-weight: 600; margin-top: 8px; min-height: 20px;"></div>
                </div>

                <!-- ── Step 2: Verificación de identidad (oculto hasta que step 1 pase) ── -->
                <div id="delete-step-2" class="hidden">
                    <div class="delete-step-divider"></div>

                    <!-- Para usuarios Google: solo OTP -->
                    <div id="delete-auth-google" class="hidden">
                        <p class="delete-step-label"><span class="delete-step-badge">2</span> Verificación de identidad
                            — enviamos un código a tu correo.</p>
                        <p id="delete-email-hint" class="delete-email-hint"></p>
                        <div class="delete-otp-row">
                            <button id="delete-send-otp-btn" class="btn btn-secondary delete-send-otp-btn">
                                <i class="ph ph-paper-plane-tilt"></i> Enviar código
                            </button>
                            <span id="delete-otp-countdown" class="delete-otp-countdown hidden"></span>
                        </div>
                        <input type="text" id="delete-otp-input" placeholder="Código de 6 dígitos" maxlength="6"
                            inputmode="numeric" pattern="\d{6}" autocomplete="one-time-code" class="hidden"
                            style="letter-spacing: 6px; font-size: 1.3rem; text-align: center;">
                        <div id="delete-otp-status" class="delete-otp-status hidden"></div>
                    </div>

                    <!-- Para usuarios con contraseña: contraseña + OTP -->
                    <div id="delete-auth-password" class="hidden">
                        <p class="delete-step-label"><span class="delete-step-badge">2</span> Verificá tu identidad para
                            continuar.</p>

                        <!-- Sub-step 2a: contraseña -->
                        <div id="delete-password-section">
                            <label class="micro-label" style="margin-bottom: 6px; display: block;">Tu contraseña de
                                acceso:</label>
                            <div style="position: relative;">
                                <input type="password" id="delete-password-input" placeholder="Contraseña"
                                    autocomplete="current-password" style="padding-right: 44px;">
                                <button type="button" id="toggle-delete-password"
                                    style="position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; cursor:pointer; color:#888; font-size:1.2rem; padding:0;">
                                    <i class="ph ph-eye"></i>
                                </button>
                            </div>
                            <div id="delete-password-error"
                                style="color: var(--accent-red); font-weight: 600; margin-top: 6px; min-height: 18px; font-size: 0.9rem;">
                            </div>
                            <button id="delete-verify-password-btn" class="btn btn-secondary"
                                style="margin-top: 10px; width: 100%;" disabled>
                                Verificar contraseña
                            </button>
                        </div>

                        <!-- Sub-step 2b: OTP (se muestra tras verificar contraseña) -->
                        <div id="delete-otp-section" class="hidden" style="margin-top: 16px;">
                            <div class="delete-step-divider" style="margin-bottom: 16px;"></div>
                            <p class="delete-step-label"><span class="delete-step-badge">3</span> Código de verificación
                                al correo:</p>
                            <p id="delete-email-hint-pass" class="delete-email-hint"></p>
                            <div class="delete-otp-row">
                                <button id="delete-send-otp-btn-pass" class="btn btn-secondary delete-send-otp-btn">
                                    <i class="ph ph-paper-plane-tilt"></i> Enviar código
                                </button>
                                <span id="delete-otp-countdown-pass" class="delete-otp-countdown hidden"></span>
                            </div>
                            <input type="text" id="delete-otp-input-pass" placeholder="Código de 6 dígitos"
                                maxlength="6" inputmode="numeric" pattern="\d{6}" autocomplete="one-time-code"
                                class="hidden" style="letter-spacing: 6px; font-size: 1.3rem; text-align: center;">
                            <div id="delete-otp-status-pass" class="delete-otp-status hidden"></div>
                        </div>
                    </div>

                </div>

            </div>

            <div class="modal-footer">
                <button id="cancel-delete-btn" class="btn btn-secondary">Cancelar</button>
                <button id="confirm-delete-btn" class="btn btn-danger" disabled>
                    <i class="ph ph-trash"></i> Eliminar Permanentemente
                </button>
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

    <div id="sale-modal-v2" class="modal-overlay hidden">
        <div class="modal-content view-container" style="width: 900px; max-width: 95vw;">
            <button class="modal-close-btn" id="close-sale-v2">&times;</button>

            <div class="modal-header">
                <h2><i class="ph ph-shopping-cart"></i> Nueva Venta</h2>
                <p>Selecciona productos y asigna un cliente.</p>
            </div>

            <div class="modal-body" style="display: flex; flex-direction: column; gap: 1.5rem;">

                <div class="rustic-block" style="padding: 1rem;">
                    <div class="flex-row align-center justify-between">
                        <div class="flex-row align-center" style="gap: 10px;">
                            <i class="ph ph-user" style="font-size: 1.5rem;"></i>
                            <div class="flex-column">
                                <span class="micro-label">Cliente</span>
                                <h3 id="v2-client-name" style="margin:0;">Consumidor Final</h3>
                                <input type="hidden" id="v2-client-id">
                            </div>
                        </div>
                        <select id="v2-client-select" class="rustic-select" style="width: auto; min-width: 200px;">
                            <option value="">Consumidor Final</option>
                        </select>
                    </div>
                </div>

                <div class="flex-row" style="gap: 10px;">
                    <div class="search-wrapper" style="height: 44px;">
                        <input type="text" style="display:none" aria-hidden="true">
                        <input type="search" id="v2-product-search" name="p_search" placeholder="Buscar producto para agregar..." spellcheck="false">
                        <div id="v2-search-results" class="search-dropdown hidden"
                            style="max-height: 300px; overflow-y: auto;"></div>
                    </div>
                </div>

                <div class="table-wrapper" style="height: 300px; min-height: 300px;">
                    <table id="v2-cart-table">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th style="width: 100px; text-align: center;">Cant.</th>
                                <th style="width: 120px; text-align: right;">Precio Unit.</th>
                                <th style="width: 120px; text-align: right;">Subtotal</th>
                                <th style="width: 60px;"></th>
                            </tr>
                        </thead>
                        <tbody id="v2-cart-body">
                        </tbody>
                    </table>
                </div>

                <div class="flex-row justify-between align-center"
                    style="background: #f4f4f4; padding: 1rem; border-radius: 8px; border: 2px solid #1b1b1b;">
                    <h3 style="margin:0;">Total a Cobrar:</h3>
                    <h1 style="margin:0; font-size: 2.5rem;" id="v2-cart-total">$0.00</h1>
                </div>

            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" id="cancel-sale-v2">Cancelar</button>
                <button class="btn btn-primary" id="confirm-sale-v2" disabled>Confirmar Venta</button>
            </div>
        </div>
    </div>

    <div id="sale-details-modal" class="modal-overlay hidden" style="z-index: 2000;">
        <div class="modal-content view-container" style="width: 500px; max-width: 90vw;">
            <div class="modal-header">
                <h2>Detalle de Transacción</h2>
                <button class="modal-close-btn" id="close-details-modal">&times;</button>
            </div>
            <div class="modal-body" id="sale-details-content">
            </div>
        </div>
    </div>

    <div id="restore-actions-tab" class="restore-tab" onclick="window.toggleActionsColumn()" title="Mostrar Acciones">
        <i class="ph ph-caret-left"></i> Acciones
    </div>

    <div id="column-manager-modal" class="modal-overlay hidden" style="z-index: 2000;">
        <div class="modal-content" style="max-width: 800px;">
            <div class="modal-header">
                <h2>Gestionar Columnas</h2>
                <button class="modal-close-btn" onclick="window.closeColumnManager()">&times;</button>
            </div>

            <div class="modal-body">
                <div id="column-manager-list" class="column-manager-list"></div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="window.closeColumnManager()">Cancelar</button>
                <button class="btn btn-primary" onclick="window.saveColumnPreferences()">
                    Guardar
                </button>
            </div>
        </div>
    </div>

    <div id="mobile-price-checker-modal" class="mobile-modal-overlay hidden">
        <div class="mobile-modal-content" style="height: 60vh;">
            <div class="mobile-modal-header">
                <h2>Consultar Precio</h2>
                <button class="close-icon" onclick="window.closePriceChecker()">&times;</button>
            </div>

            <div id="checker-body" class="checker-body" style="padding: 0 20px; border-bottom: 2px solid #1b1b1b;">
                <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                    <input type="text" id="checker-input" placeholder="Buscar producto o escanear..."
                        style="width: 100%; padding: 15px; border: 2px solid #1b1b1b; border-radius: 12px; font-size: 1.1rem;">
                    <button onclick="window.performPriceCheck()"
                        style="background: var(--color-white); color: var(--color-black); border: 3px solid #1b1b1b; border-radius: 12px; width: 60px; font-size: 1.5rem;">
                        <i class="ph-bold ph-magnifying-glass"></i>
                    </button>
                </div>

                <div id="checker-list-container" style="max-height: 330px; overflow-y: auto; display: none;"></div>

                <div id="checker-result" class="hidden"
                    style="text-align: center; border: 2px dashed #ccc; padding: 20px; border-radius: 12px;">
                    <h3 id="res-name" style="margin: 0 0 10px 0; font-size: 1.2rem; color: #666;">Nombre del Producto
                    </h3>
                    <small>Precio de Venta</small>
                    <h1 id="res-price"
                        style="margin: 10px 0; margin-top: 0; font-size: 2.5rem; color: var(--accent-green);">$0.00</h1>

                    <div style="display: flex; justify-content: center; gap: 20px; margin-top: 15px;">
                        <div style="background: #f0f0f0; padding: 10px; border-radius: 8px;">
                            <small>Stock</small>
                            <div id="res-stock" style="font-weight: bold; font-size: 1.2rem;">0</div>
                        </div>
                        <div style="background: #f0f0f0; padding: 10px; border-radius: 8px;">
                            <small>Precio de Compra</small>
                            <div id="res-cost" style="font-weight: bold; font-size: 1.2rem;">$0.00</div>
                        </div>
                    </div>
                </div>

                <div id="checker-error" class="hidden"
                    style="text-align: center; color: var(--accent-red); margin-top: 20px; font-weight: bold;">
                    Producto no encontrado.
                </div>
            </div>
        </div>
    </div>


    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>

    <script type="module" src="assets/js/import.js?v=1.2"></script>
    <script type="module" src="assets/js/export-excel.js?v=1.3"></script>
    <script type="module" src="assets/js/dashboard.js?v=1.4"></script>
    <script type="module" src="assets/js/sales/sales.js?v=1.2"></script>
    <script type="module" src="assets/js/payment/payment.js?v=1.2"></script>

    <script type="module">
        import { pop_ups } from './assets/js/notifications/pop-up.js';
        window.showLockedFeatureToast = (featureName) => {
            pop_ups.system(`Funcionabilidad no incluída en su versión de pago Básico: ${featureName}`, 'Acceso Restringido');
        };
    </script>

    <script type="module">
        import { initMobileApp } from './assets/js/mobile/mobile-app.js';
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                initMobileApp();
            }, 100);
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
</body>

</html>