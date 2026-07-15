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
    $name = isset($input['name']) ? trim($input['name']) : '';
    $price = isset($input['price']) ? (float)$input['price'] : 0.00;
    $items = isset($input['items']) && is_array($input['items']) ? $input['items'] : [];

    if ($comboId <= 0) {
        echo json_encode(['success' => false, 'message' => 'ID de combo inválida.']);
        exit;
    }

    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'El nombre del combo es obligatorio.']);
        exit;
    }

    if ($price < 0) {
        echo json_encode(['success' => false, 'message' => 'El precio de venta no puede ser negativo.']);
        exit;
    }

    if (empty($items)) {
        echo json_encode(['success' => false, 'message' => 'El combo debe contener al menos un producto componente.']);
        exit;
    }

    $comboModel = new \App\Models\ComboModel();
    // Validar propiedad del combo antes de actualizar
    $existing = $comboModel->getCombo($comboId, (int)$activeInventoryId);
    if (!$existing) {
        echo json_encode(['success' => false, 'message' => 'Combo no encontrado o no pertenece a este inventario.']);
        exit;
    }

    $success = $comboModel->updateCombo($comboId, $name, $price, $items);

    if ($success) {
        // Registrar actividad
        try {
            require_once $root . '/src/helpers/ActivityLogger.php';
            \App\helpers\ActivityLogger::log(
                'Inventario',
                'update',
                'combo',
                (string)$comboId,
                "Actualizó el combo '$name' por $" . number_format($price, 2, ',', '.'),
                "Productos integrantes: " . count($items),
                (int)$activeInventoryId,
                (int)$user['id']
            );
        } catch (\Throwable $th) {}

        echo json_encode(['success' => true, 'message' => 'Combo actualizado con éxito.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'No se pudo actualizar el combo en la base de datos.']);
    }

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
