<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';

use App\core\Database;

try {
    $pdo = Database::getInstance();

    $user = getCurrentUser();
    if (!$user || !isset($_SESSION['active_inventory_id'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No autorizado o inventario no activo.']);
        return;
    }
    $user_id = $_SESSION['user_id'];
    $inventoryID = $_SESSION['active_inventory_id'];

    // Obtener el nombre de la tabla
    $stmt = $pdo->prepare("SELECT table_name FROM user_tables WHERE inventory_id = ?");
    $stmt->execute([$inventoryID]);
    $tableName = $stmt->fetchColumn();

    $columns = ['min_stock', 'sale_price', 'receipt_price', 'percentage_gain', 'hard_gain'];
    $response = [];

    // Consulta única para todas las columnas
    $placeholders = str_repeat('?,', count($columns) - 1) . '?';
    $sql = "SHOW COLUMNS FROM {$tableName} WHERE Field IN ({$placeholders})";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($columns);

    $columnData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organizar los resultados
    foreach($columnData as $data) {
        $response[$data['Field']] = $data['Default'];
    }

    // Asegurar que todas las columnas estén en la respuesta
    foreach($columns as $column) {
        if (!isset($response[$column])) {
            $response[$column] = null;
        }
    }

    $response['success'] = true;

} catch (Exception $e) {
    $message = $e->getMessage();
    $response = ['success' => false, 'error' => 'Ha ocurrido un error interno = ' . $message];
}

echo json_encode($response, JSON_NUMERIC_CHECK);