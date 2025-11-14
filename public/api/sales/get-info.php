<?php


require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';

use App\core\Database;

$data = json_decode(file_get_contents('php://input'), true);

try {
    $pdo = Database::getInstance();

    $sale_id = $data;

    $itemListStmt = $pdo->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
    $itemListStmt ->execute([$sale_id]);
    $itemList = $itemListStmt->fetchAll();

    $saleInfoStmt = $pdo->prepare("SELECT * FROM sales WHERE id = ?");
    $saleInfoStmt ->execute([$sale_id]);
    $saleInfo = $saleInfoStmt->fetch();

    $customerInfoStmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $customerInfoStmt ->execute([$saleInfo['customer_id']]);
    $customerInfo = $customerInfoStmt->fetch();

    $response = ['itemList' => $itemList, 'id' => $saleInfo['id'], 'customerInfo' => $customerInfo,
        'totalAmount' => $saleInfo['total_amount'], 'saleDate' => $saleInfo['sale_date'],'success' => true];

    header('Content-Type: application/json');
} catch (Exception $e) {
    $message = $e->getMessage();
    $response = ['success' => false, 'error' => 'Ha ocurrido un error interno = ' . $message];
}
echo json_encode($response, JSON_NUMERIC_CHECK);