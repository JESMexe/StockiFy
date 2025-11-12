<?php

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../auth_helper.php';

use App\core\Database;

$data = json_decode(file_get_contents('php://input'), true);

try {
    $pdo = Database::getInstance();
    $user = getCurrentUser();
    $user_id = $_SESSION['user_id'];

    $stmt = $pdo->prepare("SELECT * FROM receipts WHERE user_id = ? ORDER BY receipt_date DESC");
    $stmt ->execute([$user_id]);
    $dateDescending = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM receipts WHERE user_id = ? ORDER BY receipt_date ASC");
    $stmt ->execute([$user_id]);
    $dateAscending = $stmt->fetchAll();

    $date = ['ascending' => $dateAscending, 'descending' => $dateDescending];

    $stmt = $pdo->prepare("SELECT * FROM receipts WHERE user_id = ? ORDER BY provider_id DESC");
    $stmt ->execute([$user_id]);
    $providerDescending = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM receipts WHERE user_id = ? ORDER BY provider_id ASC");
    $stmt ->execute([$user_id]);
    $providerAscending = $stmt->fetchAll();

    $provider = ['ascending' => $providerAscending, 'descending' => $providerDescending];

    $stmt = $pdo->prepare("SELECT * FROM receipts WHERE user_id = ? ORDER BY id DESC");
    $stmt ->execute([$user_id]);
    $idDescending = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM receipts WHERE user_id = ? ORDER BY id ASC");
    $stmt ->execute([$user_id]);
    $idAscending = $stmt->fetchAll();

    $id = ['ascending' => $idAscending, 'descending' => $idDescending];

    $stmt = $pdo->prepare("SELECT * FROM receipts WHERE user_id = ? ORDER BY total_amount DESC");
    $stmt ->execute([$user_id]);
    $priceDescending = $stmt->fetchAll();

    $stmt = $pdo->prepare("SELECT * FROM receipts WHERE user_id = ? ORDER BY total_amount ASC");
    $stmt ->execute([$user_id]);
    $priceAscending = $stmt->fetchAll();

    $price = ['ascending' => $priceAscending, 'descending' => $priceDescending];


    $receiptList = ['date'=>$date,'provider'=>$provider,'id'=>$id,'price'=>$price];

    $response = ['receiptList' => $receiptList, 'success' => true];

    header('Content-Type: application/json');
} catch (Exception $e) {
    $message = $e->getMessage();
    $response = ['success' => false, 'error' => 'Ha ocurrido un error interno = ' . $message];
}
echo json_encode($response, JSON_NUMERIC_CHECK);