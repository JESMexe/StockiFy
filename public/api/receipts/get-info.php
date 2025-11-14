<?php


require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';

use App\core\Database;

$data = json_decode(file_get_contents('php://input'), true);

try {
    $pdo = Database::getInstance();

    $receipt_id = $data;

    $itemListStmt = $pdo->prepare("SELECT * FROM receipt_items WHERE receipt_id = ?");
    $itemListStmt ->execute([$receipt_id]);
    $itemList = $itemListStmt->fetchAll();

    $receiptInfoStmt = $pdo->prepare("SELECT * FROM receipts WHERE id = ?");
    $receiptInfoStmt ->execute([$receipt_id]);
    $receiptInfo = $receiptInfoStmt->fetch();

    $providerInfoStmt = $pdo->prepare("SELECT * FROM providers WHERE id = ?");
    $providerInfoStmt ->execute([$receiptInfo['provider_id']]);
    $providerInfo = $providerInfoStmt->fetch();

    $response = ['itemList' => $itemList, 'id' => $receiptInfo['id'], 'providerInfo' => $providerInfo,
        'totalAmount' => $receiptInfo['total_amount'], 'receiptDate' => $receiptInfo['receipt_date'],'success' => true];

    header('Content-Type: application/json');
} catch (Exception $e) {
    $message = $e->getMessage();
    $response = ['success' => false, 'error' => 'Ha ocurrido un error interno = ' . $message];
}
echo json_encode($response, JSON_NUMERIC_CHECK);