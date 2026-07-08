<?php
header('Content-Type: application/json');

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';
require_once __DIR__ . '/../../../src/core/Database.php';
require_once __DIR__ . '/../../../src/helpers/ActivityLogger.php';

use App\core\Database;

try {
    session_start();

    $user = getCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit;
    }

    $inventoryId = (int) ($_SESSION['active_inventory_id'] ?? 0);
    if (!$inventoryId) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => 'No hay inventario activo en sesión.']);
        exit;
    }

    // Verificar si es Owner
    $myRole = getInventoryRole($user['id'], $inventoryId);
    if (!$myRole || (int)$myRole['role_id'] !== 1) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Acceso denegado. Solo el Propietario puede configurar el horario laboral.']);
        exit;
    }

    // Obtener parámetros
    $input = json_decode(file_get_contents('php://input'), true);
    
    $enabled = isset($input['enabled']) ? (int) $input['enabled'] : 0;
    $start   = !empty($input['start']) ? $input['start'] : '08:00';
    $end     = !empty($input['end']) ? $input['end'] : '20:00';

    // Validar formato simple de hora (HH:MM)
    if (!preg_match('/^(?:2[0-3]|[01][0-9]):[0-5][0-9]$/', $start) || !preg_match('/^(?:2[0-3]|[01][0-9]):[0-5][0-9]$/', $end)) {
        throw new Exception("El formato de las horas de inicio o fin no es válido (debe ser HH:MM).");
    }

    // Guardar en la base de datos
    $db = Database::getInstance();
    $stmt = $db->prepare("
        UPDATE inventories 
        SET work_hours_enabled = ?, 
            work_hours_start = ?, 
            work_hours_end = ? 
        WHERE id = ?
    ");
    $stmt->execute([$enabled, $start . ':00', $end . ':00', $inventoryId]);

    // Registrar en el log de actividades
    $statusText = $enabled ? "Habilitado (Rango: {$start}h a {$end}h)" : "Deshabilitado";
    \App\helpers\ActivityLogger::log(
        'Colaboradores',
        'work_hours_updated',
        'collaborator_settings',
        (string)$inventoryId,
        "Se actualizó la configuración de horario laboral del inventario.",
        "Estado: {$statusText}.",
        (int)$inventoryId,
        (int)$user['id']
    );

    echo json_encode([
        'success' => true,
        'message' => 'Configuración de horario laboral guardada correctamente.'
    ]);

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
