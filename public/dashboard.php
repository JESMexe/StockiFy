<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../src/helpers/auth_helper.php';
require_once __DIR__ . '/../src/Services/Payments/PricingService.php';

use App\core\Database;
use App\Services\Payments\PricingService;

$currentUser = getCurrentUser();

if (!$currentUser) {
    header('Location: login');
    exit;
}

if (!isset($currentUser['subscription_active']) || $currentUser['subscription_active'] == 0) {
    header('Location: index#section-pricing');
    exit; // Immediately kills server execution. Zero bytes sent to browser.
}

// Get active inventory name
$activeInventoryName = "Cargando...";
try {
    $pdo = Database::getInstance();

    if (isset($_SESSION['active_inventory_id'])) {
        $stmt = $pdo->prepare("SELECT name FROM inventories WHERE id = ?");
        $stmt->execute([$_SESSION['active_inventory_id']]);
        $inv = $stmt->fetch();
        if ($inv) {
            $activeInventoryName = htmlspecialchars($inv['name']);
        } else {
            $activeInventoryName = "Inventario Desconocido";
        }
    } else {
        $stmt = $pdo->prepare("SELECT name FROM inventories WHERE user_id = ? LIMIT 1");
        $stmt->execute([$currentUser['id']]);
        $inv = $stmt->fetch();
        if ($inv) {
            $activeInventoryName = htmlspecialchars($inv['name']);
        } else {
            $activeInventoryName = "Sin Inventarios";
        }
    }
} catch (Exception $e) {
    $activeInventoryName = "Error BD";
}

// Determinar rol del usuario en el inventario activo (para condicionales PHP en el template)
$activeInventoryId = (int) ($_SESSION['active_inventory_id'] ?? 0);
$currentUserRbac = ($activeInventoryId && $currentUser)
    ? getInventoryRole((int) $currentUser['id'], $activeInventoryId)
    : null;
$isOwner = $currentUserRbac && (int) $currentUserRbac['role_id'] === 1;

