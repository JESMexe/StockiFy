<?php
// public/api/table/delete-row.php

// 1. Cargar configuraciones y helpers
require_once __DIR__ . '/../../../vendor/autoload.php'; // Ajusta si tu autoloader está en otra ruta
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';

use App\Models\TableModel;

// 2. Configurar cabeceras
header('Content-Type: application/json');

// 3. Verificar sesión
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$user = getCurrentUser();
if (!$user) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Sesión expirada o no autorizada.']);
    exit;
}

// 4. Obtener datos de entrada (JSON)
$input = json_decode(file_get_contents('php://input'), true);
$idToDelete = $input['id'] ?? null;

if (!$idToDelete) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No se proporcionó un ID para eliminar.']);
    exit;
}

// 5. Obtener la tabla activa desde la sesión
$activeInventoryId = $_SESSION['active_inventory_id'] ?? null;

if (!$activeInventoryId) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No hay un inventario seleccionado.']);
    exit;
}

try {
    // Instanciamos el modelo
    $tableModel = new TableModel();

    // Obtenemos el nombre real de la tabla (ej: user_1_inventario)
    $metadata = $tableModel->getTableMetadata($activeInventoryId);

    if (!$metadata || empty($metadata['table_name'])) {
        throw new Exception("No se encontró la tabla asociada a este inventario.");
    }

    $tableName = $metadata['table_name'];

    // 6. Ejecutar el borrado
    if ($tableModel->deleteRow($tableName, $idToDelete)) {
        echo json_encode(['success' => true, 'message' => 'Registro eliminado correctamente.']);
    } else {
        throw new Exception("No se pudo eliminar el registro en la base de datos.");
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error del servidor: ' . $e->getMessage()]);
}