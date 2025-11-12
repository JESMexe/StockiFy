<?php


require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../auth_helper.php';

use App\core\Database;

try {
    $pdo = Database::getInstance();

    $user = getCurrentUser();
    $user_id = $_SESSION['user_id'];

    $stmt = $pdo->prepare("SELECT id,name FROM inventories WHERE user_id = ? AND sale_price = 1 AND receipt_price = 1");
    $stmt ->execute([$user_id]);
    $inventories = $stmt->fetchAll();

    $response = ['verifiedInventories' => $inventories, 'success' => true];

} catch (Exception $e) {
    $message = $e->getMessage();
    $response = ['success' => false, 'error' => 'Ha ocurrido un error interno = ' . $message];
}

echo json_encode($response, JSON_NUMERIC_CHECK);

