<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';

use App\core\Database;

try {
    $pdo = Database::getInstance();

    if (!getCurrentUser() || !isset($_SESSION['active_inventory_id'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'No autorizado o inventario no activo.']);
        return; // Detiene la ejecución antes de que PHP lance un Notice HTML.
    }

    $user = getCurrentUser();
    $user_id = $_SESSION['user_id'];

    $inventoryID = $_SESSION['active_inventory_id'];

    $stmt = $pdo->prepare("SELECT table_name FROM user_tables WHERE inventory_id = ?");
    $stmt->execute([$inventoryID]);
    $tableName = $stmt->fetchColumn();

    $columns = ['min_stock', 'sale_price', 'receipt_price', 'percentage_gain', 'hard_gain'];
    $response = [];

    foreach($columns as $column){
        $sql = "SHOW COLUMNS FROM {$tableName} WHERE FIELD = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$column]);

        $columnData = $stmt->fetch(PDO::FETCH_ASSOC);

        $response[$column] = $columnData['Default'];
    }

    $response['success'] = true;

} catch (Exception $e) {
    $message = $e->getMessage();
    $response = ['success' => false, 'error' => 'Ha ocurrido un error interno = ' . $message];
}

echo json_encode($response, JSON_NUMERIC_CHECK);
