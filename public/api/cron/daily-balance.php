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

    // Obtener usuarios con suscripción >= 2
    $stmtUsers = $db->query("SELECT id, email, full_name, cell FROM users WHERE subscription_active >= 2 AND (email IS NOT NULL OR cell IS NOT NULL)");
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
                // Hay movimientos: resetear inactividad a 0
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
                // No hay movimientos: incrementar inactividad
                $newInactivity = $inactivityDays + 1;

                if ($newInactivity >= 10) {
                    // Apagar reportes
                    $db->prepare("UPDATE inventories SET inactivity_days = ?, report_enabled = 0 WHERE id = ?")->execute([$newInactivity, $invId]);
                    $invName .= " (Suspendido por inactividad)";
                } else {
                    $db->prepare("UPDATE inventories SET inactivity_days = ? WHERE id = ?")->execute([$newInactivity, $invId]);
                }

                // Enviar aviso de "Sin movimientos"
                if (!empty($u['email'])) {
                    if ($mailService->sendDailyBalance($u['email'], $u['full_name'] ?? 'Usuario', $now->format('d/m/Y'), 0, 0, 0, $invName)) {
                        $emailsSent++;
                    }
                }
                if (!empty($u['cell'])) {
                    if ($whatsappService->sendDailyBalance($u['cell'], $u['full_name'] ?? 'Usuario', $now->format('d/m/Y'), 0, 0, 0, $invName)) {
                        $whatsappsSent++;
                    } else {
                        $whatsappErrors[] = "Error para {$u['cell']}: " . $whatsappService->lastError;
                    }
                }
            }
        }
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