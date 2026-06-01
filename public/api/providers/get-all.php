<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

use App\Models\ProviderModel;

try {
    $root = dirname(__DIR__, 3);
    require_once $root . '/vendor/autoload.php';
    require_once $root . '/src/helpers/auth_helper.php';
    require_once $root . '/src/Models/ProviderModel.php';

    // Guard RBAC
    requireSectionAccess('can_view_providers');



    $user = getCurrentUser();
    if (!$user) { echo json_encode(['success'=>false]); exit; }

    
$inventoryId = $_SESSION['active_inventory_id'] ?? null;
if (!$inventoryId) { echo json_encode(['success'=>false, 'message'=>'Inventario no seleccionado']); exit; }

// RBAC: los proveedores pertenecen al owner del inventario, no al colaborador activo
$ownerId = getInventoryOwnerId((int)$inventoryId) ?? $user['id'];

$model = new ProviderModel();
$providers = $model->getAll($ownerId, $_GET['order'] ?? 'desc', $inventoryId);

echo json_encode(['success'=>true, 'providers'=>$providers]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}