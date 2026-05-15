<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/Models/ActivityLogModel.php';

use App\Models\ActivityLogModel;

$user = getCurrentUser();
if (!$user) {
    http_response_code(401); echo json_encode(['success' => false, 'message' => 'No autorizado']); exit;
}
$inventoryId = $_SESSION['active_inventory_id'] ?? null;

if (!$inventoryId) {
    http_response_code(400); echo json_encode(['success' => false, 'message' => 'Faltan datos']); exit;
}

$role = getInventoryRole($user['id'], $inventoryId);
if (!$role) { 
    http_response_code(403); echo json_encode(['success' => false, 'message' => 'Permisos insuficientes']); exit;
}

$logModel = new ActivityLogModel();
$logs = $logModel->getLogs($inventoryId);
echo json_encode(['success' => true, 'logs' => $logs]);
