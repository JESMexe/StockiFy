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

    $details = [];

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
            $details[] = [
                'product' => $alert['product_name'] ?? 'Unknown',
                'type' => $alert['type'] ?? 'unknown',
                'status' => 'skipped',
                'reason' => 'No owner contact info found'
            ];
            continue; // Sin destinatario, saltar
        }

        $toEmail  = $owner['email'] ?? '';
        $toCell   = $owner['cell'] ?? '';
        $userName = $owner['full_name'] ?? 'Socio';

        $alertDetails = [
            'product' => $alert['product_name'] ?? 'Unknown',
            'type' => $alert['type'] ?? 'unknown',
            'email' => ['attempted' => false, 'sent' => false, 'error' => null],
            'whatsapp' => ['attempted' => false, 'sent' => false, 'error' => null]
        ];

        if ($alert['type'] === 'low_stock') {
            if (!empty($toEmail)) {
                $alertDetails['email']['attempted'] = true;
                $ok = $mailSvc->sendLowStockAlert($toEmail, $userName, $alert['product_name'], (float)$alert['current_stock'], (float)$alert['min_stock']);
                if ($ok) {
                    $alertDetails['email']['sent'] = true;
                    $sentCount++;
                } else {
                    $alertDetails['email']['error'] = $mailSvc->lastError ?: 'Unknown SMTP error';
                }
            }
            if (!empty($toCell)) {
                $alertDetails['whatsapp']['attempted'] = true;
                $productId = $alert['product_id'] ?? '-';
                $ok = $waSvc->sendLowStockAlert($toCell, $userName, $alert['product_name'], (float)$alert['current_stock'], (float)$alert['min_stock'], $invName, $productId);
                if ($ok) {
                    $alertDetails['whatsapp']['sent'] = true;
                    $sentCount++;
                } else {
                    $alertDetails['whatsapp']['error'] = $waSvc->lastError ?: 'Unknown WhatsApp error';
                }
            }
        } elseif ($alert['type'] === 'out_of_stock') {
            if (!empty($toEmail)) {
                $alertDetails['email']['attempted'] = true;
                $ok = $mailSvc->sendOutOfStockAlert($toEmail, $userName, $alert['product_name'], $invName);
                if ($ok) {
                    $alertDetails['email']['sent'] = true;
                    $sentCount++;
                } else {
                    $alertDetails['email']['error'] = $mailSvc->lastError ?: 'Unknown SMTP error';
                }
            }
            if (!empty($toCell)) {
                $alertDetails['whatsapp']['attempted'] = true;
                $productId = $alert['product_id'] ?? '-';
                $ok = $waSvc->sendOutOfStockAlert($toCell, $userName, $alert['product_name'], $invName, (string)$productId);
                if ($ok) {
                    $alertDetails['whatsapp']['sent'] = true;
                    $sentCount++;
                } else {
                    $alertDetails['whatsapp']['error'] = $waSvc->lastError ?: 'Unknown WhatsApp error';
                }
            }
        } elseif ($alert['type'] === 'negative_profit') {
            if (!empty($toEmail)) {
                $alertDetails['email']['attempted'] = true;
                $ok = $mailSvc->sendNegativeProfitAlert($toEmail, $userName, $alert['product_name'], (float)$alert['sale_price'], (float)$alert['cost_price']);
                if ($ok) {
                    $alertDetails['email']['sent'] = true;
                    $sentCount++;
                } else {
                    $alertDetails['email']['error'] = $mailSvc->lastError ?: 'Unknown SMTP error';
                }
            }
        }

        $details[] = $alertDetails;
    }

    echo json_encode(['success' => true, 'sent' => $sentCount, 'details' => $details]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
?>
