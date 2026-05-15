<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
use App\core\Database;

$user = getCurrentUser();
if (!$user) {
    http_response_code(401); echo json_encode(['success' => false, 'message' => 'No autorizado']); exit;
}
$inventoryId = $_SESSION['active_inventory_id'] ?? null;
$data = json_decode(file_get_contents('php://input'), true);
$targetRoleId = $data['role_id'] ?? null;
$permissions = $data['permissions'] ?? [];

if (!$inventoryId || !$targetRoleId) {
    http_response_code(400); echo json_encode(['success' => false, 'message' => 'Faltan datos']); exit;
}

$role = getInventoryRole($user['id'], $inventoryId);
if (!$role || $role['role_id'] != 1) { // Solo Owner puede editar permisos
    http_response_code(403); echo json_encode(['success' => false, 'message' => 'Solo el Owner puede configurar permisos']); exit;
}

$db = Database::getInstance();
$permissionsJson = json_encode($permissions);

$stmt = $db->prepare("
    INSERT INTO inventory_role_settings (inventory_id, role_id, permissions_json) 
    VALUES (?, ?, ?) 
    ON DUPLICATE KEY UPDATE permissions_json = VALUES(permissions_json)
");
if ($stmt->execute([$inventoryId, $targetRoleId, $permissionsJson])) {
    echo json_encode(['success' => true, 'message' => 'Permisos actualizados']);
} else {
    http_response_code(500); echo json_encode(['success' => false, 'message' => 'Error al actualizar permisos']);
}
