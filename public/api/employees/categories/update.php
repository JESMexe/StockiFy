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

    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? null;
    $name = $input['name'] ?? null;
    $fields = $input['fields'] ?? [];

    if (!$id || !$name) { echo json_encode(['success'=>false, 'message'=>'Faltan datos']); exit; }

    $model = new EmployeeCategoryModel();
    $success = $model->updateCategory($id, $user['id'], $inventoryId, $name, $fields);

    echo json_encode(['success'=>$success, 'message'=>$success ? 'Categoría actualizada' : 'Error al actualizar']);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}
