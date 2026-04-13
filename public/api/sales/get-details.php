<?php
header('Content-Type: application/json');

require_once dirname(__DIR__, 3) . '/vendor/autoload.php';
require_once dirname(__DIR__, 3) . '/src/helpers/auth_helper.php';
require_once dirname(__DIR__, 3) . '/src/Models/SalesModel.php';

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

    $saleId = $_GET['id'] ?? null;
    if (!$saleId) {
        echo json_encode(['success' => false, 'message' => 'Falta ID de venta']);
        exit;
    }

    $model = new SalesModel();
    $data = $model->getDetails($saleId, $user['id'], $activeInventoryId);

    if (!$data || empty($data['sale'])) {
        echo json_encode(['success' => false, 'message' => 'Venta no encontrada o acceso denegado']);
        exit;
    }

    $sale = $data['sale'];
    $sale['items'] = $data['items'] ?? [];
    $sale['payments'] = $data['payments'] ?? [];

    echo json_encode([
        'success' => true,
        'sale' => $sale
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}
?>