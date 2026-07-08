<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/core/Database.php';
require_once __DIR__ . '/../../../src/Services/WhatsappService.php';

use App\core\Database;
use App\Services\WhatsappService;

try {
    session_start();

    date_default_timezone_set('America/Argentina/Buenos_Aires');

    $user = getCurrentUser();
    if (!$user) throw new Exception("No autorizado");

    $inventoryId = $_SESSION['active_inventory_id'] ?? null;
    if (!$inventoryId) throw new Exception("Inventario no seleccionado");

    // El número de teléfono destino (cell del Owner o del usuario actual, según el flujo)
    // El cierre de caja se le debe notificar al dueño (Owner) del inventario.
    $db = Database::getInstance();
    $db->query("SET time_zone = '-03:00'");

    // Obtener información del dueño del inventario activo
    $stmtOwner = $db->prepare("
        SELECT u.id, u.username, u.full_name, u.cell 
        FROM inventories inv 
        INNER JOIN users u ON inv.user_id = u.id 
        WHERE inv.id = ? LIMIT 1
    ");
    $stmtOwner->execute([$inventoryId]);
    $owner = $stmtOwner->fetch(PDO::FETCH_ASSOC);

    if (!$owner || empty($owner['cell'])) {
        throw new Exception("El propietario de este inventario no tiene registrado un número de teléfono celular.");
    }

    // Obtener el nombre del inventario activo
    $stmtInv = $db->prepare("SELECT name FROM inventories WHERE id = ? LIMIT 1");
    $stmtInv->execute([$inventoryId]);
    $invName = $stmtInv->fetchColumn() ?: 'General';

    // Obtener periodo de la consulta
    $period = $_GET['period'] ?? 'today';

    $tz = new DateTimeZone('America/Argentina/Buenos_Aires');
    $now = new DateTime('now', $tz);

    if ($period === 'week') {
        $start = clone $now;
        $start->setISODate((int)$now->format('o'), (int)$now->format('W'), 1)->setTime(0, 0, 0);
        $end   = (clone $start)->modify('+1 week');
    } else if ($period === 'month') {
        $start = new DateTime($now->format('Y-m-01 00:00:00'), $tz);
        $end   = (clone $start)->modify('+1 month');
    } else if ($period === 'year') {
        $start = new DateTime($now->format('Y-01-01 00:00:00'), $tz);
        $end   = (clone $start)->modify('+1 year');
    } else {
        $start = new DateTime($now->format('Y-m-d 00:00:00'), $tz);
        $end   = (clone $start)->modify('+1 day');
    }

    $startDate = $start->format('Y-m-d H:i:s');
    $endDate   = $end->format('Y-m-d H:i:s');

    // Consultar Ventas
    $stmtSales = $db->prepare("
        SELECT SUM(total_amount) as total_sales
        FROM sales
        WHERE inventory_id = ? AND sale_date >= ? AND sale_date < ?
    ");
    $stmtSales->execute([$inventoryId, $startDate, $endDate]);
    $totalSales = (float)($stmtSales->fetchColumn() ?? 0);

    // Consultar Compras / Gastos
    $stmtPurchases = $db->prepare("
        SELECT SUM(total) as total_purchases
        FROM purchases
        WHERE inventory_id = ? AND created_at >= ? AND created_at < ?
    ");
    $stmtPurchases->execute([$inventoryId, $startDate, $endDate]);
    $totalPurchases = (float)($stmtPurchases->fetchColumn() ?? 0);

    $balance = $totalSales - $totalPurchases;

    // Formatear fecha y hora actual (solo para envío manual por turnos de trabajo)
    $formattedDateWithTime = $now->format('d/m/Y H:i');

    $whatsappService = new WhatsappService();
    $targetPhone = $owner['cell'];
    $ownerName = $owner['full_name'] ?: $owner['username'] ?: 'Propietario';

    if ($period === 'today') {
        $success = $whatsappService->sendDailyBalance(
            $targetPhone,
            $ownerName,
            $formattedDateWithTime,
            $totalSales,
            $totalPurchases,
            $balance,
            $invName
        );
    } else {
        $timeSpans = [
            'week'  => 'Semanal',
            'month' => 'Mensual',
            'year'  => 'Anual',
        ];
        $timeSpanText = $timeSpans[$period] ?? 'Mensual';

        $success = $whatsappService->sendDynamicClosureReport(
            $targetPhone,
            $ownerName,
            $timeSpanText,
            $totalSales,
            $totalPurchases,
            $balance,
            $invName
        );
    }

    if (!$success) {
        throw new Exception("Error al enviar WhatsApp: " . $whatsappService->lastError);
    }

    // Registrar actividad en el inventario
    require_once __DIR__ . '/../../../src/helpers/ActivityLogger.php';
    \App\helpers\ActivityLogger::log(
        'Caja',
        'manual_report_sent',
        'cash_register',
        (string)$inventoryId,
        "Se notificó el cierre de caja manual a {$ownerName} por WhatsApp.",
        "Monto: Ventas $" . number_format($totalSales, 0, ',', '.') . " | Gastos $" . number_format($totalPurchases, 0, ',', '.') . " | Balance $" . number_format($balance, 0, ',', '.') . " (Enviado a las " . $now->format('H:i') . "h).",
        (int)$inventoryId,
        (int)$user['id']
    );

    echo json_encode([
        'success' => true,
        'message' => 'Resumen de caja notificado con éxito al WhatsApp del propietario.'
    ]);

} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
