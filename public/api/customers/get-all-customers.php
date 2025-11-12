<?php


require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../auth_helper.php';

use App\core\Database;

//ESTAS 4 ESTADISTICAS SON INDEPENDIENTES DE LA TABLA SELECCIONADA YA QUE NO INVOLUCRAN TABLAS

try {
    $pdo = Database::getInstance();
    $user = getCurrentUser();
    $user_id = $_SESSION['user_id'];

    $clients = $pdo->prepare("SELECT * FROM customers WHERE user_id = ?");
    $clients ->execute([$user_id]);
    $clients = $clients->fetchAll();

    $response = ['clientList' => $clients, 'success' => true];

    header('Content-Type: application/json');
} catch (Exception $e) {
    $message = $e->getMessage();
    $response = ['success' => false, 'error' => 'Ha ocurrido un error interno = ' . $message];
}
echo json_encode($response, JSON_NUMERIC_CHECK);


