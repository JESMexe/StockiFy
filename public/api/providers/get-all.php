<?php


require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../auth_helper.php';

use App\core\Database;

$data = json_decode(file_get_contents('php://input'), true);

//ESTAS 4 ESTADISTICAS SON INDEPENDIENTES DE LA TABLA SELECCIONADA YA QUE NO INVOLUCRAN TABLAS

$response = [];
try {
    $pdo = Database::getInstance();
    $user = getCurrentUser();
    $user_id = $_SESSION['user_id'];

    $providers = $pdo->prepare("SELECT * FROM providers WHERE user_id = ?");
    $providers ->execute([$user_id]);
    $providers = $providers->fetchAll();

    $response = ['providerList' => $providers, 'success' => true];

    header('Content-Type: application/json');
} catch (Exception $e) {
    $message = $e->getMessage();
    $response = ['success' => false, 'error' => 'Ha ocurrido un error interno = ' . $message];
}
echo json_encode($response, JSON_NUMERIC_CHECK);