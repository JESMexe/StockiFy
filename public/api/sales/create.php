<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

use App\Models\SalesModel;

try {
    $root = dirname(__DIR__, 3);
    require_once $root . '/vendor/autoload.php';
    require_once $root . '/src/helpers/auth_helper.php';
    require_once $root . '/src/Models/SalesModel.php';

    if (!function_exists('getCurrentUser')) throw new Exception('Auth error');
    $user = getCurrentUser();
    if (!$user) { echo json_encode(['success'=>false, 'message'=>'No autorizado']); exit; }

    $input = json_decode(file_get_contents('php://input'), true);

    $primaryMethodId = null;
    if (!empty($input['payments']) && is_array($input['payments'])) {
        $primaryMethodId = $input['payments'][0]['method_id'] ?? null;
    }

    $data = [
        'total'             => (float)($input['total_final'] ?? $input['total'] ?? 0),
        'amount_tendered'   => (float)($input['amount_tendered'] ?? 0),
        'change_returned'   => (float)($input['change_returned'] ?? 0),
        'commission_amount' => (float)($input['commission_amount'] ?? 0),
        'discount_amount'   => (float)($input['discount_amount'] ?? 0),

        'exchange_rate_snapshot' => (float)($input['exchange_rate_snapshot'] ?? 1),

        'seller_id'         => !empty($input['seller_id']) ? (int)$input['seller_id'] : null,
        'payment_method_id' => $primaryMethodId,

        'notes'             => $input['notes'] ?? null,
        'items'             => $input['items'] ?? [],

        'payments'          => $input['payments'] ?? []
    ];

    $clientId = !empty($input['customer_id']) ? (int)$input['customer_id'] : null;

    if (session_status() === PHP_SESSION_NONE) { session_start(); }
    $inventoryId = $input['inventory_id'] ?? $_SESSION['active_inventory_id'] ?? null;
    
    $model = new SalesModel();
    if (!$inventoryId) {
        try {
            $ctx = $model->getInventoryContext($user['id']);
            $inventoryId = $ctx['inventory_id'];
        } catch (Exception $e) { $inventoryId = 1; }
    }

    if (empty($data['items'])) {
        echo json_encode(['success' => false, 'message' => 'Error: Carrito vacío.']);
        exit;
    }

    $alerts = [];
    $saleId = $model->createSale($user['id'], $inventoryId, $clientId, $data, $alerts);

    if ($saleId) {
        echo json_encode(['success' => true, 'sale_id' => $saleId, 'alerts' => $alerts]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al guardar en BD']);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
?>