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
$collaboratorId = $data['collaborator_id'] ?? null;

if (!$inventoryId || !$collaboratorId) {
    http_response_code(400); echo json_encode(['success' => false, 'message' => 'Faltan datos']); exit;
}

$role = getInventoryRole($user['id'], $inventoryId);
if (!$role || $role['role_id'] != 1) { // Solo Owner puede eliminar colaboradores
    http_response_code(403); echo json_encode(['success' => false, 'message' => 'Solo el Owner puede eliminar colaboradores']); exit;
}

$db = Database::getInstance();
// Previene que el Owner se elimine a sí mismo accidentalmente
$stmt = $db->prepare("DELETE FROM inventory_collaborators WHERE id = ? AND inventory_id = ? AND role_id != 1");
if ($stmt->execute([$collaboratorId, $inventoryId])) {
    echo json_encode(['success' => true, 'message' => 'Colaborador revocado con éxito']);
} else {
    http_response_code(500); echo json_encode(['success' => false, 'message' => 'Error al eliminar']);
}
