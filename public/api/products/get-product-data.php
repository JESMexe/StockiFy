<?php

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';

use App\core\Database;

try {
    $data = json_decode(file_get_contents('php://input'), true);

    $pdo = Database::getInstance();

    $user = getCurrentUser();

    $inventoryID = $data['tableID'];
    $productID = $data['productID'];

    $stmt = $pdo->prepare("SELECT table_name FROM user_tables WHERE inventory_id = ?");
    $stmt->execute([$inventoryID]);
    $databaseName = $stmt->fetch(PDO::FETCH_COLUMN);

    $sql = "SELECT `id`, `name`, `stock`, `sale_price`, `receipt_price` FROM `$databaseName` WHERE id = $productID";

    $productsStmt = $pdo->prepare($sql);
    $productsStmt->execute();
    $productInfo = $productsStmt->fetch();


    $response = ['productInfo' => $productInfo, 'success' => true];

    header('Content-Type: application/json');
} catch (Exception $e) {
    $message = $e->getMessage();
    $response = ['success' => false, 'error' => 'Ha ocurrido un error interno = ' . $message];
}
echo json_encode($response, JSON_NUMERIC_CHECK);
