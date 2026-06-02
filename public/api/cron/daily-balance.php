<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    $root = dirname(__DIR__, 3);
    require_once $root . '/vendor/autoload.php';
    require_once $root . '/src/core/Database.php';
    require_once $root . '/src/Services/MailService.php';
    require_once $root . '/src/Services/WhatsappService.php';

    $secretToken = $_ENV['CRON_SECRET_TOKEN'] ?? null;
    if (!$secretToken || !isset($_GET['token']) || $_GET['token'] !== $secretToken) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acceso denegado. Token invalido o no configurado.']);
        exit;
    }

    date_default_timezone_set('America/Argentina/Buenos_Aires');
    $tz = new DateTimeZone('America/Argentina/Buenos_Aires');
    $now = new DateTime('now', $tz);

    $start = new DateTime($now->format('Y-m-d 00:00:00'), $tz);
    $end = (clone $start)->modify('+1 day');
    $startDate = $start->format('Y-m-d H:i:s');
    $endDate = $end->format('Y-m-d H:i:s');

    $db = \App\core\Database::getInstance();
    $db->query("SET time_zone = '-03:00'");

    $mailService = new \App\Services\MailService();
    $whatsappService = new \App\Services\WhatsappService();

    // Limpieza global de suscripciones expiradas antes de procesar
    $db->query("UPDATE users SET subscription_active = 0 WHERE subscription_active > 0 AND subscription_expires_at IS NOT NULL AND subscription_expires_at < NOW()");

    // Obtener usuarios con suscripción >= 1
    $stmtUsers = $db->query("SELECT id, email, full_name, cell FROM users WHERE subscription_active >= 1 AND (email IS NOT NULL OR cell IS NOT NULL)");
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

    $emailsSent = 0;
    $whatsappsSent = 0;
    $whatsappErrors = [];

    foreach ($users as $u) {
        $userId = $u['id'];

        // Obtener inventarios del usuario que tengan los reportes activados
        $stmtInv = $db->prepare("SELECT id, name, inactivity_days FROM inventories WHERE user_id = ? AND report_enabled = 1");
        $stmtInv->execute([$userId]);
        $inventories = $stmtInv->fetchAll(PDO::FETCH_ASSOC);

        foreach ($inventories as $inv) {
            $invId = $inv['id'];
            $invName = $inv['name'];
            $inactivityDays = (int) $inv['inactivity_days'];

            $stmtSales = $db->prepare("
                SELECT SUM(total_amount) as total_sales, COUNT(*) as count_sales
                FROM sales
                WHERE user_id = ? AND inventory_id = ? AND sale_date >= ? AND sale_date < ?
            ");
            $stmtSales->execute([$userId, $invId, $startDate, $endDate]);
            $salesData = $stmtSales->fetch(PDO::FETCH_ASSOC);

            $stmtPurchases = $db->prepare("
                SELECT SUM(total) as total_purchases, COUNT(*) as count_purchases
                FROM purchases
                WHERE user_id = ? AND inventory_id = ? AND created_at >= ? AND created_at < ?
            ");
            $stmtPurchases->execute([$userId, $invId, $startDate, $endDate]);
            $purchasesData = $stmtPurchases->fetch(PDO::FETCH_ASSOC);

            $totalSales = (float) ($salesData['total_sales'] ?? 0);
            $totalPurchases = (float) ($purchasesData['total_purchases'] ?? 0);
            $balance = $totalSales - $totalPurchases;

            $countSales = (int) ($salesData['count_sales'] ?? 0);
            $countPurchases = (int) ($purchasesData['count_purchases'] ?? 0);

            if ($countSales > 0 || $countPurchases > 0) {
                // Hay movimientos: resetear inactividad y notificar por ambos canales
                $db->prepare("UPDATE inventories SET inactivity_days = 0 WHERE id = ?")->execute([$invId]);

                if (!empty($u['email'])) {
                    if ($mailService->sendDailyBalance($u['email'], $u['full_name'] ?? 'Usuario', $now->format('d/m/Y'), $totalSales, $totalPurchases, $balance, $invName)) {
                        $emailsSent++;
                    }
                }
                if (!empty($u['cell'])) {
                    if ($whatsappService->sendDailyBalance($u['cell'], $u['full_name'] ?? 'Usuario', $now->format('d/m/Y'), $totalSales, $totalPurchases, $balance, $invName)) {
                        $whatsappsSent++;
                    } else {
                        $whatsappErrors[] = "Error para {$u['cell']}: " . $whatsappService->lastError;
                    }
                }
            } else {
                // Sin movimientos: incrementar contador de inactividad
                $newInactivity = $inactivityDays + 1;

                if ($newInactivity >= 10) {
                    $db->prepare("UPDATE inventories SET inactivity_days = ?, report_enabled = 0 WHERE id = ?")->execute([$newInactivity, $invId]);
                    $invName .= " (Suspendido por inactividad)";
                } else {
                    $db->prepare("UPDATE inventories SET inactivity_days = ? WHERE id = ?")->execute([$newInactivity, $invId]);
                }

                // Solo email para "sin movimientos" — NO WhatsApp (evitar spam con $0)
                if (!empty($u['email'])) {
                    if ($mailService->sendDailyBalance($u['email'], $u['full_name'] ?? 'Usuario', $now->format('d/m/Y'), 0, 0, 0, $invName)) {
                        $emailsSent++;
                    }
                }
                // WhatsApp silencioso cuando no hubo actividad
            }
        }
    }

    // --- Lógica de Retención de Logs de Auditoría ---
    try {
        // 1. Eliminar logs de usuarios con plan Básico (o menor) antiguos de 60 días
        $db->exec("
            DELETE al FROM activity_logs al
            INNER JOIN inventories i ON al.inventory_id = i.id
            INNER JOIN users u ON i.user_id = u.id
            WHERE (u.subscription_active IS NULL OR u.subscription_active <= 1)
              AND al.created_at < DATE_SUB(NOW(), INTERVAL 60 DAY)
        ");

        // 2. Eliminar logs de usuarios con plan Profesional antiguos de 365 días
        $db->exec("
            DELETE al FROM activity_logs al
            INNER JOIN inventories i ON al.inventory_id = i.id
            INNER JOIN users u ON i.user_id = u.id
            WHERE u.subscription_active = 2
              AND al.created_at < DATE_SUB(NOW(), INTERVAL 365 DAY)
        ");

        // 3. Eliminar logs huérfanos de más de 60 días (por si el inventario o usuario ya no existe)
        $db->exec("
            DELETE al FROM activity_logs al
            LEFT JOIN inventories i ON al.inventory_id = i.id
            LEFT JOIN users u ON i.user_id = u.id
            WHERE (i.id IS NULL OR u.id IS NULL)
              AND al.created_at < DATE_SUB(NOW(), INTERVAL 60 DAY)
        ");
    } catch (\Throwable $retentionEx) {
        error_log("Error en limpieza de retención de logs: " . $retentionEx->getMessage());
    }

    echo json_encode([
        'success' => true,
        'message' => 'Cron de Balance Diario ejecutado con exito.',
        'emails_sent' => $emailsSent,
        'whatsapps_sent' => $whatsappsSent,
        'whatsapp_errors' => array_slice($whatsappErrors, 0, 3) // Mostrar solo los primeros 3 errores
    ]);

} catch (Throwable $e) {
    error_log("Error Cron Daily Balance: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ocurrio un error interno. Revisa los logs.']);
}
?>