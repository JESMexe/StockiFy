<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../auth_helper.php';

use App\core\Database;

try {
    $data = json_decode(file_get_contents('php://input'), true);

    $itemList = $data['itemList'];
    $clientID = $data['clientID'];
    $totalPrice = $data['totalPrice'];

    $pdo = Database::getInstance();
    $user = getCurrentUser();
    $user_id = $_SESSION['user_id'];

    $newsale = $pdo->prepare("INSERT INTO sales (user_id, customer_id, total_amount) 
                                        VALUES (:user_id,:customer_id,:total_amount)");

    $newsale ->execute([
        ':user_id' => $user_id,
        ':customer_id' => $clientID,
        ':total_amount' => $totalPrice,
    ]);

    $saleId = $pdo->lastInsertId();

    foreach($itemList as $item){
        $totalPrice = $item['salePrice'] * $item['amount'];
        $newsaleitem = $pdo->prepare("INSERT INTO sale_items (item_id,inventory_id, sale_id,product_name, quantity, unit_price, total_price)
                                    VALUES (:item_id,:inventory_id, :sale_id, :product_name, :quantity,:unit_price,:total_price)");
        $newsaleitem ->execute([
            ':item_id' => $item['pID'],
            ':inventory_id' => $item['tID'],
            ':sale_id' => $saleId,
            ':product_name' => $item['name'],
            ':quantity' => $item['amount'],
            ':unit_price' => $item['salePrice'],
            ':total_price' => $totalPrice]);
    }
    $response = ['success' => true, 'saleId' => $saleId];
} catch (Exception $e) {
    $response = ['success' => false, 'error' => 'Ha ocurrido un error interno = ' . $e->getMessage()];
}

echo json_encode($response, JSON_NUMERIC_CHECK);



