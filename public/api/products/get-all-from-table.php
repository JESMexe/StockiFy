<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../auth_helper.php';

use App\core\Database;

try {
    $data = json_decode(file_get_contents('php://input'), true);

    $pdo = Database::getInstance();

    $user = getCurrentUser();

    $inventoryID = $data;

    $productList = [];

    $stmt = $pdo->prepare("SELECT table_name FROM user_tables WHERE inventory_id = ?");
    $stmt->execute([$inventoryID]);
    $databaseName = $stmt->fetch(PDO::FETCH_COLUMN);

    $sql = "SELECT `id`, `name`, `stock`, `sale_price`, `receipt_price` FROM `$databaseName`";

    $productsStmt = $pdo->prepare($sql);
    $productsStmt->execute();
    $products = $productsStmt->fetchAll();

    foreach ($products as $product) {
        $productList[] = ['pID' => $product['id'], 'tID' => $inventoryID, 'stock' => $product['stock'],
            'name' => $product['name'], 'salePrice' => $product['sale_price'], 'receiptPrice' => $product['receipt_price']];
    }

    $response = ['productList' => $productList, 'success' => true];

    header('Content-Type: application/json');
} catch (Exception $e) {
    $message = $e->getMessage();
    $response = ['success' => false, 'error' => 'Ha ocurrido un error interno = ' . $message];
}
echo json_encode($response, JSON_NUMERIC_CHECK);