// Verificar acceso fuera de horario laboral de colaboradores
if ($activeInventoryId && $currentUserRbac && (int)$currentUserRbac['role_id'] !== 1) {
    if (!isset($_SESSION['outside_hours_alert_sent_' . $activeInventoryId])) {
        try {
            $stmtWork = $pdo->prepare("SELECT name, work_hours_enabled, work_hours_start, work_hours_end, user_id FROM inventories WHERE id = ?");
            $stmtWork->execute([$activeInventoryId]);
            $invRow = $stmtWork->fetch(PDO::FETCH_ASSOC);

            if ($invRow && (int)$invRow['work_hours_enabled'] === 1) {
                date_default_timezone_set('America/Argentina/Buenos_Aires');
                $now = new DateTime('now', new DateTimeZone('America/Argentina/Buenos_Aires'));
                $currentTime = $now->format('H:i:s');

                $start = $invRow['work_hours_start'] ?: '08:00:00';
                $end   = $invRow['work_hours_end'] ?: '20:00:00';

                $isOutside = false;
                if ($start <= $end) {
                    if ($currentTime < $start || $currentTime > $end) {
                        $isOutside = true;
                    }
                } else {
                    if ($currentTime > $end && $currentTime < $start) {
                        $isOutside = true;
                    }
                }

                if ($isOutside) {
                    // Evitar envíos repetidos en la sesión actual
                    $_SESSION['outside_hours_alert_sent_' . $activeInventoryId] = true;

                    // Datos del propietario
                    $ownerId = $invRow['user_id'];
                    $stmtOwner = $pdo->prepare("SELECT full_name, username, cell FROM users WHERE id = ? LIMIT 1");
                    $stmtOwner->execute([$ownerId]);
                    $ownerInfo = $stmtOwner->fetch(PDO::FETCH_ASSOC);

                    if ($ownerInfo && !empty($ownerInfo['cell'])) {
                        require_once __DIR__ . '/../src/Services/WhatsappService.php';
                        $whatsappService = new \App\Services\WhatsappService();

                        $ownerName = $ownerInfo['full_name'] ?: $ownerInfo['username'] ?: 'Propietario';
                        $collabName = $currentUser['full_name'] ?: $currentUser['username'] ?: 'Colaborador';
                        $collabEmail = $currentUser['email'];
                        $invName = $invRow['name'] ?: 'General';
                        $entryHour = $now->format('H:i') . 'h';
                        $formattedRange = substr($start, 0, 5) . 'h a ' . substr($end, 0, 5) . 'h';

                        $whatsappService->sendOutsideHoursAccessAlert(
                            $ownerInfo['cell'],
                            $ownerName,
                            $collabName,
                            $collabEmail,
                            $invName,
                            $entryHour,
                            $formattedRange
                        );

                        // Registrar actividad en el log de auditoría
                        require_once __DIR__ . '/../src/helpers/ActivityLogger.php';
                        \App\helpers\ActivityLogger::log(
                            'Seguridad',
                            'outside_hours_access',
                            'security_alert',
                            (string)$activeInventoryId,
                            "Alerta: Colaborador ingresó fuera de horario laboral.",
                            "Colaborador: {$collabName} ({$collabEmail}). Hora: {$entryHour}. Rango permitido: {$formattedRange}. Se notificó al dueño por WhatsApp.",
                            (int)$activeInventoryId,
                            (int)$currentUser['id']
                        );
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error al verificar horario laboral: " . $e->getMessage());
        }
    }
}

// El nivel de suscripción que rige las características de este inventario es el del Propietario (Owner)
$inventorySubscriptionActive = 1; // Por defecto básico
try {
    if ($activeInventoryId) {
        $ownerId = getInventoryOwnerId($activeInventoryId);
        if ($ownerId) {
            $stmtOwnerSub = $pdo->prepare("SELECT subscription_active FROM users WHERE id = ?");
            $stmtOwnerSub->execute([$ownerId]);
            $inventorySubscriptionActive = (int) $stmtOwnerSub->fetchColumn();
        }
    } else {
        $inventorySubscriptionActive = (int) ($currentUser['subscription_active'] ?? 1);
    }
} catch (Exception $e) {
    $inventorySubscriptionActive = (int) ($currentUser['subscription_active'] ?? 1);
}

// Resolver precio de slots dinámicamente
$pricingService = new PricingService();
$slotPrice = $pricingService->getSlotUnitPrice();
?>

<!DOCTYPE html>
<html lang="es" xmlns:type="http://www.w3.org/1999/xhtml">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Panel de Control - StockiFy</title>

    <link rel="stylesheet" href="/assets/css/main.css?v=1.3">
    <link rel="stylesheet" href="/assets/css/dashboard.css?v=2.2">
    <link rel="stylesheet" href="/assets/css/notifications.css?v=2.0">
    <link rel="stylesheet" href="/assets/css/employees.css?v=1.3">
    <link rel="stylesheet" href="/assets/css/purchases.css?v=2.1">
    <link rel="stylesheet" href="/assets/css/payments.css?v=1.2">
    <link rel="stylesheet" href="/assets/css/tutorials.css?v=1.0">
    <link rel="stylesheet" href="/assets/css/mobile-sheets.css?v=1.0">
    <link rel="stylesheet" href="/assets/css/sweetalert.css?v=1.1">

    <link rel="stylesheet" type="text/css"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/regular/style.css" />
    <link rel="stylesheet" type="text/css"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/bold/style.css" />
    <link rel="stylesheet" type="text/css"
        href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.1/src/fill/style.css" />

    <script src="/assets/js/theme.js"></script>
    <script>
        window.STOCKIFY_SLOT_PRICE = <?php echo (float)$slotPrice; ?>;
    </script>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/Sortable/1.15.0/Sortable.min.js"></script>
    <script src="/assets/js/sweetalert2.all.min.js?v=11.0"></script>
    <script>
        if (typeof Swal === 'undefined') {
            console.warn("SweetAlert2 local no pudo cargarse. Cargando fallback desde CDN...");
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
            document.head.appendChild(script);
        }
    </script>

    <link rel="stylesheet" href="/assets/css/analytics.css">
    <link rel="stylesheet" href="/assets/css/sales.css?v=2.1">
    <style>
        .notif-tab-btn {
            background: none !important;
            border: none !important;
            border-bottom: 3px solid transparent !important;
            padding: 8px 15px !important;
            font-weight: 700 !important;
            cursor: pointer !important;
            color: #888 !important;
            transition: all 0.2s !important;
            font-family: inherit !important;
        }

        .notif-tab-btn:hover {
            color: var(--accent-color) !important;
        }

        .notif-tab-btn.active {
            color: var(--accent-color) !important;
            border-bottom-color: var(--accent-color) !important;
        }

        /* Botones de periodo en modal de Métricas (heredan de .period-btn) */
        .metrics-period-btn {
            padding: 10px 4px !important;
            font-size: 0.85rem !important;
        }
    </style>
</head>

<body>
    <header>
        <button id="toggle-sidebar-btn" class="btn btn-secondary" style="display: none; align-items: center; justify-content: center; width: 40px; height: 40px; padding: 0; margin-right: 15px; border-radius: 8px;" title="Mostrar/Ocultar Menú">
            <i class="ph ph-list" style="font-size: 1.5rem; font-weight: bold;"></i>
        </button>
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

        <div id="invite-collaborator-modal" class="hidden"
            style="background: white; border: 2px solid #1b1b1b; border-radius: 12px; padding: 25px; max-width: 450px; width: 90%; text-align: left; position: relative;">
            <button id="close-invite-modal-btn"
                style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #1b1b1b;"><i
                    class="ph-bold ph-x"></i></button>
            <h3 style="margin-top: 0; display: flex; align-items: center; gap: 8px; color: #1b1b1b; font-size: 1.4rem;">
                <i class="ph-fill ph-envelope-simple-open" style="color: var(--accent-color);"></i> Enviar Invitación
            </h3>
            <p style="color: #666; font-size: 0.9rem; margin-bottom: 20px;">
                Ingresá el correo electrónico de la persona y asigná un rol. Recibirá un email seguro con un enlace de
                acceso único.
            </p>
            <form id="invite-collaborator-form">
                <div class="form-group" style="margin-bottom: 15px;">
                    <label class="micro-label"
                        style="display: block; margin-bottom: 5px; font-weight: bold; color: #1b1b1b;">Correo
                        Electrónico</label>
                    <input type="email" id="invite-email" class="rustic-input" placeholder="ejemplo@correo.com" required
                        style="width: 100%; box-sizing: border-box;">
                </div>
                <div class="form-group" style="margin-bottom: 25px;">
                    <label class="micro-label"
                        style="display: block; margin-bottom: 5px; font-weight: bold; color: #1b1b1b;">Rol
                        Asignado</label>
                    <select id="invite-role" class="rustic-select" required
                        style="width: 100%; box-sizing: border-box;">
                        <option value="3" selected>Empleado</option>
                        <option value="2">Administrador</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-primary" id="send-invite-submit-btn"
                    style="width: 100%; height: 48px; background: var(--accent-color); border-color: #1b1b1b; font-size: 1.1rem; display: flex; align-items: center; justify-content: center; gap: 8px;">
                    <i class="ph-bold ph-paper-plane-right"></i> Enviar Invitación
                </button>
            </form>
        </div>

        <div id="add-slots-modal" class="hidden"
            style="background: white; border: 2px solid #1b1b1b; border-radius: 12px; padding: 25px; max-width: 450px; width: 90%; text-align: left; position: relative;">
            <button id="close-add-slots-modal-btn"
                style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #1b1b1b;"><i
                    class="ph-bold ph-x"></i></button>
            <h3
                style="margin-top: 0; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; color: #1b1b1b; font-size: 1.4rem;">
                <i class="ph-bold ph-plus-circle" style="color: var(--accent-color);"></i> Agregar Slots Extra
            </h3>
            <p style="color: #666; font-size: 0.9rem; margin-bottom: 20px;">
                Sumá slots para invitar a más colaboradores de forma inmediata.
                Cada slot adicional tiene un costo de <strong>$<?php echo number_format($slotPrice, 0, ',', '.'); ?>/mes</strong>. Se registrará una deuda que deberás
                saldar en un lapso de 48 horas.
            </p>
            <form id="add-slots-form">
                <div class="form-group" style="margin-bottom: 20px;">
                    <label class="micro-label"
                        style="display: block; margin-bottom: 5px; font-weight: bold; color: #1b1b1b;">Cantidad de
                        Slots</label>
                    <input type="number" id="slots-count-input" class="rustic-input" value="1" min="1" step="1" required
                        style="width: 100%; box-sizing: border-box;">
                </div>
                <div
                    style="background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 20px; font-size: 0.85rem;">
                    <span style="color: #475569; display: block;">Resumen de Deuda:</span>
                    <strong id="slots-debt-summary"
                        style="font-size: 1.1rem; color: var(--accent-color);">$<?php echo number_format($slotPrice, 0, ',', '.'); ?></strong>
                </div>
                <button type="submit" class="btn btn-primary" id="add-slots-submit-btn"
                    style="width: 100%; height: 48px; background: var(--accent-color); border-color: #1b1b1b; font-size: 1.1rem; display: flex; align-items: center; justify-content: center; gap: 8px; color: white;">
                    <i class="ph-bold ph-check"></i> Confirmar y Agregar Slots
                </button>
            </form>
        </div>

    </div>

    <div class="desktop-app-view">
        <div class="dashboard-container">
            <aside class="dashboard-sidebar">
                <nav class="main-menu" id="sidebar-main-nav" style="visibility:hidden">
                    <h3>Inventario</h3>
                    <ul>
                        <li><button class="menu-btn active" data-target-view="view-db"><i class="ph ph-table"></i> Ver
                                Datos</button></li>
                        <li><button class="menu-btn" data-target-view="config-db"><i class="ph ph-gear"></i> Configurar
                                Tabla</button></li>
                        <li><button class="menu-btn" data-target-view="analysis"><i class="ph ph-chart-line"></i>
                                Analíticas</button></li>
                        <li><a href="select-db" class="menu-link"><i class="ph ph-database"></i> Cambiar Inventario</a>
                        </li>

                        <?php if ((int) $currentUser['subscription_active'] !== 5): ?>
                            <?php
                            $dbInstance = \App\core\Database::getInstance();
                            $stmtCount = $dbInstance->prepare("SELECT COUNT(*) FROM inventories WHERE user_id = ?");
                            $stmtCount->execute([$currentUser['id']]);
                            $invCount = $stmtCount->fetchColumn();
                            $canCreateDb = ($currentUser['subscription_active'] >= 2) || ($currentUser['subscription_active'] == 1 && $invCount == 0);
                            ?>

                            <?php if ($canCreateDb): ?>
                                <li><a href="create-db" class="menu-link"><i class="ph ph-plus-circle"></i> Crear Inventario</a>
                                </li>
                                <?php
                            else: ?>
                                <li style="opacity: 0.5;" title="Límite del Plan Básico alcanzado."><a href="#"
                                        onclick="window.showLockedFeatureToast('Múltiples Inventarios'); return false;"
                                        class="menu-link"><i class="ph ph-plus-circle"></i> Crear Inventario <i
                                            class="ph-fill ph-lock-key"
                                            style="margin-left: auto; color: var(--accent-red)"></i></a></li>
                                <?php
                            endif; ?>
                        <?php endif; ?>
                    </ul>

                    <h3>Transacciones</h3>
                    <ul>
                        <li><button class="menu-btn" data-target-view="sales"><i class="ph ph-money"></i>
                                Registrar Ingreso</button></li>
                        <li><button class="menu-btn" data-target-view="receipts"><i class="ph ph-stack"></i>
                                Registrar Egreso</button></li>
                    </ul>

                    <h3>Directorio</h3>
                    <ul>
                        <?php if ($inventorySubscriptionActive >= 2): ?>
                            <li><button class="menu-btn" data-target-view="customers"><i class="ph ph-user-focus"></i>
                                    Clientes</button></li>
                            <li><button class="menu-btn" data-target-view="providers"><i class="ph ph-van"></i>
                                    Proveedores</button></li>
                            <li><button class="menu-btn" data-target-view="employees"><i
                                        class="ph ph-identification-badge"></i> Empleados</button></li>
                            <li><button class="menu-btn" data-target-view="deliveries"><i class="ph ph-truck"></i>
                                    Envíos</button></li>
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
                            <li style="opacity: 0.5;" title="Bloqueado en el Plan Básico"><button class="menu-btn"
                                    onclick="window.showLockedFeatureToast('Sección de Envíos');"><i
                                        class="ph ph-truck"></i> Envíos <i class="ph-fill ph-lock-key"
                                        style="margin-left: auto; color: var(--accent-red)"></i></button></li>
                            <?php
                        endif; ?>
                    </ul>

                    <h3 id="sidebar-negocio-title">Negocio</h3>
                    <ul id="sidebar-negocio-list">
                        <li>
                            <button class="menu-btn" data-target-view="users-manage">
                                <i class="ph ph-users-three"></i> Colaboradores
                            </button>
                        </li>
                        <li><button class="menu-btn" data-target-view="payments"><i class="ph ph-wallet"></i> Métodos de
                                Pago</button></li>
                        <li><button class="menu-btn" data-target-view="notifications"><i class="ph ph-bell"></i>
                                Notificaciones</button></li>
                        <li><button class="menu-btn" data-target-view="history-log"><i
                                    class="ph ph-clock-counter-clockwise"></i>
                                Historial</button></li>
                    </ul>

                    <h3>Ayuda</h3>
                    <ul>
                        <li><button class="menu-btn" data-target-view="tutorials"><i class="ph ph-book-open"></i>
                                Tutoriales</button></li>
                        <li><button class="menu-btn" onclick="window.location.href='settings.php?tab=soporte'"><i
                                    class="ph ph-lifebuoy"></i>
                                Soporte</button></li>
                    </ul>
                </nav>
            </aside>


            <main class="dashboard-main">
                <div id="view-db" class="dashboard-view hidden">
                    
                    <!-- PESTAÑAS DE INVENTARIO (NEOBRUTALISMO) -->
                    <div class="inv-tab-bar">
                        <button type="button" class="inv-tab-btn active" id="tab-btn-products">
                            <i class="ph-bold ph-table"></i> Inventario
                        </button>
                        <button type="button" class="inv-tab-btn" id="tab-btn-combos">
                            <i class="ph-bold ph-tag"></i> Productos en Promo
                        </button>
                    </div>

                    <div class="table-container">

                        <!-- SECCIÓN 1: PRODUCTOS FÍSICOS -->
                        <div id="products-view-section">
                            <div class="table-header">
                                <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
                                    <h2 id="table-title"
                                        style="margin: 0; line-height: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                        Cargando...
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

                                        <!-- Honey-pot mejorado para engañar al autocompletado de Opera/Chrome -->

                                        <input type="search" id="main-table-search" name="q_stk_main"
                                            placeholder="Buscar en la tabla..." spellcheck="false" autocomplete="off"
                                            readonly onfocus="this.removeAttribute('readonly');"
                                            style="border: none; outline: none; background: transparent; width: 100%; height: 100%; padding: 10px 15px;">

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

                                    <div class="actions-dropdown-wrapper">
                                        <button type="button" id="actions-dropdown-btn" class="btn btn-secondary" style="display: none; align-items: center; gap: 8px;">
                                            <i class="ph ph-dots-three-vertical" style="font-size: 1.2rem;"></i> Acciones
                                        </button>
                                        <div class="actions-list-container">
                                            <button id="manage-columns-btn" class="btn btn-secondary"
                                                title="Ocultar o mostrar columnas" onclick="window.openColumnManager()">
                                                <i class="ph ph-eye" style="font-size: 1.2rem; font-weight: bold;"></i>
                                                <span class="btn-text">Columnas</span>
                                            </button>

                                            <button id="open-export-modal-btn" class="btn btn-secondary"
                                                style="display: flex; align-items: center; gap: 8px;" title="Exportar a Excel"
                                                onclick="window.openExportModal()">
                                                <i class="ph ph-export"></i> Exportar
                                            </button>

                                            <button id="open-import-modal-btn" class="btn btn-secondary"
                                                style="display: flex; align-items: center; gap: 8px;">
                                                <i class="ph ph-download-simple"></i> Importar
                                            </button>

                                            <!-- Catálogo Dropdown -->
                                            <div class="catalog-dropdown-wrapper" style="position: relative; display: inline-block;">
                                                <button type="button" id="catalog-actions-btn" class="btn btn-secondary" style="display: flex; align-items: center; gap: 8px;" title="Acciones del catálogo público">
                                                    <i class="ph ph-storefront" style="font-size: 1.2rem;"></i> Catálogo
                                                </button>
                                                <div id="catalog-dropdown-menu" class="hidden" style="position: absolute; top: 100%; right: 0; background: white; border: 2px solid #1b1b1b; border-radius: 4px; box-shadow: 4px 4px 0px rgba(0,0,0,0.15); z-index: 1000; min-width: 200px; display: flex; flex-direction: column; gap: 4px; padding: 8px;">
                                                    <button class="btn btn-secondary" onclick="window.bulkToggleCatalogVisibility(true)" style="margin: 0; text-align: left; font-size: 0.85rem; padding: 8px 12px; border-radius: 4px; border: 1px solid #1b1b1b; display: flex; align-items: center; gap: 8px; justify-content: flex-start; width: 100%; box-shadow: none; cursor: pointer;">
                                                        <i class="ph-fill ph-eye" style="color: var(--accent-green);"></i> Publicar Todo
                                                    </button>
                                                    <button class="btn btn-secondary" onclick="window.bulkToggleCatalogVisibility(false)" style="margin: 0; text-align: left; font-size: 0.85rem; padding: 8px 12px; border-radius: 4px; border: 1px solid #1b1b1b; display: flex; align-items: center; gap: 8px; justify-content: flex-start; width: 100%; box-shadow: none; cursor: pointer;">
                                                        <i class="ph ph-eye-slash" style="color: var(--accent-red);"></i> Ocultar Todo
                                                    </button>
                                                    <div style="height: 1px; background: #ddd; margin: 4px 0;"></div>
                                                    <button class="btn btn-secondary" onclick="window.viewMyPublicCatalog()" style="margin: 0; text-align: left; font-size: 0.85rem; padding: 8px 12px; border-radius: 4px; border: 1px solid #1b1b1b; display: flex; align-items: center; gap: 8px; justify-content: flex-start; width: 100%; box-shadow: none; cursor: pointer;">
                                                        <i class="ph ph-arrow-square-out"></i> Ver Catálogo
                                                    </button>
                                                </div>
                                            </div>

                                            <button id="add-row-btn" class="btn btn-primary" style="width: auto; margin-top: 0;">+
                                                Añadir Fila</button>
                                        </div>
                                    </div>
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

                        <!-- SECCIÓN 2: PROMOCIONES Y COMBOS -->
                        <div id="combos-view-section" class="hidden">
                            <div class="table-header">
                                <div style="display: flex; align-items: center; gap: 10px; min-width: 0;">
                                    <h2 style="margin: 0; line-height: 1; font-weight: 950;">Mis Combos y Promociones</h2>
                                    <button id="refresh-combos-btn" class="btn btn-secondary"
                                        title="Recargar y actualizar combos"
                                        style="padding: 4px 8px; display: flex; align-items: center; justify-content: center; border-radius: 6px; flex-shrink: 0; width: 32px; height: 32px; margin: 0;">
                                        <i class="ph-bold ph-arrows-clockwise" style="font-size: 1.3rem; line-height: 1;"></i>
                                    </button>
                                </div>

                                <div class="table-controls">
                                    <div class="search-wrapper">
                                        <input type="search" id="combos-table-search" placeholder="Buscar combos..." autocomplete="off"
                                            style="border: none; outline: none; background: transparent; width: 100%; height: 100%; padding: 10px 15px;">
                                    </div>
                                    <button id="add-combo-btn" class="btn btn-primary" style="width: auto; margin-top: 0;">
                                        + Nuevo Combo
                                    </button>
                                </div>
                            </div>

                            <div class="table-wrapper">
                                <table id="combos-table" class="rustic-table">
                                    <thead>
                                        <tr>
                                            <th style="text-align: left; padding-left: 15px;">Nombre de la Promo</th>
                                            <th style="text-align: center;">Precio de Venta</th>
                                            <th style="text-align: center;">Costo total componentes</th>
                                            <th style="text-align: left; padding-left: 15px;">Ingredientes / Productos integrantes</th>
                                            <th style="text-align: center;">Stock Disponible (Cuello de Botella)</th>
                                            <th style="text-align: center;">Estado</th>
                                            <th style="text-align: center;">Acciones</th>
                                        </tr>
                                    </thead>
                                    <tbody id="combos-table-body">
                                        <tr>
                                            <td colspan="7" style="text-align: center; font-style: italic; padding: 2rem;">Cargando promociones...</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>
                </div>

                <!-- MODAL NEOBRUTALISTA: NUEVO / EDITAR COMBO -->
                <div id="combo-modal" class="modal-overlay hidden" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.6); display: flex; align-items: center; justify-content: center; z-index: 10000; backdrop-filter: blur(4px);">
                    <div class="modal-content" style="background: white; border: 3px solid #1b1b1b; box-shadow: 8px 8px 0px #1b1b1b; border-radius: 12px; width: 550px; max-width: 95%; padding: 2rem; position: relative;">
                        <button type="button" class="close-icon" id="close-combo-modal-btn" style="position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 2rem; font-weight: bold; cursor: pointer; color: #1b1b1b; line-height: 0.5;">&times;</button>
                        <h2 id="combo-modal-title" style="margin-top: 0; margin-bottom: 1.5rem; font-weight: 900; border-bottom: 3px solid #1b1b1b; padding-bottom: 10px; font-size: 1.8rem;">Crear Nuevo Combo</h2>
                        
                        <form id="combo-form">
                            <input type="hidden" id="combo-id-input" value="">
                            
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label for="combo-name-input" style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.95rem;">Nombre de la Promo:</label>
                                <input type="text" id="combo-name-input" class="rustic-input" placeholder="Ej: Combo Fernet + 2 Cocas" required style="width: 100%; padding: 10px; border: 2.5px solid #1b1b1b; border-radius: 6px; box-sizing: border-box; font-weight: 600; outline: none;">
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 15px;">
                                <label for="combo-price-input" style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.95rem;">Precio de Venta Promocional ($):</label>
                                <input type="number" id="combo-price-input" class="rustic-input" step="0.01" min="0" placeholder="0.00" required style="width: 100%; padding: 10px; border: 2.5px solid #1b1b1b; border-radius: 6px; box-sizing: border-box; font-weight: 600; outline: none;">
                            </div>
                            
                            <div class="form-group" style="margin-bottom: 15px; position: relative;">
                                <label for="combo-search-products" style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.95rem;">Buscar y agregar producto integrante:</label>
                                <div style="position: relative; display: flex; gap: 8px;">
                                    <input type="text" id="combo-search-products" class="rustic-input" placeholder="Escribe el nombre o SKU del producto..." style="width: 100%; padding: 10px; border: 2.5px solid #1b1b1b; border-radius: 6px; box-sizing: border-box; font-weight: 600; outline: none;">
                                    <div id="combo-suggestions" class="autocomplete-suggestions hidden"></div>
                                </div>
                            </div>

                            <!-- ALERTA DE RENTABILIDAD -->
                            <div id="combo-rentability-alert" class="rentability-alert hidden">
                                <i class="ph-fill ph-warning-circle" style="font-size: 1.3rem;"></i>
                                <span id="combo-rentability-text">Advertencia: El precio de venta propuesto está por debajo del costo acumulado de los componentes.</span>
                            </div>
                            
                            <div style="margin-top: 15px; margin-bottom: 25px;">
                                <label style="display: block; font-weight: 600; margin-bottom: 6px; font-size: 0.95rem;">Componentes del Combo y sus Cantidades:</label>
                                <div class="ingredients-list-container" id="combo-ingredients-list">
                                    <div style="color: #666; font-style: italic; text-align: center; padding: 15px;">Ningún producto componente agregado. Usa el buscador de arriba.</div>
                                </div>
                                <div style="display: flex; justify-content: flex-end; gap: 12px; border-top: 2px solid #eee; padding-top: 15px;">
                                    <button type="button" class="btn-neo-brutal" id="cancel-combo-btn" style="margin: 0; padding: 10px 20px; font-size: 0.9rem;">Cancelar</button>
                                    <button type="submit" class="btn-neo-brutal btn-primary" id="save-combo-btn" style="margin: 0; padding: 10px 20px; font-size: 0.9rem; font-weight: bold;">Crear Combo</button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>

                <div id="history-log" class="dashboard-view hidden">
                    <!-- El contenido se cargará dinámicamente vía HistoryModule.js -->
                </div>

                <div id="deliveries" class="dashboard-view hidden">
                    <!-- El contenido se cargará dinámicamente vía deliveries.js -->
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
                                        <div class="rustic-block" style="margin-bottom: 1.5rem;">
                                            <div class="block-header"
                                                style="display: flex; align-items: center; gap: 10px; margin-bottom: 0.5rem;">
                                                <input type="checkbox" id="feature-image" style="margin: 0;">
                                                <span class="option-label"
                                                    style="font-weight: bold; font-size: 1.1rem; color: var(--accent-color);">Columna de Imagen en Catálogo</span>
                                            </div>
                                            <div class="reveal-wrapper" id="wrap-image">
                                                <div class="reveal-inner" style="padding-left: 25px;">
                                                    <p
                                                        style="font-size: 0.9rem; color: #666; margin-top: 0; margin-bottom: 1rem; line-height: 1.4;">
                                                        Habilita una columna <b>"imagen_url"</b> en tu tabla para ingresar los enlaces (links) de las fotos. Se mostrarán automáticamente en tu catálogo público.
                                                    </p>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="rustic-block"
                                            style="margin-bottom: 1.5rem; border-color: var(--accent-color);">
                                            <div class="block-header"
                                                style="display: flex; align-items: center; gap: 10px; margin-bottom: 0.5rem;">
                                                <label
                                                    style="position: relative; display: inline-block; width: 40px; height: 20px; margin: 0;">
                                                    <input type="checkbox" id="feature-daily-report"
                                                        style="opacity: 0; width: 0; height: 0; position: absolute;"
                                                        checked>
                                                    <span
                                                        style="position: absolute; cursor: pointer; top: 0; left: 0; right: 0; bottom: 0; transition: .4s; border-radius: 20px;"
                                                        class="slider round"></span>
                                                    <style>
                                                        #feature-daily-report+.slider {
                                                            background-color: #ccc;
                                                        }

                                                        #feature-daily-report:checked+.slider {
                                                            background-color: var(--accent-color);
                                                        }

                                                        #feature-daily-report:focus+.slider {
                                                            box-shadow: 0 0 1px var(--accent-color);
                                                        }

                                                        .slider:before {
                                                            position: absolute;
                                                            content: "";
                                                            height: 14px;
                                                            width: 14px;
                                                            left: 3px;
                                                            bottom: 3px;
                                                            background-color: white;
                                                            transition: .4s;
                                                            border-radius: 50%;
                                                        }

                                                        #feature-daily-report:checked+.slider:before {
                                                            transform: translateX(20px);
                                                        }
                                                    </style>
                                                </label>
                                                <span class="option-label"
                                                    style="font-weight: bold; font-size: 1.1rem; color: var(--accent-color);">Reporte
                                                    Diario Automático</span>
                                                <span id="report-status-text"
                                                    style="font-size: 0.85rem; font-weight: 700; padding: 4px 8px; border-radius: 6px; margin-left: auto;"></span>
                                            </div>

                                            <div class="reveal-inner" style="padding-left: 50px;">
                                                <p
                                                    style="font-size: 0.9rem; color: #666; margin-top: 0; margin-bottom: 1rem; line-height: 1.4;">
                                                    Recibí un balance general de tu caja todos los días a las 22:00 hs
                                                    (Ventas, Compras y Balance Final).
                                                </p>
                                                <p
                                                    style="font-size: 0.8rem; color: #888; font-style: italic; margin-top: 0;">
                                                    <i class="ph-bold ph-info" style="color: var(--accent-color);"></i>
                                                    Si el inventario no registra movimientos durante 10 días, el reporte
                                                    se pausará automáticamente.
                                                </p>
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

                        <?php if ($isOwner): ?>
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
                        <?php endif; ?>

                    </div>
                </div>

                <div id="analysis" class="dashboard-view hidden">
                </div>

                <div id="users-manage" class="dashboard-view hidden">
                    <div class="users-manage-header-row"
                        style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #1b1b1b; padding-bottom: 15px; margin-bottom: 1.5rem;">
                        <div class="desktop-only-header-text">
                            <h2 style="margin:0;"><i class="ph ph-users-three"></i> Gestión de Colaboradores</h2>
                            <p style="margin: 5px 0 0 0; color: #666; font-size: 0.9rem;">Invitá a otras personas a tu
                                inventario y administrá sus permisos.</p>
                        </div>
                        <div id="collab-quota-placeholder-mobile" class="mobile-only-quota-row" style="display: none;">
                        </div>
                        <div class="collab-header-buttons" style="display: flex; gap: 10px;">
                            <button id="add-slots-btn" class="btn btn-secondary hidden"
                                style="margin:0; width:auto; white-space:nowrap; border-color: var(--accent-color); color: var(--accent-color);">
                                <i class="ph-bold ph-plus-circle"></i> Agregar Slots
                            </button>
                            <button id="invite-collaborator-btn" class="btn btn-primary"
                                style="margin:0; width:auto; white-space:nowrap;">
                                <i class="ph ph-user-plus"></i> Nueva Invitación
                            </button>
                        </div>
                    </div>

                    <!-- Banner de advertencia de deudas pendientes -->
                    <div id="debt-warning-banner" class="hidden"
                        style="background: #FFFDF5; border: 2px solid var(--accent-yellow, #EBCB8B); padding: 16px 20px; border-radius: 12px; margin-bottom: 1.5rem; display: flex; justify-content: space-between; align-items: center; box-shadow: 4px 4px 0px var(--accent-yellow, #EBCB8B); text-align: left; gap: 16px; flex-wrap: wrap;">
                        <div style="display: flex; align-items: center; gap: 16px; flex: 1; min-width: 280px;">
                            <div style="background: var(--accent-yellow-20, rgba(235, 203, 139, 0.2)); border: 2px solid var(--accent-yellow, #EBCB8B); width: 48px; height: 48px; border-radius: 8px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 2px 2px 0px var(--accent-yellow, #EBCB8B);">
                                <i class="ph-bold ph-warning" style="font-size: 1.5rem; color: var(--accent-yellow, #EBCB8B);"></i>
                            </div>
                            <div>
                                <strong style="color: var(--color-black, #1a1a1a); font-size: 1.05rem; display: block; font-weight: 800; margin-bottom: 2px;">Pago Pendiente de Colaboradores</strong>
                                <span id="debt-warning-text" style="color: #555; font-size: 0.88rem; font-weight: 500; line-height: 1.25;">Tenés una deuda pendiente de $<?php echo number_format($slotPrice, 0, ',', '.'); ?> por slots agregados. Plazo restante para saldar: 48 horas o tus colaboradores serán eliminados.</span>
                            </div>
                        </div>
                        <button id="pay-debt-btn" type="button"
                            onclick="window.handleDebtPayment()"
                            class="btn btn-secondary"
                            style="margin: 0; width: auto; font-size: 0.9rem; padding: 10px 18px; display: inline-flex; align-items: center; gap: 8px;">
                            <img src="/assets/img/iconos/mp.png" alt="Mercado Pago" style="height: 16px; width: auto; object-fit: contain;"> Saldar Deuda
                        </button>
                    </div>

                    <div id="collaborators-list-container"
                        style="background: #fff; border: 2px solid #1b1b1b; border-radius: 12px; padding: 0; overflow: hidden;">
                        <p style="color: #666;"><i class="ph ph-spinner ph-spin"></i> Cargando colaboradores...</p>
                    </div>

                    <!-- Panel de control de acceso por rol: solo visible para Owner (controlado vía JS) -->
                    <div id="role-permissions-panel" class="hidden"
                        style="margin-top: 2rem; border-top: 2px dashed #e5e5e5; padding-top: 1.5rem;">
                        <div style="margin-bottom: 1.5rem;">
                            <h3 style="margin: 0 0 4px; display: flex; align-items: center; gap: 8px;">
                                <i class="ph ph-sliders" style="color: var(--accent-color);"></i> Control de Acceso por
                                Rol
                            </h3>
                            <p style="color: #666; font-size: 0.9rem; margin: 0;">
                                Elegí qué secciones del dashboard puede ver cada rol. El Propietario siempre tiene
                                acceso total y no puede ser restringido.
                            </p>
                        </div>

                        <div id="permissions-grid"
                            style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem; background: #fff; border: 2px solid #1b1b1b; border-radius: 12px; padding: 20px;">
                            <!-- Renderizado dinámicamente por users.js -->
                            <p style="color: #999; grid-column: 1/-1;"><i class="ph ph-spinner ph-spin"></i> Cargando
                                configuración...</p>
                        </div>

                        <div style="text-align: right; margin-top: 1.5rem;">
                            <button id="save-permissions-btn" class="btn btn-primary"
                                style="background: var(--accent-color); border-color: #1b1b1b;">
                                <i class="ph ph-floppy-disk"></i> Guardar Configuración
                            </button>
                        </div>
                    </div>

                    <!-- Panel de Horario Laboral: solo para Owner -->
                    <div id="work-hours-panel" class="hidden"
                        style="margin-top: 2rem; border-top: 2px dashed #e5e5e5; padding-top: 1.5rem; margin-bottom: 2rem;">
                        <div style="margin-bottom: 1.5rem;">
                            <h3 style="margin: 0 0 4px; display: flex; align-items: center; gap: 8px;">
                                <i class="ph ph-clock" style="color: var(--accent-color);"></i> Horario Laboral de Colaboradores
                            </h3>
                            <p style="color: #666; font-size: 0.9rem; margin: 0;">
                                Establecé el rango horario en el que tus colaboradores tienen permitido acceder al sistema. Si ingresan fuera de este horario, se te enviará una alerta automática por WhatsApp.
                            </p>
                        </div>

                        <div style="background: #fff; border: 2px solid #1b1b1b; border-radius: 12px; padding: 20px; max-width: 500px; box-shadow: 4px 4px 0px #000;">
                            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 1.25rem;">
                                <input type="checkbox" id="work-hours-enabled" style="width: 20px; height: 20px; cursor: pointer; border: 2px solid #000; border-radius: 4px;">
                                <label for="work-hours-enabled" style="font-weight: 700; cursor: pointer; user-select: none;">Habilitar alerta de acceso fuera de horario</label>
                            </div>
                            
                            <div id="work-hours-inputs" style="display: flex; gap: 15px; align-items: center;">
                                <div style="flex: 1;">
                                    <label style="display: block; font-size: 0.85rem; font-weight: 700; margin-bottom: 5px;">Hora de Inicio</label>
                                    <input type="time" id="work-hours-start" value="08:00" style="width: 100%; padding: 8px; border: 2px solid #1b1b1b; border-radius: 6px; font-family: inherit; font-weight: 700;">
                                </div>
                                <div style="font-weight: 700; margin-top: 20px;">a</div>
                                <div style="flex: 1;">
                                    <label style="display: block; font-size: 0.85rem; font-weight: 700; margin-bottom: 5px;">Hora de Fin</label>
                                    <input type="time" id="work-hours-end" value="20:00" style="width: 100%; padding: 8px; border: 2px solid #1b1b1b; border-radius: 6px; font-family: inherit; font-weight: 700;">
                                </div>
                            </div>
                        </div>

                        <div style="text-align: right; margin-top: 1.5rem; max-width: 500px;">
                            <button id="save-work-hours-btn" class="btn btn-primary" onclick="window.usersModuleInstance.saveWorkHours()"
                                style="background: var(--accent-color); border-color: #1b1b1b;">
                                <i class="ph ph-floppy-disk"></i> Guardar Horario
                            </button>
                        </div>
                    </div>
                </div>


                <div id="notifications" class="dashboard-view hidden">
                    <div
                        style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 2px solid #1b1b1b; padding-bottom: 15px; margin-bottom: 1.5rem; gap: 20px;">
                        <div style="min-width: 0;">
                            <h2 style="margin:0; white-space: nowrap;"><i class="ph ph-bell"></i> Centro de Mensajes
                            </h2>
                            <div class="notif-tabs" style="display: flex; gap: 15px; margin-top: 15px;">
                                <button class="notif-tab-btn active" data-tab="normal">Notificaciones</button>
                                <button class="notif-tab-btn" data-tab="errors" style="white-space: nowrap;">Errores
                                    Técnicos</button>
                            </div>
                        </div>
                        <button id="clear-notifications-btn" class="btn btn-secondary"
                            style="color: var(--accent-red); border-color: var(--accent-red); padding: 8px 16px; font-size: 0.85rem; flex-shrink: 0; width: auto; margin: 0;"
                            title="Limpiar todas las notificaciones">
                            <i class="ph ph-trash"></i> Limpiar Todo
                        </button>
                    </div>

                    <div id="tech-errors-header" class="hidden"
                        style="background: #fff5f5; border: 2px solid var(--accent-red); padding: 20px; border-radius: 12px; margin-bottom: 1.5rem;">
                        <div style="display: flex; align-items: center; gap: 15px;">
                            <i class="ph-bold ph-wrench" style="font-size: 2.2rem; color: var(--accent-red);"></i>
                            <div>
                                <strong style="color: var(--accent-red); display: block; font-size: 1.1rem;">Buzón de
                                    Errores Técnicos</strong>
                                <p style="margin: 0; font-size: 0.85rem; color: #666;">Acá se enlistarán los errores
                                    para que así puedas notificarle al programador y enviarle el código específico.</p>
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

                <div id="no-section-view" class="dashboard-view hidden" style="text-align: center; padding: 50px 20px; background: #fff; height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; border: var(--border-strong); border-radius: var(--border-radius); box-shadow: 10px 10px 0px var(--color-black);">
                    <img src="/assets/img/ImagenSinSeccion.svg" alt="Sin acceso" style="max-width: min(500px, 80vw); height: auto; margin-bottom: 20px;">
                    <h2 style="font-weight: 800; font-size: 1.5rem; margin-bottom: 10px; color: #1b1b1b;">Sin secciones asignadas</h2>
                    <p style="color: #666; max-width: 400px; margin: 0; font-size: 0.95rem;">Tu usuario no tiene permisos asignados para ver ninguna sección de este inventario. Contactá al propietario para que te asigne permisos.</p>
                </div>

            </main>
        </div>
    </div>

    <div class="mobile-app-view hidden">
        <div class="mobile-inventory-header"
            style="background: #fff; padding: 25px 20px 20px 20px; margin-bottom: 15px; position: sticky; top: 0; z-index: 100;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                <small
                    style="text-transform: uppercase; font-weight: 800; color: #888; font-size: 0.75rem; letter-spacing: 1.5px; margin: 0; line-height: 1; margin-top: 6px;">Inventario
                    Activo</small>
                <button onclick="window.location.href='select-db.php'"
                    style="background: #fff; color: #1b1b1b; border: 2px solid #1b1b1b; border-radius: 8px; padding: 6px 14px; font-size: 0.75rem; font-weight: 900; display: flex; align-items: center; gap: 6px; cursor: pointer; margin: 0;">
                    CAMBIAR <i class="ph-bold ph-arrows-left-right"></i>
                </button>
            </div>
            <div id="mobile-current-inv-name"
                style="font-weight: 900; font-size: 1.8rem; color: #1b1b1b; letter-spacing: -1px; line-height: 1; margin-bottom: 12px; margin-top: 5px;">

                <?= $activeInventoryName ?>
            </div>
            <div style="height: 4px; background: var(--accent-color, #1b1b1b); width: 40px; border-radius: 2px;"></div>
        </div>

        <div id="mobile-dollar-box"
            style="background: #f9f9f9; border: 2px solid #1b1b1b; border-radius: 12px; padding: 12px 20px; display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <i class="ph-fill ph-currency-dollar" style="font-size: 1.8rem; color: var(--accent-color);"></i>
                <div style="display: flex; flex-direction: column;">
                    <strong style="font-size: 1.1rem; color: #1b1b1b; line-height: 1;">Dólar: $<span
                            id="mobile-dollar-price">---</span></strong>
                    <span id="mobile-dollar-source"
                        style="font-size: 0.75rem; color: #888; text-transform: uppercase; font-weight: bold; margin-top: 3px;">Cargando...</span>
                </div>
            </div>
            <div
                style="background: var(--accent-color-quat-opacity); border: 2px solid var(--accent-color); color: var(--accent-color); padding: 6px 12px; border-radius: 6px; font-weight: bold; font-size: 0.8rem; white-space: nowrap; text-align: center; display: inline-flex; align-items: center; justify-content: center; height: fit-content; flex-shrink: 0;">
                Cambio Actual
            </div>
        </div>

        <div class="mobile-actions-grid">

            <div class="mobile-card action-sale" onclick="window.openMobileTransaction('sale')">
                <div class="icon-circle"><i class="ph-bold ph-shopping-cart"></i></div>
                <h3>Registrar Ingreso</h3>
                <p>Registrar salida</p>
            </div>

            <div class="mobile-card action-purchase" onclick="window.openMobileTransaction('purchase')">
                <div class="icon-circle"><i class="ph-bold ph-package"></i></div>
                <h3>Registrar Compra</h3>
                <p>Registrar entrada</p>
            </div>

            <div class="mobile-card action-expense" onclick="window.openMobileExpense()">
                <div class="icon-circle"><i class="ph-bold ph-lightning"></i></div>
                <h3>Registrar Gasto</h3>
                <p>Gasto rápido</p>
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

            <div class="mobile-card action-metrics" onclick="window.openMobileMetrics()">
                <div class="icon-circle"><i class="ph-bold ph-chart-pie-slice"></i></div>
                <h3>Métricas</h3>
                <p>Ventas y Gastos</p>
            </div>

            <div class="mobile-card action-history" onclick="window.openMobileHistory()">
                <div class="icon-circle"><i class="ph-bold ph-clock-counter-clockwise"></i></div>
                <h3>Historial</h3>
                <p>Ver Movimientos</p>
            </div>

            <div class="mobile-card action-providers" onclick="window.openMobileEntityList('providers')">
                <div class="icon-circle"><i class="ph-bold ph-truck"></i>
                </div>
                <h3>Proveedores</h3>
                <p>Ver listado</p>
            </div>

            <div class="mobile-card action-deliveries" onclick="window.openMobileDeliveries()">
                <div class="icon-circle"><i class="ph-bold ph-truck"></i></div>
                <h3>Envíos</h3>
                <p>Gestionar</p>
            </div>

            <div class="mobile-card action-customers" onclick="window.openMobileEntityList('customers')">
                <div class="icon-circle"><i class="ph-bold ph-users"></i></div>
                <h3>Clientes</h3>
                <p>Ver listado</p>
            </div>

            <div class="mobile-card action-employees" onclick="window.openMobileEntityList('employees')">
                <div class="icon-circle"><i class="ph-bold ph-identification-card"></i></div>
                <h3>Trabajadores</h3>
                <p>Ver equipo</p>
            </div>

            <?php if ($isOwner): ?>
                <div class="mobile-card action-collaborators" onclick="window.openMobileCollaborators()">
                    <div class="icon-circle"><i class="ph-bold ph-users-three"></i></div>
                    <h3>Colaboradores</h3>
                    <p>Gestionar equipo</p>
                </div>
            <?php endif; ?>
        </div>

        <div class="mobile-quick-stats">
        </div>
    </div>

    <div id="mobile-balance-modal" class="mobile-modal-overlay hidden">
        <div class="mobile-modal-content">
            <div class="mobile-modal-header">
                <h2>Balance y Caja</h2>
                <button class="close-icon" onclick="window.closeCashBalance()">&times;</button>
            </div>

            <div class="balance-body">
                <div class="period-selector">
                    <button id="btn-period-today" class="period-btn active"
                        onclick="window.loadBalanceData('today')">Hoy</button>
                    <button id="btn-period-week" class="period-btn"
                        onclick="window.loadBalanceData('week')">Semana</button>
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
                            <span>Egresos (Compras/Gastos)</span>
                            <h4 id="balance-expense">$0.00</h4>
                        </div>
                    </div>
                </div>

                <div class="balance-action-area" style="margin-top: 1.75rem; width: 100%; display: flex; justify-content: center;">
                    <button id="btn-notify-cash" class="btn-notify-cash" onclick="window.notifyCashBalance()">
                        <i class="ph ph-whatsapp-logo" style="font-size: 1.4rem;"></i> Notificar cierre de caja
                    </button>
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
                    <button class="import-tab active" data-tab="csv">Archivo CSV / Excel</button>
                    <button class="import-tab" data-tab="tiendanube">TiendaNube</button>
                </div>
            </div>

            <div class="modal-body">
                <div id="import-section-csv">
                    <p>Selecciona o arrastra tu archivo CSV o Excel.</p>
                    <div id="import-step-1">
                        <div id="import-drop-zone" class="drop-zone">
                            <p>Arrastra tu archivo CSV o Excel acá o hacé clic para seleccionar</p>
                            <input type="file" id="csv-file-input" accept=".csv, .xlsx, .xls" style="display: none;">
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
                                <p>Si está activado, se elimina lo existente y queda solo lo importado del archivo.</p>
                            </div>
                        </label>
                    </div>

                    <div id="import-step-2" class="hidden">
                        <h3>Mapeá las Columnas</h3>
                        <p>Asigná las columnas de tu archivo a las de StockiFy.</p>
                        <form id="mapping-form" class="import-mapping-form" style="padding-right: 10px;"></form>
                    </div>
                </div>

                <div id="import-section-tiendanube" class="hidden">
                    <div id="tn-connection-status" class="tn-status-container"
                        style="padding: 20px; text-align: center;">
                        <p><i class="ph ph-spinner ph-spin"></i> Verificando conexión con TiendaNube...</p>
                    </div>

                    <div id="tn-config-step" class="hidden">
                        <p style="margin-bottom: 15px;">Mapeá las columnas de tu inventario con los datos de tu tienda.
                        </p>
                        <div id="tn-mapping-form" class="import-mapping-form" style="padding-right: 10px;"></div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button id="import-cancel-btn" class="btn btn-secondary">Cancelar</button>
                <button id="validate-prepare-btn" class="btn btn-primary hidden">Validar y Preparar Datos</button>
                <button id="tn-import-btn" class="btn btn-primary hidden">Sincronizar con TiendaNube</button>
            </div>
        </div>

    </div>
    </div>
    </div>

    <div id="delete-confirm-modal" class="modal-overlay hidden">
        <style>
            #delete-confirm-modal .modal-content {
                max-width: 550px;
                border: 2px solid #1b1b1b;
                border-radius: 12px;
                box-shadow: 10px 10px 0px var(--accent-red) !important;
                padding: 1.8rem;
                max-height: 92vh !important;
                overflow-y: auto;
                scrollbar-width: thin;
                scrollbar-color: rgba(0, 0, 0, 0.2) transparent;
            }

            #delete-confirm-modal .modal-content::-webkit-scrollbar {
                width: 6px;
            }

            #delete-confirm-modal .modal-content::-webkit-scrollbar-track {
                background: transparent;
            }

            #delete-confirm-modal .modal-content::-webkit-scrollbar-thumb {
                background: rgba(0, 0, 0, 0.2);
                border-radius: 10px;
            }

            #delete-confirm-modal .modal-content::-webkit-scrollbar-thumb:hover {
                background: rgba(0, 0, 0, 0.4);
            }

            /* Animations for progressive sections appearance */
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(15px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .animated-section {
                animation: fadeInUp 0.3s cubic-bezier(0.16, 1, 0.3, 1) forwards;
            }

            /* Otp inputs divided look */
            .otp-digits-container {
                display: flex;
                gap: 8px;
                justify-content: center;
                margin: 15px 0;
            }

            .otp-digit-input {
                width: 42px !important;
                height: 48px !important;
                font-size: 1.4rem !important;
                text-align: center !important;
                border: 2px solid #1a1a1a !important;
                border-radius: 6px !important;
                font-weight: 700 !important;
                color: #1a1a1a !important;
                background: #ffffff !important;
                margin: 0 !important;
                padding: 0 !important;
                box-shadow: 2px 2px 0px #1a1a1a !important;
                transition: all 0.15s ease !important;
            }

            .otp-digit-input:focus {
                border-color: var(--accent-red) !important;
                box-shadow: 3px 3px 0px var(--accent-red) !important;
                outline: none !important;
            }

            /* Password field input fixes */
            .delete-password-wrapper {
                position: relative;
                margin-top: 5px;
                display: flex;
                width: 100%;
            }

            #delete-password-input {
                width: 100% !important;
                border: 2px solid #1a1a1a !important;
                border-radius: 8px !important;
                padding: 10px 44px 10px 14px !important;
                outline: none !important;
                background: #fff !important;
                font-size: 1rem !important;
                font-weight: 500 !important;
                color: #1a1a1a !important;
                margin: 0 !important;
            }

            #delete-password-input:focus {
                border-color: var(--accent-red) !important;
                box-shadow: 4px 4px 0px var(--accent-red) !important;
            }

            #toggle-delete-password {
                position: absolute !important;
                right: 12px !important;
                top: 0 !important;
                bottom: 0 !important;
                margin: auto !important;
                height: 32px !important;
                width: 32px !important;
                background: none !important;
                border: none !important;
                cursor: pointer !important;
                color: #666 !important;
                font-size: 1.3rem !important;
                padding: 0 !important;
                display: flex !important;
                align-items: center !important;
                justify-content: center !important;
            }

            /* Confirm input */
            #delete-confirm-input {
                width: 100% !important;
                border: 2px solid #1a1a1a !important;
                border-radius: 8px !important;
                padding: 10px 14px !important;
                outline: none !important;
                background: #fff !important;
                font-size: 1rem !important;
                font-weight: 500 !important;
                color: #1a1a1a !important;
                margin-top: 5px !important;
                transition: all 0.2s ease;
            }

            #delete-confirm-input::placeholder {
                color: #757575 !important;
                font-weight: normal !important;
            }

            #delete-confirm-input:focus {
                border-color: var(--accent-red) !important;
                box-shadow: 4px 4px 0px var(--accent-red) !important;
            }

            /* Spin animation for icons */
            @keyframes spin {
                from { transform: rotate(0deg); }
                to { transform: rotate(360deg); }
            }
            .spin-icon {
                animation: spin 1s linear infinite;
                display: inline-block;
            }

            /* Verify password button visual adjustments */
            #delete-verify-password-btn {
                margin-top: 12px !important;
                width: 100% !important;
                box-sizing: border-box !important;
                border: 2px solid #1b1b1b !important;
                border-radius: 6px !important;
                font-weight: 700 !important;
                background: #f6f8fa !important;
                color: #1b1b1b !important;
                box-shadow: none !important;
                transition: all 0.2s ease !important;
                padding: 10px 16px !important;
            }

            #delete-verify-password-btn:hover:not(:disabled) {
                box-shadow: 3px 3px 0px rgba(0, 0, 0, 0.15) !important;
                background: #eaeef2 !important;
            }

            #delete-verify-password-btn:disabled {
                opacity: 0.6;
                cursor: not-allowed;
            }

            #delete-verify-password-btn.verified {
                color: var(--accent-green) !important;
                border-color: var(--accent-green) !important;
                background: #f4faf6 !important;
                box-shadow: none !important;
            }
        </style>
        <div class="modal-content view-container">
            <button id="close-delete-modal-btn" class="modal-close-btn" style="position: absolute; top: 15px; right: 20px; font-size: 1.8rem; background: none; border: none; cursor: pointer; color: #666; font-weight: bold;">&times;</button>

            <div class="modal-header" style="border-bottom: 2px solid #1b1b1b; padding-bottom: 15px; margin-bottom: 20px;">
                <h2 style="color: var(--accent-red); font-size: 1.5rem; font-weight: 800; display: flex; align-items: center; gap: 10px; margin: 0;">
                    <i class="ph ph-warning-octagon" style="font-size: 1.8rem;"></i> Eliminar Inventario
                </h2>
            </div>

            <div style="background: var(--accent-red-20); border: 2px solid var(--accent-red); border-radius: 8px; padding: 15px 20px; margin-bottom: 20px; box-shadow: 4px 4px 0px var(--accent-red);">
                <p style="margin: 0; color: #1b1b1b; font-size: 0.95rem; line-height: 1.5; font-weight: 500;">
                    <strong style="color: var(--accent-red); font-weight: 700; font-size: 1rem; display: block; margin-bottom: 5px;">ALERTA DE SEGURIDAD</strong>
                    Esta acción es <strong>irreversible</strong> y no se puede deshacer. Se borrará permanentemente el inventario con todos sus productos, categorías, movimientos de stock, reportes e historial de ventas.
                </p>
            </div>

            <div class="modal-body" style="padding: 0;">

                <!-- ── Step 1: Nombre del inventario ── -->
                <div id="delete-step-1">
                    <p class="delete-step-label" style="font-size: 0.95rem; font-weight: 600; color: #1b1b1b; display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
                        <span class="delete-step-badge" style="background-color: var(--accent-red); color: white; display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%; font-size: 0.8rem; font-weight: 700; border: 1.5px solid #1b1b1b; box-shadow: 1px 1px 0px #1b1b1b;">1</span>
                        Escribí el nombre exacto del inventario para continuar:
                    </p>
                    
                    <div class="inventory-name-copy-container" style="display: flex; align-items: center; justify-content: space-between; background: #f6f8fa; border: 2px solid #1b1b1b; border-radius: 8px; padding: 10px 14px; margin-top: 10px; margin-bottom: 15px; box-shadow: 3px 3px 0px #1b1b1b;">
                        <code id="delete-db-name-confirm" style="font-family: monospace; font-size: 1.1rem; font-weight: 700; color: var(--color-black); word-break: break-all;"></code>
                        <button type="button" id="copy-delete-db-name-btn" class="btn btn-secondary" style="margin: 0; width: auto; padding: 6px 12px; font-size: 0.85rem; display: inline-flex; align-items: center; gap: 6px; border: 2px solid #1b1b1b; border-radius: 4px; background: #ffffff; box-shadow: 2px 2px 0px #1b1b1b; cursor: pointer;">
                            <i class="ph ph-copy"></i> <span>Copiar</span>
                        </button>
                    </div>

                    <input type="text" id="delete-confirm-input" placeholder="Nombre del Inventario" autocomplete="off">
                    <div id="delete-error-message"
                        style="color: var(--accent-red); font-weight: 700; margin-top: 8px; min-height: 20px; font-size: 0.95rem;">
                    </div>
                </div>

                <!-- ── Step 2: Verificación de identidad (oculto hasta que step 1 pase) ── -->
                <div id="delete-step-2" class="hidden animated-section">
                    <div class="delete-step-divider" style="height: 2px; background: #1b1b1b; margin: 20px 0;"></div>

                    <!-- Para usuarios Google: solo OTP -->
                    <div id="delete-auth-google" class="hidden animated-section">
                        <p class="delete-step-label" style="font-size: 0.95rem; font-weight: 600; color: #1b1b1b; display: flex; align-items: center; gap: 8px;">
                            <span class="delete-step-badge" style="background-color: var(--accent-red); color: white; display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%; font-size: 0.8rem; font-weight: 700; border: 1.5px solid #1b1b1b; box-shadow: 1px 1px 0px #1b1b1b;">2</span>
                            Verificación de identidad:
                        </p>
                        <p id="delete-email-hint" class="delete-email-hint" style="font-size: 0.95rem; margin: 10px 0; color: #333; font-weight: 500;"></p>
                        
                        <div class="delete-otp-row" style="display: flex; align-items: center; justify-content: center; gap: 12px; margin-bottom: 15px; margin-top: 10px; flex-wrap: wrap;">
                            <button id="delete-send-otp-btn" class="btn btn-primary delete-send-otp-btn" style="width: auto; margin-top: 0; padding: 10px 16px; display: inline-flex; align-items: center; gap: 8px; border: 2px solid #1b1b1b; border-radius: 6px; box-shadow: 3px 3px 0px #1b1b1b;">
                                <i class="ph ph-envelope"></i> Enviar código por correo
                            </button>
                            <span id="delete-otp-countdown" class="delete-otp-countdown hidden" style="font-weight: 700; color: var(--accent-red); font-size: 0.95rem;"></span>
                        </div>

                        <!-- Casillas divididas para código OTP -->
                        <div id="otp-container-google" class="otp-digits-container hidden">
                            <input type="text" class="otp-digit-input" data-index="0" maxlength="1" pattern="\d" inputmode="numeric" autocomplete="off">
                            <input type="text" class="otp-digit-input" data-index="1" maxlength="1" pattern="\d" inputmode="numeric" autocomplete="off">
                            <input type="text" class="otp-digit-input" data-index="2" maxlength="1" pattern="\d" inputmode="numeric" autocomplete="off">
                            <input type="text" class="otp-digit-input" data-index="3" maxlength="1" pattern="\d" inputmode="numeric" autocomplete="off">
                            <input type="text" class="otp-digit-input" data-index="4" maxlength="1" pattern="\d" inputmode="numeric" autocomplete="off">
                            <input type="text" class="otp-digit-input" data-index="5" maxlength="1" pattern="\d" inputmode="numeric" autocomplete="off">
                        </div>

                        <!-- Input oculto compatible con la lógica existente -->
                        <input type="hidden" id="delete-otp-input">

                        <div id="delete-otp-status" class="delete-otp-status hidden" style="text-align: center; font-weight: 700; margin-top: 10px; font-size: 0.95rem;"></div>
                    </div>

                    <!-- Para usuarios con contraseña: contraseña + OTP -->
                    <div id="delete-auth-password" class="hidden animated-section">
                        <!-- Sub-step 2a: contraseña -->
                        <div id="delete-password-section">
                            <p class="delete-step-label" style="font-size: 0.95rem; font-weight: 600; color: #1b1b1b; display: flex; align-items: center; gap: 8px; margin-bottom: 10px;">
                                <span class="delete-step-badge" style="background-color: var(--accent-red); color: white; display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%; font-size: 0.8rem; font-weight: 700; border: 1.5px solid #1b1b1b; box-shadow: 1px 1px 0px #1b1b1b;">2</span>
                                Verificá tu identidad para continuar:
                            </p>
                            
                            <label class="micro-label" style="margin-bottom: 6px; display: block; font-weight: 700; font-size: 0.8rem; letter-spacing: 0.5px; color: #666; text-transform: uppercase;">Tu contraseña de acceso:</label>
                            <div class="delete-password-wrapper">
                                <input type="password" id="delete-password-input" placeholder="Ingresá tu contraseña de acceso" autocomplete="current-password">
                                <button type="button" id="toggle-delete-password">
                                    <i class="ph ph-eye"></i>
                                </button>
                            </div>
                            <div id="delete-password-error"
                                style="color: var(--accent-red); font-weight: 700; margin-top: 6px; min-height: 18px; font-size: 0.95rem;">
                            </div>
                            <button id="delete-verify-password-btn" class="btn btn-secondary"
                                style="margin-top: 12px; width: 100%; border: 2px solid #1b1b1b; border-radius: 6px; box-shadow: 3px 3px 0px #1b1b1b; font-weight: 700;" disabled>
                                Verificar contraseña
                            </button>
                        </div>

                        <!-- Sub-step 2b: OTP (se muestra tras verificar contraseña) -->
                        <div id="delete-otp-section" class="hidden animated-section" style="margin-top: 16px;">
                            <div class="delete-step-divider" style="height: 2px; background: #1b1b1b; margin: 20px 0;"></div>
                            
                            <p class="delete-step-label" style="font-size: 0.95rem; font-weight: 600; color: #1b1b1b; display: flex; align-items: center; gap: 8px;">
                                <span class="delete-step-badge" style="background-color: var(--accent-red); color: white; display: inline-flex; align-items: center; justify-content: center; width: 22px; height: 22px; border-radius: 50%; font-size: 0.8rem; font-weight: 700; border: 1.5px solid #1b1b1b; box-shadow: 1px 1px 0px #1b1b1b;">3</span>
                                Código de verificación por correo:
                            </p>
                            <p id="delete-email-hint-pass" class="delete-email-hint" style="font-size: 0.95rem; margin: 10px 0; color: #333; font-weight: 500;"></p>

                            <div class="delete-otp-row" style="display: flex; align-items: center; justify-content: center; gap: 12px; margin-bottom: 15px; margin-top: 10px; flex-wrap: wrap;">
                                <button id="delete-send-otp-btn-pass" class="btn btn-primary delete-send-otp-btn" style="width: auto; margin-top: 0; padding: 10px 16px; display: inline-flex; align-items: center; gap: 8px; border: 2px solid #1b1b1b; border-radius: 6px; box-shadow: 3px 3px 0px #1b1b1b;">
                                    <i class="ph ph-envelope"></i> Enviar código por correo
                                </button>
                                <span id="delete-otp-countdown-pass" class="delete-otp-countdown hidden" style="font-weight: 700; color: var(--accent-red); font-size: 0.95rem;"></span>
                            </div>

                            <!-- Casillas divididas para código OTP -->
                            <div id="otp-container-password" class="otp-digits-container hidden">
                                <input type="text" class="otp-digit-input" data-index="0" maxlength="1" pattern="\d" inputmode="numeric" autocomplete="off">
                                <input type="text" class="otp-digit-input" data-index="1" maxlength="1" pattern="\d" inputmode="numeric" autocomplete="off">
                                <input type="text" class="otp-digit-input" data-index="2" maxlength="1" pattern="\d" inputmode="numeric" autocomplete="off">
                                <input type="text" class="otp-digit-input" data-index="3" maxlength="1" pattern="\d" inputmode="numeric" autocomplete="off">
                                <input type="text" class="otp-digit-input" data-index="4" maxlength="1" pattern="\d" inputmode="numeric" autocomplete="off">
                                <input type="text" class="otp-digit-input" data-index="5" maxlength="1" pattern="\d" inputmode="numeric" autocomplete="off">
                            </div>

                            <!-- Input oculto compatible con la lógica existente -->
                            <input type="hidden" id="delete-otp-input-pass">

                            <div id="delete-otp-status-pass" class="delete-otp-status hidden" style="text-align: center; font-weight: 700; margin-top: 10px; font-size: 0.95rem;"></div>
                        </div>
                    </div>
                </div>

            </div>

            <div class="modal-footer" style="margin-top: 25px; border-top: 2px solid #1b1b1b; padding-top: 15px; display: flex; justify-content: flex-end; gap: 12px;">
                <button id="cancel-delete-btn" class="btn btn-secondary" style="width: auto; margin: 0; padding: 10px 20px; border: 2px solid #1b1b1b; border-radius: 6px; box-shadow: 3px 3px 0px #1b1b1b; font-weight: 700;">Cancelar</button>
                <button id="confirm-delete-btn" class="btn btn-danger" style="width: auto; margin: 0; padding: 10px 20px; border: 2px solid #1b1b1b; border-radius: 6px; box-shadow: 3px 3px 0px var(--accent-red); font-weight: 700;" disabled>
                    <i class="ph ph-trash"></i> Eliminar Permanentemente
                </button>
            </div>
        </div>
    </div>


    <div id="toast-container"></div>

    <div id="stockify-global-modal" class="modal-overlay hidden">
        <div class="modal-content" style="max-width: 500px;">
            <div class="modal-header">
                <h2 id="prompt-title">Título del Prompt</h2>
                <p id="prompt-message" style="text-align: left;">Mensaje de ayuda.</p>
            </div>
            <form id="prompt-form">
                <div class="modal-body" style="padding: 1.5rem 1.8rem;">
                    <style>
                        #prompt-input {
                            width: 100% !important;
                            box-sizing: border-box !important;
                            padding: 10px 14px !important;
                            border: 1.5px solid #1b1b1b !important;
                            border-radius: 8px !important;
                            font-size: 1rem !important;
                            font-family: 'Satoshi', sans-serif !important;
                        }
                    </style>
                    <input type="text" id="prompt-input" placeholder="Escribí acá..." required>
                </div>
                <div class="modal-footer">
                    <button type="button" id="prompt-cancel-btn" class="btn btn-secondary">Cancelar</button>
                    <button type="submit" id="prompt-confirm-btn" class="btn btn-primary">Confirmar</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Modal de Detalles de Historial (Auditoría) -->
    <style>
        .history-modal-content {
            box-shadow: 8px 8px 0px #1b1b1b;
            transition: box-shadow 0.3s ease;
        }

        .history-modal-content:hover {
            box-shadow: 8px 8px 0px var(--accent-color);
        }

        .history-inner-box {
            background: #fff;
            border: 1px solid #eaeaea;
            border-radius: 12px;
            padding: 15px;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
            transition: border-color 0.2s ease;
        }

        .history-inner-box:hover {
            border-color: color-mix(in srgb, var(--accent-color) 30%, transparent);
        }

        .history-desc-box {
            background: color-mix(in srgb, var(--accent-color) 4%, transparent);
            border: 1px solid color-mix(in srgb, var(--accent-color) 20%, transparent);
            border-radius: 10px;
            padding: 15px;
            color: #333;
        }

        .history-extra-box {
            background: #fafafa;
            border: 1px dashed #ddd;
            border-radius: 10px;
            padding: 15px;
        }

        #close-history-detail-modal:hover {
            color: var(--accent-color) !important;
        }
    </style>
    <div id="history-detail-modal" class="modal-overlay hidden" style="z-index: 2100;">
        <div class="modal-content history-modal-content"
            style="max-width: 500px; padding: 0; border-radius: 16px; overflow: hidden; border: 2px solid #1b1b1b;">
            <div class="modal-header"
                style="background: #fff; border-bottom: 1px solid #eaeaea; padding: 20px 25px; position: relative;">
                <h2
                    style="margin: 0; font-size: 1.3rem; display: flex; align-items: center; gap: 10px; color: #1b1b1b;">
                    <i class="ph-bold ph-notebook" style="color: var(--accent-color);"></i> Detalle de Actividad
                </h2>
                <button class="modal-close-btn" id="close-history-detail-modal"
                    style="position: absolute; top: 20px; right: 20px; background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #999; transition: color 0.2s;">&times;</button>
            </div>
            <div class="modal-body"
                style="padding: 25px; display: flex; flex-direction: column; gap: 20px; max-height: 70vh; overflow-y: auto; background: #fafafa;">

                <!-- Tarjeta del Usuario Snapshot -->
                <div class="history-inner-box" style="display: flex; align-items: center; gap: 15px;">
                    <div id="history-detail-avatar"
                        style="width: 48px; height: 48px; background: color-mix(in srgb, var(--accent-color) 15%, transparent); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; font-weight: 800; border: 1px solid color-mix(in srgb, var(--accent-color) 30%, transparent); flex-shrink: 0; color: var(--accent-color);">
                        U
                    </div>
                    <div>
                        <div
                            style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: #a0a0a0; font-weight: 700; margin-bottom: 2px;">
                            Realizado por</div>
                        <div id="history-detail-username"
                            style="font-size: 1.05rem; font-weight: 700; color: #1b1b1b; line-height: 1.2;">Nombre
                            Usuario</div>
                        <div id="history-detail-role"
                            style="font-size: 0.75rem; font-weight: 700; color: var(--accent-color); margin-top: 4px; display: inline-block; padding: 3px 8px; background: color-mix(in srgb, var(--accent-color) 10%, transparent); border-radius: 6px;">
                            Rol</div>
                    </div>
                </div>

                <!-- Detalles Metadatos -->
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; padding: 0 5px;">
                    <div>
                        <div
                            style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: #a0a0a0; font-weight: 700; margin-bottom: 6px;">
                            Fecha y Hora</div>
                        <div id="history-detail-datetime" style="font-size: 0.95rem; font-weight: 600; color: #444;">
                            dd/mm/yyyy hh:mm</div>
                    </div>
                    <div>
                        <div
                            style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: #a0a0a0; font-weight: 700; margin-bottom: 6px;">
                            Módulo / Sección</div>
                        <div>
                            <span id="history-detail-section" class="history-badge"
                                style="font-size: 0.75rem; padding: 4px 10px; background: #eee; color: #555; border-radius: 6px; font-weight: 700; border: 1px solid #ddd;">SECCIÓN</span>
                        </div>
                    </div>
                </div>

                <!-- Descripción Principal -->
                <div>
                    <div
                        style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: #a0a0a0; font-weight: 700; margin-bottom: 8px; padding-left: 5px;">
                        Descripción</div>
                    <div id="history-detail-description" class="history-desc-box"
                        style="font-size: 1rem; font-weight: 600; line-height: 1.5; word-break: break-word;">
                        Detalle principal...
                    </div>
                </div>

                <!-- Descripción Secundaria (Extra) -->
                <div id="history-detail-extra-container" style="display: none;">
                    <div
                        style="font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; color: #a0a0a0; font-weight: 700; margin-bottom: 8px; padding-left: 5px;">
                        Información Adicional</div>
                    <div id="history-detail-extra-description" class="history-extra-box"
                        style="font-size: 0.9rem; color: #666; line-height: 1.5; white-space: pre-wrap; word-break: break-word;">
                        Detalles técnicos adicionales...
                    </div>
                </div>

            </div>
            <div class="modal-footer"
                style="background: #fff; border-top: 1px solid #eaeaea; padding: 15px 25px; border-radius: 0 0 16px 16px; display: flex; justify-content: flex-end;">
                <button type="button" class="btn btn-secondary" id="history-detail-close-btn"
                    style="min-width: 100px; margin: 0; background: #fafafa; border-color: #ddd; color: #555;">Cerrar</button>
            </div>
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
                        <input type="search" id="v2-product-search" name="p_search"
                            placeholder="Buscar producto para agregar..." spellcheck="false">
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

    <div id="detail-sale-modal" class="modal-overlay hidden"
        style="z-index: 20000; padding: 20px; align-items: center; justify-content: center;"
        onclick="if(event.target === this) { this.classList.add('hidden'); this.style.display='none'; }">
        <div class="modal-content"
            style="width: 400px; max-width: 100%; background: #fff; padding: 0; overflow: hidden; display: flex; flex-direction: column; border-radius: 16px; margin: auto; box-shadow: 0 10px 30px rgba(0,0,0,0.2);">
            <div class="modal-header"
                style="padding: 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center;">
                <h3 style="margin:0; font-size: 1.2rem;">Ticket de Venta</h3>
                <button class="modal-close-btn"
                    onclick="document.getElementById('detail-sale-modal').classList.add('hidden'); document.getElementById('detail-sale-modal').style.display='none';">&times;</button>
            </div>
            <div id="detail-modal-content"
                style="padding: 20px; overflow-y: auto; max-height: calc(100vh - 120px); background: #fff;">
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
        <div class="m-checker-sheet">

            <!-- DRAG HANDLE -->
            <div class="m-checker-handle"></div>

            <!-- HEADER -->
            <div class="m-checker-header">
                <div class="m-checker-header-left">
                    <div class="m-checker-icon-wrap">
                        <i class="ph-bold ph-barcode"></i>
                    </div>
                    <div>
                        <h2 class="m-checker-title">Consultar Precio</h2>
                        <div class="m-checker-subtitle">Precio y stock en tiempo real</div>
                    </div>
                </div>
                <div class="m-checker-header-right">
                    <button class="m-checker-close-btn" onclick="window.closePriceChecker()">
                        <i class="ph ph-x"></i>
                    </button>
                </div>
            </div>

            <!-- SEARCH BAR -->
            <div class="m-checker-search-wrap">
                <div class="m-checker-search-row">
                    <div class="m-checker-search-box">
                        <i class="ph ph-magnifying-glass m-checker-search-icon"></i>
                        <input type="text" id="checker-input" class="m-checker-input"
                            placeholder="Buscar producto o escanear código...">
                    </div>
                    <button onclick="window.performPriceCheck()" class="m-checker-search-btn">
                        <i class="ph-bold ph-magnifying-glass"></i>
                    </button>
                </div>
            </div>

            <!-- BODY -->
            <div class="m-checker-body" id="checker-body">

                <!-- LISTA DE RESULTADOS -->
                <div id="checker-list-container" class="m-checker-list-container" style="display: none;"></div>

                <!-- RESULTADO ÚNICO -->
                <div id="checker-result" class="m-checker-result hidden">
                    <div class="m-checker-result-name-wrap">
                        <i class="ph ph-tag m-checker-result-tag-icon"></i>
                        <h3 id="res-name" class="m-checker-result-name">Nombre del Producto</h3>
                    </div>

                    <div class="m-checker-price-card">
                        <small class="m-checker-price-label">Precio de Venta</small>
                        <h1 id="res-price" class="m-checker-price-value">$0.00</h1>
                    </div>

                    <div class="m-checker-stats-row">
                        <div class="m-checker-stat-box">
                            <i class="ph ph-stack"></i>
                            <small>Stock disponible</small>
                            <div id="res-stock" class="m-checker-stat-value">0</div>
                        </div>
                        <div class="m-checker-stat-box">
                            <i class="ph ph-receipt"></i>
                            <small>Precio de Compra</small>
                            <div id="res-cost" class="m-checker-stat-value">$0.00</div>
                        </div>
                    </div>

                    <button id="checker-back-btn" onclick="window.checkerBackToList()" class="m-checker-back-btn">
                        <i class="ph-bold ph-arrow-left"></i> Volver a la Lista
                    </button>
                </div>

                <!-- ERROR -->
                <div id="checker-error" class="m-checker-error hidden">
                    <i class="ph ph-warning-circle"></i>
                    <p>Producto no encontrado.</p>
                </div>

            </div>
        </div>
    </div>

    <!-- MODAL MÉTRICAS MÓVIL -->
    <div id="mobile-metrics-modal" class="mobile-modal-overlay hidden">
        <div class="mobile-modal-content" style="height: 90vh;">
            <div class="mobile-modal-header">
                <h2><i class="ph ph-chart-pie-slice"></i> Métricas Estratégicas</h2>
                <button class="close-icon"
                    onclick="document.getElementById('mobile-metrics-modal').classList.add('hidden')">&times;</button>
            </div>
            <div class="period-selector" style="padding: 15px 20px 5px 20px; margin-bottom: 0; gap: 10px;">
                <button id="metrics-btn-today" class="period-btn metrics-period-btn active"
                    onclick="window.loadMobileMetricsData('today')">
                    Hoy
                </button>
                <button id="metrics-btn-month" class="period-btn metrics-period-btn"
                    onclick="window.loadMobileMetricsData('month')">
                    Mes
                </button>
                <button id="metrics-btn-year" class="period-btn metrics-period-btn"
                    onclick="window.loadMobileMetricsData('year')">
                    Año
                </button>
                <button id="metrics-btn-total" class="period-btn metrics-period-btn"
                    onclick="window.loadMobileMetricsData('total')">
                    Total
                </button>
            </div>
            <div class="mobile-modal-body" style="padding: 20px; overflow-y: auto;">
                <div id="metrics-loader" style="text-align: center; padding: 40px;">
                    <i class="ph ph-spinner ph-spin" style="font-size: 2rem; color: var(--accent-color);"></i>
                    <p>Analizando datos del periodo...</p>
                </div>
                <div id="metrics-content" class="hidden">

                    <div style="display: flex; flex-direction: column; gap: 10px; margin-bottom: 15px;">
                        <div id="btn-show-sales-detail"
                            style="background: var(--accent-green-20); color: var(--color-black, #1b1b1b); padding: 15px; border-radius: 15px; border: 2px solid var(--accent-green); cursor: pointer; position: relative;">
                            <small style="opacity: 0.9;">Total Ventas</small>
                            <div id="mobile-m-sales" style="font-weight: 800; font-size: 1.3rem; margin-top: 5px;">$0.00
                            </div>
                            <i class="ph-bold ph-caret-right"
                                style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: var(--accent-green); font-size: 1.2rem;"></i>
                        </div>
                        <div id="btn-show-expenses-detail"
                            style="background: var(--accent-red-20); color: var(--color-black, #1b1b1b); padding: 15px; border-radius: 15px; border: 2px solid var(--accent-red); cursor: pointer; position: relative;">
                            <small style="opacity: 0.9;">Total Gastos</small>
                            <div id="mobile-m-expenses" style="font-weight: 800; font-size: 1.3rem; margin-top: 5px;">
                                $0.00</div>
                            <i class="ph-bold ph-caret-right"
                                style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); color: var(--accent-red); font-size: 1.2rem;"></i>
                        </div>
                    </div>

                    <div class="metric-card-mobile"
                        style="background: #fff; color: #1b1b1b; padding: 15px; border-radius: 15px; margin-bottom: 15px; border: 2px solid #1b1b1b; display: flex; justify-content: space-between; align-items: center;">
                        <div>
                            <small style="color: #888;">Balance Neto (<span
                                    id="mobile-m-period-label">Hoy</span>)</small>
                            <h2 style="margin: 0; font-size: 1.5rem; display: flex; align-items: center; gap: 8px;">
                                <span id="mobile-m-balance">$0.00</span>
                                <span id="mobile-m-balance-arrow"></span>
                            </h2>
                        </div>
                        <div id="mobile-m-balance-icon"
                            style="width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; background: #eee; border: 2px solid transparent;">
                            <i class="ph ph-scales" id="mobile-m-scales-icon" style="font-size: 1.5rem;"></i>
                        </div>
                    </div>

                    <div style="display: flex; gap: 10px; margin-bottom: 20px;">
                        <div
                            style="flex: 1; background: #f9f9f9; border: 2px solid #ddd; padding: 12px; border-radius: 12px; text-align: center;">
                            <small style="color: #888;">Ticket Prom.</small>
                            <div id="mobile-m-avg" style="font-weight: 800; font-size: 1rem; margin-top: 3px;">$0.00
                            </div>
                        </div>
                        <div
                            style="flex: 1; background: #f9f9f9; border: 2px solid #ddd; padding: 12px; border-radius: 12px; text-align: center;">
                            <small style="color: #888;">Operaciones</small>
                            <div id="mobile-m-count" style="font-weight: 800; font-size: 1rem; margin-top: 3px;">0</div>
                        </div>
                    </div>

                    <div class="metric-card-mobile"
                        style="background: #fff; color: var(--color-black, #1b1b1b); padding: 15px; border-radius: 15px; margin-bottom: 25px; border: 2px solid #1b1b1b;">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <small style="opacity: 0.9; font-weight: bold;">Stock Valorizado (Costo)</small>
                                <h2 id="mobile-m-valuation" style="margin: 5px 0 0 0; font-size: 1.6rem;">$0.00</h2>
                            </div>
                            <i class="ph ph-package" style="font-size: 2.2rem; color: var(--accent-color);"></i>
                        </div>
                    </div>

                    <h3
                        style="margin-bottom: 12px; border-left: 4px solid var(--accent-color); padding-left: 10px; font-size: 1.1rem;">
                        Top Productos Vendidos</h3>
                    <div id="mobile-m-top-products" style="margin-bottom: 20px;"></div>

                    <h3
                        style="margin-bottom: 12px; border-left: 4px solid var(--accent-green); padding-left: 10px; font-size: 1.1rem;">
                        Mejores Clientes</h3>
                    <div id="mobile-m-top-clients" style="margin-bottom: 20px;"></div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL LISTADO DE ENTIDADES MÓVIL (Clientes, Proveedores, Trabajadores) -->
    <div id="mobile-entity-list-modal" class="mobile-modal-overlay hidden" style="z-index: 10001;">
        <div class="m-entity-sheet">
            <div class="m-entity-handle"></div>
            <div class="m-entity-header">
                <div class="m-entity-header-left">
                    <div class="m-entity-icon-wrap"><i id="m-entity-icon" class="ph ph-identification-card"></i></div>
                    <div>
                        <div class="m-entity-title" id="m-entity-title">Trabajadores</div>
                        <div class="m-entity-subtitle" id="m-entity-subtitle">Lista del equipo</div>
                    </div>
                </div>
                <button class="m-entity-close-btn" onclick="window.closeMobileEntityList()" type="button">
                    <i class="ph-bold ph-x"></i>
                </button>
            </div>
            <div class="m-entity-body" id="m-entity-body"></div>
        </div>
    </div>

    <!-- DETALLE DE ENTIDAD MÓVIL -->
    <div id="mobile-entity-detail-modal" class="mobile-modal-overlay hidden" style="z-index: 10002;">
        <div class="m-entity-detail-sheet">
            <div class="m-entity-handle"></div>
            <div class="m-entity-header">
                <button class="m-entity-back-btn" onclick="window.closeMobileEntityDetail()" type="button">
                    <i class="ph-bold ph-arrow-left"></i>
                </button>
                <div class="m-entity-title" id="m-detail-title" style="flex:1; text-align:center; margin:0 8px;">Perfil
                </div>
                <button class="m-entity-close-btn" onclick="window.closeMobileEntityDetail(true)" type="button">
                    <i class="ph-bold ph-x"></i>
                </button>
            </div>
            <div class="m-entity-detail-hero">
                <div class="m-entity-detail-avatar" id="m-detail-avatar">?</div>
                <div style="flex:1; min-width:0;">
                    <div class="m-entity-detail-name" id="m-detail-name">—</div>
                    <div class="m-entity-detail-cat" id="m-detail-cat">Sin categoría</div>
                </div>
                <div id="m-detail-wa"></div>
            </div>
            <div class="m-entity-detail-body" id="m-entity-detail-body"></div>
        </div>
    </div>

    <!-- MODAL DETALLE DE OPERACIONES MÓVIL (SUB-MODAL) -->
    <div id="mobile-transaction-list-modal" class="mobile-modal-overlay hidden" style="z-index: 10001;">
        <div class="mobile-modal-content" style="height: 80vh; border-top: 4px solid var(--accent-color);">
            <div class="mobile-modal-header">
                <h2 id="m-trans-title">Detalle de Operaciones</h2>
                <button class="close-icon"
                    onclick="document.getElementById('mobile-transaction-list-modal').classList.add('hidden')">&times;</button>
            </div>
            <div class="mobile-modal-body" id="m-trans-body" style="padding: 15px; overflow-y: auto;">
            </div>
        </div>
    </div>

    <!-- MODAL HISTORIAL MÓVIL -->
    <div id="mobile-history-modal" class="mobile-modal-overlay hidden">
        <div class="m-history-sheet">

            <!-- DRAG HANDLE -->
            <div class="m-history-handle"></div>

            <!-- HEADER -->
            <div class="m-history-header">
                <div class="m-history-header-left">
                    <div class="m-history-icon-wrap">
                        <i class="ph-bold ph-clock-counter-clockwise"></i>
                    </div>
                    <div>
                        <h2 class="m-history-title">Historial</h2>
                        <div class="m-history-subtitle">Movimientos del inventario</div>
                    </div>
                </div>
                <div class="m-history-header-right">
                    <button class="m-history-refresh-btn" id="m-history-refresh-btn"
                        onclick="window.openMobileHistory()">
                        <i class="ph ph-arrows-clockwise"></i>
                    </button>
                    <button class="m-history-close-btn" onclick="window.closeMobileHistory()">
                        <i class="ph ph-x"></i>
                    </button>
                </div>
            </div>

            <!-- FILTER TABS -->
            <div class="m-history-tabs" id="m-history-tabs">
                <button class="m-history-tab active" id="m-hist-tab-all" onclick="window.filterMobileHistory('all')">
                    <i class="ph ph-squares-four"></i> Todo
                </button>
                <button class="m-history-tab" id="m-hist-tab-product" onclick="window.filterMobileHistory('product')">
                    <i class="ph ph-package"></i> Productos
                </button>
                <button class="m-history-tab" id="m-hist-tab-sale" onclick="window.filterMobileHistory('sale')">
                    <i class="ph ph-shopping-cart"></i> Ventas
                </button>
                <button class="m-history-tab" id="m-hist-tab-purchase" onclick="window.filterMobileHistory('purchase')">
                    <i class="ph ph-truck"></i> Compras / Gastos
                </button>
                <button class="m-history-tab" id="m-hist-tab-collaborator"
                    onclick="window.filterMobileHistory('collaborator')">
                    <i class="ph ph-users-three"></i> Equipo
                </button>
                <button class="m-history-tab" id="m-hist-tab-customer" onclick="window.filterMobileHistory('customer')">
                    <i class="ph ph-user"></i> Clientes
                </button>
                <button class="m-history-tab" id="m-hist-tab-provider" onclick="window.filterMobileHistory('provider')">
                    <i class="ph ph-factory"></i> Proveedores
                </button>
                <button class="m-history-tab" id="m-hist-tab-employee" onclick="window.filterMobileHistory('employee')">
                    <i class="ph ph-identification-card"></i> Empleados
                </button>
                <button class="m-history-tab" id="m-hist-tab-payment_method"
                    onclick="window.filterMobileHistory('payment_method')">
                    <i class="ph ph-credit-card"></i> Métodos Pago
                </button>
            </div>

            <!-- BODY -->
            <div class="m-history-body" id="mobile-history-body">
                <div class="m-history-loading">
                    <i class="ph ph-spinner ph-spin"></i>
                    <span>Cargando historial...</span>
                </div>
            </div>

        </div>
    </div>


    <!-- MODAL COLABORADORES MÓVIL -->
    <div id="mobile-collaborators-modal" class="mobile-modal-overlay hidden" style="z-index: 10001;">
        <div class="m-collab-sheet">

            <!-- DRAG HANDLE -->
            <div class="m-collab-handle"></div>

            <!-- HEADER -->
            <div class="m-collab-header">
                <div class="m-collab-header-left">
                    <div class="m-collab-icon-wrap">
                        <i class="ph-bold ph-users-three"></i>
                    </div>
                    <div>
                        <h2 class="m-collab-title">Colaboradores</h2>
                        <div id="m-collab-quota-badge" class="m-collab-quota-badge">
                            <i class="ph ph-spinner ph-spin"></i>
                        </div>
                    </div>
                </div>
                <div class="m-collab-header-right">
                    <button id="m-collab-invite-btn" class="m-collab-invite-btn" style="display:none;">
                        <i class="ph-bold ph-user-plus"></i>
                    </button>
                    <button class="m-collab-close-btn" onclick="window.closeMobileCollaborators()">
                        <i class="ph ph-x"></i>
                    </button>
                </div>
            </div>

            <!-- DEBT WARNING BANNER MÓVIL -->
            <div id="m-collab-debt-banner" class="m-collab-debt-banner hidden">
                <i class="ph-bold ph-warning"></i>
                <div>
                    <strong>Pago Pendiente</strong>
                    <span id="m-collab-debt-text">Tenés deuda pendiente por slots.</span>
                </div>
                <a id="m-collab-pay-btn" href="#" target="_blank" class="m-collab-pay-btn">
                    <i class="ph-bold ph-whatsapp-logo"></i>
                </a>
            </div>

            <!-- TABS -->
            <div class="m-collab-tabs" id="m-collab-tabs">
                <button class="m-collab-tab active" onclick="window.switchMobileCollabTabNative('list')"
                    id="m-tab-list">
                    <i class="ph ph-users"></i> Equipo
                </button>
                <button class="m-collab-tab" onclick="window.switchMobileCollabTabNative('permissions')"
                    id="m-tab-permissions">
                    <i class="ph ph-sliders"></i> Permisos
                </button>
            </div>

            <!-- BODY -->
            <div class="m-collab-body">

                <!-- PANEL: LISTA DE EQUIPO -->
                <div id="m-collab-list-panel" class="m-collab-panel">
                    <div class="m-collab-loading">
                        <i class="ph ph-spinner ph-spin"></i>
                        <span>Cargando equipo...</span>
                    </div>
                </div>

                <!-- PANEL: PERMISOS -->
                <div id="m-collab-perms-panel" class="m-collab-panel hidden">
                    <div class="m-collab-loading">
                        <i class="ph ph-spinner ph-spin"></i>
                        <span>Cargando permisos...</span>
                    </div>
                </div>

            </div>
        </div>
    </div>


    <!-- MODAL ENVÍOS MÓVIL -->
    <div id="mobile-deliveries-modal" class="mobile-modal-overlay hidden" style="z-index: 10002;">
        <div class="m-deliv-sheet">

            <!-- DRAG HANDLE -->
            <div class="m-deliv-handle"></div>

            <!-- HEADER -->
            <div class="m-deliv-header">
                <div class="m-deliv-header-left">
                    <div class="m-deliv-icon-wrap">
                        <i class="ph-bold ph-truck"></i>
                    </div>
                    <div>
                        <h2 class="m-deliv-title">Envíos</h2>
                        <div id="m-deliv-count-badge" class="m-deliv-count-badge">
                            <i class="ph ph-spinner ph-spin"></i>
                        </div>
                    </div>
                </div>
                <div class="m-deliv-header-right">
                    <button id="m-deliv-refresh-btn" class="m-deliv-refresh-btn"
                        onclick="window._loadMobileDeliveriesData()">
                        <i class="ph ph-arrows-clockwise"></i>
                    </button>
                    <button class="m-deliv-close-btn" onclick="window.closeMobileDeliveries()">
                        <i class="ph ph-x"></i>
                    </button>
                </div>
            </div>

            <!-- TABS (solo para admin/owner) -->
            <div class="m-deliv-tabs" id="m-deliv-tabs">
                <button class="m-deliv-tab active" onclick="window.switchMobileDelivTab('pending')"
                    id="m-deliv-tab-pending">
                    <i class="ph ph-clock"></i> Pendientes
                </button>
                <button class="m-deliv-tab" onclick="window.switchMobileDelivTab('completed')"
                    id="m-deliv-tab-completed">
                    <i class="ph ph-check-circle"></i> Finalizados
                </button>
            </div>

            <!-- BODY -->
            <div class="m-deliv-body">
                <div id="m-deliv-list-panel" class="m-deliv-panel">
                    <div class="m-deliv-loading">
                        <i class="ph ph-spinner ph-spin"></i>
                        <span>Cargando envíos...</span>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- MODAL SELECTOR DE INVENTARIO MÓVIL -->
    <div id="mobile-inventory-selector-modal" class="mobile-modal-overlay hidden">
        <div class="mobile-modal-content" style="height: 80vh; border-top: 6px solid var(--accent-color);">
            <div class="mobile-modal-header">
                <h2><i class="ph ph-buildings"></i> Mis Negocios</h2>
                <button class="close-icon"
                    onclick="document.getElementById('mobile-inventory-selector-modal').classList.add('hidden')">&times;</button>
            </div>
            <div class="mobile-modal-body" id="m-inv-list-container"
                style="padding: 20px; overflow-y: auto; display: grid; gap: 15px;">
                <!-- Se llena vía JS -->
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script src="https://cdn.sheetjs.com/xlsx-latest/package/dist/xlsx.full.min.js"></script>

    <script type="module" src="assets/js/users/users.js?v=1.2"></script>

    <script type="module" src="assets/js/import.js?v=1.4"></script>
    <script type="module" src="assets/js/export-excel.js?v=1.3"></script>
    <script type="module" src="assets/js/dashboard.js?v=2.10"></script>
    <script type="module" src="assets/js/sales/sales.js?v=2.3"></script>
    <script type="module" src="assets/js/purchases/purchases.js?v=2.3"></script>
    <script type="module" src="assets/js/history/history.js?v=1.3"></script>
    <script type="module" src="assets/js/payment/payment.js?v=1.2"></script>
    <script src="assets/js/tutorials.js?v=1.0"></script>

    <script type="module">
        import { pop_ups } from './assets/js/notifications/pop-up.js?v=3.0';
        window.showLockedFeatureToast = (featureName) => {
            pop_ups.system(`Funcionabilidad no incluída en su versión de pago Básico: ${featureName}`, 'Acceso Restringido');
        };
    </script>

    <script type="module">
        import { initMobileApp } from './assets/js/mobile/mobile-app.js?v=2.6';
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                initMobileApp();
            }, 100);
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
</body>

</html>