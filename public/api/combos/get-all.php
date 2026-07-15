<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    $root = dirname(__DIR__, 3);
    require_once $root . '/vendor/autoload.php';
    require_once $root . '/src/helpers/auth_helper.php';
    require_once $root . '/src/Models/ComboModel.php';

    $user = getCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit;
    }

    requireSectionAccess('can_view_data');
    $activeInventoryId = $_SESSION['active_inventory_id'] ?? null;

    if (!$activeInventoryId) {
        echo json_encode(['success' => false, 'message' => 'No hay inventario activo seleccionado.']);
        exit;
    }

    $comboModel = new \App\Models\ComboModel();
    $combos = $comboModel->getCombosByInventory((int)$activeInventoryId);

    echo json_encode(['success' => true, 'combos' => $combos]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
