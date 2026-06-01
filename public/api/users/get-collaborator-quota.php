<?php
/**
 * GET /api/users/get-collaborator-quota.php
 *
 * Retorna la información de cupo de colaboradores del Owner activo.
 * Usado por el frontend para renderizar la UI de la sección Colaboradores.
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/helpers/quota_helper.php';

use App\core\Database;

try {
    $user = getCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit;
    }

    // El cupo siempre se calcula sobre el Owner del inventario activo.
    // Si el usuario activo es un colaborador, buscamos al Owner real del inventario.
    $inventoryId = (int)($_SESSION['active_inventory_id'] ?? 0);
    if (!$inventoryId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No hay inventario activo.']);
        exit;
    }

    $db = Database::getInstance();
    $stmt = $db->prepare('SELECT user_id FROM inventories WHERE id = ? LIMIT 1');
    $stmt->execute([$inventoryId]);
    $ownerId = (int)$stmt->fetchColumn();

    if (!$ownerId) {
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Inventario no encontrado.']);
        exit;
    }

    $quota = getCollaboratorQuota($ownerId);

    echo json_encode([
        'success' => true,
        ...$quota,
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error al obtener cuota de colaboradores.']);
}
