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

    $providerData = $data['provider'];

    $newprovider = $pdo->prepare("INSERT INTO providers (user_id,full_name, email, phone, address) 
                                    VALUES (:user_id,:name, :email, :phone, :address)");
    $newprovider ->execute([
        ':user_id' => $user_id,
        ':name' => $providerData['name'],
        ':email' => $providerData['email'],
        ':phone' => $providerData['phone'],
        ':address' => $providerData['address']]);
    $response = ['success' => true];
} catch (Exception $e) {
    $response = ['success' => false];
}

echo json_encode($response, JSON_NUMERIC_CHECK);
