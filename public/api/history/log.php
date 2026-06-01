<?php
/**
 * POST /api/history/log.php
 * Endpoint para registrar logs de auditoría desde el frontend (ej. exportación Excel).
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/helpers/ActivityLogger.php';

use App\helpers\ActivityLogger;

$user = getCurrentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'No autorizado']);
    exit;
}

$inventoryId = (int)($_SESSION['active_inventory_id'] ?? 0);
if (!$inventoryId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No hay inventario activo.']);
    exit;
}

$myRole = getInventoryRole($user['id'], $inventoryId);
if (!$myRole) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Sin acceso a este inventario.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

$section          = trim($input['section'] ?? '');
$action           = trim($input['action'] ?? '');
$entityType       = trim($input['entity_type'] ?? '');
$entityId         = isset($input['entity_id']) ? trim($input['entity_id']) : null;
$description      = trim($input['description'] ?? '');
$extraDescription = isset($input['extra_description']) ? trim($input['extra_description']) : null;

if (empty($section) || empty($action) || empty($entityType) || empty($description)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Faltan campos obligatorios.']);
    exit;
}

$logged = ActivityLogger::log(
    $section,
    $action,
    $entityType,
    $entityId,
    $description,
    $extraDescription,
    $inventoryId,
    (int)$user['id'],
    $myRole['name']
);

if ($logged) {
    echo json_encode(['success' => true, 'message' => 'Log registrado.']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al registrar el log.']);
}
?>
