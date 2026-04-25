<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();

use App\Models\EmployeeCategoryModel;

try {
    $root = dirname(__DIR__, 4);
    require_once $root . '/vendor/autoload.php';
    require_once $root . '/src/helpers/auth_helper.php';
    require_once $root . '/src/Models/EmployeeCategoryModel.php';

    $user = getCurrentUser();
    if (!$user) { echo json_encode(['success'=>false, 'message'=>'No autenticado']); exit; }

    $inventoryId = $_SESSION['active_inventory_id'] ?? null;
    if (!$inventoryId) { echo json_encode(['success'=>false, 'message'=>'Inventario no seleccionado']); exit; }

    $model = new EmployeeCategoryModel();
    $categories = $model->getAll($user['id'], $inventoryId);

    echo json_encode(['success'=>true, 'categories'=>$categories]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
