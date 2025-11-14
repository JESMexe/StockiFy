<?php

require_once __DIR__ . '/../../../vendor/autoload.php';
require_once __DIR__ . '/../../../src/helpers/auth_helper.php';

use App\core\Database;

try {
    $data = json_decode(file_get_contents('php://input'), true);

    $clientID = $data['id'];

    $pdo = Database::getInstance();

    $user = getCurrentUser();
    $user_id = $_SESSION['user_id'];

    $stmt = $pdo->prepare("UPDATE customers SET full_name = ?, email = ?, phone = ?,
                       address = ?, tax_id = ? WHERE id = ?");
    $stmt->execute([$data['name'], $data['email'], $data['phone'], $data['address'], $data['tax_id'], $clientID]);
    $response = ['success' => true];
} catch (Exception $e) {
    $response = ['success' => false, 'error' => $e->getMessage()];
}

echo json_encode($response, JSON_NUMERIC_CHECK);
