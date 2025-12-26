<?php
// public/api/table/delete-row.php

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';

use App\Models\TableModel;

header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = getCurrentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesión expirada o no autorizada.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$idToDelete = $input['id'] ?? null;

if (!$idToDelete) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No se proporcionó un ID para eliminar.']);
    exit;
}

$activeInventoryId = $_SESSION['active_inventory_id'] ?? null;

if (!$activeInventoryId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No hay un inventario seleccionado.']);
    exit;
}

try {
    $tableModel = new TableModel();

    $metadata = $tableModel->getTableMetadata($activeInventoryId);

    if (!$metadata || empty($metadata['table_name'])) {
        throw new Exception("No se encontró la tabla asociada a este inventario.");
    }

    $tableName = $metadata['table_name'];

    if ($tableModel->deleteRow($tableName, $idToDelete)) {
        echo json_encode(['success' => true, 'message' => 'Registro eliminado correctamente.']);
    } else {
        throw new Exception("No se pudo eliminar el registro en la base de datos.");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}