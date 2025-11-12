<?php


require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../auth_helper.php';

use App\core\Database;

$data = json_decode(file_get_contents('php://input'), true);

//ESTAS 4 ESTADISTICAS SON INDEPENDIENTES DE LA TABLA SELECCIONADA YA QUE NO INVOLUCRAN TABLAS

$response = [];
try {
    $pdo = Database::getInstance();
    getCurrentUser();
    $user_id = $_SESSION['user_id'];

    $clientData = $data['client'];

    $newclient = $pdo->prepare("INSERT INTO customers (user_id,full_name, email, phone, tax_id, address) 
                                    VALUES (:user_id,:name, :email, :phone, :dni, :address)");
    $newclient ->execute([
        ':user_id' => $user_id,
        ':name' => $clientData['name'],
        ':email' => $clientData['email'],
        ':phone' => $clientData['phone'],
        ':dni' => $clientData['dni'],
        ':address' => $clientData['address']]);
    $response = ['success' => true];
} catch (Exception $e) {
    $response = ['success' => false];
}

echo json_encode($response, JSON_NUMERIC_CHECK);



