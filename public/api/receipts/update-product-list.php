<?php


require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';

use App\core\Database;

$data = json_decode(file_get_contents('php://input'), true);

try {
    $pdo = Database::getInstance();

    $productList= $data['items'];
    $receiptId = $data['receipt_id'];
    $newTotal = $data['newTotal'];

    foreach ($productList as $product) {
        $productStmt = $pdo->prepare("UPDATE receipt_items SET 
                      quantity = ?, 
                      total_price = ?,
                      unit_price = ? 
                  WHERE item_id = ? AND inventory_id = ? AND receipt_id = ?");
        $productStmt ->execute([$product['new_quantity'],
            $product['new_total_price'],
            $product['new_unit_price'],
            $product['product_id'],
            $product['inventory_id'], $receiptId]);

        $databaseStmt = $pdo->prepare("SELECT table_name FROM user_tables WHERE inventory_id = ?");
        $databaseStmt->execute([$product['inventory_id']]);
        $databaseName = $databaseStmt->fetch(PDO::FETCH_COLUMN);

        $difference = $product['new_quantity'] - $product['original_quantity'];

        $stockStmt = $pdo->prepare("UPDATE `$databaseName` SET stock = (stock + ?) WHERE id = ?");
        $stockStmt ->execute([$difference, $product['product_id']]);
    }

    $saleStmt = $pdo->prepare("UPDATE receipts SET total_amount = ? WHERE id = ?");
    $saleStmt ->execute([$newTotal, $receiptId]);

    $response = ['success' => true];

    header('Content-Type: application/json');
} catch (Exception $e) {
    $message = $e->getMessage();
    $response = ['success' => false, 'error' => 'Ha ocurrido un error interno = ' . $message];
}
echo json_encode($response, JSON_NUMERIC_CHECK);