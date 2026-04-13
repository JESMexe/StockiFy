<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    $root = dirname(__DIR__, 3);
    require_once $root . '/vendor/autoload.php';
    require_once $root . '/src/helpers/auth_helper.php';

    if (!function_exists('getCurrentUser')) throw new Exception('Auth error');
    $user = getCurrentUser();
    if (!$user) { echo json_encode(['success'=>false, 'message'=>'No autorizado']); exit; }

    $input = json_decode(file_get_contents('php://input'), true);
    $alerts = $input['alerts'] ?? [];

    if (empty($alerts)) {
        echo json_encode(['success' => true, 'sent' => 0]);
        exit;
    }

    require_once $root . '/src/Services/MailService.php';
    $mailSvc = new \App\Services\MailService();
    $sentCount = 0;
    
    require_once $root . '/src/core/Database.php';
    $db = \App\core\Database::getInstance();
    $stmtUser = $db->prepare("SELECT email, full_name FROM users WHERE id = :id");
    $stmtUser->execute([':id' => $user['id']]);
    $u = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if ($u && !empty($u['email'])) {
        $toEmail = $u['email'];
        $userName = $u['full_name'] ?? 'Socio';
        
        foreach ($alerts as $alert) {
            if ($alert['type'] === 'low_stock') {
                $mailSvc->sendLowStockAlert($toEmail, $userName, $alert['product_name'], $alert['current_stock'], $alert['min_stock']);
                $sentCount++;
            } elseif ($alert['type'] === 'negative_profit') {
                $mailSvc->sendNegativeProfitAlert($toEmail, $userName, $alert['product_name'], $alert['sale_price'], $alert['cost_price']);
                $sentCount++;
            }
        }
    }

    echo json_encode(['success' => true, 'sent' => $sentCount]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
?>
