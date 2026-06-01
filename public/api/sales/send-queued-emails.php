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
    require_once $root . '/src/Services/WhatsappService.php';
    $mailSvc = new \App\Services\MailService();
    $waSvc = new \App\Services\WhatsappService();
    $sentCount = 0;
    
    require_once $root . '/src/core/Database.php';
    $db = \App\core\Database::getInstance();

    // Resolve the real owner of the active inventory
    $ownerId = (int)$user['id'];
    $activeInventoryId = $_SESSION['active_inventory_id'] ?? null;
    if ($activeInventoryId) {
        $stmtOwner = $db->prepare("SELECT user_id FROM inventories WHERE id = :inv_id");
        $stmtOwner->execute([':inv_id' => $activeInventoryId]);
        $ownerRow = $stmtOwner->fetch(PDO::FETCH_ASSOC);
        if ($ownerRow && !empty($ownerRow['user_id'])) {
            $ownerId = (int)$ownerRow['user_id'];
        }
    }

    $stmtUser = $db->prepare("SELECT email, full_name, cell FROM users WHERE id = :id");
    $stmtUser->execute([':id' => $ownerId]);
    $u = $stmtUser->fetch(PDO::FETCH_ASSOC);

    if ($u && (!empty($u['email']) || !empty($u['cell']))) {
        $toEmail = $u['email'];
        $toCell = $u['cell'];
        $userName = $u['full_name'] ?? 'Socio';
        
        foreach ($alerts as $alert) {
            $invName = $alert['inventory_name'] ?? 'Principal';
            if ($alert['type'] === 'low_stock') {
                if (!empty($toEmail)) {
                    $mailSvc->sendLowStockAlert($toEmail, $userName, $alert['product_name'], $alert['current_stock'], $alert['min_stock']);
                    $sentCount++;
                }
                if (!empty($toCell)) {
                    $productId = $alert['product_id'] ?? '-';
                    $waSvc->sendLowStockAlert($toCell, $userName, $alert['product_name'], $alert['current_stock'], $alert['min_stock'], $invName, $productId);
                    $sentCount++;
                }
            } elseif ($alert['type'] === 'out_of_stock') {
                if (!empty($toEmail)) {
                    $mailSvc->sendOutOfStockAlert($toEmail, $userName, $alert['product_name'], $invName);
                    $sentCount++;
                }
                if (!empty($toCell)) {
                    $productId = $alert['product_id'] ?? '-';
                    $waSvc->sendOutOfStockAlert($toCell, $userName, $alert['product_name'], $invName, (string)$productId);
                    $sentCount++;
                }
            } elseif ($alert['type'] === 'negative_profit') {
                if (!empty($toEmail)) {
                    $mailSvc->sendNegativeProfitAlert($toEmail, $userName, $alert['product_name'], $alert['sale_price'], $alert['cost_price']);
                    $sentCount++;
                }
                // Negative profit currently does not have a WhatsApp template based on our plan, but if it did, we would dispatch it here.
            }
        }
    }

    echo json_encode(['success' => true, 'sent' => $sentCount]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
?>
