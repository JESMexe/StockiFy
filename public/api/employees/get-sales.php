<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/Models/SalesModel.php';

use App\Models\SalesModel;

try {
    $user = getCurrentUser();
    if (!$user) {
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit;
    }

    $activeInventoryId = $_SESSION['active_inventory_id'] ?? null;
    if (!$activeInventoryId) {
        echo json_encode(['success' => false, 'message' => 'No hay inventario activo seleccionado']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $employeeId = $data['employee_id'] ?? $_GET['employee_id'] ?? null;

    if (!$employeeId) {
        echo json_encode(['success' => false, 'message' => 'ID de empleado no proporcionado']);
        exit;
    }

    $model = new SalesModel();

    $sales = $model->getSalesByEmployee($user['id'], $activeInventoryId, $employeeId, $_GET['order'] ?? 'desc');

    echo json_encode(['success' => true, 'sales' => $sales]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
