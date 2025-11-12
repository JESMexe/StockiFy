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

    $dateDescending = $pdo->prepare("SELECT * FROM providers WHERE user_id = ? ORDER BY created_at DESC");
    $dateDescending->execute([$user_id]);
    $dateDescending = $dateDescending->fetchAll();

    $dateAscending = $pdo->prepare("SELECT * FROM providers WHERE user_id = ? ORDER BY created_at ASC");
    $dateAscending->execute([$user_id]);
    $dateAscending = $dateAscending->fetchAll();

    $date = ['ascending' => $dateAscending, 'descending' => $dateDescending];

    $emailDescending = $pdo->prepare("SELECT * FROM providers WHERE user_id = ? ORDER BY email DESC");
    $emailDescending->execute([$user_id]);
    $emailDescending = $emailDescending->fetchAll();

    $emailAscending = $pdo->prepare("SELECT * FROM providers WHERE user_id = ? ORDER BY email ASC");
    $emailAscending->execute([$user_id]);
    $emailAscending = $emailAscending->fetchAll();

    $email = ['ascending' => $emailAscending, 'descending' => $emailDescending];

    $nameDescending = $pdo->prepare("SELECT * FROM providers WHERE user_id = ? ORDER BY full_name DESC");
    $nameDescending->execute([$user_id]);
    $nameDescending = $nameDescending->fetchAll();

    $nameAscending = $pdo->prepare("SELECT * FROM providers WHERE user_id = ? ORDER BY full_name ASC");
    $nameAscending->execute([$user_id]);
    $nameAscending = $nameAscending->fetchAll();

    $name = ['ascending' => $nameAscending, 'descending' => $nameDescending];

    $phoneDescending = $pdo->prepare("SELECT * FROM providers WHERE user_id = ? ORDER BY phone DESC");
    $phoneDescending->execute([$user_id]);
    $phoneDescending = $phoneDescending->fetchAll();

    $phoneAscending = $pdo->prepare("SELECT * FROM providers WHERE user_id = ? ORDER BY phone ASC");
    $phoneAscending->execute([$user_id]);
    $phoneAscending = $phoneAscending->fetchAll();

    $phone = ['ascending' => $phoneAscending, 'descending' => $phoneDescending];

    $addressDescending = $pdo->prepare("SELECT * FROM providers WHERE user_id = ? ORDER BY address DESC");
    $addressDescending->execute([$user_id]);
    $addressDescending = $addressDescending->fetchAll();

    $addressAscending = $pdo->prepare("SELECT * FROM providers WHERE user_id = ? ORDER BY address ASC");
    $addressAscending->execute([$user_id]);
    $addressAscending = $addressAscending->fetchAll();

    $address = ['ascending' => $addressAscending, 'descending' => $addressDescending];


    $providers = ['date' => $date, 'email' => $email, 'name' => $name, 'phone' => $phone, 'address' => $address];

    $response = ['providerList' => $providers, 'success' => true];

    header('Content-Type: application/json');
} catch (Exception $e) {
    $message = $e->getMessage();
    $response = ['success' => false, 'error' => 'Ha ocurrido un error interno = ' . $message];
}
echo json_encode($response, JSON_NUMERIC_CHECK);


