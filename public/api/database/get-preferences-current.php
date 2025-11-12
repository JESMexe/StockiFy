<?php


require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../auth_helper.php';

use App\core\Database;

try {
    $pdo = Database::getInstance();

    $user = getCurrentUser();
    $user_id = $_SESSION['user_id'];

    $inventoryID = $_SESSION['active_inventory_id'];
    $stmt = $pdo->prepare("SELECT min_stock,sale_price,receipt_price,hard_gain,percentage_gain,auto_price,auto_price_type FROM inventories WHERE id = ?");
    $stmt ->execute([$inventoryID]);
    $preferences = $stmt->fetch();

    $response = ['min_stock' => $preferences['min_stock'],'sale_price' => $preferences['sale_price'],
        'receipt_price' => $preferences['receipt_price'],'hard_gain' => $preferences['hard_gain'],
        'percentage_gain' => $preferences['percentage_gain'],'auto_price' => $preferences['auto_price'],
        'auto_price_type' => $preferences['auto_price_type'], 'success' => true];

} catch (Exception $e) {
    $message = $e->getMessage();
    $response = ['success' => false, 'error' => 'Ha ocurrido un error interno = ' . $message];
}

echo json_encode($response, JSON_NUMERIC_CHECK);
