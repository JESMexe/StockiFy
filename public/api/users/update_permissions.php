<?php
/**
 * POST /api/users/update_permissions.php
 * Guarda la configuración de permisos por rol para el inventario activo.
 * Solo el Owner puede ejecutar esta acción.
 *
 * Body esperado: { "settings": { "2": { "can_view_analytics": true, ... }, "3": {...} } }
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';

use App\core\Database;

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

// Solo el Owner puede configurar permisos
$myRole = getInventoryRole($user['id'], $inventoryId);
if (!$myRole || (int)$myRole['role_id'] !== 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Solo el Owner puede configurar permisos.']);
    exit;
}

$data     = json_decode(file_get_contents('php://input'), true);
$settings = $data['settings'] ?? null;

if (!is_array($settings)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Formato de datos inválido.']);
    exit;
}

$db   = Database::getInstance();
$stmt = $db->prepare("
    INSERT INTO inventory_role_settings (inventory_id, role_id, permissions_json) 
    VALUES (?, ?, ?) 
    ON DUPLICATE KEY UPDATE permissions_json = VALUES(permissions_json)
");

$errors = [];
foreach ($settings as $roleId => $permissions) {
    $roleId = (int)$roleId;
    // Solo permitir roles 2 (Admin) y 3 (Employee)
    if (!in_array($roleId, [2, 3])) continue;

    if (!is_array($permissions)) continue;

    if (!$stmt->execute([$inventoryId, $roleId, json_encode($permissions)])) {
        $errors[] = "Error al guardar rol {$roleId}";
    }
}

if (empty($errors)) {
    try {
        $updatedRoles = [];
        foreach (array_keys($settings) as $rId) {
            if ($rId == 2) $updatedRoles[] = 'Administrador';
            if ($rId == 3) $updatedRoles[] = 'Empleado';
        }
        require_once __DIR__ . '/../../../src/helpers/ActivityLogger.php';
        \App\helpers\ActivityLogger::log(
            'Colaboradores',
            'update_permissions',
            'role_settings',
            null,
            "Configuró los permisos de roles del inventario",
            "Roles actualizados: " . (empty($updatedRoles) ? 'Ninguno' : implode(', ', $updatedRoles)),
            (int)$inventoryId,
            (int)$user['id']
        );
    } catch (\Throwable $logErr) {
        error_log('ActivityLogger error in update_permissions: ' . $logErr->getMessage());
    }
    echo json_encode(['success' => true, 'message' => 'Permisos actualizados correctamente.']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
}
