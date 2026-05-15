<?php
/**
 * GET /api/history/get.php
 * Retorna el historial de auditoría del inventario activo.
 * Lee de activity_logs — completamente separado del sistema de notificaciones.
 * Solo colaboradores activos del inventario pueden consultar.
 *
 * Parámetros opcionales:
 *   ?page=1          Paginación (50 registros por página)
 *   ?type=product    Filtrar por entity_type: product, sale, purchase, collaborator
 */
header('Content-Type: application/json');
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/Models/ActivityLogModel.php';

use App\Models\ActivityLogModel;

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

$page           = max(1, (int)($_GET['page'] ?? 1));
$filterType     = $_GET['type'] ?? null;
$limit          = 50;
$offset         = ($page - 1) * $limit;

$logModel = new ActivityLogModel();
$logs     = $logModel->getLogs($inventoryId, $limit, $offset, $filterType ?: null);
$total    = $logModel->countLogs($inventoryId, $filterType ?: null);

echo json_encode([
    'success'   => true,
    'logs'      => $logs,
    'total'     => $total,
    'page'      => $page,
    'pages'     => max(1, (int)ceil($total / $limit))
]);
