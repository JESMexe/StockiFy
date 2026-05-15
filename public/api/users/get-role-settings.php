<?php
/**
 * GET /api/users/get-role-settings.php
 * Devuelve la configuración de permisos del inventario activo.
 *
 * Para Owner (role_id=1): devuelve settings de TODOS los roles (para editar el panel).
 * Para Admin/Employee: devuelve solo SUS permisos (para aplicar restricciones en el frontend).
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';

use App\Core\Database;

$user = getCurrentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$inventoryId = (int)($_SESSION['active_inventory_id'] ?? 0);
if (!$inventoryId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No hay inventario activo en sesión.']);
    exit;
}

$myRole = getInventoryRole($user['id'], $inventoryId);
if (!$myRole) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin acceso a este inventario.']);
    exit;
}

$db = Database::getInstance();

if ($myRole['role_id'] === 1) {
    // === OWNER: retorna configuración de Admin (2) y Employee (3) para el panel de control ===
    $stmt = $db->prepare(
        "SELECT role_id, permissions_json 
         FROM inventory_role_settings 
         WHERE inventory_id = ? AND role_id IN (2, 3)"
    );
    $stmt->execute([$inventoryId]);

    $settings = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $settings[$row['role_id']] = json_decode($row['permissions_json'], true) ?? [];
    }

    // Completar con defaults vacíos si aún no hay configuración para algún rol
    if (!isset($settings[2])) $settings[2] = [];
    if (!isset($settings[3])) $settings[3] = [];

    echo json_encode([
        'success'  => true,
        'mode'     => 'owner',
        'settings' => $settings
    ]);

} else {
    // === ADMIN / EMPLOYEE: retorna sus propios permisos para aplicarlos al sidebar ===
    $stmt = $db->prepare(
        "SELECT permissions_json 
         FROM inventory_role_settings 
         WHERE inventory_id = ? AND role_id = ?"
    );
    $stmt->execute([$inventoryId, $myRole['role_id']]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    $permissions = $row ? (json_decode($row['permissions_json'], true) ?? []) : [];

    echo json_encode([
        'success'     => true,
        'mode'        => 'collaborator',
        'role_id'     => $myRole['role_id'],
        'role_name'   => $myRole['name'],
        'permissions' => $permissions
    ]);
}
