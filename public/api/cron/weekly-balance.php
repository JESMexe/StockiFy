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
        echo json_encode(['success' => false, 'message' => 'Acceso denegado. Token inválido o no configurado.']);
        exit;
    }

    date_default_timezone_set('America/Argentina/Buenos_Aires');
    $tz = new DateTimeZone('America/Argentina/Buenos_Aires');
    $now = new DateTime('now', $tz);

    // Rango semanal: últimos 7 días completos (sin contar hoy)
    $end = new DateTime($now->format('Y-m-d 00:00:00'), $tz);
    $start = (clone $end)->modify('-7 days');
    
    $startDate = $start->format('Y-m-d H:i:s');
    $endDate = $end->format('Y-m-d H:i:s');
    
    // Rango legible para el correo, ej: "14/05/2026 al 20/05/2026"
    $dateRange = $start->format('d/m/Y') . ' al ' . (clone $end)->modify('-1 day')->format('d/m/Y');

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

        // Obtener inventarios del usuario con reportes activos
        $stmtInv = $db->prepare("SELECT id, name FROM inventories WHERE user_id = ? AND report_enabled = 1");
        $stmtInv->execute([$userId]);
        $inventories = $stmtInv->fetchAll(PDO::FETCH_ASSOC);

        foreach ($inventories as $inv) {
            $invId = $inv['id'];
            $invName = $inv['name'];

            // Calcular ventas semanales
            $stmtSales = $db->prepare("
                SELECT SUM(total_amount) as total_sales, COUNT(*) as count_sales
                FROM sales
                WHERE user_id = ? AND inventory_id = ? AND sale_date >= ? AND sale_date < ?
            ");
            $stmtSales->execute([$userId, $invId, $startDate, $endDate]);
            $salesData = $stmtSales->fetch(PDO::FETCH_ASSOC);

            // Calcular compras/gastos semanales
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
                // Hay movimientos: notificar por ambos canales (Email + WhatsApp)
                if (!empty($u['email'])) {
                    if ($mailService->sendWeeklyBalance($u['email'], $u['full_name'] ?? 'Usuario', $dateRange, $totalSales, $totalPurchases, $balance, $invName)) {
                        $emailsSent++;
                    }
                }
                if (!empty($u['cell'])) {
                    if ($whatsappService->sendWeeklyBalance($u['cell'], $u['full_name'] ?? 'Usuario', $totalSales, $totalPurchases, $balance, $invName)) {
                        $whatsappsSent++;
                    } else {
                        $whatsappErrors[] = "Error para {$u['cell']}: " . $whatsappService->lastError;
                    }
                }
            } else {
                // Sin movimientos: solo enviar correo informativo, omitir WhatsApp para evitar spam y costos
                if (!empty($u['email'])) {
                    if ($mailService->sendWeeklyBalance($u['email'], $u['full_name'] ?? 'Usuario', $dateRange, 0, 0, 0, $invName)) {
                        $emailsSent++;
                    }
                }
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Cron de Balance Semanal ejecutado con éxito.',
        'emails_sent' => $emailsSent,
        'whatsapps_sent' => $whatsappsSent,
        'whatsapp_errors' => array_slice($whatsappErrors, 0, 3)
    ]);

} catch (Throwable $e) {
    error_log("Error Cron Weekly Balance: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ocurrió un error interno. Revisa los logs.']);
}
?>
