<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/helpers/quota_helper.php';
require_once __DIR__ . '/../../../src/Services/CollaboratorDebtService.php';

use App\Services\CollaboratorDebtService;
use App\core\Database;

try {
    $user = getCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autorizado.']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $slotsCount = (int)($data['slots_count'] ?? 0);
    $inventoryId = (int)($data['inventory_id'] ?? $_SESSION['active_inventory_id'] ?? 0);

    if (!$inventoryId || $slotsCount <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'Datos inválidos o cantidad incorrecta de slots.']);
        exit;
    }

    // Verificar que el usuario actual es el Owner de este inventario
    $myRole = getInventoryRole($user['id'], $inventoryId);
    if (!$myRole || (int)$myRole['role_id'] !== 1) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Solo el propietario (Owner) puede agregar slots.']);
        exit;
    }

    // Obtener los datos de cuota para validar el plan
    $quota = getCollaboratorQuota($user['id']);
    // Solo permitimos agregar slots en el plan Profesional (2) y Vitalicio (4)
    if (!in_array((int)$quota['plan'], [2, 4])) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Solo podés agregar slots adicionales si estás en el plan Profesional o Vitalicio.'
        ]);
        exit;
    }

    // Agregar slots pendientes usando el servicio
    $debtSvc = new CollaboratorDebtService();
    $ok = $debtSvc->addPendingSlots($user['id'], $inventoryId, $slotsCount);

    if ($ok) {
        echo json_encode([
            'success' => true,
            'message' => "¡Se agregaron {$slotsCount} slots de colaboradores con éxito! Acordate de saldar el pago dentro de las próximas 48 horas."
        ]);
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Error interno al registrar la adición de slots.']);
    }

} catch (Throwable $e) {
    http_response_code(500);
    error_log("Error in add-slots.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Ocurrió un error inesperado al procesar la solicitud.']);
}
