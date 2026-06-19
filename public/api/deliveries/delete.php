<?php
header('Content-Type: application/json');
if (session_status() === PHP_SESSION_NONE) session_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);

use App\Models\DeliveryModel;
use App\core\Database;

try {
    $root = dirname(__DIR__, 3);
    require_once $root . '/vendor/autoload.php';
    require_once $root . '/src/helpers/auth_helper.php';
    require_once $root . '/src/Models/DeliveryModel.php';

    $user = getCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit;
    }

    $inventoryId = $_SESSION['active_inventory_id'] ?? null;
    if (!$inventoryId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Inventario no seleccionado']);
        exit;
    }

    requireSectionAccess('can_view_deliveries');

    // Plan Check
    $ownerId = getInventoryOwnerId((int)$inventoryId) ?? $user['id'];
    $db = Database::getInstance();
    $stmtUser = $db->prepare("SELECT subscription_active FROM users WHERE id = ? LIMIT 1");
    $stmtUser->execute([$ownerId]);
    $subActive = (int)$stmtUser->fetchColumn();

    if ($subActive === 1) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'El módulo de envíos requiere el Plan Profesional o superior (bloqueado para Plan Básico)']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);
    $id = $input['id'] ?? null;

    if (!$id) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'El ID del envío es obligatorio']);
        exit;
    }

    $model = new DeliveryModel();
    $success = $model->deleteDelivery($id, $user['id'], $inventoryId);

    if ($success) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar el envío']);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
