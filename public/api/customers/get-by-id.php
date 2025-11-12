<?php


require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../auth_helper.php';

use App\core\Database;

$data = json_decode(file_get_contents('php://input'), true);

//ESTAS 4 ESTADISTICAS SON INDEPENDIENTES DE LA TABLA SELECCIONADA YA QUE NO INVOLUCRAN TABLAS

$response = [];
try {

    $id = $data['id'];
    $pdo = Database::getInstance();

    $client = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
    $client->execute([$id]);
    $client = $client->fetch();

    $response = ['clientInfo' => $client, 'success' => true];

    header('Content-Type: application/json');
} catch (Exception $e) {
    $message = $e->getMessage();
    $response = ['success' => false, 'error' => 'Ha ocurrido un error interno = ' . $message];
}
echo json_encode($response, JSON_NUMERIC_CHECK);


