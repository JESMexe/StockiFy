<?php
// public/api/cron/daily-balance.php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    $root = dirname(__DIR__, 3);
    require_once $root . '/vendor/autoload.php';
    require_once $root . '/src/core/Database.php';
    require_once $root . '/src/Services/MailService.php';

    // Basic security token to prevent external spam execution
    $secretToken = 'STOCKIFY_CRON_2026';
    if (!isset($_GET['token']) || $_GET['token'] !== $secretToken) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acceso denegado. Token invalido.']);
        exit;
    }

    date_default_timezone_set('America/Argentina/Buenos_Aires');
    $tz = new DateTimeZone('America/Argentina/Buenos_Aires');
    $now = new DateTime('now', $tz);

    // Fechas de Hoy exactas
    $start = new DateTime($now->format('Y-m-d 00:00:00'), $tz);
    $end   = (clone $start)->modify('+1 day');
    $startDate = $start->format('Y-m-d H:i:s');
    $endDate   = $end->format('Y-m-d H:i:s');

    $db = \App\core\Database::getInstance();
    $db->query("SET time_zone = '-03:00'");
    
    $mailService = new \App\Services\MailService();

    // Buscar todos los usuarios con suscripcion activa
    $stmtUsers = $db->query("SELECT id, email, full_name FROM users WHERE subscription_active > 0 AND email IS NOT NULL AND email != ''");
    $users = $stmtUsers->fetchAll(PDO::FETCH_ASSOC);

    $emailsSent = 0;

    foreach ($users as $u) {
        $userId = $u['id'];
        
        // Ventas (Ingresos del dia)
        $stmtSales = $db->prepare("
            SELECT SUM(total_amount) as total_sales, COUNT(*) as count_sales
            FROM sales
            WHERE user_id = ? AND sale_date >= ? AND sale_date < ?
        ");
        $stmtSales->execute([$userId, $startDate, $endDate]);
        $salesData = $stmtSales->fetch(PDO::FETCH_ASSOC);

        // Compras (Egresos del dia)
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

        // Enviar reporte SOLAMENTE si hubo flujo de dinero ese dia
        if ($countSales > 0 || $countPurchases > 0) {
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
    }

    echo json_encode([
        'success' => true,
        'message' => 'Cron de Balance Diario ejecutado con exito.',
        'emails_sent' => $emailsSent
    ]);

} catch (Throwable $e) {
    error_log("Error Cron Daily Balance: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Ocurrio un error interno. Revisa los logs.']);
}
?>
