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

    // Cache de owners por inventory_id para no repetir queries
    $ownerCache = [];

    /**
     * Resuelve el owner de un inventario dado su ID.
     * Siempre devuelve los datos del propietario del inventario,
     * sin importar si el usuario activo es un colaborador.
     */
    $resolveOwner = function(int $invId) use ($db, &$ownerCache): ?array {
        if (isset($ownerCache[$invId])) {
            return $ownerCache[$invId];
        }
        $stmt = $db->prepare(
            "SELECT u.email, u.full_name, u.cell
             FROM users u
             JOIN inventories i ON u.id = i.user_id
             WHERE i.id = ?
             LIMIT 1"
        );
        $stmt->execute([$invId]);
        $owner = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        $ownerCache[$invId] = $owner;
        return $owner;
    };

    foreach ($alerts as $alert) {
        $invName = $alert['inventory_name'] ?? 'Principal';

        // Resolver owner por inventory_id de la alerta (si viene).
        // Fallback: inventario activo en sesión; último fallback: usuario actual.
        $inventoryIdForAlert = (int)($alert['inventory_id'] ?? $_SESSION['active_inventory_id'] ?? 0);
        $owner = $inventoryIdForAlert ? $resolveOwner($inventoryIdForAlert) : null;

        if (!$owner) {
            // Fallback de seguridad: usar datos del usuario actual
            $stmtFallback = $db->prepare("SELECT email, full_name, cell FROM users WHERE id = ?");
            $stmtFallback->execute([$user['id']]);
            $owner = $stmtFallback->fetch(PDO::FETCH_ASSOC) ?: null;
        }

        if (!$owner || (empty($owner['email']) && empty($owner['cell']))) {
            continue; // Sin destinatario, saltar
        }

        $toEmail  = $owner['email'] ?? '';
        $toCell   = $owner['cell'] ?? '';
        $userName = $owner['full_name'] ?? 'Socio';

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
            // WhatsApp: sin plantilla aprobada para ganancia negativa actualmente.
        }
    }

    echo json_encode(['success' => true, 'sent' => $sentCount]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
?>
