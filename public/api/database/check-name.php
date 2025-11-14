<?php

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';

use App\core\Database;

try {
    $data = json_decode(file_get_contents('php://input'), true);

    $dbName = $data;
    $user = getCurrentUser();
    $userID = $_SESSION['user_id'];

    $pdo = Database::getInstance();
    $stmt = $pdo->prepare("SELECT name FROM inventories WHERE name = ?");
    $stmt->execute([$dbName]);
    $name = $stmt->fetch();

    $exists = $name !== false;

    $response = ['success' => true, 'exists' => $exists];

    header('Content-Type: application/json');
} catch (Exception $e) {
    $message = $e->getMessage();
    $response = ['success' => false, 'error' => 'Ha ocurrido un error interno = ' . $message];
}
echo json_encode($response, JSON_NUMERIC_CHECK);



