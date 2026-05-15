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
if (!$inventoryId) {
    http_response_code(400); echo json_encode(['success' => false, 'message' => 'Inventario no seleccionado']); exit;
}

$role = getInventoryRole($user['id'], $inventoryId);
if (!$role || !in_array($role['role_id'], [1, 2])) {
    http_response_code(403); echo json_encode(['success' => false, 'message' => 'Permisos insuficientes']); exit;
}

$db = Database::getInstance();
$stmt = $db->prepare("
    SELECT ic.id as collaborator_id, u.username, u.email, r.name as role_name, ic.status, ic.invited_at 
    FROM inventory_collaborators ic
    JOIN users u ON ic.user_id = u.id
    JOIN roles r ON ic.role_id = r.id
    WHERE ic.inventory_id = ?
");
$stmt->execute([$inventoryId]);
$collaborators = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'collaborators' => $collaborators]);
