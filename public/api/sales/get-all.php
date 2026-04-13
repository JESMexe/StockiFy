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

    $model = new SalesModel();

    $sales = $model->getHistory($user['id'], $activeInventoryId, $_GET['order'] ?? 'desc');

    echo json_encode(['success' => true, 'sales' => $sales]); // Nota: El JS espera "sales", no "purchases"

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}