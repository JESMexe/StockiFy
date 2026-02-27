<?php

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';

use App\core\Database;

$data = json_decode(file_get_contents('php://input'), true);

try {
    $pdo = Database::getInstance();
    $user = getCurrentUser();
    $user_id = $_SESSION['user_id'];
    $activeInventoryId = $_SESSION['active_inventory_id'] ?? null;

    if (!$user || !$activeInventoryId) {
        throw new Exception('No autorizado o sin inventario activo');
    }

    $stmt = $pdo->prepare("SELECT * FROM sales WHERE user_id = ? AND inventory_id = ? ORDER BY sale_date DESC");
    $stmt ->execute([$user_id, $activeInventoryId]);
    $dateDescending = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM sales WHERE user_id = ? AND inventory_id = ? ORDER BY sale_date ASC");
    $stmt ->execute([$user_id, $activeInventoryId]);
    $dateAscending = $stmt->fetchAll();

    $date = ['ascending' => $dateAscending, 'descending' => $dateDescending];

    $stmt = $pdo->prepare("SELECT * FROM sales WHERE user_id = ? AND inventory_id = ? ORDER BY customer_id DESC");
    $stmt ->execute([$user_id, $activeInventoryId]);
    $customerDescending = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM sales WHERE user_id = ? AND inventory_id = ? ORDER BY customer_id ASC");
    $stmt ->execute([$user_id, $activeInventoryId]);
    $customerAscending = $stmt->fetchAll();

    $customer = ['ascending' => $customerAscending, 'descending' => $customerDescending];

    $stmt = $pdo->prepare("SELECT * FROM sales WHERE user_id = ? AND inventory_id = ? ORDER BY id DESC");
    $stmt ->execute([$user_id, $activeInventoryId]);
    $idDescending = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM sales WHERE user_id = ? AND inventory_id = ? ORDER BY id ASC");
    $stmt ->execute([$user_id, $activeInventoryId]);
    $idAscending = $stmt->fetchAll();

    $id = ['ascending' => $idAscending, 'descending' => $idDescending];

    $stmt = $pdo->prepare("SELECT * FROM sales WHERE user_id = ? AND inventory_id = ? ORDER BY total_amount DESC");
    $stmt ->execute([$user_id, $activeInventoryId]);
    $priceDescending = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM sales WHERE user_id = ? AND inventory_id = ? ORDER BY total_amount ASC");
    $stmt ->execute([$user_id, $activeInventoryId]);
    $priceAscending = $stmt->fetchAll();

    $price = ['ascending' => $priceAscending, 'descending' => $priceDescending];


    $saleList = ['date'=>$date,'customer'=>$customer,'id'=>$id,'price'=>$price];

    $response = ['saleList' => $saleList, 'success' => true];

    header('Content-Type: application/json');
} catch (Exception $e) {
    $message = $e->getMessage();
    $response = ['success' => false, 'error' => 'Ha ocurrido un error interno = ' . $message];
}
echo json_encode($response, JSON_NUMERIC_CHECK);