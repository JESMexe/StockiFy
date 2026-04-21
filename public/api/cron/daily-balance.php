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

    $secretToken = 'STOCKIFY_CRON_2026';
    if (!isset($_GET['token']) || $_GET['token'] !== $secretToken) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acceso denegado. Token invalido.']);
        exit;
    }

    date_default_timezone_set('America/Argentina/Buenos_Aires');
    $tz = new DateTimeZone('America/Argentina/Buenos_Aires');
    $now = new DateTime('now', $tz);

    $start = new DateTime($now->format('Y-m-d 00:00:00'), $tz);
    $end   = (clone $start)->modify('+1 day');
    $startDate = $start->format('Y-m-d H:i:s');
    $endDate   = $end->format('Y-m-d H:i:s');

    $db = \App\core\Database::getInstance();
    $db->query("SET time_zone = '-03:00'");
    
    $mailService = new \App\Services\MailService();
    $whatsappService = new \App\Services\WhatsappService();

    $stmtUsers = $db->query("SELECT id, email, full_name, cell FROM users WHERE subscription_active > 0 AND (email IS NOT NULL OR cell IS NOT NULL)");
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

    $emailsSent = 0;
    $whatsappsSent = 0;

    foreach ($users as $u) {
        $userId = $u['id'];
        
        $stmtSales = $db->prepare("
            SELECT SUM(total_amount) as total_sales, COUNT(*) as count_sales
            FROM sales
            WHERE user_id = ? AND sale_date >= ? AND sale_date < ?
        ");
        $stmtSales->execute([$userId, $startDate, $endDate]);
        $salesData = $stmtSales->fetch(PDO::FETCH_ASSOC);

        $stmtPurchases = $db->prepare("
            SELECT SUM(total) as total_purchases, COUNT(*) as count_purchases
            FROM purchases
            WHERE user_id = ? AND created_at >= ? AND created_at < ?
        ");
        $stmtPurchases->execute([$userId, $startDate, $endDate]);
        $purchasesData = $stmtPurchases->fetch(PDO::FETCH_ASSOC);

        $totalSales = (float)($salesData['total_sales'] ?? 0);
        $totalPurchases = (float)($purchasesData['total_purchases'] ?? 0);
        $balance = $totalSales - $totalPurchases;
        
        $countSales = (int)($salesData['count_sales'] ?? 0);
        $countPurchases = (int)($purchasesData['count_purchases'] ?? 0);

        if ($countSales > 0 || $countPurchases > 0) {
            if (!empty($u['email'])) {
                $mailService->sendDailyBalance(
                    $u['email'], 
                    $u['full_name'] ?? 'Usuario', 
                    $now->format('d/m/Y'), 
                    $totalSales, 
                    $totalPurchases, 
                    $balance
                );
                $emailsSent++;
            }
            if (!empty($u['cell'])) {
                $whatsappService->sendDailyBalance(
                    $u['cell'], 
                    $u['full_name'] ?? 'Usuario', 
                    $now->format('d/m/Y'), 
                    $totalSales, 
                    $totalPurchases, 
                    $balance,
                    'General'
                );
                $whatsappsSent++;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Cron de Balance Diario ejecutado con exito.',
        'emails_sent' => $emailsSent,
        'whatsapps_sent' => $whatsappsSent
    ]);

} catch (Throwable $e) {
    error_log("Error Cron Daily Balance: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ocurrio un error interno. Revisa los logs.']);
}
?>
