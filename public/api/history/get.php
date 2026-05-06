<?php
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/core/Database.php';
require_once __DIR__ . '/../../../src/Models/NotificationModel.php';

use App\Models\NotificationModel;

header('Content-Type: application/json');

session_start();
$user = getCurrentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$inventoryId = $_SESSION['active_inventory_id'] ?? 0;

try {
    $model = new NotificationModel();
    // Usamos el nuevo método que ignora el borrado lógico
    $notifications = $model->getAllForHistory($inventoryId);
    
    echo json_encode([
        'success' => true,
        'notifications' => $notifications
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
