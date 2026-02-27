<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__, 3) . '/src/helpers/auth_helper.php';
require_once dirname(__DIR__, 3) . '/src/Models/EmployeeModel.php';

use App\Models\EmployeeModel;

try {
    $user = getCurrentUser();
    if (!$user) { echo json_encode(['success'=>false, 'message'=>'No autorizado']); exit; }

    
$inventoryId = $_SESSION['active_inventory_id'] ?? null;
if (!$inventoryId) { echo json_encode(['success'=>false, 'message'=>'Inventario no seleccionado']); exit; }
$input = json_decode(file_get_contents('php://input'), true);

    if (empty($input['id']) || empty($input['name'])) {
        echo json_encode(['success'=>false, 'message'=>'Datos incompletos']); exit;
    }

    $model = new EmployeeModel();
    $success = $model->updateEmployee(
        $input['id'],
        $user['id'],
        $input['name'],
        $input['dni'] ?? null,
        $input['phone'] ?? null,
        $input['email'] ?? null,
        $inventoryId
    );

    echo json_encode(['success' => $success]);

} catch (Throwable $e) {
    echo json_encode(['success'=>false, 'message'=>$e->getMessage()]);
}