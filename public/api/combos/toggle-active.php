<?php
header('Content-Type: application/json');
ini_set('display_errors', 0);
error_reporting(E_ALL);

try {
    $root = dirname(__DIR__, 3);
    require_once $root . '/vendor/autoload.php';
    require_once $root . '/src/helpers/auth_helper.php';
    require_once $root . '/src/Models/ComboModel.php';

    $user = getCurrentUser();
    if (!$user) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'No autorizado']);
        exit;
    }

    requireSectionAccess('can_modify_data');
    $activeInventoryId = $_SESSION['active_inventory_id'] ?? null;

    if (!$activeInventoryId) {
        echo json_encode(['success' => false, 'message' => 'No hay inventario activo seleccionado.']);
        exit;
    }

    $input = json_decode(file_get_contents('php://input'), true);

    $comboId = isset($input['id']) ? (int)$input['id'] : 0;
    $status = isset($input['is_active']) ? (int)$input['is_active'] : 1;

    if ($comboId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de combo inválida.']);
        exit;
    }

    $comboModel = new \App\Models\ComboModel();
    // Validar propiedad del combo
    $existing = $comboModel->getCombo($comboId, (int)$activeInventoryId);
    if (!$existing) {
        echo json_encode(['success' => false, 'message' => 'Combo no encontrado o no pertenece a este inventario.']);
        exit;
    }

    $name = $existing['name'];
    $success = $comboModel->toggleComboStatus($comboId, $status);

    if ($success) {
        // Registrar actividad
        try {
            $actionLabel = $status === 1 ? 'activó' : 'desactivó';
            require_once $root . '/src/helpers/ActivityLogger.php';
            \App\helpers\ActivityLogger::log(
                'Inventario',
                'update',
                'combo',
                (string)$comboId,
                "El usuario $actionLabel el combo '$name'",
                "",
                (int)$activeInventoryId,
                (int)$user['id']
            );
        } catch (\Throwable $th) {}

        echo json_encode(['success' => true, 'message' => 'Estado del combo actualizado con éxito.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se pudo actualizar el estado del combo.']);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
